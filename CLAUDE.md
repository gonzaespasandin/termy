# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Project Identity

**Termy** is a physical kiosk queue management system (turnero) deployed at retail/service locations. Each installation has:
- One or more **totems** (touchscreen kiosks running Electron on Windows, BMax hardware) where customers pick up tickets
- One or more **operator stations** (browser-based, any PC on the LAN)
- One or more **display screens** (browser-based, public view)
- One **backend server** (Laravel REST API + SSE)

Each totem runs its own Electron process with a bundled print microservice (Python win32print). All components communicate over a local area network.

---

## Repository Structure

```
termy/
├── backend/        # Laravel 12 REST API (PHP 8.2+)
├── frontend/       # Vue 3 SPA — totem UI, operator panel, admin, display
└── microservice/   # Electron 28 desktop app — kiosk shell + thermal printing
```

---

## Backend (Laravel 12)

### Commands

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed                          # sample data
php artisan storage:link                     # required for totem logo uploads
php artisan serve --host=0.0.0.0             # dev server on all interfaces
```

Run tests (uses SQLite in-memory — no DB setup needed):
```bash
php artisan test
php artisan test --filter TurnStateTest      # single test class
php artisan test tests/Feature/TurnTest.php  # single file
```

Code style:
```bash
./vendor/bin/pint                            # Laravel Pint (PSR-12)
```

### Architecture

**Controllers** (`app/Http/Controllers/Api/`): One per domain entity. No fat controllers — business logic belongs in Models or dedicated Service classes.

| Controller | Responsibility |
|---|---|
| `AuthController` | Login / logout / current user |
| `TurnController` | Full turn lifecycle (create, state transitions, queues) |
| `ServiceController` | Service CRUD |
| `WindowController` | Window CRUD + by-IP lookup |
| `TotemController` | Totem/profile CRUD + public services-by-code |
| `UserController` | User CRUD |
| `SseController` | SSE streams for display and operator |
| `DashboardController` | Today's KPIs and statistics |
| `HistoryController` | Paginated turn history with filters |

**Models** (`app/Models/`): Business logic lives here via methods and accessors. `Turn` is the core domain model — it owns all state transition methods (`call()`, `startAttention()`, `complete()`, etc.) and time calculation accessors (`wait_time`, `attention_time`, `total_time`).

**Routes** (`routes/api.php`): Three tiers — public, `auth:sanctum`, and admin-only (`role=admin`). SSE routes handle manual auth via `?token=` query param because `EventSource` doesn't support custom headers.

**Real-time**: SSE (Server-Sent Events) via `SseController`. No WebSockets, no broadcasting driver. Max 1800 iterations per connection (1-hour timeout). Currently **polling is the default** in the frontend — SSE is implemented but set to `USE_SSE = false` in `DisplayView.vue` and `OperatorView.vue`.

**Database**: SQLite by default. Migrations in `database/migrations/`. JSON columns (`services`, `windows.services`, `totems.services`) store ID arrays for many-to-many relationships without pivot tables. **Add pivot tables** before adding filtering/querying on these relationships at scale.

**Operator-window binding**: The **window (ventanilla) has a fixed IP** assigned to the physical PC. Any operator who logs in from that machine is bound to that window via `GET /api/windows/by-ip`. User accounts have no fixed IP — operators can rotate between stations freely. Do NOT add a `user_id → window_id` binding at the account level; the IP-based lookup is the intentional design.

**Service filtering for operators**: `User.services` is a JSON array of service IDs. An empty array means the operator can attend all services.

**Ticket numbering**: Sequential per service, never resets daily. Format: `{service.code}-{zero_padded_number}` (e.g., `AC-001`).

**Timezone**: `America/Argentina/Buenos_Aires` (in `config/app.php`).

**CORS**: Configure `FRONTEND_URL` in `.env`.

### Domain Model

```
users          — role: admin | operator; services: JSON [id,...]
services       — code (unique, short), color (hex), is_active
windows        — ip_address (unique, used for operator lookup), services: JSON
totems         — code (unique, matched by Electron .env), services: JSON, customization fields
turns          — core state machine; FKs to service, window, totem, attended_by, called_by
```

**Turn lifecycle:**
```
waiting → called → in_progress → completed
                              → cancelled
                              → no_show
