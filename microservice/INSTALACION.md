# 🚀 Guía de Instalación - Turnero Totem

## 📋 Instalación en PC Nueva (Aplicación Compilada)

### Requisitos Previos

1. **Node.js** (solo si vas a desarrollar/compilar, NO necesario para ejecutar el .exe)
   - Descarga desde: https://nodejs.org/ (versión LTS)
   - Solo necesario si vas a modificar el código o compilar

2. **Python 3.x** con **pywin32** (OBLIGATORIO para impresión)
   ```bash
   # Instalar Python desde python.org
   # Luego instalar pywin32:
   pip install pywin32
   ```
   - Verificar que Python esté en el PATH: abrir CMD y ejecutar `python --version`
   - Si no está en PATH, configurar `PYTHON_EXE` en el `.env` (ver abajo)

### Paso 1: Instalar la Aplicación

1. Ejecutar el instalador: `TurneroTotem-Setup-1.0.0.exe`
2. Seguir el asistente de instalación
3. La aplicación se instalará automáticamente

### Paso 2: Configurar el Archivo .env

**⚠️ IMPORTANTE: El archivo `.env` NO va en la carpeta de instalación**

Cuando la aplicación está instalada, el `.env` debe estar en:
```
C:\Users\<TuUsuario>\AppData\Roaming\Turnero Totem\.env
```

**Pasos para configurar:**

1. Abrir el Explorador de Windows
2. En la barra de direcciones, escribir: `%APPDATA%\Turnero Totem`
3. Si no existe la carpeta, ejecutar la aplicación una vez (la creará automáticamente)
4. Buscar el archivo `.env` en esa carpeta
5. Si no existe, copiar `env.example` desde la carpeta de recursos de la aplicación:
   - Ubicación típica: `C:\Program Files\Turnero Totem\resources\env.example`
   - Copiarlo a `%APPDATA%\Turnero Totem\.env`

6. Editar el archivo `.env` con un editor de texto (Bloc de notas, Notepad++, etc.):

```env
# URL del servidor Vue (frontend)
# IMPORTANTE: Debe ser la IP del servidor donde corre el frontend
SERVER_URL=http://192.168.0.51:5173

# Código único de este totem (debe coincidir con la base de datos)
TOTEM_CODE=TOTEM-PRINCIPAL

# Nombre de la empresa (aparece en los tickets)
COMPANY_NAME=Dayco Gaming S.A.

# Nombre de la impresora (dejar vacío para usar la predeterminada)
# Ejemplo: PRINTER_NAME=KPOS_58 Printer
PRINTER_NAME=

# Ancho del ticket en mm (típico: 58 o 80)
TICKET_WIDTH=58

# Auto-iniciar con Windows (true/false)
AUTO_START=true

# Ruta de Python (solo si "python" no está en PATH)
# Si instalaste Python pero no está en PATH, usar:
# PYTHON_EXE=C:\Python312\python.exe
# O si está en PATH, dejar como está:
PYTHON_EXE=python
```

### Paso 3: Verificar Configuración

1. **Verificar que el servidor Vue esté corriendo:**
   - Abrir navegador en la PC del servidor
   - Ir a: `http://192.168.0.51:5173` (o la IP que configuraste)
   - Debe cargar la interfaz del turnero

2. **Verificar conectividad de red:**
   - Desde la PC del totem, abrir CMD
   - Ejecutar: `ping 192.168.0.51` (o la IP de tu servidor)
   - Debe responder sin pérdida de paquetes

3. **Verificar firewall:**
   - Asegurarse que Windows Firewall permita la conexión
   - O desactivar temporalmente el firewall para probar

### Paso 4: Ejecutar la Aplicación

1. Abrir "Turnero Totem" desde el menú de inicio o escritorio
2. La aplicación intentará conectarse al servidor
3. Si hay error, revisar la consola (ver solución de problemas)

---

## 🔧 Desarrollo (Solo para Modificar el Código)

### Instalación para Desarrollo

1. **Instalar Node.js** (si no lo tienes)

2. **Clonar/descargar el proyecto** y abrir terminal en `turnero-totem`

3. **Instalar dependencias:**
   ```bash
   npm install
   ```

4. **Configurar `.env` en la raíz del proyecto:**
   ```bash
   copy env.example .env
   ```
   Editar `.env` con tus valores

