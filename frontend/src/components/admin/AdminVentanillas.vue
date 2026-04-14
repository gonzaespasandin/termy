<template>
  <div class="section-content">
    <!-- Header -->
    <div class="section-header">
      <div>
        <h2 class="section-title">Ventanillas</h2>
        <p class="section-subtitle">Puntos de atención del sistema</p>
      </div>
      <button class="btn-primary" @click="openModal()">
        <i class="fas fa-plus"></i>
        Nueva Ventanilla
      </button>
    </div>

    <!-- Info box -->
    <div class="info-box">
      <i class="fas fa-info-circle info-icon"></i>
      <div>
        <p>Las <strong>ventanillas</strong> se asignan automáticamente por <strong>IP</strong>. Cuando un operador abre
        <code>/operator</code>, el sistema detecta su IP y lo asocia a la ventanilla correspondiente.
        El <strong>número</strong> se muestra en el display cuando se llama un turno.</p>
      </div>
    </div>

    <!-- Tabla -->
    <div class="table-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>N°</th>
            <th>Nombre</th>
            <th>IP Asignada</th>
            <th>Servicios</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="empty-cell">
              <i class="fas fa-spinner fa-spin"></i>
              Cargando ventanillas...
            </td>
          </tr>
          <tr v-else-if="windows.length === 0">
            <td colspan="6" class="empty-cell">
              <i class="fas fa-desktop empty-icon"></i>
              <p>No hay ventanillas creadas</p>
            </td>
          </tr>
          <tr v-else v-for="win in windows" :key="win.id" class="table-row">
            <td>
              <span class="number-badge">{{ win.number }}</span>
            </td>
            <td class="cell-primary">{{ win.name }}</td>
            <td>
              <span v-if="win.ip_address" class="code-badge">{{ win.ip_address }}</span>
              <span v-else class="cell-muted">Sin IP</span>
            </td>
            <td>
              <span v-if="!win.service_names || win.service_names.length === 0" class="cell-muted">Todos los servicios</span>
              <div v-else class="services-list">
                <span v-for="name in win.service_names.slice(0, 3)" :key="name" class="service-pill">{{ name }}</span>
                <span v-if="win.service_names.length > 3" class="service-pill service-more">+{{ win.service_names.length - 3 }}</span>
              </div>
            </td>
            <td>
              <span class="status-badge" :class="win.is_active ? 'status-active' : 'status-inactive'">
                {{ win.is_active ? 'Activa' : 'Inactiva' }}
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button class="btn-action btn-edit" title="Editar" @click="openModal(win)">
                  <i class="fas fa-pencil-alt"></i>
                </button>
                <button class="btn-action btn-delete" title="Eliminar" @click="handleDelete(win)">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-container">
          <div class="modal-accent" style="background: #10b981;"></div>

          <div class="modal-header">
            <div class="modal-title-group">
              <div class="modal-icon" style="background: rgba(16,185,129,0.1); color: #10b981;">
                <i class="fas fa-desktop"></i>
              </div>
              <h3 class="modal-title">{{ editingItem ? 'Editar Ventanilla' : 'Nueva Ventanilla' }}</h3>
            </div>
            <button class="modal-close" @click="closeModal">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="modal-body">
            <div v-if="formError" class="form-error">
              <i class="fas fa-exclamation-circle"></i>
              {{ formError }}
            </div>

            <div class="form-grid-2">
              <div class="form-group">
                <label class="form-label">Nombre <span class="required">*</span></label>
                <input v-model="form.name" type="text" class="form-input" placeholder="Ej: Caja 1, Recepción" />
              </div>
              <div class="form-group">
                <label class="form-label">Número <span class="required">*</span></label>
                <input v-model.number="form.number" type="number" min="1" class="form-input" placeholder="1" />
                <p class="form-hint">Se muestra en el display público</p>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">IP Asignada <span class="optional-hint">(opcional)</span></label>
              <input v-model="form.ip_address" type="text" class="form-input font-mono" placeholder="192.168.0.10" />
              <p class="form-hint">La PC con esta IP será asociada automáticamente a esta ventanilla</p>
            </div>

            <div class="form-group">
              <label class="form-label">Servicios que puede atender <span class="optional-hint">(opcional)</span></label>
              <p class="form-hint" style="margin-bottom: 0.5rem;">Si no seleccionás ninguno, la ventanilla puede atender todos los servicios.</p>
              <div v-if="servicesLoading" class="loading-services">
                <i class="fas fa-spinner fa-spin"></i> Cargando servicios...
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

            <div class="form-group">
              <label class="toggle-label">
                <div class="toggle-switch" :class="{ 'toggle-on': form.is_active }" @click="form.is_active = !form.is_active">
                  <div class="toggle-thumb"></div>
                </div>
                <div>
                  <span class="toggle-text">Ventanilla activa</span>
                  <p class="toggle-hint">Las ventanillas inactivas no reciben turnos</p>
                </div>
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn-cancel" @click="closeModal">Cancelar</button>
            <button class="btn-save btn-save--emerald" :disabled="saving" @click="save">
              <i class="fas fa-check" v-if="!saving"></i>
              <i class="fas fa-spinner fa-spin" v-else></i>
              {{ saving ? 'Guardando...' : 'Guardar Ventanilla' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getWindows, createWindow, updateWindow, deleteWindow, getServices } from '../../api'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'

const { showToast } = useToast()
const { showConfirm } = useConfirm()

const windows = ref([])
const loading = ref(false)
const showModal = ref(false)
const editingItem = ref(null)
const saving = ref(false)
const formError = ref('')
const allServices = ref([])
const servicesLoading = ref(false)

const defaultForm = () => ({
  name: '',
  number: null,
  ip_address: '',
  services: [],
  is_active: true,
})

const form = ref(defaultForm())

const loadWindows = async () => {
  loading.value = true
  try {
    const response = await getWindows()
    windows.value = response.data
  } catch (error) {
    console.error('Error cargando ventanillas:', error)
    showToast('Error al cargar ventanillas', 'error')
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

const openModal = async (win = null) => {
  formError.value = ''
  await loadServices()

  if (win) {
    editingItem.value = win
    form.value = {
      name: win.name,
      number: win.number,
      ip_address: win.ip_address || '',
      services: Array.isArray(win.services) ? win.services : [],
      is_active: win.is_active !== undefined ? win.is_active : true,
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
  if (!form.value.name || !form.value.number) {
    formError.value = 'Nombre y número son obligatorios'
    return
  }

  saving.value = true
  try {
    if (editingItem.value) {
      await updateWindow(editingItem.value.id, form.value)
      showToast('Ventanilla actualizada correctamente')
    } else {
      await createWindow(form.value)
      showToast('Ventanilla creada correctamente')
    }
    await loadWindows()
    closeModal()
  } catch (error) {
    console.error('Error guardando ventanilla:', error)
    formError.value = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join(', ')
      : error.response?.data?.message || 'Error al guardar ventanilla'
  } finally {
    saving.value = false
  }
}

const handleDelete = (win) => {
  showConfirm(
    'Eliminar Ventanilla',
    `¿Estás seguro de eliminar la ventanilla "${win.name}"?`,
    async () => {
      try {
        await deleteWindow(win.id)
        await loadWindows()
        showToast('Ventanilla eliminada correctamente')
      } catch (error) {
        console.error('Error eliminando ventanilla:', error)
        showToast(error.response?.data?.message || 'Error al eliminar ventanilla', 'error')
      }
    }
  )
}

onMounted(loadWindows)
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
  background: #10b981;
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

.btn-primary:hover { background: #059669; }
.btn-primary:active { transform: scale(0.97); }

.info-box {
  display: flex;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 12px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  color: #166534;
  line-height: 1.5;
}

.info-icon { color: #16a34a; font-size: 1rem; margin-top: 0.1rem; flex-shrink: 0; }
.info-box code {
  background: #dcfce7;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  font-family: monospace;
  font-size: 0.8rem;
}

.table-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

.data-table { width: 100%; border-collapse: collapse; }

.data-table thead tr {
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

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

.cell-primary {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  color: #0f172a;
}

.cell-muted {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  color: #94a3b8;
  font-style: italic;
}

.empty-cell {
  padding: 3rem;
  text-align: center;
  color: #94a3b8;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
}

.empty-icon { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; color: #cbd5e1; }

.number-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 32px;
  padding: 0 0.5rem;
  background: linear-gradient(135deg, #10b981, #059669);
  color: #fff;
  border-radius: 8px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 700;
}

.code-badge {
  font-family: 'JetBrains Mono', 'Courier New', monospace;
  font-size: 0.8rem;
  font-weight: 600;
  background: #f1f5f9;
  color: #334155;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-active { background: #dcfce7; color: #16a34a; }
.status-inactive { background: #fee2e2; color: #dc2626; }

.services-list { display: flex; flex-wrap: wrap; gap: 0.3rem; }

.service-pill {
  padding: 0.2rem 0.5rem;
  background: #f1f5f9;
  color: #475569;
  border-radius: 6px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.72rem;
  font-weight: 500;
}

.service-more { background: #e2e8f0; color: #94a3b8; }

.action-buttons { display: flex; gap: 0.4rem; }

.btn-action {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  transition: all 0.15s;
}

.btn-edit { color: #3b82f6; }
.btn-edit:hover { background: #eff6ff; border-color: #bfdbfe; }
.btn-delete { color: #ef4444; }
.btn-delete:hover { background: #fef2f2; border-color: #fecaca; }

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 1rem;
  backdrop-filter: blur(4px);
}

.modal-container {
  background: #fff;
  border-radius: 16px;
  max-width: 540px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 25px 60px rgba(0,0,0,0.2);
  position: relative;
}

.modal-accent { height: 5px; width: 100%; border-radius: 16px 16px 0 0; }

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
}

.modal-title-group { display: flex; align-items: center; gap: 0.75rem; }

.modal-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
}

.modal-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.modal-close {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  transition: all 0.15s;
}

.modal-close:hover { background: #f8fafc; color: #0f172a; }
.modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.modal-footer {
  display: flex;
  gap: 0.75rem;
  padding: 1.25rem 1.5rem;
  border-top: 1px solid #f1f5f9;
  justify-content: flex-end;
}

.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

.form-label {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.required { color: #ef4444; }
.optional-hint { font-size: 0.7rem; color: #94a3b8; font-weight: 400; text-transform: none; margin-left: 0.25rem; }

.form-input {
  padding: 0.625rem 0.875rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  color: #0f172a;
  background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
  outline: none;
  width: 100%;
  box-sizing: border-box;
}

.form-input:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }

.form-hint {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  color: #94a3b8;
  margin: 0;
}

.form-error {
  padding: 0.75rem 1rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  color: #dc2626;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.loading-services {
  padding: 0.75rem 1rem;
  color: #94a3b8;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
}

.services-checkboxes {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 180px;
  overflow-y: auto;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.75rem;
}

.service-checkbox {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  cursor: pointer;
  padding: 0.35rem 0.5rem;
  border-radius: 8px;
  transition: background 0.1s;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  color: #334155;
}

.service-checkbox:hover { background: #f8fafc; }

.checkbox-input { width: 16px; height: 16px; accent-color: #10b981; cursor: pointer; flex-shrink: 0; }
.service-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.service-code { margin-left: auto; font-family: monospace; font-size: 0.75rem; color: #94a3b8; }

.toggle-label { display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer; }

.toggle-switch {
  position: relative;
  width: 44px;
  height: 24px;
  background: #e2e8f0;
  border-radius: 12px;
  transition: background 0.2s;
  flex-shrink: 0;
  cursor: pointer;
  margin-top: 2px;
}

.toggle-on { background: #10b981; }

.toggle-thumb {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  background: #fff;
  border-radius: 50%;
  transition: transform 0.2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.toggle-on .toggle-thumb { transform: translateX(20px); }

.toggle-text {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  color: #0f172a;
  display: block;
}

.toggle-hint { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.75rem; color: #94a3b8; margin: 0.2rem 0 0; }

.btn-cancel {
  padding: 0.6rem 1.25rem;
  background: #f1f5f9;
  color: #64748b;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-cancel:hover { background: #e2e8f0; color: #334155; }

.btn-save {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.4rem;
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

.btn-save--emerald { background: #10b981; }
.btn-save--emerald:hover { background: #059669; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
.font-mono { font-family: 'JetBrains Mono', 'Courier New', monospace !important; }
</style>
