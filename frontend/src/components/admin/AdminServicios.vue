<template>
  <div class="section-content">
    <!-- Header -->
    <div class="section-header">
      <div>
        <h2 class="section-title">Servicios</h2>
        <p class="section-subtitle">Gestión de servicios del sistema de turnos</p>
      </div>
      <button class="btn-primary" @click="openModal()">
        <i class="fas fa-plus"></i>
        Nuevo Servicio
      </button>
    </div>

    <!-- Tabla -->
    <div class="table-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Descripción</th>
            <th>Color</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="empty-cell">
              <i class="fas fa-spinner fa-spin"></i>
              Cargando servicios...
            </td>
          </tr>
          <tr v-else-if="services.length === 0">
            <td colspan="6" class="empty-cell">
              <i class="fas fa-clipboard-list empty-icon"></i>
              <p>No hay servicios creados</p>
            </td>
          </tr>
          <tr v-else v-for="service in services" :key="service.id" class="table-row">
            <td>
              <span class="code-badge">{{ service.code }}</span>
            </td>
            <td class="cell-primary">{{ service.name }}</td>
            <td class="cell-secondary">{{ service.description || '—' }}</td>
            <td>
              <div class="color-preview" :style="{ backgroundColor: service.color }">
                <span class="color-hex">{{ service.color }}</span>
              </div>
            </td>
            <td>
              <span class="status-badge" :class="service.is_active ? 'status-active' : 'status-inactive'">
                {{ service.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button class="btn-action btn-edit" title="Editar" @click="openModal(service)">
                  <i class="fas fa-pencil-alt"></i>
                </button>
                <button class="btn-action btn-delete" title="Eliminar" @click="handleDelete(service)">
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
          <div class="modal-accent" style="background: #3b82f6;"></div>

          <div class="modal-header">
            <div class="modal-title-group">
              <div class="modal-icon" style="background: rgba(59,130,246,0.1); color: #3b82f6;">
                <i class="fas fa-clipboard-list"></i>
              </div>
              <h3 class="modal-title">{{ editingItem ? 'Editar Servicio' : 'Nuevo Servicio' }}</h3>
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

            <div class="form-group">
              <label class="form-label">Nombre <span class="required">*</span></label>
              <input v-model="form.name" type="text" class="form-input" placeholder="Ej: Caja, Consultorios, Admisión" />
            </div>

            <div class="form-group">
              <label class="form-label">Código <span class="required">*</span></label>
              <input
                v-model="form.code"
                type="text"
                maxlength="10"
                class="form-input font-mono uppercase"
                placeholder="Ej: CA, CO, AD"
                @input="form.code = form.code.toUpperCase()"
              />
              <p class="form-hint">Máximo 10 caracteres. Se usa como prefijo del ticket (ej: CA-001).</p>
            </div>

            <div class="form-group">
              <label class="form-label">Descripción</label>
              <textarea v-model="form.description" rows="3" class="form-input" placeholder="Descripción breve del servicio..."></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Color del servicio</label>
              <div class="color-picker-row">
                <input v-model="form.color" type="color" class="color-input" />
                <input v-model="form.color" type="text" class="form-input font-mono" placeholder="#667eea" style="flex:1" />
                <div class="color-swatch" :style="{ backgroundColor: form.color }"></div>
              </div>
              <p class="form-hint">Este color se usa en el totem y en los reportes.</p>
            </div>

            <div class="form-group">
              <label class="toggle-label">
                <div class="toggle-switch" :class="{ 'toggle-on': form.is_active }" @click="form.is_active = !form.is_active">
                  <div class="toggle-thumb"></div>
                </div>
                <div>
                  <span class="toggle-text">Servicio activo</span>
                  <p class="toggle-hint">Los servicios inactivos no se muestran en el totem</p>
                </div>
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn-cancel" @click="closeModal">Cancelar</button>
            <button class="btn-save" :disabled="saving" @click="save">
              <i class="fas fa-check" v-if="!saving"></i>
              <i class="fas fa-spinner fa-spin" v-else></i>
              {{ saving ? 'Guardando...' : 'Guardar Servicio' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getServices, createService, updateService, deleteService } from '../../api'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'

const { showToast } = useToast()
const { showConfirm } = useConfirm()

const services = ref([])
const loading = ref(false)
const showModal = ref(false)
const editingItem = ref(null)
const saving = ref(false)
const formError = ref('')

const defaultForm = () => ({
  name: '',
  code: '',
  description: '',
  color: '#3b82f6',
  is_active: true,
})

const form = ref(defaultForm())

const loadServices = async () => {
  loading.value = true
  try {
    const response = await getServices()
    services.value = response.data
  } catch (error) {
    console.error('Error cargando servicios:', error)
    showToast('Error al cargar servicios', 'error')
  } finally {
    loading.value = false
  }
}

const openModal = (service = null) => {
  formError.value = ''
  if (service) {
    editingItem.value = service
    form.value = {
      name: service.name,
      code: service.code,
      description: service.description || '',
      color: service.color || '#3b82f6',
      is_active: service.is_active !== undefined ? service.is_active : true,
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
  if (!form.value.name || !form.value.code) {
    formError.value = 'Nombre y código son obligatorios'
    return
  }

  saving.value = true
  try {
    if (editingItem.value) {
      await updateService(editingItem.value.id, form.value)
      showToast('Servicio actualizado correctamente')
    } else {
      await createService(form.value)
      showToast('Servicio creado correctamente')
    }
    await loadServices()
    closeModal()
  } catch (error) {
    console.error('Error guardando servicio:', error)
    formError.value = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join(', ')
      : error.response?.data?.message || 'Error al guardar servicio'
  } finally {
    saving.value = false
  }
}

const handleDelete = (service) => {
  showConfirm(
    'Eliminar Servicio',
    `¿Estás seguro de eliminar el servicio "${service.name}"? Esta acción no se puede deshacer.`,
    async () => {
      try {
        await deleteService(service.id)
        await loadServices()
        showToast('Servicio eliminado correctamente')
      } catch (error) {
        console.error('Error eliminando servicio:', error)
        showToast(error.response?.data?.message || 'Error al eliminar servicio', 'error')
      }
    }
  )
}

onMounted(loadServices)
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

/* ── Buttons ─────────────────────────────────────── */
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

/* ── Table card ─────────────────────────────────── */
.table-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

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

.table-row {
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.1s;
}

.table-row:last-child { border-bottom: none; }
.table-row:hover { background: #f8fafc; }

.data-table td { padding: 0.875rem 1.25rem; }

.cell-primary {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  color: #0f172a;
}

.cell-secondary {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  color: #64748b;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.empty-cell {
  padding: 3rem;
  text-align: center;
  color: #94a3b8;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
}

.empty-icon {
  font-size: 2.5rem;
  display: block;
  margin-bottom: 0.75rem;
  color: #cbd5e1;
}

/* ── Badges ─────────────────────────────────────── */
.code-badge {
  font-family: 'JetBrains Mono', 'Courier New', monospace;
  font-size: 0.8rem;
  font-weight: 700;
  background: #f1f5f9;
  color: #334155;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  letter-spacing: 0.05em;
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

/* ── Color preview ───────────────────────────────── */
.color-preview {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 2px solid rgba(0,0,0,0.08);
  position: relative;
}

.color-hex {
  display: none;
  position: absolute;
  left: calc(100% + 8px);
  top: 50%;
  transform: translateY(-50%);
  background: #1e293b;
  color: #fff;
  font-family: monospace;
  font-size: 0.7rem;
  padding: 2px 6px;
  border-radius: 4px;
  white-space: nowrap;
  pointer-events: none;
}

.color-preview:hover .color-hex { display: block; }

/* ── Action buttons ─────────────────────────────── */
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

/* ── Modal ───────────────────────────────────────── */
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
  overflow-x: hidden;
}

.modal-accent {
  height: 5px;
  width: 100%;
  border-radius: 16px 16px 0 0;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
}

.modal-title-group {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

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

/* ── Form ────────────────────────────────────────── */
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }

.form-label {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.required { color: #ef4444; }

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

.form-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

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

/* ── Color picker ────────────────────────────────── */
.color-picker-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.color-input {
  width: 48px;
  height: 42px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 2px;
  cursor: pointer;
  background: transparent;
  flex-shrink: 0;
}

.color-swatch {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  border: 1.5px solid rgba(0,0,0,0.1);
  flex-shrink: 0;
}

/* ── Toggle ─────────────────────────────────────── */
.toggle-label {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  cursor: pointer;
}

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

.toggle-on { background: #3b82f6; }

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

.toggle-hint {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  color: #94a3b8;
  margin: 0.2rem 0 0;
}

/* ── Footer buttons ─────────────────────────────── */
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

.btn-save:hover { background: #2563eb; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.font-mono { font-family: 'JetBrains Mono', 'Courier New', monospace !important; }
.uppercase { text-transform: uppercase; }
</style>