called  → waiting  (return-to-queue)
```

### Known Issues to Fix

- `User.services`, `Window.services`, `Totem.services` use JSON arrays — no relational integrity, hard to query. Migration to pivot tables is the clean solution.
- No rate limiting on `POST /api/turns` (totem can spam tickets) or `POST /api/login`.
- No 401 interceptor in frontend — expired tokens silently fail instead of redirecting to `/login`.
- `windows.current_turn_id` exists in the DB but is not reliably updated by the turn state machine (verify before relying on it).
- No admin-role middleware guard; admin routes inside the Sanctum group rely on controller-level role checks — centralize this in a middleware.

---

## Frontend (Vue 3 + Vite 7 + Tailwind CSS v4)

### Commands

```bash
cd frontend
npm install
npm run dev      # dev server — change --host IP in package.json for your LAN
npm run build    # output to dist/
```

### Architecture

**Views** (`src/views/`):

| View | Route | Auth | Purpose |
|---|---|---|---|
| `HomeView.vue` | `/` | Public | Totem kiosk — service selection + ticket generation |
| `DisplayView.vue` | `/display` | Public | Large screen showing current queue state |
| `LoginView.vue` | `/login` | Public | Auth |
| `OperatorView.vue` | `/operator` | Operator | Queue management panel |
| `AdminView.vue` | `/admin` | Admin | Dashboard, CRUD, history |

**Components** (`src/components/`): Split into `admin/` and `operator/` subdirectories. Each component is scoped to its parent view.

**API client** (`src/api/index.js`): Single Axios instance with Bearer token interceptor. **All API calls go through here** — never create a second Axios instance.

**Print service** (`src/services/printService.js`): Unified interface that auto-detects execution context:
- In Electron → calls `window.turneroAPI.printTicket()` (IPC)
- In browser → HTTP POST to `VITE_PRINT_SERVICE_URL` (default: `http://localhost:5000`)

**Composables**: `useToast.js` (singleton toast, 3.5s auto-dismiss) and `useConfirm.js` (singleton confirmation dialog).

**Router** (`src/router/index.js`): Guards check `localStorage` for `token` and `user.role`. No Pinia or Vuex — state is local to each view.

**SSE**: Established directly inside views (`EventSource` on mount, closed on unmount). Both `DisplayView` and `OperatorView` default to **polling every 2 seconds** — switching `USE_SSE = true` activates SSE.

### Critical Configuration

**The backend URL is hardcoded** in `src/api/index.js` line 4. The `VITE_API_URL` env var is defined in `env.example` but currently unused. This must be fixed before deploying to any machine other than `192.168.0.51`. Same issue with logo URLs in `HomeView.vue` and SSE URL in `DisplayView.vue`.

When deploying to a new network:
1. `src/api/index.js` — backend base URL
2. `package.json` dev script — Vite `--host` IP
3. Totem logo URLs constructed inline in `HomeView.vue` — use env var

---

## Microservice / Electron App (`microservice/`)

### Commands

```bash
cd microservice
npm install
cp env.example .env        # edit SERVER_URL and TOTEM_CODE
npm start                  # production kiosk (fullscreen, kiosk mode)
npm run dev                # development (with DevTools, framed window)
npm run build:win          # build NSIS installer + portable .exe → dist/
```

### Architecture

**`main.js`** — Electron main process. Creates fullscreen kiosk window (1080×1920), registers IPC handlers, prevents navigation outside `SERVER_URL`, auto-retries connection on load failure.

**`preload.js`** — Context-isolated bridge. Exposes `window.turneroAPI` with: `printTicket()`, `getPrinter()`, `testPrint()`, `getConfig()`, `reloadApp()`, `openConfigFolder()`, `isElectron: true`.

**`src/config.js`** — Loads and exposes `.env` values. Single source of truth for runtime config.

