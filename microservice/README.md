# termy-electron

Aplicación de escritorio Electron para totems físicos. Carga la interfaz web del frontend en modo kiosko y gestiona la impresión local de tickets en impresoras térmicas.

## Funcionalidades

- Carga el frontend Vue en pantalla completa bloqueada (modo kiosko)
- Impresión directa de tickets en impresoras térmicas mediante Python + win32print
- Configuración flexible mediante archivo `.env`
- Instalador Windows (NSIS) y versión portable ejecutable
- Reconexión automática si el servidor no está disponible al iniciar

## Requisitos

- Node.js 18+
- Python 3.x con `pywin32` instalado (`pip install pywin32`)
- Windows 64-bit (usa APIs nativas de Windows para impresión)

## Instalación para desarrollo

```bash
npm install
cp env.example .env
# Editar .env con la IP del frontend y el código del totem
```

## Uso

Modo desarrollo:
```bash
npm start
# o con flag de desarrollo (habilita DevTools):
npm run dev
```

Generar instalador:
```bash
npm run build:win
```

Los archivos generados en `dist/`:
- `Turnero Totem Setup 1.1.4.exe` — Instalador NSIS
- `Turnero Totem 1.1.4.exe` — Versión portable

## Configuración

El archivo `.env` controla el comportamiento de la aplicación.

### Ubicación del `.env`

| Contexto | Ubicación |
|---|---|
| Desarrollo | `termy-electron/.env` |
| Instalado | `%APPDATA%\Turnero Totem\.env` |

### Variables disponibles

```env
# URL del servidor Vue (frontend)
SERVER_URL=http://192.168.0.51:5173

# Código único de este totem (debe coincidir con la base de datos)
TOTEM_CODE=TOTEM-PRINCIPAL

# Nombre de la empresa (aparece impreso en los tickets)
COMPANY_NAME=Mi Empresa S.A.

# Nombre exacto de la impresora (dejar vacío para usar la predeterminada)
PRINTER_NAME=

# Ancho del ticket en mm (valores válidos: 58 o 80)
TICKET_WIDTH=58

# Ejecutable de Python (usar ruta absoluta si no está en PATH)
PYTHON_EXE=python
# Ejemplo: PYTHON_EXE=C:\Python312\python.exe
```

## Impresión

La impresión funciona en tres pasos:

1. El frontend llama a `window.turneroAPI.printTicket(data)` via IPC
2. El proceso principal de Electron recibe el evento y ejecuta `print_ticket.py` como subproceso
3. El script Python usa `win32print` para enviar el ticket a la impresora térmica

Para modificar el formato del ticket, editar `print_ticket.py`. Los cambios se incluyen en el próximo build.

## Estructura

```
termy-electron/
├── main.js               # Proceso principal: ventana, IPC, reconexión
├── preload.js            # Bridge aislado para comunicación segura con el renderer
├── print_ticket.py       # Script Python para impresión térmica (se bundlea en el build)
├── env.example           # Plantilla de configuración
└── src/
    ├── config.js         # Carga y expone las variables del .env
    ├── envLoader.js      # Lectura del archivo .env
    └── print/
        └── ticketPrinter.js  # Módulo de impresión (ejecuta el script Python)
```

## Notas

- Requiere conexión de red al servidor Vue para funcionar
- Si `PRINTER_NAME` está vacío, usa la impresora predeterminada configurada en Windows
- Python debe estar en el PATH o configurar la ruta completa en `PYTHON_EXE`
