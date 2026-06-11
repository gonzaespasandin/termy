# Totem Kiosk Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the totem Electron app fully kiosk-locked (no exit to Windows desktop except via a hidden password-gated maintenance shortcut), and ensure it auto-relaunches if closed/crashed, without requiring Windows Assigned Access certification.

**Architecture:** Three layers — (1) Electron-side hardening in `microservice/` (close prevention, crash recovery, password-gated maintenance escape hatch via global shortcut), (2) a dedicated non-admin Windows `totem` account whose shell is replaced by a watchdog loop that relaunches the totem app, (3) registry-based restrictions (Task Manager, Control Panel, Run dialog, etc.) baked into new user profiles via the Default user hive.

**Tech Stack:** Electron 28 (main/preload/renderer), Node `crypto`/`child_process`, PowerShell + VBScript for the Windows-side setup.

---

## Reference: Design Spec

See `docs/superpowers/specs/2026-06-11-totem-kiosk-mode-design.md` for the full rationale and trade-offs.

## Note on testing approach

`microservice/` has no automated test framework (only a manual `test-print-local.js` script). This plan uses **manual verification steps** for each task instead of unit tests — run the app / script and check the described behavior. This matches existing project conventions for this package.

---

### Task 1: Add maintenance config (`KIOSK_EXIT_PASSWORD_HASH`)

**Files:**
- Modify: `microservice/src/config.js`
- Modify: `microservice/env.example`

- [ ] **Step 1: Add `kioskExitPasswordHash` to config**

In `microservice/src/config.js`, add a new field to the exported `config` object, right after `autoStart`:

```js
  // Auto-inicio con Windows
  autoStart: process.env.AUTO_START !== 'false',

  // Hash SHA-256 de la contraseña para salir del modo kiosko (Ctrl+Alt+Shift+Q)
  // Vacío = atajo de mantenimiento deshabilitado
  kioskExitPasswordHash: process.env.KIOSK_EXIT_PASSWORD_HASH || ''
```

(Remember to remove the trailing comma from the previous last field if your editor doesn't auto-fix it — `autoStart` line needs a trailing comma since it's no longer last.)

- [ ] **Step 2: Document the variable in `env.example`**

In `microservice/env.example`, append a new section at the end of the file:

```env

# ============================================================
# MODO KIOSKO - MANTENIMIENTO
# ============================================================
# Hash SHA-256 de la contraseña para salir del modo kiosko.
# Atajo: Ctrl+Alt+Shift+Q (abre un prompt de contraseña).
# Generar el hash con:
#   node -e "console.log(require('crypto').createHash('sha256').update('TU_CONTRASEÑA').digest('hex'))"
# Dejar vacío deshabilita el atajo de mantenimiento.
KIOSK_EXIT_PASSWORD_HASH=
```

- [ ] **Step 3: Manually verify config reads the value**

Run from `microservice/`:

```bash
node -e "process.env.KIOSK_EXIT_PASSWORD_HASH='abc123'; console.log(require('./src/config').kioskExitPasswordHash)"
```

Expected output: `abc123`

Then run without setting the env var:

```bash
node -e "console.log(require('./src/config').kioskExitPasswordHash)"
```

Expected output: empty string (just a blank line).

- [ ] **Step 4: Commit**

```bash
git add microservice/src/config.js microservice/env.example
git commit -m "feat(totem): add kiosk maintenance password hash config"
```

---

### Task 2: Expose maintenance IPC bridge in preload

**Files:**
- Modify: `microservice/preload.js`

- [ ] **Step 1: Add `verifyKioskPassword` and `cancelMaintenancePrompt` to the exposed API**

In `microservice/preload.js`, inside the `contextBridge.exposeInMainWorld('turneroAPI', { ... })` object, add two new methods. Insert them after the `retryConnection` method (before the `// Información` section comment):

```js
  /**
   * Reintenta la conexión con el servidor (desde la pantalla de error)
   */
  retryConnection: () => ipcRenderer.invoke('retry-connection'),

  // ============================================================
  // Modo Mantenimiento (Kiosko)
  // ============================================================

  /**
   * Verifica la contraseña de mantenimiento. Si es correcta, la app
   * sale del modo kiosko y se abre el escritorio de Windows.
   * @param {string} password
   * @returns {Promise<{success: boolean, error?: string}>}
   */
  verifyKioskPassword: (password) => ipcRenderer.invoke('verify-kiosk-password', password),

  /**
   * Cierra el prompt de mantenimiento sin verificar la contraseña.
   */
  cancelMaintenancePrompt: () => ipcRenderer.invoke('cancel-maintenance-prompt'),

```

- [ ] **Step 2: Commit**

```bash
git add microservice/preload.js
git commit -m "feat(totem): expose maintenance IPC bridge in preload"
```

---

