@echo off
REM Script para verificar la instalación del Totem

echo ========================================
echo   Verificacion de Instalacion
echo ========================================
echo.

set "ERRORS=0"

REM 1. Verificar Python
echo [1/5] Verificando Python...
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo    [ERROR] Python no encontrado en PATH
    echo    Solucion: Instalar Python o configurar PYTHON_EXE en .env
    set /a ERRORS+=1
) else (
    python --version
    echo    [OK] Python encontrado
)
echo.

REM 2. Verificar pywin32
echo [2/5] Verificando pywin32...
python -c "import win32print" >nul 2>&1
if %errorlevel% neq 0 (
    echo    [ERROR] pywin32 no instalado
    echo    Solucion: Ejecutar: pip install pywin32
    set /a ERRORS+=1
) else (
    echo    [OK] pywin32 instalado
)
echo.

REM 3. Verificar carpeta de configuración
echo [3/5] Verificando carpeta de configuracion...
set "CONFIG_DIR=%APPDATA%\turnero-totem"
if not exist "%CONFIG_DIR%" (
    echo    [ADVERTENCIA] Carpeta de configuracion no existe
    echo    Se creara automaticamente al ejecutar la aplicacion
) else (
    echo    [OK] Carpeta encontrada: %CONFIG_DIR%
)
echo.

REM 4. Verificar archivo .env
echo [4/5] Verificando archivo .env...
set "ENV_FILE=%CONFIG_DIR%\.env"
if not exist "%ENV_FILE%" (
    echo    [ERROR] Archivo .env no encontrado
    echo    Ubicacion esperada: %ENV_FILE%
    echo    Solucion: Copiar env.example desde recursos y editarlo
    set /a ERRORS+=1
) else (
    echo    [OK] Archivo .env encontrado
    echo    Ubicacion: %ENV_FILE%
    echo.
    echo    Contenido del .env:
    echo    ----------------------------------------
    type "%ENV_FILE%"
    echo    ----------------------------------------
)
echo.

REM 5. Verificar conectividad (si hay SERVER_URL)
echo [5/5] Verificando configuracion de servidor...
if exist "%ENV_FILE%" (
    findstr /C:"SERVER_URL=" "%ENV_FILE%" >nul 2>&1
    if %errorlevel% equ 0 (
        for /f "tokens=2 delims==" %%a in ('findstr /C:"SERVER_URL=" "%ENV_FILE%"') do (
            set "SERVER_URL=%%a"
            echo    URL configurada: !SERVER_URL!
            echo    (Nota: No se verifica conectividad, solo configuracion)
        )
    ) else (
        echo    [ADVERTENCIA] SERVER_URL no configurado en .env
    )
) else (
    echo    [SKIP] No se puede verificar (archivo .env no existe)
)
echo.

REM Resumen
echo ========================================
echo   Resumen
echo ========================================
echo.
if %ERRORS% equ 0 (
    echo [OK] Instalacion verificada correctamente
    echo.
    echo La aplicacion deberia funcionar correctamente.
    echo Si hay problemas de conexion, verifica:
    echo - Que el servidor Vue este corriendo
    echo - Que la IP en SERVER_URL sea correcta
    echo - Que el firewall permita la conexion
) else (
    echo [ERROR] Se encontraron %ERRORS% problema(s)
    echo.
    echo Revisa los errores arriba y corrigelos antes de ejecutar la aplicacion.
)
echo.
pause
