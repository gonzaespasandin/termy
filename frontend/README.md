# Turnero Frontend

Aplicación web SPA desarrollada con Vue 3 que funciona como interfaz del sistema de turnos.

## Funcionalidades

- **Pantalla de totem**: Selección de servicios y generación de tickets
- **Panel de operador**: Gestión de cola de turnos, llamar turnos, marcar estados
- **Panel de administración**: CRUD de servicios, usuarios y perfiles, dashboard con estadísticas, historial de turnos
- **Pantalla de display**: Visualización pública de turnos llamados
- **Impresión de tickets**: Integración con servicio de impresión (Electron o HTTP)

## Requisitos

- Node.js 18+
- npm o yarn

## Instalación

```bash
npm install
```

## Uso

Desarrollo:
```bash
npm run dev
```

La aplicación estará disponible en `http://localhost:5173`

Build para producción:
```bash
npm run build
```

Los archivos compilados estarán en `dist/`

## Estructura

- `/src/views/` - Vistas principales (HomeView, OperatorView, AdminView, DisplayView)
- `/src/api/` - Cliente API para comunicación con el backend
- `/src/services/` - Servicios (impresión, etc.)
- `/src/router/` - Configuración de rutas

## Configuración

La aplicación se conecta al backend configurado en `/src/api/index.js` (por defecto `http://localhost:8000/api`).

Para desarrollo en red local, modificar el host en `package.json`:
```json
"dev": "vite --host TU_IP_LOCAL"
```

## Notas

- Usa Tailwind CSS para estilos
- Requiere autenticación para acceder a `/admin` y `/operator`
- La pantalla de totem (`/`) es pública
- La impresión funciona mediante el servicio de Electron o un microservicio HTTP