### Task 3: Build the maintenance password prompt UI

**Files:**
- Create: `microservice/src/maintenance.html`

- [ ] **Step 1: Create the maintenance prompt HTML**

Create `microservice/src/maintenance.html`:

```html
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Modo Mantenimiento</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #1a1a2e;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      overflow: hidden;
    }

    .container {
      text-align: center;
      padding: 30px;
      width: 100%;
    }

    h1 {
      font-size: 18px;
      margin-bottom: 15px;
    }

    input[type="password"] {
      width: 80%;
      padding: 10px;
      font-size: 16px;
      border-radius: 6px;
      border: 1px solid #444;
      background: #0f0f1e;
      color: white;
      margin-bottom: 10px;
    }

    button {
      padding: 10px 25px;
      font-size: 14px;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      margin: 5px;
    }

    .confirm {
      background: #3498db;
      color: white;
    }

    .cancel {
      background: #555;
      color: white;
    }

    .error {
      color: #e74c3c;
      min-height: 18px;
      font-size: 13px;
      margin-top: 8px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>🔒 Modo Mantenimiento</h1>
    <input type="password" id="password" placeholder="Contraseña" autofocus>
    <div class="error" id="error"></div>
    <div>
      <button class="confirm" id="confirm-btn">Ingresar</button>
      <button class="cancel" id="cancel-btn">Cancelar</button>
    </div>
  </div>
  <script>
    const input = document.getElementById('password');
    const errorEl = document.getElementById('error');

    async function submitPassword() {
      const result = await window.turneroAPI.verifyKioskPassword(input.value);
      if (!result.success) {
        errorEl.textContent = result.error || 'Contraseña incorrecta';
        input.value = '';
        input.focus();
      }
    }

    function cancel() {
      window.turneroAPI.cancelMaintenancePrompt();
    }

    document.getElementById('confirm-btn').addEventListener('click', submitPassword);
    document.getElementById('cancel-btn').addEventListener('click', cancel);

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') submitPassword();
      if (e.key === 'Escape') cancel();
    });
  </script>
</body>
</html>
```

- [ ] **Step 2: Commit**

```bash
git add microservice/src/maintenance.html
git commit -m "feat(totem): add maintenance mode password prompt UI"
```

---

### Task 4: Implement maintenance mode, global shortcut, close prevention, and crash recovery in `main.js`

**Files:**
- Modify: `microservice/main.js`

This is the core of the kiosk lockdown. Broken into sub-steps that build on each other.

- [ ] **Step 1: Add new imports**

In `microservice/main.js`, replace the top imports:

```js
const { app, BrowserWindow, ipcMain, dialog, Menu, shell } = require('electron');
const path = require('path');
const { loadEnv } = require('./src/envLoader');
```

with:

```js
const { app, BrowserWindow, ipcMain, dialog, Menu, shell, globalShortcut } = require('electron');
const path = require('path');
const crypto = require('crypto');
const { spawn } = require('child_process');
const { loadEnv } = require('./src/envLoader');
```

- [ ] **Step 2: Add maintenance mode state**

After the existing module-level state:

```js
// Mantener referencia global de la ventana
let mainWindow = null;

let config = null;
let ticketPrinter = null;
let isShowingErrorPage = false;
```

add:

```js

// Estado del modo mantenimiento (escape del kiosko)
let maintenanceMode = false;
let maintenanceWindow = null;
let explorerProcess = null;

const MAINTENANCE_SHORTCUT = 'Control+Alt+Shift+Q';
```

- [ ] **Step 3: Add maintenance mode helper functions**

Add these functions after `loadServer()` and before `createWindow()`:

```js
// Abre el prompt de contraseña de mantenimiento
function openMaintenancePrompt() {
  if (maintenanceWindow) {
    maintenanceWindow.focus();
    return;
  }

  maintenanceWindow = new BrowserWindow({
    width: 400,
    height: 250,
    frame: false,
    alwaysOnTop: true,
    resizable: false,
    parent: mainWindow,
    modal: true,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.join(__dirname, 'preload.js')
    }
  });

  maintenanceWindow.setMenuBarVisibility(false);
  maintenanceWindow.loadFile(path.join(__dirname, 'src', 'maintenance.html'));

  maintenanceWindow.on('closed', () => {
    maintenanceWindow = null;
  });
}

// Sale del modo kiosko y abre el explorador de Windows para mantenimiento
function enterMaintenanceMode() {
  maintenanceMode = true;

  if (mainWindow) {
    mainWindow.setKiosk(false);
    mainWindow.setFullScreen(false);
    mainWindow.minimize();
  }

  if (process.platform === 'win32') {
    explorerProcess = spawn('explorer.exe', [], { detached: true, stdio: 'ignore' });
    explorerProcess.unref();
  }

  if (maintenanceWindow) {
    maintenanceWindow.close();
  }

  console.log('🔧 Modo mantenimiento activado');
}

// Vuelve a poner la app en modo kiosko y cierra el explorador abierto
function exitMaintenanceMode() {
  maintenanceMode = false;

  if (process.platform === 'win32') {
    spawn('taskkill', ['/IM', 'explorer.exe', '/F'], { stdio: 'ignore' });
  }
  explorerProcess = null;

  if (mainWindow) {
    mainWindow.restore();
    mainWindow.setFullScreen(true);
    mainWindow.setKiosk(true);
    mainWindow.focus();
  }

  if (maintenanceWindow) {
    maintenanceWindow.close();
  }

  console.log('🔒 Modo kiosko reactivado');
}
```

