# Modo Kiosko del Totem (sin Assigned Access)

Esta guía explica cómo dejar un totem Windows 10/11 Pro corriendo
"Turnero Totem.exe" en modo kiosko: pantalla completa, sin acceso al
escritorio de Windows, y con reinicio automático si la app se cierra.

No requiere Windows Assigned Access ni certificación de dispositivos.

## Orden correcto de instalación (resumen ejecutivo)

Antes de entrar en detalle, este es el orden correcto para hacer el setup
desde cero en un totem nuevo. Saltarse el orden causa problemas de permisos
que son difíciles de diagnosticar.

1. **Instalar Python para todos los usuarios** (ver Pre-requisitos)
2. **Instalar `pywin32`** para todos los usuarios
3. **Instalar la app del totem** en `C:\ProgramData\turnero-totem\`
4. **Crear la cuenta kiosko** y correr `setup-kiosk.ps1`
5. **Configurar el `.env`** del usuario kiosko
6. **Configurar la contraseña de mantenimiento**

> ⚠️ **El error más común:** instalar Python o la app "para el usuario
> actual" (o desde la Microsoft Store). Siempre usar el instalador de
> [python.org](https://www.python.org/downloads/) con "Install for all users".

---

## Pre-requisitos

### Python (obligatorio para impresión)

La impresión de tickets usa Python + `win32print`. Python **debe estar
instalado para todos los usuarios**, no solo para el admin.

**No usar** la versión de la Microsoft Store — no soporta bien `pywin32`.

1. Bajá el instalador desde [python.org](https://www.python.org/downloads/).
2. Al correrlo, marcá **"Add Python to PATH"**.
3. Elegí **"Install for all users"** (requiere admin). Esto lo instala en
   `C:\Program Files\Python3X\` y lo hace accesible para la cuenta kiosko.
4. Instalá `pywin32`:
   ```powershell
   python -m pip install pywin32
   ```
5. Verificá que funcione:
   ```powershell
   python -c "import win32print; print(win32print.GetDefaultPrinter())"
   ```

Si `pip` no se reconoce:
```powershell
python -m ensurepip --upgrade
python -m pip install pywin32
```

### App del totem

Instalá `Turnero Totem Setup *.exe` **en `C:\ProgramData\turnero-totem\`**,
no en `AppData` del usuario admin. Esto evita problemas de permisos cuando
la cuenta kiosko intenta ejecutar la app.

La manera más simple es instalar normalmente y después copiar la carpeta:
```powershell
Copy-Item "C:\Users\<admin>\AppData\Local\Programs\turnero-totem" `
    "C:\ProgramData\turnero-totem" -Recurse
```

La ruta del `.exe` que usarás en los pasos siguientes es:
```
C:\ProgramData\turnero-totem\Turnero Totem.exe
```

---

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

## Paso 0: Crear la cuenta kiosko primero (si no existe)

Antes de correr el script, creá la cuenta kiosko manualmente para que
su perfil de Windows ya exista. Esto es importante porque:
- El script aplica restricciones al perfil "Default" (para cuentas nuevas),
  pero si la cuenta ya existía antes de aplicarlas, no las hereda
  automáticamente.
- Si la cuenta no existe, el script la crea — pero conviene crearla antes
  para poder iniciar sesión una vez y que Windows genere el perfil.

Panel de Control → Cuentas de usuario → Agregar otra persona → cuenta
local, sin permisos de administrador. Ponerle contraseña (requerido para
que el auto-logon funcione en Windows Pro).

## Paso 1: Configurar la contraseña de mantenimiento

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

## Paso 2: Correr el script de setup (recomendado)

Desde `microservice/scripts/`, como **Administrador**:

```powershell
powershell.exe -ExecutionPolicy Bypass -File .\setup-kiosk.ps1 `
    -TotemUsername "totem" `
    -TotemPassword "ELEGIR_CLAVE_SEGURA" `
    -ExePath "C:\ProgramData\turnero-totem\Turnero Totem.exe"
