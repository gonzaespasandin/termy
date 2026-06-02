# Display Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir al admin subir un video y activar/desactivar el panel lateral del display; cuando está desactivado, los turnos ocupan el 100% del ancho.

**Architecture:** Tabla `display_settings` de fila única en el backend (Laravel), controller con endpoints público GET y admin POST/DELETE. Nuevo componente `AdminDisplay.vue` con toggle + upload. `DisplayView.vue` consume los settings al montar y ajusta el layout condicionalmente.

**Tech Stack:** Laravel 12 (PHP 8.2), Vue 3 Composition API, Axios, CSS custom properties.

---

### Task 1: Migración y modelo

**Files:**
- Create: `backend/database/migrations/2026_06_02_000000_create_display_settings_table.php`
- Create: `backend/app/Models/DisplaySettings.php`

- [ ] **Step 1: Crear la migración**

```php
<?php
// backend/database/migrations/2026_06_02_000000_create_display_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('display_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('video_enabled')->default(true);
            $table->string('video_path')->nullable();
            $table->unsignedBigInteger('channel_id')->nullable(); // reservado para canales futuros
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('display_settings');
    }
};
```

- [ ] **Step 2: Crear el modelo**

```php
<?php
// backend/app/Models/DisplaySettings.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisplaySettings extends Model
{
    protected $fillable = [
        'video_enabled',
        'video_path',
        'channel_id',
    ];

    protected $casts = [
        'video_enabled' => 'boolean',
    ];
}
```

- [ ] **Step 3: Ejecutar la migración**

```bash
cd backend
php artisan migrate
```

Expected output: `Migrating: 2026_06_02_000000_create_display_settings_table` seguido de `Migrated`.

- [ ] **Step 4: Commit**

```bash
git add backend/database/migrations/2026_06_02_000000_create_display_settings_table.php backend/app/Models/DisplaySettings.php
git commit -m "feat(display): add display_settings migration and model"
```

---

### Task 2: Controller y rutas

**Files:**
- Create: `backend/app/Http/Controllers/Api/DisplaySettingsController.php`
- Modify: `backend/routes/api.php`

- [ ] **Step 1: Crear el controller**

```php
<?php
// backend/app/Http/Controllers/Api/DisplaySettingsController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisplaySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DisplaySettingsController extends Controller
{
    /**
     * Retorna la configuración actual del display.
     * GET /api/display-settings — público, sin auth.
     */
    public function show()
    {
        $settings = DisplaySettings::whereNull('channel_id')->first();

        if (!$settings) {
            return response()->json([
                'video_enabled' => true,
                'video_path'    => null,
                'video_url'     => null,
            ]);
        }

        return response()->json([
            'video_enabled' => $settings->video_enabled,
            'video_path'    => $settings->video_path,
            'video_url'     => $settings->video_path
                ? Storage::disk('public')->url($settings->video_path)
                : null,
        ]);
    }

    /**
     * Actualiza la configuración (toggle + upload de video).
     * POST /api/admin/display-settings — auth:sanctum + admin.
     */
    public function update(Request $request)
    {
        $request->validate([
            'video_enabled' => 'sometimes|boolean',
            'video'         => 'nullable|mimes:mp4,webm|max:51200',
        ]);

        $data = [];

        if ($request->has('video_enabled')) {
            $data['video_enabled'] = filter_var($request->input('video_enabled'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('video')) {
            // Eliminar video anterior si existe
            $existing = DisplaySettings::whereNull('channel_id')->first();
            if ($existing && $existing->video_path) {
                Storage::disk('public')->delete($existing->video_path);
            }

            $path = $request->file('video')->store('display_videos', 'public');
            $data['video_path'] = $path;
        }

        $settings = DisplaySettings::updateOrCreate(
            ['channel_id' => null],
            $data
        );

        return response()->json([
            'video_enabled' => $settings->video_enabled,
            'video_path'    => $settings->video_path,
            'video_url'     => $settings->video_path
                ? Storage::disk('public')->url($settings->video_path)
                : null,
        ]);
    }

    /**
     * Elimina el video subido (mantiene el toggle intacto).
     * DELETE /api/admin/display-settings/video — auth:sanctum + admin.
     */
    public function deleteVideo()
    {
        $settings = DisplaySettings::whereNull('channel_id')->first();

        if ($settings && $settings->video_path) {
            Storage::disk('public')->delete($settings->video_path);
            $settings->update(['video_path' => null]);
        }

        return response()->json(['message' => 'Video eliminado correctamente']);
    }
}
```

- [ ] **Step 2: Registrar las rutas en `routes/api.php`**

