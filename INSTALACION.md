# 🚀 Guía de Instalación - Turnero Totem

## Instalación Rápida (3 pasos)

### Paso 1: Instalar Node.js
Descarga e instala Node.js desde: https://nodejs.org/
(Elige la versión LTS)

### Paso 2: Instalar dependencias
Abre una terminal en la carpeta `turnero-totem` y ejecuta:

```bash
npm install
```

### Paso 3: Configurar
Copia el archivo de ejemplo y edítalo:

```bash
copy env.example .env
```

Edita `.env` con tus valores:
```env
SERVER_URL=http://192.168.0.51:5173
TOTEM_CODE=TOTEM-PRINCIPAL
COMPANY_NAME=Dayco Gaming S.A.
PRINTER_NAME=KPOS_58 Printer
```

## Ejecutar

### Modo Desarrollo (ventana normal, con DevTools)
```bash
npm run dev
```

### Modo Producción (pantalla completa, sin marcos)
```bash
npm start
```

## Crear Instalador (.exe)

Para crear el ejecutable instalable:

```bash
npm run build:win
```

El instalador se genera en `dist/`:
- `TurneroTotem-Setup-1.0.0.exe` - Instalador
- `TurneroTotem-1.0.0-portable.exe` - Versión portable

## Configurar Auto-Inicio con Windows

Ejecuta el script:
```bash
scripts\setup-autostart.bat
```

O manualmente: copia un acceso directo a:
`%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup`

## Agregar Nuevo Totem

1. Copia la carpeta `turnero-totem` o el `.exe` al nuevo equipo
2. Edita el archivo `.env` con el nuevo código de totem
3. Ejecuta la aplicación

¡Listo! 🎉

## Solución de Problemas

### "npm no se reconoce como comando"
→ Instala Node.js y reinicia la terminal

### "No conecta al servidor"
→ Verifica que `SERVER_URL` sea correcto en `.env`
→ Asegúrate que el servidor Vue esté corriendo

### "No imprime"
→ Verifica el nombre de la impresora en `.env`
→ Ejecuta `npm run dev` para ver errores en consola

### Pantalla negra o error de GPU
→ Prueba ejecutar: `electron . --disable-gpu`

