# Cloud migration and roadmap

Status: in progress (branch `feat/cloud-ready`). Done so far: frontend backend URLs read from `VITE_API_URL`
(`frontend/src/config.js`), deployment files in `deploy/`. Test instance running on EC2 (Ohio, `us-east-2`) with
nginx, PHP 8.3, MySQL and Redis installed. Everything else below is still pending.
Source: read-only audit of `backend/`, `frontend/` and `microservice/` (2026-10-08), plus product ideas.

---

## 1. Goal

Move the Laravel backend from a LAN server to a cloud server. Totems, operator stations (cajas) and display screens keep running at the client site and reach the backend over the internet.

## 2. Target topology

One public domain, for example `https://turnos.example.com`, served by one cloud host:

```
Internet
  |
  nginx (HTTPS)
    |-- /            -> static Vue build (frontend/dist)
    |-- /api         -> php-fpm (Laravel)
    |-- /storage     -> persistent volume (logos, videos)
  |
  MySQL/Postgres (+ Redis for cache/queue/session, optional at first)
```

Why a single domain with relative `/api`:

- No CORS problems (same origin).
- No mixed content (everything HTTPS).
- The frontend no longer needs to know any IP.

### How each client device connects

| Device | Today | After migration |
|---|---|---|
| Totem (Electron) | `SERVER_URL=http://192.168.0.51:5173` in `%APPDATA%\Turnero Totem\.env` | `SERVER_URL=https://turnos.example.com` (edit `.env` per totem) |
| Display (TV browser) | `http://<lan-ip>:5173/display/1` | `https://turnos.example.com/display/1` (change the browser bookmark or kiosk URL once) |
| Operator station (caja) | `http://<lan-ip>:5173/operator` | `https://turnos.example.com/operator` (bookmark) |

Displays and operator stations need no software or env change, only a new URL. Totems need the `.env` change because Electron loads the URL from config.

The local print microservice (`http://localhost:5000` / Python `win32print`) stays on each totem and is unaffected, but verify it answers CORS / Private Network Access preflights for the new HTTPS origin.

---

## 3. Migration checklist

### 3.1 Will break (must fix)

- [x] **Hardcoded backend URL** `http://192.168.0.51:8000` (done in `feat/cloud-ready`) in:
  - `frontend/src/api/index.js:4`
  - `frontend/src/views/DisplayView.vue:276` (SSE)
  - `frontend/src/views/OperatorView.vue:276` (SSE)
  - `frontend/src/views/HomeView.vue:463` (logo)
  - `frontend/src/components/admin/AdminPerfiles.vue:345` (logo)

  Fix: `VITE_API_URL` (default `/api`), derive SSE and storage URLs from it, or return a full `logo_url` from the API (as `DisplaySettingsController` already does for video).
