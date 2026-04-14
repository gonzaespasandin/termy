# termy-backend

API REST desarrollada con Laravel 12 que gestiona el sistema de turnos.

## Funcionalidades

- **Autenticación**: Login/logout con Laravel Sanctum (tokens Bearer)
- **Gestión de turnos**: Creación, seguimiento de estados y ciclo de vida completo
- **CRUD de servicios**: Administración de servicios disponibles con código y color
- **CRUD de usuarios**: Gestión de operadores y administradores con asignación de servicios
- **CRUD de ventanillas**: Puntos de atención con servicios habilitados
- **CRUD de perfiles (totems)**: Configuración de grupos de servicios y personalización visual
- **Panel de operador**: Cola filtrada, llamar turnos, atender, completar, marcar ausente, devolver a cola
- **Pantalla de display**: Cola pública con actualizaciones en tiempo real (SSE)
- **Dashboard admin**: Estadísticas y KPIs del día
- **Historial**: Búsqueda y filtrado de turnos históricos con paginación

## Requisitos

- PHP 8.2+
- Composer
- SQLite (incluido en PHP) o MySQL/MariaDB

## Instalación

1. Instalar dependencias:
```bash
composer install
```

2. Configurar `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

3. (Opcional) Configurar base de datos en `.env`. Por defecto usa SQLite:
```
DB_CONNECTION=sqlite
```
Para MySQL:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=turnero
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

4. Ejecutar migraciones:
```bash
php artisan migrate
```

5. Crear el enlace simbólico para imágenes públicas (logos de totems):
```bash
php artisan storage:link
```

6. (Opcional) Poblar con datos de ejemplo:
```bash
php artisan db:seed
```

7. Crear usuario admin manualmente (si no usas seeders):
```bash
php artisan tinker
```
```php
User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => Hash::make('password'),
    'role' => 'admin'
]);
```

## Uso

Servidor de desarrollo:
```bash
php artisan serve --host=0.0.0.0
```

La API estará disponible en `http://<tu-ip>:8000/api`

## Endpoints de la API

### Rutas públicas

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/login` | Autenticación |
| `GET` | `/api/services` | Listar servicios activos |
| `POST` | `/api/turns` | Crear turno (emitir ticket) |
| `GET` | `/api/turns/queue` | Cola para pantalla de display |
| `GET` | `/api/totems/{code}/services` | Servicios de un perfil de totem |
| `GET` | `/api/sse/display` | Stream SSE para pantalla pública |

### Rutas protegidas (requieren Bearer token)

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/logout` | Cerrar sesión |
| `GET` | `/api/user` | Usuario autenticado |
| `GET` | `/api/windows/by-ip` | Ventanilla asociada a la IP del operador |
| `GET` | `/api/operator/queue` | Cola filtrada para operador |
| `GET` | `/api/sse/operator` | Stream SSE para operador |
| `POST` | `/api/turns/{id}/call` | Llamar turno |
| `POST` | `/api/turns/{id}/attend` | Iniciar atención |
| `POST` | `/api/turns/{id}/complete` | Completar turno |
| `POST` | `/api/turns/{id}/no-show` | Marcar como ausente |
| `POST` | `/api/turns/{id}/cancel` | Cancelar turno |
| `POST` | `/api/turns/{id}/return-to-queue` | Devolver turno a la cola |

### Rutas de administrador (requieren rol `admin`)

| Método | Ruta | Descripción |
|---|---|---|
| `GET/POST` | `/api/admin/services` | CRUD de servicios |
| `GET/POST` | `/api/admin/users` | CRUD de usuarios |
| `GET/POST` | `/api/admin/windows` | CRUD de ventanillas |
| `GET/POST` | `/api/admin/totems` | CRUD de perfiles de totem |
| `GET/POST` | `/api/admin/turns` | CRUD de turnos |
| `GET` | `/api/admin/dashboard/stats` | Estadísticas del dashboard |
| `GET` | `/api/admin/history` | Historial de turnos (paginado) |
| `GET` | `/api/admin/history/operators` | Listado de operadores |
| `GET` | `/api/admin/history/{id}` | Detalle de un turno |

## Estados de un turno

| Estado | Descripción |
|---|---|
| `waiting` | En cola |
| `called` | Llamado, esperando al cliente |
| `in_progress` | En atención |
| `completed` | Completado |
| `cancelled` | Cancelado |
| `no_show` | Ausente (el cliente no se presentó) |

## Configuración

- **Zona horaria**: `America/Argentina/Buenos_Aires` (configurable en `config/app.php`)
- **Base de datos**: SQLite por defecto; configurable a MySQL/MariaDB en `.env`
- **Numeración de tickets**: continua, no se resetea diariamente
- **Tiempo real**: SSE (Server-Sent Events), compatible con Nginx (`X-Accel-Buffering: no`)
- **CORS**: configurar `FRONTEND_URL` en `.env` para permitir el origen del frontend
- **Imágenes**: los logos de totems se almacenan en `storage/app/public/totem_logos/` y se sirven vía el symlink `public/storage` (creado con `php artisan storage:link`)