Agregar el `use` al bloque de imports al inicio del archivo:

```php
use App\Http\Controllers\Api\DisplaySettingsController;
```

Agregar la ruta pública **antes** del grupo `auth:sanctum` (junto a `/turns/queue`):

```php
Route::get('/display-settings', [DisplaySettingsController::class, 'show']);
```

Agregar las rutas admin **dentro** del grupo `auth:sanctum`, junto a las demás de admin:

```php
Route::post('/admin/display-settings', [DisplaySettingsController::class, 'update']);
Route::delete('/admin/display-settings/video', [DisplaySettingsController::class, 'deleteVideo']);
```

- [ ] **Step 3: Verificar endpoints con curl o Postman**

```bash
# GET público (debe responder sin token)
curl http://localhost:8000/api/display-settings
# Expected: {"video_enabled":true,"video_path":null,"video_url":null}
```

- [ ] **Step 4: Commit**

```bash
git add backend/app/Http/Controllers/Api/DisplaySettingsController.php backend/routes/api.php
git commit -m "feat(display): add DisplaySettingsController and routes"
```

---

### Task 3: Funciones API en el frontend

**Files:**
- Modify: `frontend/src/api/index.js`

- [ ] **Step 1: Agregar las tres funciones al final de `src/api/index.js`, antes del `export default api`**

```js
// Display settings
export const getDisplaySettings = () => api.get('/display-settings');

export const updateDisplaySettings = (data) => {
  const config = data instanceof FormData
    ? { headers: { 'Content-Type': 'multipart/form-data' } }
    : {};
  return api.post('/admin/display-settings', data, config);
};

export const deleteDisplayVideo = () => api.delete('/admin/display-settings/video');
```

- [ ] **Step 2: Commit**

```bash
git add frontend/src/api/index.js
git commit -m "feat(display): add display settings API functions"
```

---

### Task 4: Componente AdminDisplay.vue

**Files:**
- Create: `frontend/src/components/admin/AdminDisplay.vue`

- [ ] **Step 1: Crear el componente completo**

