# termy-frontend

Aplicación web SPA desarrollada con Vue 3 que sirve como interfaz principal del sistema de turnos.

## Funcionalidades

- **Pantalla de totem** (`/`): Selección de servicios, captura opcional del nombre del cliente y generación de tickets
- **Login** (`/login`): Autenticación para operadores y administradores
- **Panel de operador** (`/operator`): Gestión de cola en tiempo real, llamar turnos, marcar estados
- **Panel de administración** (`/admin`): CRUD de servicios, usuarios, ventanillas y perfiles; dashboard con estadísticas; historial de turnos
- **Pantalla de display** (`/display`): Visualización pública de turnos llamados con actualización en tiempo real (SSE)
- **Impresión de tickets**: Integración dual con Electron (IPC) o microservicio HTTP

## Requisitos

- Node.js 18+
- npm

## Instalación

```bash
npm install
```

## Uso

Desarrollo:
```bash
npm run dev
```

La aplicación estará disponible en `http://<tu-ip>:5173`

Build para producción:
```bash
npm run build
```

Los archivos compilados estarán en `dist/`

## Estructura

```
src/
├── api/
│   └── index.js          # Cliente Axios + todas las funciones de API
├── components/
│   ├── admin/             # AdminDashboard, AdminHistorial, AdminServicios,
│   │                      # AdminUsuarios, AdminVentanillas, AdminPerfiles, AdminSidebar
│   └── operator/          # OperatorCurrentTurn, OperatorQueue, OperatorHeader, OperatorNoWindow
├── composables/
│   ├── useToast.js        # Notificaciones toast
│   └── useConfirm.js      # Diálogos de confirmación
├── router/
│   └── index.js           # Rutas con guards de autenticación y rol
├── services/
│   └── printService.js    # Servicio de impresión (detecta Electron vs HTTP)
└── views/
    ├── HomeView.vue        # Pantalla de totem (pública)
    ├── LoginView.vue       # Autenticación
    ├── OperatorView.vue    # Panel de operador (protegido)
    ├── AdminView.vue       # Panel de administración (protegido, rol admin)
    └── DisplayView.vue     # Pantalla de display pública
```

## Configuración

### URL del backend

La URL de la API está definida en `src/api/index.js`:

```js
const API_BASE_URL = 'http://<IP-DEL-SERVIDOR>:8000/api'
```

Cambiar esta IP por la dirección real del servidor backend en tu red.

### Host del servidor de desarrollo

Para que otros dispositivos de la red puedan acceder durante el desarrollo, el servidor Vite debe escuchar en la IP local. Modificar en `package.json`:

```json
"dev": "vite --host <TU_IP_LOCAL>"
```

O usar `--host 0.0.0.0` para escuchar en todas las interfaces.

## Notas

- Usa Tailwind CSS v4 para estilos
- La autenticación requiere token Bearer almacenado en `localStorage`
- Las rutas `/admin` y `/operator` requieren autenticación; `/admin` requiere rol `admin`
- Las rutas `/` y `/display` son públicas
- La impresión de tickets detecta automáticamente si corre dentro de Electron (usa IPC) o en navegador (usa microservicio HTTP en puerto 3001)
