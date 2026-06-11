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
$ExePath = "$env:LOCALAPPDATA\Programs\Turnero Totem\Turnero Totem.exe"

$RestartDelaySeconds = 2
$MissingExeRetrySeconds = 10

$LogFile = Join-Path $PSScriptRoot "watchdog.log"
function Write-Log {
    param([string]$Message)
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"
    Add-Content -Path $LogFile -Value $line -ErrorAction SilentlyContinue
}

while ($true) {
    if (Test-Path -LiteralPath $ExePath) {
        try {
            Start-Process -FilePath $ExePath -Wait
        } catch {
            Write-Log "Error al iniciar Turnero Totem: $_"
        }
        Start-Sleep -Seconds $RestartDelaySeconds
    } else {
        Write-Log "No se encontro el ejecutable en: $ExePath"
        Start-Sleep -Seconds $MissingExeRetrySeconds
    }
}
