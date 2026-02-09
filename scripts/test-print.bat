@echo off
chcp 65001 >nul
title Test de Impresión - Turnero Totem

echo.
echo ═══════════════════════════════════════════════════════════════
echo   TEST DE IMPRESIÓN - TURNERO TOTEM
echo ═══════════════════════════════════════════════════════════════
echo.
echo Ejecutando test de impresión...
echo.

cd /d "%~dp0.."
call npm run test-print

echo.
pause