```vue
<!-- frontend/src/components/admin/AdminDisplay.vue -->
<template>
  <div class="section-content">

    <!-- Header -->
    <div class="section-header">
      <div>
        <h2 class="section-title">Pantalla de Display</h2>
        <p class="section-subtitle">Configurá el video que se muestra en el panel lateral del display público</p>
      </div>
    </div>

    <!-- Settings card -->
    <div class="settings-card" v-if="!loading">

      <!-- Toggle video habilitado -->
      <div class="setting-row">
        <div class="setting-info">
          <div class="setting-label">Mostrar panel de video</div>
          <div class="setting-desc">
            Cuando está activo, el display muestra un panel lateral con el video. Al desactivarlo, los turnos ocupan el 100% de la pantalla.
          </div>
        </div>
        <button
          class="toggle-btn"
          :class="{ 'toggle-btn--on': videoEnabled }"
          @click="videoEnabled = !videoEnabled"
          :title="videoEnabled ? 'Desactivar' : 'Activar'"
        >
          <span class="toggle-knob"></span>
        </button>
      </div>

      <div class="setting-divider"></div>

      <!-- Zona de video -->
      <div class="setting-row setting-row--column">
        <div class="setting-info">
          <div class="setting-label">Video del panel lateral</div>
          <div class="setting-desc">MP4 o WebM · máx. 50 MB · resolución ideal <strong>576 × 1020 px</strong> (9:16)</div>
        </div>

        <!-- Video actual subido -->
        <div v-if="videoUrl && !previewUrl" class="video-current">
          <video :src="videoUrl" class="video-preview" muted controls></video>
          <div class="video-actions">
            <span class="video-filename">Video cargado</span>
            <button class="btn-danger-sm" @click="handleDeleteVideo" :disabled="saving">
              <i class="fas fa-trash-alt"></i>
              Eliminar video
            </button>
          </div>
        </div>

        <!-- Preview local (antes de guardar) -->
        <div v-else-if="previewUrl" class="video-current">
          <video :src="previewUrl" class="video-preview" muted controls></video>
          <div class="video-actions">
            <span class="video-filename pending-badge">
              <i class="fas fa-clock"></i>
              Pendiente de guardar — {{ selectedFile?.name }}
            </span>
            <button class="btn-secondary-sm" @click="clearSelectedFile">
              <i class="fas fa-times"></i>
              Cancelar
            </button>
          </div>
        </div>

        <!-- Zona de drop / selección -->
        <div
          v-else
          class="upload-zone"
          :class="{ 'upload-zone--drag': isDragging }"
          @dragover.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="handleDrop"
          @click="fileInput.click()"
        >
          <i class="fas fa-film upload-icon"></i>
          <p class="upload-text">Arrastrá un video o <span class="upload-link">hacé click para seleccionar</span></p>
          <p class="upload-hint">MP4 o WebM · máx. 50 MB</p>
          <input
            ref="fileInput"
            type="file"
            accept="video/mp4,video/webm"
            class="file-input-hidden"
            @change="handleFileSelect"
          />
        </div>
      </div>

      <div class="setting-divider"></div>

      <!-- Error -->
      <div v-if="saveError" class="form-error">
        <i class="fas fa-exclamation-circle"></i>
        {{ saveError }}
      </div>

      <!-- Guardar -->
      <div class="settings-footer">
        <button class="btn-primary" @click="handleSave" :disabled="saving">
          <i v-if="saving" class="fas fa-spinner fa-spin"></i>
          <i v-else class="fas fa-save"></i>
          {{ saving ? 'Guardando...' : 'Guardar configuración' }}
        </button>
      </div>

    </div>

    <!-- Loading -->
    <div v-else class="loading-state">
      <i class="fas fa-spinner fa-spin"></i>
      Cargando configuración...
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { getDisplaySettings, updateDisplaySettings, deleteDisplayVideo } from '../../api'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'

const { showToast } = useToast()
const { showConfirm } = useConfirm()

const loading   = ref(true)
const saving    = ref(false)
const saveError = ref('')

const videoEnabled   = ref(true)
const videoUrl       = ref(null)
const selectedFile   = ref(null)
const previewUrl     = ref(null)
const isDragging     = ref(false)
const fileInput      = ref(null)

const loadSettings = async () => {
  try {
    const res = await getDisplaySettings()
    videoEnabled.value = res.data.video_enabled
    videoUrl.value     = res.data.video_url || null
  } catch (err) {
    console.error('Error cargando settings:', err)
    showToast('Error al cargar la configuración', 'error')
  } finally {
    loading.value = false
  }
}

const handleFileSelect = (event) => {
  const file = event.target.files[0]
  if (!file) return
  applyFile(file)
}

const handleDrop = (event) => {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (!file) return
  if (!['video/mp4', 'video/webm'].includes(file.type)) {
    saveError.value = 'Solo se aceptan archivos MP4 o WebM'
    return
  }
  applyFile(file)
}

const applyFile = (file) => {
  saveError.value = ''
  if (file.size > 52428800) { // 50 MB en bytes
    saveError.value = 'El archivo supera el límite de 50 MB'
    return
  }
  selectedFile.value = file
  previewUrl.value = URL.createObjectURL(file)
}

const clearSelectedFile = () => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  selectedFile.value = null
  previewUrl.value   = null
  if (fileInput.value) fileInput.value.value = ''
}

const handleSave = async () => {
  saveError.value = ''
  saving.value = true

  try {
    const formData = new FormData()
    formData.append('video_enabled', videoEnabled.value ? '1' : '0')
    if (selectedFile.value) {
      formData.append('video', selectedFile.value)
    }

    const res = await updateDisplaySettings(formData)
    videoUrl.value = res.data.video_url || null
    clearSelectedFile()
    showToast('Configuración guardada correctamente')
  } catch (err) {
    const errors = err.response?.data?.errors
    if (errors) {
      saveError.value = Object.values(errors).flat().join(', ')
    } else {
      saveError.value = err.response?.data?.message || 'Error al guardar la configuración'
    }
  } finally {
    saving.value = false
  }
}

const handleDeleteVideo = () => {
  showConfirm(
    'Eliminar video',
    '¿Seguro que querés eliminar el video del display? El panel quedará con el placeholder.',
    async () => {
      try {
        await deleteDisplayVideo()
        videoUrl.value = null
        showToast('Video eliminado correctamente')
      } catch (err) {
        showToast(err.response?.data?.message || 'Error al eliminar el video', 'error')
      }
    },
    'Eliminar'
  )
}

onMounted(loadSettings)

onBeforeUnmount(() => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
})
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────── */
.section-content { display: flex; flex-direction: column; gap: 1.5rem; }

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.section-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.section-subtitle {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  color: #64748b;
  margin: 0.25rem 0 0;
}

/* ── Settings card ─────────────────────────────── */
.settings-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  padding: 1.75rem 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.setting-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
}

.setting-row--column {
  align-items: flex-start;
  flex-direction: column;
  gap: 1rem;
}

.setting-info { flex: 1; }

.setting-label {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
  font-weight: 600;
  color: #0f172a;
  margin-bottom: 0.25rem;
}

.setting-desc {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  color: #64748b;
  line-height: 1.5;
}

.setting-divider {
  height: 1px;
  background: #f1f5f9;
}

/* ── Toggle ─────────────────────────────────────── */
.toggle-btn {
  width: 52px;
  height: 28px;
  border-radius: 999px;
  border: none;
  background: #e2e8f0;
  cursor: pointer;
  position: relative;
  transition: background 0.2s;
  flex-shrink: 0;
}

.toggle-btn--on { background: #3b82f6; }

.toggle-knob {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 4px rgba(0,0,0,0.15);
  transition: transform 0.2s;
  display: block;
}

.toggle-btn--on .toggle-knob { transform: translateX(24px); }

/* ── Upload zone ─────────────────────────────────── */
.upload-zone {
  width: 100%;
  border: 2px dashed #e2e8f0;
  border-radius: 12px;
  padding: 2.5rem 1.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
  background: #f8fafc;
  text-align: center;
}

.upload-zone:hover,
.upload-zone--drag {
  border-color: #3b82f6;
  background: #eff6ff;
}

.upload-icon {
  font-size: 2.5rem;
  color: #94a3b8;
  margin-bottom: 0.25rem;
}

.upload-text {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
  font-weight: 500;
  color: #475569;
  margin: 0;
}

.upload-link { color: #3b82f6; font-weight: 600; }

.upload-hint {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.78rem;
  color: #94a3b8;
  margin: 0;
}

.file-input-hidden { display: none; }

/* ── Video preview ───────────────────────────────── */
.video-current {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.video-preview {
  width: 100%;
  max-height: 280px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #0f172a;
  object-fit: contain;
}

.video-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.video-filename {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.pending-badge { color: #d97706; }

/* ── Buttons ─────────────────────────────────────── */
.btn-primary {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 1.5rem;
  background: #3b82f6;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-primary:hover:not(:disabled) { background: #2563eb; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-danger-sm {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.9rem;
  background: #fef2f2;
  color: #ef4444;
  border: 1px solid #fecaca;
  border-radius: 8px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-danger-sm:hover:not(:disabled) { background: #fee2e2; }
.btn-danger-sm:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-secondary-sm {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.9rem;
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-secondary-sm:hover { background: #e2e8f0; }

/* ── Misc ────────────────────────────────────────── */
.settings-footer {
  display: flex;
  justify-content: flex-end;
}

.form-error {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  color: #dc2626;
  font-weight: 500;
}

.loading-state {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 2rem 0;
}
</style>
```