- [ ] **Operator-window binding by IP** (`WindowController.php:43`, `$request->ip()`). From the cloud every PC at a site shares one public NAT IP, and `windows.ip_address` is unique. Operators cannot resolve their window. Also `trustProxies` is not configured in `bootstrap/app.php`. **Needs a design decision, see section 4.**
- [ ] **SQLite** is the default DB. Use MySQL or Postgres. Session and queue also default to `database`; set drivers explicitly (Redis when available).
- [ ] **SSE vs php-fpm.** Each SSE stream holds a PHP worker up to 1 hour (1800 iterations x 2s). Size `pm.max_children` for displays + operators, disable proxy/fastcgi buffering in nginx, or move to Reverb/Pusher, or space out polling. `php artisan serve` is not viable.
- [ ] **Uploads on ephemeral disk.** Totem logos and display videos live on the `public` disk. Use a persistent volume or S3 (`FILESYSTEM_DISK`). Raise nginx `client_max_body_size` and PHP `upload_max_filesize` / `post_max_size` (video limit is 50 MB).
- [ ] **Totem `.env`** per totem: new `SERVER_URL`. Update `microservice/env.example`, `KIOSK_SETUP.md`, `README.md`.
- [ ] **Electron navigation guard** (`microservice/main.js:154`) uses `startsWith`; `https://x.com.evil.io` would match `https://x.com`. Compare `new URL(url).origin` instead.
- [ ] **Production env**: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...` (video URL uses `url()`), `config:cache`, HTTPS, `SESSION_SECURE_COOKIE`.
- [ ] **Dev server** `frontend/package.json` runs `vite --host 192.168.0.51`. Production serves `vite build` output.

### 3.2 Security (the API becomes public)

- [ ] **No admin role middleware.** All `admin/*` routes only require `auth:sanctum`; any operator token can manage users and turns. Add an `EnsureIsAdmin` middleware.
- [ ] **No throttling** on `POST /login` or `POST /turns`. Add `throttle` (login stricter; turns per IP or per totem key).
- [ ] **Public endpoints leak data.** `/turns/queue` and the display SSE serialize whole `Turn` models. Check whether `customer_dni` (or other PII) is exposed and strip it from public payloads.
- [ ] **Device identity for totems/displays.** Consider a per-device key so anonymous internet clients cannot create tickets.
- [ ] **SSE token in query string** (`?token=`) appears in proxy logs and tokens never expire (`sanctum.expiration = null`). Use a short-lived single-use SSE ticket, and set a token expiration.
- [ ] **Add a 401 response interceptor** in `frontend/src/api/index.js` (clear `localStorage`, redirect to `/login`).
- [ ] **CORS**: there is no `config/cors.php` (framework default). If same-origin is used it matters less; otherwise restrict to the frontend origin.
- [ ] **Seeders**: `UserSeeder` gives every user, including the admin, the password `password`. Never run it in production.
- [ ] **`pass.txt`** exists untracked in the repo root. Add to `.gitignore` and rotate whatever it contains.

### 3.3 Resilience and correctness

- [ ] **Ticket numbering race** (`TurnController.php:59-66`): read last number + 1 without a transaction or unique index. More latency means more duplicates. Use `DB::transaction` + `lockForUpdate` and a unique index.
- [ ] **Internet outage = no tickets.** No offline queue and an 8s timeout. A retry after timeout can create a duplicate turn. Add an idempotency key and clear error UX on the totem.
- [ ] **Electron error page** only covers initial page-load failures, not API/SSE failures after load.
- [ ] **Client clocks.** Elapsed and waiting times use `Date.now()` against server timestamps. Compute from server time or send a server timestamp/offset.
- [ ] **Polling**: 2s polling plus SSE fallback stacks up on flaky links. Add backoff; do not poll while SSE is connected.
- [ ] **Timezone**: keep `America/Argentina/Buenos_Aires`; make sure the cloud DB and PHP agree on day boundaries (`Turn::scopeToday`).
- [ ] **Health check**: `/up` already exists, wire it to the load balancer or uptime monitor.
- [ ] **Backups** for the DB and the uploads volume (new responsibility once it is in the cloud).

### 3.4 Suggested order

1. URLs via env (unblocks everything).
2. Cloud host: nginx + php-fpm + managed DB + HTTPS + persistent storage.
3. Replace the operator-window IP binding.
4. Hardening: admin middleware, throttle, public payload cleanup, SSE auth.
5. Ticket numbering fix and idempotent ticket creation.
6. Switch totems, then displays and operator bookmarks, site by site.

---

## 4. Design decision pending: operator-window binding

The current design (window owns a fixed IP, operator inherits it by logging in from that PC) assumes a LAN. In the cloud the IP is no longer unique per PC.

Candidate approaches (to decide):

- **A. Window picker persisted per PC.** On first login the operator picks a window; the choice is stored in that browser (`localStorage`) and sent as an identifier. Simple, no hardware binding. Anyone can pick any window unless restricted.
- **B. Device token per PC.** An admin registers the PC once and gets a device token stored in the browser; the token maps to the window. Keeps the "window belongs to the PC" intent, works over NAT.
- **C. Keep IP binding via a site-local agent/VPN.** Heavier to operate; only worth it if a VPN exists already.

Note: this reverses a previous intentional design decision (IP belongs to the PC). Option B is the closest to the original intent.

---

## 5. Roadmap: modules per client

Goal: small features that can be enabled per client/installation without forking the code.

Proposal: a feature-flag layer.

- Backend: `config/modules.php` with defaults from env, optionally overridable per installation in a settings table (the `DisplaySettings` singleton pattern already exists).
- Expose enabled modules to the frontend through an existing public endpoint (for example alongside display/totem settings).
- Frontend: wrap module UI with a `useModules()` composable; disabled modules render nothing and their routes return 404.
- Candidate modules: `transfer`, `whatsapp_notifications`, `whatsapp_booking`, `priority_queue`.

This also lines up with the long-term Channels architecture (`channel_id` on turns/windows/totems/displays); module flags can later become per-channel.

### 5.1 Turn transfer (derive a turn to another queue)

Need: an operator moves a turn to another service queue. Problem: the ticket number carries the service prefix (`{service.code}-{number}`, for example `AC-001`).

Recommended approach: **keep the original ticket number; change only the current queue.**

- The customer already holds a printed ticket and the display announces the number. Renumbering would confuse them.
- Keep `ticket_number` immutable. Add `origin_service_id` (first service), keep `service_id` as the *current* queue, plus `transferred_at`, `transferred_by` and a `transfer_count`.
- The display and operator UI show the ticket number as-is, with the destination service shown separately (name/color).
- Add a model method `Turn::transfer($serviceId, $user)` (state transitions stay in the model, per project principle). Valid from `called` / `in_progress`; the turn returns to `waiting` in the new queue.
- Decide whether the turn keeps its original `created_at` position in the new queue or goes to the back (suggested: keep original wait time for metrics, but add `transferred_at` for queue ordering, configurable).
- The unique index on `(service_id, ticket_number)` must be revisited, since `service_id` now changes. Use `(origin_service_id, ticket_number)` or a global unique on `ticket_number`.
- Metrics: wait time should be split per segment (before/after transfer).

Alternative (not recommended): issue a new number in the destination queue. It gives consistent prefixes but breaks the customer's reference and requires reprinting.

Open questions:

- Should the destination queue be limited to services the operator can see?
- Does the operator choose a destination service only, or also a specific window?

### 5.2 WhatsApp notifications ("you are next")

Need: capture and verify the customer's phone number at the totem, then message them via WhatsApp when their turn is close.

Considerations:

- Use the WhatsApp Business Platform (Meta Cloud API or a BSP). A "you're next" message is business-initiated, so it needs a pre-approved **template** and has per-conversation cost. Requires explicit customer opt-in.
- **Verification options (to decide):**
  - Send an OTP code via a WhatsApp template and have the customer type it on the totem (needs a keyboard UI; adds time at the kiosk).
  - Show a QR / `wa.me` link on the totem or ticket with a prefilled message containing the ticket code. The customer's own message verifies the number and opens a 24h session window (service messages are cheaper), with no typing on the totem. Likely the better UX.
- Phone numbers are personal data: store only what is needed, normalize to E.164, define retention, and keep it out of public payloads (see 3.2).
- Needs a queue worker (Redis driver, not `database`) so sending never blocks turn calls. Trigger: when a turn becomes N positions away, or on `called`.
- Add `customer_phone`, `phone_verified_at`, `notification_opt_in`, and a notification log table (status, provider message id, errors) to the turns domain.
- Handle failures gracefully: a failed message must never block the queue.

### 5.3 Create a turn from WhatsApp (future)

Need: customers who already have a Termy contact can request a turn via WhatsApp.

- Inbound webhook endpoint (signature-verified), conversation flow to pick a service, then call the same turn-creation code path as the totem (single source of truth for numbering).
- Depends on 5.2 (provider setup, verified numbers, queue worker) and on the numbering fix in 3.3.
- Open questions: how to avoid remote ticket abuse (limit one active turn per phone, optional location/opening-hours check), and how the person is told their number (reply with the ticket and queue position).

---

## 6. Open questions

1. Operator-window binding: A, B or C? (section 4)
2. Hosting target: single VPS (nginx + php-fpm + DB) or a managed platform? Does the host offer Redis and persistent volumes?
3. Is there one backend per client or one multi-tenant backend for many clients? This changes the "module per client" design and the Channels work.
4. WhatsApp: Cloud API directly or through a provider/BSP?
