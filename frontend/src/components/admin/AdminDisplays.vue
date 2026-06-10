<template>
  <div class="section-content">
    <div class="section-header">
      <div>
        <h2 class="section-title">Pantallas</h2>
        <p class="section-subtitle">Cada pantalla muestra solo los turnos de sus servicios</p>
      </div>
      <button class="btn-primary" @click="openModal()">
        <i class="fas fa-plus"></i> Nueva Pantalla
      </button>
    </div>

    <div class="info-box">
      <i class="fas fa-info-circle info-icon"></i>
      <div>
        <p>Cada <strong>pantalla</strong> se abre en <code>/display/&lt;id&gt;</code> y
        muestra únicamente los turnos de los servicios seleccionados. El video y el clima son globales (configurá en la tab Display).</p>
      </div>
    </div>

    <div class="table-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Barra superior</th>
            <th>Servicios</th>
            <th>URL</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="empty-cell">
              <i class="fas fa-spinner fa-spin"></i> Cargando pantallas...
            </td>
          </tr>
          <tr v-else-if="displays.length === 0">
            <td colspan="7" class="empty-cell">
              <i class="fas fa-display empty-icon"></i>
              <p>No hay pantallas creadas</p>
            </td>
          </tr>
          <tr v-else v-for="d in displays" :key="d.id" class="table-row">
            <td><span class="code-badge">{{ d.id }}</span></td>
            <td><span class="cell-primary">{{ d.name }}</span></td>
            <td>
              <div class="color-swatch-cell">
                <span class="color-swatch" :style="{ backgroundColor: d.topbar_color }"></span>
                <span class="cell-secondary font-mono">{{ d.topbar_color }}</span>
              </div>
            </td>
            <td>
              <span v-if="!d.service_names || d.service_names.length === 0" class="cell-muted">Sin servicios</span>
              <div v-else class="services-list">
                <span v-for="name in d.service_names.slice(0, 3)" :key="name" class="service-pill">{{ name }}</span>
                <span v-if="d.service_names.length > 3" class="service-pill service-more">+{{ d.service_names.length - 3 }}</span>
              </div>
            </td>
            <td>
              <a class="display-link" :href="`/display/${d.id}`" target="_blank">
                /display/{{ d.id }}
              </a>
            </td>
            <td>
              <span class="status-badge" :class="d.is_active ? 'status-active' : 'status-inactive'">
                {{ d.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button class="btn-action btn-edit" title="Editar" @click="openModal(d)">
                  <i class="fas fa-pencil-alt"></i>
                </button>
                <button class="btn-action btn-delete" title="Eliminar" @click="handleDelete(d)">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-container" ref="modalContainer">
          <div class="modal-accent" style="background: #3b82f6;"></div>
          <div class="modal-header">
            <div class="modal-title-group">
              <div class="modal-icon" style="background: rgba(59,130,246,0.1); color: #3b82f6;">
                <i class="fas fa-display"></i>
              </div>
              <h3 class="modal-title">{{ editingItem ? 'Editar Pantalla' : 'Nueva Pantalla' }}</h3>
            </div>
            <button class="modal-close" @click="closeModal"><i class="fas fa-times"></i></button>
          </div>

          <div class="modal-body">
            <div v-if="formError" class="form-error">
              <i class="fas fa-exclamation-circle"></i> {{ formError }}
            </div>

            <!-- Información básica -->
            <div class="form-section">
              <h4 class="form-section-title"><i class="fas fa-id-card"></i> Información</h4>
              <div class="form-group">
                <label class="form-label">Nombre <span class="required">*</span></label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-input"
                  placeholder="Ej: Planta Baja, Piso 2, Recepción"
                />
              </div>
            </div>

            <!-- Servicios -->
            <div class="form-section">
              <h4 class="form-section-title">
                <i class="fas fa-clipboard-list"></i> Servicios a mostrar <span class="required">*</span>
              </h4>
              <div v-if="servicesLoading" class="loading-services">
                <i class="fas fa-spinner fa-spin"></i> Cargando servicios...
              </div>
              <div v-else-if="allServices.length === 0" class="no-services-msg">
                No hay servicios creados. Creá servicios primero.
              </div>
              <div v-else class="services-checkboxes">
                <label v-for="service in allServices" :key="service.id" class="service-checkbox">
                  <input v-model="form.services" :value="service.id" type="checkbox" class="checkbox-input" />
                  <div class="service-dot" :style="{ backgroundColor: service.color }"></div>
                  <span>{{ service.name }}</span>
                  <span class="service-code">{{ service.code }}</span>
                </label>
              </div>
            </div>

            <!-- Apariencia -->
            <div class="form-section">
              <h4 class="form-section-title"><i class="fas fa-palette"></i> Apariencia</h4>
              <div class="form-group">
                <label class="form-label">Color de la barra superior</label>
                <div class="color-picker-row">
                  <input v-model="form.topbar_color" type="color" class="color-input" />
                  <input
                    v-model="form.topbar_color"
                    type="text"
                    class="form-input font-mono"
                    style="flex: 1"
                    placeholder="#1e40af"
                  />
                </div>
                <p class="form-hint">Color del recuadro que contiene el reloj, la fecha y el clima.</p>
              </div>
            </div>

            <!-- Estado -->
            <div class="form-section">
              <h4 class="form-section-title"><i class="fas fa-sliders-h"></i> Estado</h4>
              <div class="form-group">
                <label class="toggle-label">
                  <div
                    class="toggle-switch"
                    :class="{ 'toggle-on': form.is_active }"
                    @click="form.is_active = !form.is_active"
                  >
                    <div class="toggle-thumb"></div>
                  </div>
                  <div>
                    <span class="toggle-text">Pantalla activa</span>
                    <p class="toggle-hint">Las pantallas inactivas no son accesibles desde la URL pública</p>
                  </div>
                </label>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn-cancel" @click="closeModal">Cancelar</button>
            <button class="btn-save" :disabled="saving" @click="save">
              <i class="fas fa-check" v-if="!saving"></i>
              <i class="fas fa-spinner fa-spin" v-else></i>
              {{ saving ? 'Guardando...' : 'Guardar Pantalla' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getDisplays, createDisplay, updateDisplay, deleteDisplay, getServices } from '../../api'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'

const { showToast } = useToast()
const { showConfirm } = useConfirm()

const displays = ref([])
const loading = ref(false)
const showModal = ref(false)
const editingItem = ref(null)
const saving = ref(false)
const formError = ref('')
const allServices = ref([])
const servicesLoading = ref(false)
const modalContainer = ref(null)

const defaultForm = () => ({
  name: '',
  services: [],
  topbar_color: '#1e40af',
  is_active: true,
})

const form = ref(defaultForm())

const scrollModalToTop = () => {
  setTimeout(() => {
    if (modalContainer.value) modalContainer.value.scrollTo({ top: 0, behavior: 'smooth' })
  }, 100)
}

const loadDisplays = async () => {
  loading.value = true
  try {
    const response = await getDisplays()
    displays.value = response.data
  } catch (error) {
    console.error('Error cargando pantallas:', error)
    showToast('Error al cargar pantallas', 'error')
  } finally {
    loading.value = false
  }
}

const loadServices = async () => {
  if (allServices.value.length > 0) return
  servicesLoading.value = true
  try {
    const response = await getServices()
    allServices.value = response.data
  } catch (error) {
    console.error('Error cargando servicios:', error)
  } finally {
    servicesLoading.value = false
  }
}

const openModal = async (display = null) => {
  formError.value = ''
  await loadServices()
  if (display) {
    editingItem.value = display
    form.value = {
      name: display.name,
      services: Array.isArray(display.services) ? display.services : [],
      topbar_color: display.topbar_color || '#1e40af',
      is_active: display.is_active !== undefined ? display.is_active : true,
    }
  } else {
    editingItem.value = null
    form.value = defaultForm()
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  editingItem.value = null
  formError.value = ''
}

const save = async () => {
  formError.value = ''
  if (!form.value.name) {
    formError.value = 'El nombre es obligatorio'
    scrollModalToTop()
    return
  }
  if (!form.value.services || form.value.services.length === 0) {
    formError.value = 'Debe seleccionar al menos un servicio'
    scrollModalToTop()
    return
  }

  saving.value = true
  try {
    const data = {
      name: form.value.name,
      services: form.value.services,
      topbar_color: form.value.topbar_color,
      is_active: form.value.is_active,
    }
    if (editingItem.value) {
      await updateDisplay(editingItem.value.id, data)
    } else {
      await createDisplay(data)
    }
    showToast('Pantalla guardada correctamente')
    await loadDisplays()
    closeModal()
  } catch (error) {
    console.error('Error guardando pantalla:', error)
    formError.value = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join(', ')
      : error.response?.data?.message || 'Error al guardar pantalla'
    scrollModalToTop()
  } finally {
    saving.value = false
  }
}

const handleDelete = (display) => {
  showConfirm(
    'Eliminar Pantalla',
    `¿Estás seguro de eliminar la pantalla "${display.name}"? Esta acción no se puede deshacer.`,
    async () => {
      try {
        await deleteDisplay(display.id)
        await loadDisplays()
        showToast('Pantalla eliminada correctamente')
      } catch (error) {
        console.error('Error eliminando pantalla:', error)
        showToast(error.response?.data?.message || 'Error al eliminar pantalla', 'error')
      }
    }
  )
}

onMounted(loadDisplays)
</script>

<style scoped>
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

.btn-primary {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.25rem;
  background: #3b82f6;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
  white-space: nowrap;
}

.btn-primary:hover { background: #2563eb; }
.btn-primary:active { transform: scale(0.97); }

.info-box {
  display: flex;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  color: #1e40af;
  line-height: 1.5;
}

.info-icon { color: #3b82f6; font-size: 1rem; margin-top: 0.1rem; flex-shrink: 0; }
.info-box code { background: #dbeafe; padding: 0.1rem 0.4rem; border-radius: 4px; font-family: monospace; font-size: 0.8rem; }

.table-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

.data-table { width: 100%; border-collapse: collapse; }
.data-table thead tr { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.data-table th {
  padding: 0.75rem 1.25rem;
  text-align: left;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.7rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.table-row { border-bottom: 1px solid #f1f5f9; transition: background 0.1s; }
.table-row:last-child { border-bottom: none; }
.table-row:hover { background: #f8fafc; }
.data-table td { padding: 0.875rem 1.25rem; vertical-align: middle; }

.cell-primary { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.875rem; font-weight: 600; color: #0f172a; }
.cell-secondary { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.85rem; color: #64748b; }
.cell-muted { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.8rem; color: #94a3b8; font-style: italic; }

.empty-cell { padding: 3rem; text-align: center; color: #94a3b8; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.9rem; }
.empty-icon { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; color: #cbd5e1; }

.code-badge {
  font-family: 'JetBrains Mono', 'Courier New', monospace;
  font-size: 0.8rem;
  font-weight: 700;
  background: #f1f5f9;
  color: #334155;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
}

.color-swatch-cell { display: flex; align-items: center; gap: 0.5rem; }
.color-swatch { width: 18px; height: 18px; border-radius: 5px; border: 1px solid rgba(0,0,0,0.1); flex-shrink: 0; }

.services-list { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.service-pill { padding: 0.2rem 0.5rem; background: #f1f5f9; color: #475569; border-radius: 6px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.72rem; font-weight: 500; }
.service-more { background: #e2e8f0; color: #94a3b8; }

.display-link { font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #3b82f6; text-decoration: none; }
.display-link:hover { text-decoration: underline; }

.status-badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 20px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.75rem; font-weight: 600; }
.status-active { background: #dcfce7; color: #16a34a; }
.status-inactive { background: #fee2e2; color: #dc2626; }

.action-buttons { display: flex; gap: 0.4rem; }
.btn-action { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; transition: all 0.15s; }
.btn-edit { color: #3b82f6; }
.btn-edit:hover { background: #eff6ff; border-color: #bfdbfe; }
.btn-delete { color: #ef4444; }
.btn-delete:hover { background: #fef2f2; border-color: #fecaca; }

/* Modal */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem; backdrop-filter: blur(4px); }
.modal-container { background: #fff; border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,0.2); position: relative; }
.modal-accent { height: 5px; width: 100%; border-radius: 16px 16px 0 0; }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; position: sticky; top: 0; background: #fff; z-index: 1; }
.modal-title-group { display: flex; align-items: center; gap: 0.75rem; }
.modal-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; }
.modal-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0; }
.modal-close { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; transition: all 0.15s; }
.modal-close:hover { background: #f8fafc; color: #0f172a; }
.modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 0; }
.modal-footer { display: flex; gap: 0.75rem; padding: 1.25rem 1.5rem; border-top: 1px solid #f1f5f9; justify-content: flex-end; position: sticky; bottom: 0; background: #fff; }

/* Form */
.form-section { padding: 1.25rem 0; border-bottom: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: 1rem; }
.form-section:last-child { border-bottom: none; }
.form-section-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-label { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.required { color: #ef4444; }
.form-input { padding: 0.625rem 0.875rem; border: 1.5px solid #e2e8f0; border-radius: 10px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.875rem; color: #0f172a; background: #fff; transition: border-color 0.15s, box-shadow 0.15s; outline: none; width: 100%; box-sizing: border-box; }
.form-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.form-hint { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.75rem; color: #94a3b8; margin: 0; }
.form-error { padding: 0.75rem 1rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; color: #dc2626; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }

/* Services checkboxes */
.loading-services { padding: 0.75rem; color: #94a3b8; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.85rem; }
.no-services-msg { padding: 0.75rem; color: #94a3b8; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.85rem; font-style: italic; }
.services-checkboxes { display: flex; flex-direction: column; gap: 0.4rem; max-height: 200px; overflow-y: auto; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 0.75rem; }
.service-checkbox { display: flex; align-items: center; gap: 0.6rem; cursor: pointer; padding: 0.35rem 0.5rem; border-radius: 8px; transition: background 0.1s; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.875rem; color: #334155; }
.service-checkbox:hover { background: #f8fafc; }
.checkbox-input { width: 16px; height: 16px; accent-color: #3b82f6; cursor: pointer; flex-shrink: 0; }
.service-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.service-code { margin-left: auto; font-family: monospace; font-size: 0.75rem; color: #94a3b8; }

/* Color picker */
.color-picker-row { display: flex; align-items: center; gap: 0.5rem; }
.color-input { width: 48px; height: 42px; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 2px; cursor: pointer; background: transparent; flex-shrink: 0; }

/* Toggle */
.toggle-label { display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; }
.toggle-switch { position: relative; width: 44px; height: 24px; background: #e2e8f0; border-radius: 12px; transition: background 0.2s; flex-shrink: 0; cursor: pointer; margin-top: 2px; }
.toggle-on { background: #3b82f6; }
.toggle-thumb { position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: #fff; border-radius: 50%; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
.toggle-on .toggle-thumb { transform: translateX(20px); }
.toggle-text { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.875rem; font-weight: 600; color: #0f172a; display: block; }
.toggle-hint { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.75rem; color: #94a3b8; margin: 0.2rem 0 0; }

/* Footer buttons */
.btn-cancel { padding: 0.6rem 1.25rem; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; border-radius: 10px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.15s; }
.btn-cancel:hover { background: #e2e8f0; color: #334155; }
.btn-save { display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.4rem; background: #3b82f6; color: #fff; border: none; border-radius: 10px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: background 0.15s; }
.btn-save:hover { background: #2563eb; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.font-mono { font-family: 'JetBrains Mono', 'Courier New', monospace !important; }
</style>
