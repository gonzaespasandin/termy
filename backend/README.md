# Turnero Backend

API REST desarrollada con Laravel 12 que gestiona el sistema de turnos.

## Funcionalidades

- **Autenticación**: Login/logout con Laravel Sanctum
- **Gestión de turnos**: Creación, actualización y seguimiento de estados (waiting, called, in_progress, completed, cancelled)
- **CRUD de servicios**: Administración de servicios disponibles
- **CRUD de usuarios**: Gestión de operadores y administradores
- **CRUD de perfiles (totems)**: Configuración de grupos de servicios por perfil
- **Panel de operador**: Cola de turnos, llamar turnos, marcar como atendido/completado
- **Dashboard admin**: Estadísticas de turnos del día
- **Historial**: Búsqueda y filtrado de turnos históricos con paginación

## Requisitos

- PHP 8.2+
- Composer
- MySQL/MariaDB
- Node.js (para compilar assets)

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

3. Configurar base de datos en `.env`:
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

5. (Opcional) Crear usuario admin:
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

La API estará disponible en `http://localhost:8000/api`

## Estructura de la API

- `POST /api/login` - Autenticación
- `POST /api/logout` - Cerrar sesión
- `GET /api/user` - Usuario actual
- `GET /api/turns` - Listar turnos
- `POST /api/turns` - Crear turno
- `GET /api/totems/{code}/services` - Servicios de un perfil
- `GET /api/operator/queue` - Cola de turnos para operador
- `POST /api/turns/{id}/call` - Llamar turno
- `POST /api/turns/{id}/attend` - Iniciar atención
- `POST /api/turns/{id}/complete` - Completar turno
- `GET /api/admin/dashboard/stats` - Estadísticas del dashboard
- `GET /api/admin/history` - Historial de turnos

## Configuración

- Zona horaria: `America/Argentina/Buenos_Aires` (configurable en `config/app.php`)
- Los números de ticket son continuos (no se resetean diariamente)
- El campo `issued_at` guarda la hora de emisión y no se modifica
