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
