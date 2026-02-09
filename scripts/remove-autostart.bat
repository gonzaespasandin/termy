@echo off
chcp 65001 >nul
title Remover Auto-Inicio - Turnero Totem

echo.
echo ═══════════════════════════════════════════════════════════════
echo   REMOVER AUTO-INICIO DE TURNERO TOTEM
echo ═══════════════════════════════════════════════════════════════
echo.

set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "SHORTCUT_NAME=TurneroTotem.lnk"

if exist "%STARTUP_FOLDER%\%SHORTCUT_NAME%" (
    del "%STARTUP_FOLDER%\%SHORTCUT_NAME%"
    echo ✅ Auto-inicio removido correctamente.
) else (
    echo ⚠️  No se encontró el acceso directo de auto-inicio.
)

echo.
echo ═══════════════════════════════════════════════════════════════
pause

