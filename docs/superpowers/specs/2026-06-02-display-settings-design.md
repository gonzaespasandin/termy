# Display Settings — Spec

**Fecha:** 2026-06-02  
**Estado:** Aprobado  
**Scope:** Backend (migración + controller + rutas) + Frontend admin (nueva tab + componente) + Frontend display (consumo de settings + layout condicional).

---

## Objetivo

Permitir al administrador activar/desactivar el panel de video del display y subir un video MP4/WebM que se reproduce en loop en la pantalla pública. Cuando el video está desactivado, el panel de turnos ocupa el 100% del ancho.

---

## Base de datos

### Nueva tabla: `display_settings`

```
display_settings
├── id                   (bigint, PK)
├── video_enabled        (boolean, default true, not null)
├── video_path           (string, nullable)  — ruta relativa en storage/public
├── channel_id           (bigint, nullable, sin FK por ahora)  — reservado para canales futuros
├── created_at
└── updated_at
```

La tabla tiene **una sola fila** por instalación (channel_id = null). El controller usa `firstOrCreate` / `updateOrCreate` — nunca inserta una segunda fila con `channel_id = null`.

---

## Backend

### Migración

`database/migrations/YYYY_MM_DD_create_display_settings_table.php`

Crea la tabla con los campos descritos arriba.

### Modelo

`app/Models/DisplaySettings.php`

```php
protected $fillable = ['video_enabled', 'video_path', 'channel_id'];
protected $casts = ['video_enabled' => 'boolean'];
```

### Controller

`app/Http/Controllers/Api/DisplaySettingsController.php`

**`GET /api/display-settings`** — público, sin auth.  
Retorna la fila existente o los defaults `{ video_enabled: true, video_path: null }` si no existe.

**`POST /api/admin/display-settings`** — requiere `auth:sanctum` + rol admin.  
- Acepta `video_enabled` (boolean) y opcionalmente `video` (archivo).
- Si se sube un archivo: valida `mimes:mp4,webm`, `max:51200` (50 MB). Elimina el video anterior del storage si existe. Guarda en `display_videos/` en el disco `public`.
- Usa `updateOrCreate(['channel_id' => null], $data)`.
- Retorna el registro actualizado con `video_url` (URL pública completa).

Respuesta exitosa incluye:
```json
{
  "video_enabled": true,
  "video_path": "display_videos/abc123.mp4",
  "video_url": "http://servidor/storage/display_videos/abc123.mp4"
}
```

**`DELETE /api/admin/display-settings/video`** — requiere `auth:sanctum` + rol admin.  
Elimina el archivo del storage y setea `video_path = null`. No cambia `video_enabled`.

### Rutas

En `routes/api.php`, dentro del grupo `auth:sanctum`:
```php
Route::get('/display-settings', [DisplaySettingsController::class, 'show']); // público, fuera del grupo auth
Route::post('/admin/display-settings', [DisplaySettingsController::class, 'update']);
Route::delete('/admin/display-settings/video', [DisplaySettingsController::class, 'deleteVideo']);
```

La ruta GET va **fuera** del middleware `auth:sanctum` (igual que `/turns/queue`).

---

## Frontend — Admin

### Sidebar

`frontend/src/components/admin/AdminSidebar.vue`

Agregar a la lista `tabs`:
```js
{ id: 'display', name: 'Display', icon: 'fas fa-tv' }
```

### AdminView.vue

Importar y registrar `AdminDisplay`. Agregar al bloque de tabs:
```html
<AdminDisplay v-else-if="activeTab === 'display'" />
```

### AdminDisplay.vue

`frontend/src/components/admin/AdminDisplay.vue`

**Estado:**
- `videoEnabled` (boolean) — refleja `settings.video_enabled`
- `videoPath` (string | null) — ruta actual
- `videoUrl` (string | null) — URL pública para preview
- `selectedFile` (File | null) — archivo seleccionado antes de guardar
- `previewUrl` (string | null) — ObjectURL local para previsualizar antes de subir
- `loading` (boolean), `saving` (boolean)

**UI — secciones:**

1. **Header** — título "Pantalla de Display" + subtítulo.

2. **Toggle "Mostrar video"** — switch visual (on/off). Al cambiar, no guarda automáticamente — el usuario confirma con el botón Guardar.