- [ ] **Step 4: Add `close` prevention and crash recovery to `createWindow()`**

In `createWindow()`, find this block:

```js
  // Manejar cierre de ventana
  mainWindow.on('closed', () => {
    mainWindow = null;
  });
```

and add a new handler **before** it:

```js
  // Evitar que la ventana se cierre (Alt+F4, etc.) — el kiosko nunca se cierra solo
  mainWindow.on('close', (event) => {
    event.preventDefault();
  });

  // Manejar cierre de ventana
  mainWindow.on('closed', () => {
    mainWindow = null;
  });
```

Then find:

```js
  // Manejar errores de carga (solo del frame principal)
  mainWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription, validatedURL, isMainFrame) => {
    if (!isMainFrame) return;
    console.error(`❌ Error de carga: ${errorDescription} (${errorCode})`);
    showErrorPage();
  });
}
```

and add a crash-recovery handler before the closing `}`:

```js
  // Manejar errores de carga (solo del frame principal)
  mainWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription, validatedURL, isMainFrame) => {
    if (!isMainFrame) return;
    console.error(`❌ Error de carga: ${errorDescription} (${errorCode})`);
    showErrorPage();
  });

  // Si el renderer crashea, recrear la ventana en vez de dejarla en blanco
  mainWindow.webContents.on('render-process-gone', (event, details) => {
    console.error('❌ Render process gone:', details.reason);
    if (mainWindow) {
      mainWindow.destroy();
      mainWindow = null;
    }
    createWindow();
  });
}
```

- [ ] **Step 5: Add IPC handlers for maintenance mode**

In `registerIpcHandlers()`, add new handlers after the `open-config-folder` handler (before the closing `}` of the function):

```js
  // Abrir carpeta de configuración (soporte)
  ipcMain.handle('open-config-folder', async () => {
    try {
      const userDataDir = app.getPath('userData');
      await shell.openPath(userDataDir);
      return { success: true };
    } catch (error) {
      return { success: false, error: error.message };
    }
  });

  // Verificar contraseña de mantenimiento y, si es correcta, salir del kiosko
  ipcMain.handle('verify-kiosk-password', (event, password) => {
    if (!config.kioskExitPasswordHash) {
      return { success: false, error: 'Modo mantenimiento no configurado' };
    }

    const hash = crypto.createHash('sha256').update(String(password || ''), 'utf8').digest('hex');

    if (hash === config.kioskExitPasswordHash) {
      enterMaintenanceMode();
      return { success: true };
    }

    return { success: false, error: 'Contraseña incorrecta' };
  });

  // Cerrar el prompt de mantenimiento sin verificar
  ipcMain.handle('cancel-maintenance-prompt', () => {
    if (maintenanceWindow) {
      maintenanceWindow.close();
    }
    return { success: true };
  });
```

- [ ] **Step 6: Register the global shortcut and clean it up on quit**

In `app.whenReady().then(() => { ... })`, find:

```js
  registerIpcHandlers();
  createWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});
```

and replace with:

```js
  registerIpcHandlers();
  createWindow();

  // Atajo oculto para entrar/salir del modo mantenimiento
  const shortcutOk = globalShortcut.register(MAINTENANCE_SHORTCUT, () => {
    if (maintenanceMode) {
      exitMaintenanceMode();
    } else {
      openMaintenancePrompt();
    }
  });

  if (!shortcutOk) {
    console.error(`⚠️ No se pudo registrar el atajo de mantenimiento (${MAINTENANCE_SHORTCUT})`);
  }

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('will-quit', () => {
  globalShortcut.unregisterAll();
});
```

- [ ] **Step 7: Manually verify in dev mode**

From `microservice/`:

1. Generate a test password hash:
   ```bash
   node -e "console.log(require('crypto').createHash('sha256').update('test123').digest('hex'))"
   ```
