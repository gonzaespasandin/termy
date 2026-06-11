# Totem Kiosk Mode — Design

## Problem

The totem app (`microservice/`, Electron) already runs with `kiosk: true` and
`fullscreen: true`, but this only hides window chrome. It does not prevent:

- Exiting via OS-level shortcuts (Alt+F4, Alt+Tab, Win key, Ctrl+Alt+Del menu,
  Task Manager).
- The app failing to relaunch if it crashes or is force-closed.

Windows' built-in "Assigned Access" kiosk mode would solve this but requires
device certification / enterprise enrollment that isn't available to this
deployment. We need a self-managed equivalent that works on Windows 10/11 Pro
on the BMax hardware, without certification.

## Goals

- Customers cannot exit the totem app or reach the Windows desktop.
- If the app is closed (crash, force-kill, stray shortcut), it relaunches
  automatically within a couple of seconds.
- A technician can still get to the Windows desktop for maintenance (printer
  config, app updates) via a hidden shortcut + password.
- Reproducible setup across multiple totems via a script + documentation.

## Non-goals

- Full MDM/enterprise kiosk management (Intune, Assigned Access).
- Locking down totems against a determined attacker with physical access and
  unlimited time (BIOS access, disk removal, etc. are out of scope).

## Architecture Overview

Three layers, from innermost to outermost:

1. **Electron app hardening** (`microservice/main.js` + new files) — prevents
   the renderer/window from being closed normally, and provides a
   password-gated maintenance escape hatch.
2. **Dedicated Windows account + custom shell** — a non-admin `totem` user
   whose "shell" (replacing `explorer.exe`) is a small watchdog script that
   keeps the totem app running.
3. **Registry-based restrictions** for the `totem` user — disables Task
   Manager, Ctrl+Alt+Del options, Control Panel, Run dialog, etc.

A separate admin account remains untouched for remote maintenance (RDP).

## Part 1: Electron App Hardening

### Prevent normal close

In `createWindow()`, listen to the window's `close` event:

```js
mainWindow.on('close', (event) => {
  if (!maintenanceMode) {
    event.preventDefault();
  }
});
```

This is a defense-in-depth measure on top of `kiosk: true`.

### Maintenance escape hatch

- Register a global shortcut (e.g. `Ctrl+Alt+Shift+Q`) via
  `globalShortcut.register`.
- On trigger, open a small frameless `BrowserWindow` (loaded from a local
  HTML file bundled in `microservice/src/`) with a password input.
- Compare a SHA-256 hash of the entered password against
  `KIOSK_EXIT_PASSWORD_HASH` from `.env` (loaded via the existing
  `envLoader`/`config` modules). Never store the plaintext password.
