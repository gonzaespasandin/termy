@echo off
chcp 65001 >nul
title Test de Impresión Local - Turnero Totem

cd /d "%~dp0.."

:: Verificar que exista node_modules
if not exist "node_modules" (
    echo.
    echo ⚠️  Dependencias no instaladas. Ejecutando npm install...
    call npm install
    echo.
)

:: Ejecutar el test
node test-print-local.js

pause