2. Add the resulting hash to `microservice/.env` as `KIOSK_EXIT_PASSWORD_HASH=<hash>`.
3. Run `npm run dev`.
4. Press `Ctrl+Alt+Shift+Q`. **Expected:** a small dark "Modo Mantenimiento" window appears with a password field.
5. Type a wrong password and press Enter. **Expected:** "Contraseña incorrecta" appears in red, field clears.
6. Type `test123` and press Enter. **Expected:** the maintenance window closes, the main window exits fullscreen/kiosk and minimizes, and (on Windows) `explorer.exe` launches/focuses.
7. Press `Ctrl+Alt+Shift+Q` again. **Expected:** `explorer.exe` is killed, the main window restores to fullscreen kiosk mode.
8. Try closing the main window via Alt+F4 while in kiosk mode (not maintenance mode). **Expected:** the window does not close.

- [ ] **Step 8: Commit**

```bash
git add microservice/main.js
git commit -m "feat(totem): add kiosk lockdown with password-gated maintenance mode"
```

---

### Task 5: Watchdog scripts for auto-relaunch

**Files:**
- Create: `microservice/scripts/kiosk-watchdog.ps1`
- Create: `microservice/scripts/kiosk-watchdog.vbs`

- [ ] **Step 1: Create the watchdog loop script**

Create `microservice/scripts/kiosk-watchdog.ps1`:

```powershell
<#
  Kiosk Watchdog - Turnero Totem
  ================================
  Mantiene "Turnero Totem.exe" corriendo. Pensado para ejecutarse como el
  "Shell" de la cuenta de Windows dedicada al totem (ver KIOSK_SETUP.md).

  Si la app se cierra (usuario, crash, etc.), este script la vuelve a abrir
  despues de unos segundos. El loop nunca termina por si mismo.
#>

# Ruta al ejecutable instalado. Ajustar segun la instalacion real
# (ver KIOSK_SETUP.md para como encontrarla).
$ExePath = "C:\Users\totem\AppData\Local\Programs\Turnero Totem\Turnero Totem.exe"

$RestartDelaySeconds = 2
$MissingExeRetrySeconds = 10

while ($true) {
    if (Test-Path -LiteralPath $ExePath) {
        try {
            Start-Process -FilePath $ExePath -Wait
        } catch {
            Write-Host "Error al iniciar Turnero Totem: $_"
        }
        Start-Sleep -Seconds $RestartDelaySeconds
    } else {
        Write-Host "No se encontro el ejecutable en: $ExePath"
        Start-Sleep -Seconds $MissingExeRetrySeconds
    }
}
```

- [ ] **Step 2: Create the hidden launcher (no console window)**

Create `microservice/scripts/kiosk-watchdog.vbs`:

```vbs
' Lanza kiosk-watchdog.ps1 sin mostrar ninguna ventana de consola.
' Pensado para usarse como "Shell" de Winlogon (ver KIOSK_SETUP.md).

Set objShell = CreateObject("WScript.Shell")
scriptDir = objShell.ExpandEnvironmentStrings("%ProgramData%") & "\TurneroTotemKiosk"
scriptPath = scriptDir & "\kiosk-watchdog.ps1"

command = "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File """ & scriptPath & """"

' El tercer argumento (True) hace que este script espere a que termine
' powershell.exe. Como el watchdog es un loop infinito, este wscript.exe
' permanece vivo mientras el watchdog corre - eso es lo que lo hace
' valido como "Shell" (si el shell termina, Windows cierra la sesion).
objShell.Run command, 0, True
```

- [ ] **Step 3: Manually verify the watchdog loop**

On a Windows machine with the totem app built (or any test `.exe`):

1. Edit `$ExePath` in `kiosk-watchdog.ps1` to point to a real executable (e.g. `notepad.exe` for a quick test).
2. Run: `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\kiosk-watchdog.ps1`
3. **Expected:** Notepad opens.
4. Close Notepad.
5. **Expected:** after ~2 seconds, Notepad opens again automatically.
6. Press `Ctrl+C` in the PowerShell window to stop the loop, close Notepad.

- [ ] **Step 4: Commit**

```bash
git add microservice/scripts/kiosk-watchdog.ps1 microservice/scripts/kiosk-watchdog.vbs
git commit -m "feat(totem): add kiosk watchdog scripts for auto-relaunch"
```

---

### Task 6: One-time Windows setup script

**Files:**
- Create: `microservice/scripts/setup-kiosk.ps1`

- [ ] **Step 1: Create the setup script**

Create `microservice/scripts/setup-kiosk.ps1`:

