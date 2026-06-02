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