**`src/envLoader.js`** — `.env` resolution priority: `%APPDATA%\Turnero Totem\.env` → `exe_dir/.env` → `cwd/.env`. Auto-creates from `env.example` on first run.

**`src/print/ticketPrinter.js`** — Spawns `print_ticket.py` as a child process. Passes ticket data as CLI arguments. Returns a Promise that resolves/rejects based on Python process exit code.

**`print_ticket.py`** — Windows-only. Uses `win32print` + `win32ui` to draw the ticket layout and send it to the thermal printer. **Edit this file to change ticket format.** Arguments: `ticket_number service_name [customer_name] [company_name]`.

### `.env` Variables

```env
SERVER_URL=http://<frontend-ip>:5173   # Vue frontend URL
TOTEM_CODE=TOTEM-PRINCIPAL             # Must match a totem record in the DB
COMPANY_NAME=Mi Empresa S.A.           # Printed on every ticket
PRINTER_NAME=                          # Empty = system default printer
TICKET_WIDTH=58                        # 58 or 80 (mm)
PYTHON_EXE=python                      # Absolute path if not in PATH
```

In production (installed), `.env` lives at `%APPDATA%\Turnero Totem\.env`.

### IPC Flow (Printing)

```
Vue (renderer)
  window.turneroAPI.printTicket(data)
    → IPC invoke 'print-ticket'
      → ticketPrinter.printTicket()
        → spawn('python', ['print_ticket.py', ...args])
          → win32print.GetDefaultPrinter()
          → win32ui.CreateDC()
          → dc.TextOut() × N
          → EndDoc → exit 0
      → Promise resolves {success: true}
    → IPC returns result
  ← {success, message?, error?}
```

---

## Development Principles (apply to all code in this repo)

1. **Ask before assuming** — If a requirement is ambiguous (e.g., "should operators see all services or only assigned ones?"), ask. Don't implement the easiest interpretation.
2. **No hardcoded values** — IPs, ports, URLs, and strings go into `.env` / `import.meta.env`. The codebase currently has hardcoded IPs that must be progressively eliminated.
3. **State transitions are explicit** — Turn status changes must go through the model's methods (`call()`, `startAttention()`, etc.), never raw `update(['status' => ...])` from controllers.
4. **One source of truth for API calls** — All frontend HTTP calls go through `src/api/index.js`. Never use `fetch()` or a second Axios instance for backend calls (print service HTTP calls are the exception — different host/port).
5. **Fail loudly in dev, fail gracefully in prod** — Backend validation errors (422) must surface to the user in a readable way, not swallowed by a generic catch block.
6. **SOLID in controllers** — Controllers validate, delegate to model/service, and return responses. No business logic in controllers.
7. **Component responsibility** — Vue components own their local state. Shared state (auth user, current window) flows via props/emits or composables, not localStorage reads scattered across components.

---

## Available Skills for This Project

Use these skills (via the `Skill` tool) when working on the corresponding areas:

| Skill | When to Use |
|---|---|
| `laravel-patterns` | Adding controllers, services, middleware, query optimization, Eloquent relationships, API resources |
| `laravel-specialist` | Sanctum auth flows, Eloquent pivot tables, Pest/PHPUnit tests, queue jobs, events |
| `vue` | Vue 3 components, Composition API, reactivity, lifecycle hooks |
| `vue-best-practices` | Component design, prop validation, composable patterns, performance |
| `vue-debug-guides` | Debugging reactivity, SSE/EventSource issues, rendering bugs |
| `tailwind-css-patterns` | Styling components, responsive layouts, Tailwind v4 patterns |
| `vite` | Vite config, env vars, build optimization, plugin setup |
| `frontend-design` | UI/UX improvements, new screens, component design |
| `nodejs-backend-patterns` | If adding an Express/Fastify print microservice HTTP server |
| `nodejs-best-practices` | Electron main process improvements, async patterns |
| `accessibility` | Making totem/display screens accessible |
| `superpowers:systematic-debugging` | Any bug or unexpected behavior — always invoke before proposing a fix |
| `superpowers:test-driven-development` | Before implementing any new feature or fix |
| `superpowers:writing-plans` | Before any multi-step feature work |
| `superpowers:brainstorming` | When exploring new features or architectural decisions |