```powershell
<#
  Setup Kiosk - Turnero Totem
  ============================
  Configura un totem Windows 10/11 Pro para correr "Turnero Totem.exe" en
  modo kiosko sin Assigned Access:

    1. Crea una cuenta local "totem" (sin admin) con auto-logon.
    2. Reemplaza el "Shell" de Winlogon por el watchdog (kiosk-watchdog.vbs),
       que mantiene la app del totem siempre abierta.
    3. Conserva explorer.exe como shell para la cuenta admin actual.
    4. Aplica restricciones (Task Manager, Panel de Control, Ejecutar, etc.)
       al perfil "Default" de Windows, para que las herede la cuenta "totem"
       cuando inicie sesion por primera vez.

  USO (ejecutar como Administrador):
    powershell.exe -ExecutionPolicy Bypass -File setup-kiosk.ps1 `
        -TotemUsername "totem" `
        -TotemPassword "CAMBIAR_ESTA_CLAVE" `
        -ExePath "C:\Users\totem\AppData\Local\Programs\Turnero Totem\Turnero Totem.exe"

  Ver KIOSK_SETUP.md para el detalle de cada paso y como revertir.
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$TotemUsername,

    [Parameter(Mandatory = $true)]
    [string]$TotemPassword,

    [Parameter(Mandatory = $true)]
    [string]$ExePath,

    [string]$AdminUsername = $env:USERNAME
)

$ErrorActionPreference = "Stop"

function Assert-Admin {
    $currentPrincipal = New-Object Security.Principal.WindowsPrincipal(
        [Security.Principal.WindowsIdentity]::GetCurrent()
    )
    if (-not $currentPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw "Este script debe ejecutarse como Administrador."
    }
}

Assert-Admin

$WinlogonPath = "HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon"
$KioskDir = Join-Path $env:ProgramData "TurneroTotemKiosk"

Write-Host "=== 1. Creando cuenta local '$TotemUsername' ==="
$existingUser = Get-LocalUser -Name $TotemUsername -ErrorAction SilentlyContinue
if ($existingUser) {
    Write-Host "El usuario '$TotemUsername' ya existe, se actualiza la contraseña."
    $securePass = ConvertTo-SecureString $TotemPassword -AsPlainText -Force
    Set-LocalUser -Name $TotemUsername -Password $securePass -PasswordNeverExpires $true
} else {
    $securePass = ConvertTo-SecureString $TotemPassword -AsPlainText -Force
    New-LocalUser -Name $TotemUsername -Password $securePass `
        -PasswordNeverExpires -AccountNeverExpires -UserMayNotChangePassword `
        -Description "Cuenta dedicada para el totem (modo kiosko)"
    Add-LocalGroupMember -Group "Users" -Member $TotemUsername
}

Write-Host "=== 2. Copiando scripts del watchdog a $KioskDir ==="
New-Item -ItemType Directory -Path $KioskDir -Force | Out-Null

$watchdogSrc = Join-Path $PSScriptRoot "kiosk-watchdog.ps1"
$watchdogVbsSrc = Join-Path $PSScriptRoot "kiosk-watchdog.vbs"
$watchdogDst = Join-Path $KioskDir "kiosk-watchdog.ps1"
$watchdogVbsDst = Join-Path $KioskDir "kiosk-watchdog.vbs"

# Copiar el .ps1 reemplazando $ExePath por la ruta real provista
(Get-Content $watchdogSrc) `
    -replace '\$ExePath = ".*"', "`$ExePath = `"$ExePath`"" |
    Set-Content $watchdogDst

Copy-Item $watchdogVbsSrc $watchdogVbsDst -Force

Write-Host "=== 3. Configurando auto-logon para '$TotemUsername' ==="
Set-ItemProperty -Path $WinlogonPath -Name "AutoAdminLogon" -Value "1" -Type String
Set-ItemProperty -Path $WinlogonPath -Name "DefaultUserName" -Value $TotemUsername -Type String
Set-ItemProperty -Path $WinlogonPath -Name "DefaultPassword" -Value $TotemPassword -Type String
Set-ItemProperty -Path $WinlogonPath -Name "DefaultDomainName" -Value $env:COMPUTERNAME -Type String