5. **Ejecutar en modo desarrollo:**
   ```bash
   npm run dev
   ```

6. **Compilar para producción:**
   ```bash
   npm run build:win
   ```

---

## 🐛 Solución de Problemas

### ❌ "Error al conectarse al servidor"

**Causas comunes:**

1. **El `.env` está en el lugar equivocado**
   - ✅ Correcto: `%APPDATA%\Turnero Totem\.env`
   - ❌ Incorrecto: Carpeta de instalación o cualquier otro lugar

2. **La IP del servidor es incorrecta**
   - Verificar `SERVER_URL` en el `.env`
   - Debe ser la IP donde corre el servidor Vue (frontend)
   - Ejemplo: `http://192.168.0.51:5173`

3. **El servidor Vue no está corriendo**
   - Verificar que el servidor esté activo en la PC servidor
   - Probar abrir la URL en un navegador desde la PC del totem

4. **Problemas de red/firewall**
   - Verificar que ambas PCs estén en la misma red
   - Verificar que el firewall permita la conexión
   - Probar hacer ping entre las PCs

**Cómo verificar la configuración cargada:**

1. Abrir la aplicación
2. Presionar `Ctrl+Shift+I` (si está en modo desarrollo) para abrir DevTools
3. O revisar los logs en la consola de Windows
4. Debe mostrar la configuración cargada al inicio

### ❌ "No imprime tickets"

1. **Verificar Python:**
   ```bash
   python --version
   ```
   Debe mostrar la versión instalada

2. **Verificar pywin32:**
   ```bash
   pip list | findstr pywin32
   ```
   Debe aparecer en la lista

3. **Verificar impresora:**
   - Ir a Configuración > Impresoras
   - Verificar que la impresora esté instalada y configurada como predeterminada
   - O configurar `PRINTER_NAME` en el `.env` con el nombre exacto

4. **Verificar `PRINTER_NAME` en `.env`:**
   - Si está vacío, usa la impresora predeterminada de Windows
   - Si especificas un nombre, debe coincidir exactamente con el nombre de la impresora

### ❌ "Python no se encuentra"

1. **Verificar que Python esté en PATH:**
   ```bash
   python --version
   ```

2. **Si no está en PATH, configurar en `.env`:**
   ```env
   PYTHON_EXE=C:\Python312\python.exe
   ```
   (Ajustar la ruta según tu instalación)

### ❌ Pantalla negra o error de GPU

Ejecutar con flag de GPU deshabilitado:
```bash
electron . --disable-gpu
```

---

## 📍 Ubicaciones Importantes

### Cuando la aplicación está INSTALADA:

- **Ejecutable:** `C:\Program Files\Turnero Totem\Turnero Totem.exe`
- **Archivo `.env`:** `%APPDATA%\Turnero Totem\.env` (ej: `C:\Users\<Usuario>\AppData\Roaming\Turnero Totem\.env`)
- **Recursos (print_ticket.py, env.example):** `C:\Program Files\Turnero Totem\resources\`

### Cuando estás DESARROLLANDO:

- **Código fuente:** `turnero-totem\`
- **Archivo `.env`:** `turnero-totem\.env` (en la raíz del proyecto)

---

## ✅ Checklist de Instalación

- [ ] Python 3.x instalado
- [ ] pywin32 instalado (`pip install pywin32`)
- [ ] Python accesible desde PATH (o configurado `PYTHON_EXE` en `.env`)
- [ ] Aplicación instalada (ejecutable .exe)
- [ ] Archivo `.env` creado en `%APPDATA%\Turnero Totem\.env`
- [ ] `SERVER_URL` configurado correctamente en `.env`
- [ ] `TOTEM_CODE` configurado (debe coincidir con la BD)
- [ ] Servidor Vue corriendo y accesible desde la red
- [ ] Conectividad de red verificada (ping funciona)
- [ ] Firewall configurado correctamente
- [ ] Impresora instalada y configurada

---

## 🎯 Resumen Rápido para PC Nueva

1. Instalar Python + pywin32
2. Instalar la aplicación (.exe)
3. Ejecutar la aplicación una vez (crea la carpeta de configuración)
4. Editar `%APPDATA%\Turnero Totem\.env` con la IP del servidor y código del totem
5. Reiniciar la aplicación
6. ¡Listo!
