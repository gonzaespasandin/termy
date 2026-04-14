# Termy - Sistema de Gestión de Turnos

Sistema completo de turnos (tipo ticketera) compuesto por tres aplicaciones interconectadas: un backend REST, una interfaz web y una app de escritorio para totems físicos.

## Arquitectura

```
termy/
├── termy-backend/      # API REST (Laravel 12 + PHP 8.2)
├── termy-frontend/     # Interfaz web SPA (Vue 3 + Tailwind CSS)
└── termy-electron/     # App de escritorio para totems (Electron 28)
```

### Flujo general

1. El cliente se acerca al **totem físico** (app Electron) y selecciona un servicio.
2. Se genera un ticket impreso con número de turno.
3. El **operador** gestiona la cola desde el panel web, llamando y atendiendo turnos.
4. Una **pantalla de display** (vista pública) muestra los turnos llamados en tiempo real.
5. El **administrador** configura servicios, usuarios, ventanillas y totems; y consulta estadísticas e historial.

### Comunicación entre componentes

```
Electron (totem)
     │  carga
     ▼
Vue Frontend ──────── HTTP / Axios ──────── Laravel Backend
     │                                            │
     │  SSE (tiempo real)  ◄──────────────────── │
     │                                            │
     └──── IPC (window.turneroAPI) ──── Electron (impresión Python)
```

---

## Stack tecnológico

| Componente | Tecnología | Versión |
|---|---|---|
| Backend | Laravel | 12 |
| Backend | PHP | 8.2+ |
| Backend auth | Laravel Sanctum | 4.x |
| Frontend framework | Vue | 3.5.x |
| Frontend router | Vue Router | 4.6.x |
| Frontend estilos | Tailwind CSS | 4.x |
| Frontend build | Vite | 7.x |
| HTTP client | Axios | 1.x |
| Desktop runtime | Electron | 28 |
| Impresión térmica | Python + pywin32 | 3.x |
| Base de datos | SQLite (por defecto) / MySQL | — |

---

## Inicio rápido

### 1. Backend

```bash
cd termy-backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed          # opcional: datos de ejemplo
php artisan serve --host=0.0.0.0
```

Ver [termy-backend/README.md](backend/README.md) para configuración detallada.

### 2. Frontend

```bash
cd termy-frontend
npm install
# Editar src/api/index.js con la IP del backend
npm run dev
```

Ver [termy-frontend/README.md](frontend/README.md) para configuración detallada.

### 3. App Electron (totem)

```bash
cd termy-electron
npm install
cp env.example .env
# Editar .env con la IP del frontend y el código del totem
npm start
```

Ver [termy-electron/README.md](microservice/README.md) para instalación en producción y solución de problemas.

---

## Funcionalidades principales

### Totem (pantalla kiosko)
- Selección de servicio con grilla configurable
- Captura opcional del nombre del cliente
- Impresión del ticket en impresora térmica
- Modo pantalla completa bloqueada

### Panel de operador
- Cola de turnos en tiempo real (SSE)
- Llamar, atender, completar, marcar ausente o devolver a cola
- Filtrado automático por servicios asignados al operador

### Panel de administración
- CRUD de servicios, usuarios, ventanillas y perfiles de totem
- Dashboard con métricas del día
- Historial de turnos con filtros y paginación

### Pantalla de display
- Muestra turnos llamados y en atención en tiempo real
- Actualización automática sin recargar la página (SSE)

---

## Estados de un turno

| Estado | Descripción |
|---|---|
| `waiting` | En cola, esperando ser llamado |
| `called` | Llamado, esperando que el cliente llegue a la ventanilla |
| `in_progress` | En atención activa |
| `completed` | Atención completada |
| `cancelled` | Cancelado manualmente |
| `no_show` | El cliente no se presentó |

---

## Roles de usuario

| Rol | Acceso |
|---|---|
| `admin` | Todo el panel de administración + funciones de operador |
| `operator` | Solo panel de operador; filtrado por servicios asignados |

---

## Requisitos del sistema

| Componente | Requisito |
|---|---|
| Backend | PHP 8.2+, Composer, SQLite o MySQL |
| Frontend | Node.js 18+ |
| Electron | Node.js 18+, Python 3.x, pywin32, Windows 64-bit |

---

## Notas de despliegue en red local

Los tres componentes deben estar en la misma red. La configuración de IP se hace en:

- **Frontend** → `termy-frontend/src/api/index.js` (URL del backend)
- **Frontend** → `termy-frontend/package.json` (host del servidor de desarrollo)
- **Electron** → `termy-electron/.env` → variable `SERVER_URL` (URL del frontend)

---

## Autor

Gonzalo Espasandin.