Write-Host "=== 4. Configurando el watchdog como Shell por defecto ==="
$shellCommand = "wscript.exe `"$watchdogVbsDst`""
Set-ItemProperty -Path $WinlogonPath -Name "Shell" -Value $shellCommand -Type String

Write-Host "=== 5. Preservando explorer.exe como Shell para la cuenta admin '$AdminUsername' ==="
$adminProfile = (Get-CimInstance Win32_UserProfile |
    Where-Object { $_.LocalPath -like "*\$AdminUsername" } |
    Select-Object -First 1).LocalPath

if (-not $adminProfile) {
    Write-Warning "No se encontro el perfil de '$AdminUsername'. Saltando este paso - configuralo manualmente (ver KIOSK_SETUP.md)."
} else {
    $hiveFile = Join-Path $adminProfile "NTUSER.DAT"
    $hiveName = "TempAdminHive"
    reg load "HKU\$hiveName" "$hiveFile" | Out-Null
    try {
        $adminWinlogonPath = "Registry::HKEY_USERS\$hiveName\Software\Microsoft\Windows NT\CurrentVersion\Winlogon"
        if (-not (Test-Path $adminWinlogonPath)) {
            New-Item -Path $adminWinlogonPath -Force | Out-Null
        }
        Set-ItemProperty -Path $adminWinlogonPath -Name "Shell" -Value "explorer.exe" -Type String
    } finally {
        [gc]::Collect()
        reg unload "HKU\$hiveName" | Out-Null
    }
}

Write-Host "=== 6. Aplicando restricciones al perfil 'Default' (heredadas por cuentas nuevas) ==="
$defaultHive = "C:\Users\Default\NTUSER.DAT"
$hiveName = "TempDefaultHive"
reg load "HKU\$hiveName" "$defaultHive" | Out-Null
try {
    $systemPolicyPath = "Registry::HKEY_USERS\$hiveName\Software\Microsoft\Windows\CurrentVersion\Policies\System"
    $explorerPolicyPath = "Registry::HKEY_USERS\$hiveName\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer"

    New-Item -Path $systemPolicyPath -Force | Out-Null
    New-Item -Path $explorerPolicyPath -Force | Out-Null

    # Deshabilitar Administrador de Tareas
    Set-ItemProperty -Path $systemPolicyPath -Name "DisableTaskMgr" -Value 1 -Type DWord
    # Restringir opciones de Ctrl+Alt+Supr
    Set-ItemProperty -Path $systemPolicyPath -Name "DisableLockWorkstation" -Value 1 -Type DWord
    Set-ItemProperty -Path $systemPolicyPath -Name "DisableChangePassword" -Value 1 -Type DWord
    # Bloquear Panel de Control / Configuracion
    Set-ItemProperty -Path $explorerPolicyPath -Name "NoControlPanel" -Value 1 -Type DWord
    # Deshabilitar el cuadro "Ejecutar"
    Set-ItemProperty -Path $explorerPolicyPath -Name "NoRun" -Value 1 -Type DWord
    # Bloquear cierre de sesion desde la UI
    Set-ItemProperty -Path $explorerPolicyPath -Name "NoLogoff" -Value 1 -Type DWord
} finally {
    [gc]::Collect()
    reg unload "HKU\$hiveName" | Out-Null
}

Write-Host ""
Write-Host "=== Listo ==="
Write-Host "Reiniciar el equipo para que '$TotemUsername' inicie sesion automaticamente"
Write-Host "y arranque el modo kiosko."
Write-Host ""
Write-Host "IMPORTANTE: revisar KIOSK_SETUP.md y probar en un solo totem antes de"
Write-Host "replicar esta configuracion al resto."
```

- [ ] **Step 2: Manually verify on a spare/test machine**

This script makes machine-wide changes (auto-logon, default shell, default user profile policies) and **must not** be run on a developer's primary machine. On a spare Windows 10/11 Pro machine or VM:

1. Build the totem app (`npm run build:win`) or note the path of an existing install.
2. Run as Administrator:
   ```powershell
   powershell.exe -ExecutionPolicy Bypass -File .\setup-kiosk.ps1 -TotemUsername "totem" -TotemPassword "Test1234!" -ExePath "C:\Path\To\Turnero Totem.exe"
   ```
3. **Expected:** script completes without errors, prints the "Listo" message.
4. Reboot.
5. **Expected:** Windows logs in as `totem` automatically (no login screen), and the totem app opens in kiosk mode (or the watchdog retries every 10s if `ExePath` is wrong).
6. Press the maintenance shortcut (`Ctrl+Alt+Shift+Q`) and enter the configured password — verify `explorer.exe` opens and Task Manager / Control Panel / Run dialog are blocked for the `totem` session.
7. Log off `totem` and log back in as the admin account — verify the admin still gets a normal `explorer.exe` desktop.

- [ ] **Step 3: Commit**

```bash
git add microservice/scripts/setup-kiosk.ps1
git commit -m "feat(totem): add one-time kiosk setup script for Windows"
```

---

### Task 7: Documentation — `KIOSK_SETUP.md`

**Files:**
- Create: `microservice/KIOSK_SETUP.md`
- Modify: `microservice/README.md`

- [ ] **Step 1: Write the setup/rollout/revert documentation**

Create `microservice/KIOSK_SETUP.md`:

