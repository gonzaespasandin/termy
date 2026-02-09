@echo off
chcp 65001 >nul
title Configurar Auto-Inicio - Turnero Totem

echo.
echo ═══════════════════════════════════════════════════════════════
echo   CONFIGURAR AUTO-INICIO DE TURNERO TOTEM
echo ═══════════════════════════════════════════════════════════════
echo.

:: Obtener ruta del ejecutable
set "APP_PATH=%~dp0..\TurneroTotem.exe"
set "SHORTCUT_NAME=TurneroTotem"
set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"

:: Verificar si existe el ejecutable
if not exist "%APP_PATH%" (
    :: Si no existe, usar npm start
    set "APP_PATH=cmd /c cd /d %~dp0.. && npm start"
    echo ⚠️  No se encontró el ejecutable compilado.
    echo    Se configurará para ejecutar con npm start
    echo.
)

:: Crear acceso directo en Startup
echo 📁 Creando acceso directo en: %STARTUP_FOLDER%
echo.

:: Usar PowerShell para crear el acceso directo
powershell -Command "$ws = New-Object -ComObject WScript.Shell; $s = $ws.CreateShortcut('%STARTUP_FOLDER%\%SHORTCUT_NAME%.lnk'); $s.TargetPath = '%APP_PATH%'; $s.WorkingDirectory = '%~dp0..'; $s.Save()"

if %ERRORLEVEL% EQU 0 (
    echo ✅ Auto-inicio configurado correctamente!
    echo.
    echo    La aplicación se iniciará automáticamente al encender Windows.
) else (
    echo ❌ Error al configurar auto-inicio.
    echo    Intenta ejecutar como Administrador.
)

echo.
echo ═══════════════════════════════════════════════════════════════
pause

