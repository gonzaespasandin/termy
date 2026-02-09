# Turnero Totem

Aplicación de escritorio Electron para totems físicos que muestra la interfaz web y maneja la impresión local de tickets.

## Funcionalidades

- Carga la interfaz web desde el servidor Vue
- Impresión directa de tickets en impresoras térmicas usando Python + win32print
- Configuración mediante archivo `.env` local
- Instalador Windows (NSIS) y versión portable

## Requisitos

- Node.js 18+
- Python 3.x con pywin32 instalado
- Windows (usa APIs nativas de Windows para impresión)

## Instalación para desarrollo

```bash
npm install
```

## Uso

Desarrollo:
```bash
npm start
```

Generar instalador:
```bash
npm run build:win
```

Los archivos generados estarán en `dist/`:
- `Turnero Totem Setup 1.0.0.exe` - Instalador
- `Turnero Totem 1.0.0.exe` - Versión portable

## Configuración

Después de instalar, crear archivo `.env` en:
- Desarrollo: `turnero-totem/.env`
- Instalado: `%APPDATA%\Turnero Totem\.env`

Ejemplo:
```
VUE_SERVER_URL=http://192.168.0.51:5173
TOTEM_CODE=TOTEM-PRINCIPAL
TOTEM_NAME=Totem Principal
COMPANY_NAME=Mi Empresa
PYTHON_EXE=python
```

## Impresión

La aplicación ejecuta `print_ticket.py` como proceso hijo para imprimir tickets. El script usa `win32print` para comunicación directa con la impresora térmica.

Para modificar el formato del ticket, editar `print_ticket.py` y recompilar:
```bash
npm run build:win
```

## Estructura

- `main.js` - Proceso principal de Electron
- `preload.js` - Script de precarga para comunicación segura
- `src/config.js` - Carga de configuración desde `.env`
- `src/print/ticketPrinter.js` - Módulo de impresión
- `print_ticket.py` - Script Python para impresión (se copia a recursos en build)

## Notas

- Requiere conexión de red al servidor Vue
- La impresora debe estar configurada como predeterminada en Windows
- Python debe estar instalado y accesible desde PATH (o configurar `PYTHON_EXE` en `.env`)