```markdown
# Modo Kiosko del Totem (sin Assigned Access)

Esta guía explica cómo dejar un totem Windows 10/11 Pro corriendo
"Turnero Totem.exe" en modo kiosko: pantalla completa, sin acceso al
escritorio de Windows, y con reinicio automático si la app se cierra.

No requiere Windows Assigned Access ni certificación de dispositivos.

## Resumen del enfoque

1. **App (Electron):** la app ya corre en `kiosk: true` + `fullscreen: true`,
   bloquea el cierre de la ventana, y tiene un atajo oculto
   (`Ctrl+Alt+Shift+Q`) protegido por contraseña para entrar en
   "modo mantenimiento" (sale del kiosko y abre el explorador de Windows).
2. **Cuenta dedicada `totem`:** una cuenta de Windows local sin privilegios
   de administrador, con inicio de sesión automático.
3. **Shell reemplazado:** en vez de `explorer.exe`, la sesión de `totem`
   arranca un script "watchdog" que abre la app del totem y la vuelve a
   abrir si se cierra.
4. **Restricciones:** Task Manager, Panel de Control, cuadro "Ejecutar" y
   cierre de sesión quedan deshabilitados para `totem`.

La cuenta de administrador actual queda intacta (sigue usando
`explorer.exe` normalmente) para mantenimiento remoto (RDP/AnyDesk).

## Paso 0: Configurar la contraseña de mantenimiento

1. Elegí una contraseña (solo para técnicos).
2. Generá su hash SHA-256:
   ```bash
   node -e "console.log(require('crypto').createHash('sha256').update('TU_CONTRASEÑA').digest('hex'))"
   ```
3. Agregá el resultado a `.env` del totem (ver tabla de ubicaciones en
   `README.md`):
   ```env
   KIOSK_EXIT_PASSWORD_HASH=<hash generado>
   ```
4. Reiniciá la app para que tome el nuevo valor.

## Paso 1: Instalar la app del totem normalmente

Instalá `Turnero Totem Setup *.exe` (o copiá la versión portable) en el
equipo, con la cuenta de administrador actual. Anotá la ruta final del
`.exe` — la vas a necesitar en el paso 2. Ubicaciones típicas:

- Instalador NSIS: `C:\Users\<usuario>\AppData\Local\Programs\Turnero Totem\Turnero Totem.exe`
- Versión portable: donde hayas copiado `Turnero Totem *.exe`

## Paso 2: Correr el script de setup (recomendado)

Desde `microservice/scripts/`, como **Administrador**:

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\setup-kiosk.ps1 `
    -TotemUsername "totem" `
    -TotemPassword "ELEGIR_CLAVE_SEGURA" `
    -ExePath "C:\Users\totem\AppData\Local\Programs\Turnero Totem\Turnero Totem.exe"
