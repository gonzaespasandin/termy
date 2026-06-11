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