- [ ] **Step 2: Verificar que el archivo creado no tiene errores de sintaxis abriendo el admin en el navegador** (lo haremos cuando esté conectado en el siguiente task).

- [ ] **Step 3: Commit**

```bash
git add frontend/src/components/admin/AdminDisplay.vue
git commit -m "feat(display): add AdminDisplay component with toggle and video upload"
```

---

### Task 5: Conectar la tab en sidebar y AdminView

**Files:**
- Modify: `frontend/src/components/admin/AdminSidebar.vue`
- Modify: `frontend/src/views/AdminView.vue`

- [ ] **Step 1: Agregar la tab en `AdminSidebar.vue`**

Localizar el array `tabs` en `<script setup>` (línea ~64) y agregar la nueva entrada al final:

```js
const tabs = [
  { id: 'dashboard',   name: 'Dashboard',   icon: 'fas fa-chart-bar' },
  { id: 'historial',   name: 'Historial',   icon: 'fas fa-history' },
  { id: 'usuarios',    name: 'Usuarios',    icon: 'fas fa-users' },
  { id: 'perfiles',    name: 'Perfiles',    icon: 'fas fa-layer-group' },
  { id: 'ventanillas', name: 'Ventanillas', icon: 'fas fa-desktop' },
  { id: 'servicios',   name: 'Servicios',   icon: 'fas fa-clipboard-list' },
  { id: 'display',     name: 'Display',     icon: 'fas fa-tv' },
]
```

- [ ] **Step 2: Importar y registrar `AdminDisplay` en `AdminView.vue`**

En el bloque de imports del `<script setup>` (después de `AdminServicios`), agregar:

```js
import AdminDisplay from '../components/admin/AdminDisplay.vue'
```

En el bloque `<main class="admin-content">` del template, agregar después del bloque de `AdminServicios`:

```html
<AdminDisplay v-else-if="activeTab === 'display'" />
```

- [ ] **Step 3: Verificar en el navegador**

Abrir `http://<ip>:5173/admin`, hacer login como admin, confirmar que aparece la tab "Display" en el sidebar y que el componente carga sin errores en consola.