```

Esto:
- Crea la cuenta `totem` (sin admin) con auto-logon.
- Copia los scripts del watchdog a `C:\ProgramData\TurneroTotemKiosk\`.
- Configura el watchdog como "Shell" por defecto.
- Mantiene `explorer.exe` como shell para tu cuenta admin actual.
- Aplica las restricciones (Task Manager, Panel de Control, Ejecutar,
  cierre de sesión) al perfil "Default", para que las herede la cuenta
  `totem` quando inicie sesión por primera vez.

Reiniciá el equipo al terminar.

## Paso 2 (alternativa): Pasos manuales

Si preferís no correr el script, o querés entender/auditar cada cambio:

### 2a. Crear la cuenta `totem`

Panel de Control → Cuentas de usuario → Agregar otra persona → cuenta
local, sin permisos de administrador.

### 2b. Configurar auto-logon

`regedit` → `HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon`:

| Valor | Tipo | Dato |
|---|---|---|
| `AutoAdminLogon` | REG_SZ | `1` |
| `DefaultUserName` | REG_SZ | `totem` |
| `DefaultPassword` | REG_SZ | `<contraseña de totem>` |
| `DefaultDomainName` | REG_SZ | `<nombre del equipo>` |

### 2c. Copiar los scripts del watchdog

Copiá `microservice/scripts/kiosk-watchdog.ps1` y `kiosk-watchdog.vbs` a
`C:\ProgramData\TurneroTotemKiosk\`. Editá `kiosk-watchdog.ps1` y poné la
ruta real del `.exe` en `$ExePath`.

### 2d. Configurar el Shell

En la misma clave `Winlogon`, cambiá:

| Valor | Tipo | Dato |
|---|---|---|
| `Shell` | REG_SZ | `wscript.exe "C:\ProgramData\TurneroTotemKiosk\kiosk-watchdog.vbs"` |

⚠️ Esto cambia el shell **por defecto para todas las cuentas**. Para que tu
cuenta admin siga teniendo `explorer.exe`, necesitás agregar un `Shell`
en `HKEY_CURRENT_USER\Software\Microsoft\Windows NT\CurrentVersion\Winlogon`
(creá la clave `Winlogon` si no existe) con valor `explorer.exe`,
**estando logueado con esa cuenta admin**.

### 2e. Restricciones para `totem`

Iniciá sesión una vez como `totem` (para que se cree su perfil), después
abrí `regedit` **logueado como `totem`** y creá/editá:

`HKEY_CURRENT_USER\Software\Microsoft\Windows\CurrentVersion\Policies\System`:

| Valor | Tipo | Dato |
|---|---|---|
| `DisableTaskMgr` | REG_DWORD | `1` |
| `DisableLockWorkstation` | REG_DWORD | `1` |
| `DisableChangePassword` | REG_DWORD | `1` |

`HKEY_CURRENT_USER\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer`:

| Valor | Tipo | Dato |
|---|---|---|
| `NoControlPanel` | REG_DWORD | `1` |
| `NoRun` | REG_DWORD | `1` |
| `NoLogoff` | REG_DWORD | `1` |

## Plan de pruebas (probar en UN totem antes de replicar)

Después de reiniciar:

- [ ] El equipo arranca directo en la app del totem, sin pantalla de login.
- [ ] La app está en pantalla completa, sin barra de tareas ni bordes.
- [ ] Tecla Windows, Alt+Tab, Ctrl+Alt+Supr → no permiten salir a un
      escritorio usable. **Si alguno funciona y muestra algo**, anotarlo:
      puede necesitar una restricción adicional no cubierta acá.
- [ ] Cerrar la app a la fuerza (si es posible llegar al Administrador de
      Tareas) → la app vuelve a abrirse sola en pocos segundos.
- [ ] `Ctrl+Alt+Shift+Q` + contraseña correcta → sale del kiosko, abre
      `explorer.exe`.
- [ ] `Ctrl+Alt+Shift+Q` de nuevo → cierra `explorer.exe`, vuelve al
      kiosko.
- [ ] Iniciar sesión con la cuenta admin (remoto o localmente) → sigue
      teniendo escritorio normal.

Solo después de validar todo esto, repetir el script en el resto de los
totems.

## Revertir

1. `regedit` → `HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon`:
   - `AutoAdminLogon` → `0`
   - `Shell` → `explorer.exe`
   - (opcional) borrar `DefaultPassword`
2. Borrar la cuenta `totem` (Panel de Control → Cuentas de usuario).
3. Borrar `C:\ProgramData\TurneroTotemKiosk\`.
4. Las restricciones del perfil "Default" solo afectan a cuentas creadas
   *después* de aplicarlas — no es necesario revertirlas para cuentas
   existentes, pero si se quiere, se editan con `reg load` igual que en
   el script (sección 6), poniendo los valores en `0` o eliminándolos.

## Notas / mantenimiento

- Las actualizaciones grandes de Windows (feature updates) a veces
  resetean valores de `Winlogon`/políticas — revisar después de cada una.
- Si cambia la ruta de instalación de la app, actualizar `$ExePath` en
  `C:\ProgramData\TurneroTotemKiosk\kiosk-watchdog.ps1`.
- Los scripts `.ps1` requieren `-ExecutionPolicy Bypass` para correr (no
  hace falta cambiar la política de ejecución del sistema).
```

- [ ] **Step 2: Link the new doc from the README**

In `microservice/README.md`, find the `## Funcionalidades` section:

```markdown
## Funcionalidades

- Carga el frontend Vue en pantalla completa bloqueada (modo kiosko)
- Impresión directa de tickets en impresoras térmicas mediante Python + win32print
- Configuración flexible mediante archivo `.env`
- Instalador Windows (NSIS) y versión portable ejecutable
- Reconexión automática si el servidor no está disponible al iniciar
```

and add a line after it:

```markdown
## Funcionalidades

- Carga el frontend Vue en pantalla completa bloqueada (modo kiosko)
- Impresión directa de tickets en impresoras térmicas mediante Python + win32print
- Configuración flexible mediante archivo `.env`
- Instalador Windows (NSIS) y versión portable ejecutable
- Reconexión automática si el servidor no está disponible al iniciar
- Modo kiosko reforzado a nivel de Windows (sin salida al escritorio,
  reinicio automático) — ver [`KIOSK_SETUP.md`](./KIOSK_SETUP.md)
```

- [ ] **Step 3: Commit**

```bash
git add microservice/KIOSK_SETUP.md microservice/README.md
git commit -m "docs(totem): add kiosk mode setup, rollout and revert guide"
```

---

## Final check

After all tasks:

- [ ] `npm run dev` in `microservice/` still starts normally and loads the
      frontend (no regressions from the `main.js` changes).
- [ ] With `KIOSK_EXIT_PASSWORD_HASH` unset, pressing `Ctrl+Alt+Shift+Q`
      opens the prompt but any password shows "Modo mantenimiento no
      configurado" — confirms the feature is safely opt-in.
- [ ] All new files (`maintenance.html`, `kiosk-watchdog.ps1`,
      `kiosk-watchdog.vbs`, `setup-kiosk.ps1`, `KIOSK_SETUP.md`) are
      committed.