---

## Recommended Improvements (Priority Order)

### P1 — Fix Before Shipping New Features

1. **Eliminate hardcoded IPs** — Move `API_BASE_URL` in `src/api/index.js` to `import.meta.env.VITE_API_URL`. Same for the logo base URL in `HomeView.vue` and the SSE URL in `DisplayView.vue`. One `.env` file per deploy.

2. **Add 401 interceptor in Axios** — In `src/api/index.js`, add a response interceptor that clears `localStorage` and redirects to `/login` on `401 Unauthorized`. Currently expired tokens silently fail.

3. **Rate limit critical endpoints** — `POST /api/turns` and `POST /api/login` need throttle middleware (`throttle:60,1` minimum). A broken totem can currently flood the queue.

4. **Centralize admin role guard** — Create a `EnsureIsAdmin` middleware and apply it to the admin route group instead of checking roles in each controller.

### P2 — Architecture Cleanup

5. **Migrate JSON service arrays to pivot tables** — `user_service`, `window_service`, `totem_service` pivot tables enable proper FK constraints, relational queries, and easy filtering. Current JSON arrays are a scalability dead-end.

6. **Enable SSE by default** — Remove the `USE_SSE = false` flags and switch both `DisplayView` and `OperatorView` to SSE. SSE is already implemented; polling is unnecessary overhead. Keep polling as a reconnect fallback.

7. **Add turn number reset per day option** — Currently ticket numbers never reset. Many deployments expect daily reset (e.g., `AC-001` again each morning). Make this configurable per totem profile.

8. **Operator service validation on turn call** — `TurnController::call()` should verify the calling operator is actually assigned to that turn's service (or has no service restriction).

### P3 — New Features

9. **Priority queue UI** — The `priority` field exists in the DB and model but there's no UI to create priority turns (e.g., for elderly/disabled customers or pre-scheduled appointments).

10. **Operator performance dashboard** — Average service time, turns attended per operator per shift, no-show rates. Data already in `turns` table via timestamps.

11. **Daily ticket number reset** — Configurable via admin panel. Requires a scheduled command (`php artisan schedule:run`) and a `last_reset_at` field on services.

12. **Print service HTTP fallback server** — The `microservice/scripts/` directory may contain a standalone Python HTTP server (`print_service.py`) for when Electron isn't used. Formalize this as a proper Express or Python Flask server with health endpoint.

13. **Totem offline page** — When `main.js` fails to load `SERVER_URL`, it shows `src/error.html`. Improve this page to show the totem code, last-known server URL, and a retry countdown.

14. **Config QR code** — Generate a QR in the admin panel encoding the totem's `.env` variables so field technicians can configure new totems by scanning instead of typing IPs.

---

## Future Architecture — Channels

**Current limitation:** All totems, operator stations, and display screens share a single global queue. Every totem creates turns visible to all operators that have that service, and the display shows all turns regardless of physical location.

**Target model — Channels:** A "channel" groups a set of totems, operator windows, and display screens that operate independently. Example: a 3-floor hospital with channel A (floor 1 totem + 2 cashiers + 1 display), channel B (floor 2), channel C (floor 3). Turns issued by a totem in channel A are only visible to operators and displays in channel A.

**Design implications for future work:**
- `turns`, `windows`, `totems`, and display configs must all carry a `channel_id` FK.
- The operator queue endpoint filters by the operator's channel, not just their services.
- The display `/display?channel=A` endpoint filters turns by channel.
- The SSE streams emit per-channel, not globally.
- The admin panel manages channels as a new entity (CRUD).
- **Do not design new features that couple global state further** — keep per-entity config (video, settings) scoped in a way that can be migrated to per-channel later with a FK addition.

**Current implementation context:** Display settings (video, layout) are currently global (one per installation). When channels are introduced, `display_settings` will need a `channel_id` (nullable = global default) to support per-channel display configuration.