- [ ] **Step 4: Commit**

```bash
git add frontend/src/components/admin/AdminSidebar.vue frontend/src/views/AdminView.vue
git commit -m "feat(display): wire Display tab in admin sidebar and view"
```

---

### Task 6: Actualizar DisplayView para consumir settings

**Files:**
- Modify: `frontend/src/views/DisplayView.vue`

- [ ] **Step 1: Agregar el import de `getDisplaySettings` al inicio del `<script setup>`**

La línea de import actual es:
```js
import { getQueue } from '../api';
```

Reemplazarla por:
```js
import { getQueue, getDisplaySettings } from '../api';
```

- [ ] **Step 2: Agregar el ref `settings` y el computed `showVideoPanel` junto a los demás refs**

Agregar después de `const lastCalledId = ref(null);`:

```js
const settings = ref({ video_enabled: true, video_path: null, video_url: null });
```

Agregar después del computed `lastCalledTurn`:

```js
const showVideoPanel = computed(() => settings.value.video_enabled);
```

- [ ] **Step 3: Agregar la carga de settings en `onMounted`**

La función `onMounted` actual es:
```js
onMounted(() => {
  loadQueue();
  updateClock();

  if (USE_SSE) {
    connectSSE();
  } else {
    intervalId = setInterval(loadQueue, 2000);
  }

  clockId = setInterval(updateClock, 1000);
});
```

Reemplazarla por:
```js
onMounted(async () => {
  try {
    const res = await getDisplaySettings();
    settings.value = res.data;
  } catch (err) {
    console.error('Error cargando display settings:', err);
  }

  loadQueue();
  updateClock();

  if (USE_SSE) {
    connectSSE();
  } else {
    intervalId = setInterval(loadQueue, 2000);
  }

  clockId = setInterval(updateClock, 1000);
});
```

- [ ] **Step 4: Actualizar el template — panel de turnos con clase condicional**

Localizar la línea:
```html
<section class="turns-panel">
```

Reemplazarla por:
```html
<section class="turns-panel" :class="{ 'turns-panel--full': !showVideoPanel }">
```

- [ ] **Step 5: Actualizar el template — panel de video con v-if y video real**

Localizar el bloque:
```html
<!-- ══════════════════════════════════
     PANEL VIDEO — 30%
══════════════════════════════════ -->
<aside class="video-panel">
  <div class="video-placeholder">
    <div class="video-icon">▶</div>
    <div class="video-title">Tu video aquí</div>
    <div class="video-hint">
      Subí tu video desde el<br>panel de administración
    </div>
    <div class="video-resolution">
      <span>Resolución ideal</span>
      <strong>576 × 1020 px</strong>
    </div>
  </div>
</aside>
```

Reemplazarlo por:
```html
<!-- ══════════════════════════════════
     PANEL VIDEO — 30%
══════════════════════════════════ -->
<aside class="video-panel" v-if="showVideoPanel">
  <!-- Video subido -->
  <video
    v-if="settings.video_url"
    :src="settings.video_url"
    class="video-player"
    loop
    autoplay
    muted
    playsinline
  ></video>
  <!-- Placeholder si no hay video -->
  <div v-else class="video-placeholder">
    <div class="video-icon">▶</div>
    <div class="video-title">Tu video aquí</div>
    <div class="video-hint">
      Subí tu video desde el<br>panel de administración
    </div>
    <div class="video-resolution">
      <span>Resolución ideal</span>
      <strong>576 × 1020 px</strong>
    </div>
  </div>
</aside>
```

- [ ] **Step 6: Agregar los estilos `turns-panel--full` y `video-player` al `<style scoped>`**

Al final del bloque de estilos del `.video-panel`, agregar:

```css
.video-player {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.turns-panel--full {
  flex: 0 0 100%;
  border-right: none;
}
```

- [ ] **Step 7: Verificar los tres estados en el navegador**

1. Con `video_enabled = false` (setear desde admin o curl): el display muestra los turnos a 100% sin panel lateral.
2. Con `video_enabled = true` y sin video: el display muestra el placeholder ▶.
3. Con `video_enabled = true` y video subido: el display reproduce el video en loop.

```bash
# Forzar video_enabled=false vía curl para verificar el layout completo:
curl -X POST http://localhost:8000/api/admin/display-settings \
  -H "Authorization: Bearer <token>" \
  -d "video_enabled=0"
```

- [ ] **Step 8: Commit**

```bash
git add frontend/src/views/DisplayView.vue
git commit -m "feat(display): consume display settings in DisplayView, conditional video panel"
```