```

Reemplazá `"totem"` por el nombre de usuario que creaste en el Paso 0.
Usá la ruta de `C:\ProgramData\` (ver Pre-requisitos) — no la de `AppData`
del admin, porque la cuenta kiosko no tendrá permisos para acceder ahí.

Esto:
- Crea la cuenta kiosko (sin admin) con auto-logon, o actualiza su
  contraseña si ya existe.
- Copia los scripts del watchdog a `C:\ProgramData\TurneroTotemKiosk\`.
- Configura el watchdog como "Shell" por defecto.
- Mantiene `explorer.exe` como shell para tu cuenta admin actual.
- Aplica las restricciones (Task Manager, Panel de Control, Ejecutar,
  cierre de sesión) al perfil "Default", para que las herede la cuenta
  kiosko la primera vez que inicie sesión.

> ⚠️ **Nota:** el script intenta montar el NTUSER.DAT de la cuenta admin
> para preservarle `explorer.exe`. Esto falla si estás logueado con esa
> cuenta (Windows ya tiene el hive cargado). Si pasa, el script para en
> el Paso 5 con un error. Solución: correr manualmente en PowerShell admin:
> ```powershell
> $path = "HKCU:\Software\Microsoft\Windows NT\CurrentVersion\Winlogon"
> if (-not (Test-Path $path)) { New-Item -Path $path -Force | Out-Null }
> Set-ItemProperty -Path $path -Name "Shell" -Value "explorer.exe"
> ```
> Después correr el Paso 6 manualmente (ver más abajo) y reiniciar.

Reiniciá el equipo al terminar.

## Paso 3: Configurar el `.env` de la cuenta kiosko

Al primer arranque, la app crea el `.env` automáticamente copiando
`env.example`. Está en:
```
C:\Users\<totem-username>\AppData\Roaming\Turnero Totem\.env
```

Editalo y completá al menos:
```env
SERVER_URL=http://<IP-del-servidor>:5173
TOTEM_CODE=TOTEM-PRINCIPAL     # debe coincidir con un registro en la BD
KIOSK_EXIT_PASSWORD_HASH=      # ver Paso 1
```

Si la impresora térmica no es la predeterminada del sistema para la cuenta
kiosko, especificala:
```env
PRINTER_NAME=KPOS_58 Printer   # nombre exacto como aparece en Windows
```

Verificá el nombre con:
```powershell
Get-CimInstance -ClassName Win32_Printer | Select-Object Name, Default
```

## Paso 4 (alternativa manual): Pasos manuales del setup

Si preferís no correr el script, o querés entender/auditar cada cambio:

### 4a. Crear la cuenta kiosko

Panel de Control → Cuentas de usuario → Agregar otra persona → cuenta
local, sin permisos de administrador.

### 4b. Configurar auto-logon

`regedit` → `HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon`:

| Valor | Tipo | Dato |
|---|---|---|
| `AutoAdminLogon` | REG_SZ | `1` |
| `DefaultUserName` | REG_SZ | `totem` |
| `DefaultPassword` | REG_SZ | `<contraseña de totem>` |
| `DefaultDomainName` | REG_SZ | `<nombre del equipo>` |

### 4c. Copiar los scripts del watchdog

Copiá `microservice/scripts/kiosk-watchdog.ps1` y `kiosk-watchdog.vbs` a
`C:\ProgramData\TurneroTotemKiosk\`. Editá `kiosk-watchdog.ps1` y poné la
ruta real del `.exe` en `$ExePath`.

### 4d. Configurar el Shell

En la misma clave `Winlogon`, cambiá:

| Valor | Tipo | Dato |
|---|---|---|
| `Shell` | REG_SZ | `wscript.exe "C:\ProgramData\TurneroTotemKiosk\kiosk-watchdog.vbs"` |

⚠️ Esto cambia el shell **por defecto para todas las cuentas**. Para que tu
cuenta admin siga teniendo `explorer.exe`, necesitás agregar un `Shell`
en `HKEY_CURRENT_USER\Software\Microsoft\Windows NT\CurrentVersion\Winlogon`
(creá la clave `Winlogon` si no existe) con valor `explorer.exe`,
**estando logueado con esa cuenta admin**.

### 4e. Restricciones para la cuenta kiosko

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