3. **Zona de video** (visible siempre):
   - Si hay video subido (`videoUrl`): muestra `<video>` con preview + nombre del archivo + botón "Eliminar video".
   - Si hay archivo seleccionado localmente (`previewUrl`): muestra preview local con indicador "Pendiente de guardar".
   - Si no hay video: zona de drop con ícono, texto "Arrastrá un video o hacé click para seleccionar", hint "MP4 o WebM · máx. 50 MB · resolución ideal 576 × 1020 px". Input file oculto, acepta `video/mp4,video/webm`.

4. **Botón Guardar** — disabled mientras `saving`. Envía `video_enabled` + el archivo si hay uno seleccionado. Usa `FormData` + `POST /api/admin/display-settings`.

**Comportamiento:**
- Al montar: `GET /api/admin/display-settings` (puede usar la ruta pública o la misma con auth — usar la pública para consistencia).
- Al eliminar video: `DELETE /api/admin/display-settings/video`, luego limpia `videoPath`, `videoUrl`, `previewUrl`, `selectedFile`.
- Errores de validación (422) se muestran con el composable `useToast`.
- Éxito: toast "Configuración guardada" + actualiza estado local con la respuesta.

### API client

`frontend/src/api/index.js` — agregar:
```js
export const getDisplaySettings = () => api.get('/display-settings');
export const updateDisplaySettings = (data) => {
  const config = data instanceof FormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : {};
  return api.post('/admin/display-settings', data, config);
};
export const deleteDisplayVideo = () => api.delete('/admin/display-settings/video');
```

---

## Frontend — Display

### DisplayView.vue

**Cambios en `<script setup>`:**
- Agregar `const settings = ref({ video_enabled: true, video_path: null, video_url: null })`.
- En `onMounted`: llamar `getDisplaySettings()` y poblar `settings.value`.
- Agregar computed `showVideoPanel = computed(() => settings.value.video_enabled)`.

**Cambios en `<template>`:**

Panel de turnos: cambiar `flex: 0 0 70%` a dinámico:
```html
<section class="turns-panel" :class="{ 'turns-panel--full': !showVideoPanel }">
```

Panel de video: envolver en `v-if="showVideoPanel"`. Dentro del panel:
- Si `settings.video_url` existe: `<video :src="settings.video_url" loop autoplay muted playsinline class="video-player" />`.
- Si no: mantener el placeholder actual (▶ "Tu video aquí" + resolución).

**Cambios en `<style>`:**
```css
.turns-panel--full {
  flex: 0 0 100%;
  border-right: none;
}
```

---

## Lo que NO entra en este task

- Integración con canales (channel_id queda null)
- Progreso de upload en tiempo real (barra de progreso)
- Múltiples videos o playlist
- Validación del formato del video más allá del MIME type

---

## Archivos a crear / modificar

| Archivo | Acción |
|---|---|
| `backend/database/migrations/..._create_display_settings_table.php` | Crear |
| `backend/app/Models/DisplaySettings.php` | Crear |
| `backend/app/Http/Controllers/Api/DisplaySettingsController.php` | Crear |
| `backend/routes/api.php` | Modificar — 3 nuevas rutas |
| `frontend/src/api/index.js` | Modificar — 3 nuevas funciones |
| `frontend/src/components/admin/AdminDisplay.vue` | Crear |
| `frontend/src/components/admin/AdminSidebar.vue` | Modificar — nueva tab |
| `frontend/src/views/AdminView.vue` | Modificar — import + v-else-if |
| `frontend/src/views/DisplayView.vue` | Modificar — consumir settings + layout condicional |

---

## Criterios de aceptación

1. `GET /api/display-settings` responde sin auth y retorna `{ video_enabled, video_path, video_url }`.
2. Subir un MP4 de ≤50 MB desde el admin guarda el archivo y devuelve la URL pública.
3. Intentar subir un archivo >50 MB devuelve error 422 visible en el admin.
4. Con `video_enabled = false`, el display muestra el panel de turnos a 100% de ancho.
5. Con `video_enabled = true` y video subido, el display reproduce el video en loop.
6. Con `video_enabled = true` y sin video, el display muestra el placeholder.
7. Eliminar el video desde el admin limpia el video en el display al recargar.
8. La tab "Display" aparece en el sidebar del admin y navega al componente correcto.
