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