- On success:
  - `mainWindow.setKiosk(false)`, `mainWindow.setFullScreen(false)`,
    `maintenanceMode = true`.
  - Spawn `explorer.exe` (since it's not running as the shell) so the
    technician has a taskbar/desktop to work with.
- The same shortcut, while in maintenance mode, re-arms kiosk mode: kills the
  spawned `explorer.exe`, sets `maintenanceMode = false`,
  `setKiosk(true)`/`setFullScreen(true)`, refocuses the window.

### Auto-recovery from renderer crashes

Add a handler so a crashed renderer doesn't leave a blank window:

```js
mainWindow.webContents.on('render-process-gone', (event, details) => {
  console.error('Renderer gone:', details.reason);
  createWindow();
});
```

### Config additions

- `microservice/env.example`: add `KIOSK_EXIT_PASSWORD_HASH=` with a comment
  explaining it's a SHA-256 hex hash, plus a one-liner showing how to generate
  one (e.g. via `node -e "console.log(require('crypto').createHash('sha256').update('yourpassword').digest('hex'))"`).
- `microservice/src/config.js`: expose `kioskExitPasswordHash`.

## Part 2: Windows Account, Shell Replacement, Watchdog

### Dedicated `totem` account

- Local standard user (no admin rights), e.g. `totem`.
- Auto-logon configured via registry under
  `HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon`:
  - `AutoAdminLogon = 1`
  - `DefaultUserName = totem`
  - `DefaultPassword = <password>`
  - `DefaultDomainName = <computer name>`

### Watchdog as shell

- The user's shell (`HKEY_USERS\<totem-SID>\Software\Microsoft\Windows
  NT\CurrentVersion\Winlogon\Shell`, or machine-wide `Shell` value if a
  per-user override isn't picked up — verify on real hardware) points to a
  hidden-window wrapper script: `microservice/scripts/kiosk-watchdog.ps1`,
  launched via `wscript.exe` + a `.vbs` shim (so no console window flashes).
- The watchdog loop:
  1. Launch `Turnero Totem.exe` (the built app from `dist/win-unpacked/` or
     installed location).
  2. Wait for the process to exit.
  3. Sleep ~2 seconds.
  4. Repeat indefinitely.
- If the watchdog process itself dies, Windows ends the `totem` session;
  auto-logon immediately starts a new session, restarting the watchdog. This
  is the outer safety net.

### Admin account for remote maintenance

- Existing (or a newly created) admin account stays as-is, used for RDP /
  AnyDesk access. Not touched by the kiosk setup.

## Part 3: Registry-Based Restrictions for `totem`

Applied under `HKEY_USERS\<totem-SID>\Software\Microsoft\Windows\CurrentVersion\Policies\...`
(equivalent of per-user Group Policy, set directly via registry so no
`gpedit.msc` GUI interaction is required):

- `System\DisableTaskMgr = 1` — disable Task Manager.
- `System\DisableLockWorkstation = 1`, `Explorer\NoLogoff = 1`,
  `System\DisableChangePassword = 1` — restrict Ctrl+Alt+Del menu options.
- `Explorer\NoControlPanel = 1` — block Control Panel / Settings.
- `Explorer\NoRun = 1` — disable the Run dialog.
- `Explorer\NoDrives = 0xFFFFFFFF` — hide drives if any file browser surfaces.

**Verification required on real hardware** (BMax units): since the shell is
not `explorer.exe`, behaviors of the Win key, Alt+Tab, and Start menu are
uncertain on Windows 10/11 Pro and must be checked during rollout — some of
these may need additional registry keys (e.g. `NoWinKeys`) or may simply be
non-functional already because there's nothing for them to switch to.

## Deliverables

1. **`microservice/main.js`** — updated with close-prevention, global
   shortcut + maintenance mode, render-process-gone recovery.
2. **`microservice/src/maintenance.html`** (or similar) — minimal password
   prompt UI for the escape hatch.
3. **`microservice/scripts/setup-kiosk.ps1`** — one-time setup script (run as
   admin on each totem): creates the `totem` user, configures auto-logon,
   installs the watchdog as shell, applies registry restrictions. Idempotent
   where practical (safe to re-run).
4. **`microservice/scripts/kiosk-watchdog.ps1`** + a `.vbs`/shortcut shim to
   run it hidden (no console window).
5. **`microservice/KIOSK_SETUP.md`** — explains what the script does step by
   step, how to set the maintenance password (and regenerate the hash), how
   to revert (restore `explorer.exe` as shell, remove `totem` user, undo
   auto-logon), and the manual registry steps for anyone who prefers not to
   run the script.
6. **`microservice/env.example`** — add `KIOSK_EXIT_PASSWORD_HASH`.
7. **Rollout/test plan** (in `KIOSK_SETUP.md`): apply to one totem first,
   manually verify:
   - App auto-starts on boot with no login prompt.
   - Win key / Alt+Tab / Ctrl+Alt+Del / Task Manager are blocked or harmless.
   - Force-killing the totem process (e.g. via Ctrl+Alt+Del → if still
     reachable) results in auto-relaunch within a few seconds.
   - The maintenance shortcut + password correctly exits/re-enters kiosk mode
     and provides a usable desktop for updates/printer config.
   - Only after this passes on one unit, replicate `setup-kiosk.ps1` to the
     remaining totems.

## Open Risks / Caveats

- Windows 11 Pro feature updates occasionally reset shell/policy registry
  values; `KIOSK_SETUP.md` should note this as a known maintenance item to
  re-check after major Windows updates.
- The `.ps1` scripts require `Set-ExecutionPolicy` adjustments or
  `-ExecutionPolicy Bypass` to run; document this in `KIOSK_SETUP.md`.
- If a totem's `Turnero Totem.exe` path changes between installs (e.g. NSIS
  installer vs portable build), `kiosk-watchdog.ps1` needs the correct path —
  document how to find/update it.
