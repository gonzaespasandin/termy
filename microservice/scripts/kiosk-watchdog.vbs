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
