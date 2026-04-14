<template>
  <div class="section-content">
    <!-- Header -->
    <div class="section-header">
      <div>
        <h2 class="section-title">Usuarios</h2>
        <p class="section-subtitle">Administradores y operadores del sistema</p>
      </div>
      <button class="btn-primary" @click="openModal()">
        <i class="fas fa-plus"></i>
        Nuevo Usuario
      </button>
    </div>

    <!-- Tabla -->
    <div class="table-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Servicios</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="empty-cell">
              <i class="fas fa-spinner fa-spin"></i>
              Cargando usuarios...
            </td>
          </tr>
          <tr v-else-if="users.length === 0">
            <td colspan="5" class="empty-cell">
              <i class="fas fa-users empty-icon"></i>
              <p>No hay usuarios creados</p>
            </td>
          </tr>
          <tr v-else v-for="usr in users" :key="usr.id" class="table-row">
            <td>
              <div class="user-name-cell">
                <div class="user-avatar" :style="getAvatarStyle(usr.name)">{{ getInitials(usr.name) }}</div>
                <span class="cell-primary">{{ usr.name }}</span>
              </div>
            </td>
            <td class="cell-secondary">{{ usr.email }}</td>
            <td>
              <span class="role-badge" :class="usr.role === 'admin' ? 'role-admin' : 'role-operator'">
                <i :class="usr.role === 'admin' ? 'fas fa-shield-alt' : 'fas fa-headset'"></i>
                {{ usr.role === 'admin' ? 'Administrador' : 'Operador' }}
              </span>
            </td>
            <td>
              <span v-if="!usr.service_names || usr.service_names.length === 0" class="cell-muted">Todos los servicios</span>
              <div v-else class="services-list">
                <span v-for="name in usr.service_names.slice(0,3)" :key="name" class="service-pill">{{ name }}</span>
                <span v-if="usr.service_names.length > 3" class="service-pill service-more">+{{ usr.service_names.length - 3 }}</span>
              </div>
            </td>
            <td>
              <div class="action-buttons">
                <button class="btn-action btn-edit" title="Editar" @click="openModal(usr)">
                  <i class="fas fa-pencil-alt"></i>
                </button>
                <button class="btn-action btn-delete" title="Eliminar" @click="handleDelete(usr)">
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
          <div class="modal-accent" style="background: #8b5cf6;"></div>

          <div class="modal-header">
            <div class="modal-title-group">
              <div class="modal-icon" style="background: rgba(139,92,246,0.1); color: #8b5cf6;">
                <i class="fas fa-user-plus"></i>
              </div>
              <h3 class="modal-title">{{ editingItem ? 'Editar Usuario' : 'Nuevo Usuario' }}</h3>
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
              <label class="form-label">Nombre completo <span class="required">*</span></label>
              <input v-model="form.name" type="text" class="form-input" placeholder="Ej: Juan Pérez" />
            </div>

            <div class="form-group">
              <label class="form-label">Email <span class="required">*</span></label>
              <input v-model="form.email" type="email" class="form-input" placeholder="correo@ejemplo.com" />
            </div>

            <div class="form-group">
              <label class="form-label">
                Contraseña
                <span class="required" v-if="!editingItem">*</span>
                <span class="optional-hint" v-else>(dejar vacío para no cambiar)</span>
              </label>
              <input v-model="form.password" type="password" class="form-input" placeholder="••••••••" />
            </div>

            <div class="form-group">
              <label class="form-label">Rol <span class="required">*</span></label>
              <div class="role-selector">
                <button
                  type="button"
                  class="role-option"
                  :class="{ 'role-option--active role-option--admin': form.role === 'admin' }"
                  @click="form.role = 'admin'"
                >
                  <i class="fas fa-shield-alt"></i>
                  <span>Administrador</span>
                </button>
                <button
                  type="button"
                  class="role-option"
                  :class="{ 'role-option--active role-option--operator': form.role === 'operator' }"
                  @click="form.role = 'operator'"
                >
                  <i class="fas fa-headset"></i>
                  <span>Operador</span>
                </button>
              </div>
            </div>

            <div v-if="form.role === 'operator'" class="form-group">
              <label class="form-label">Servicios asignados</label>
              <p class="form-hint mb-2">Si no seleccionás ninguno, el operador verá todos los servicios.</p>
              <div v-if="servicesLoading" class="loading-services">
                <i class="fas fa-spinner fa-spin"></i> Cargando servicios...
              </div>
              <div v-else class="services-checkboxes">
                <label v-for="service in allServices" :key="service.id" class="service-checkbox">
                  <input v-model="form.services" :value="service.id" type="checkbox" class="checkbox-input" />
                  <div class="service-dot" :style="{ backgroundColor: service.color }"></div>
                  <span>{{ service.name }}</span>
                </label>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn-cancel" @click="closeModal">Cancelar</button>
            <button class="btn-save btn-save--purple" :disabled="saving" @click="save">
              <i class="fas fa-check" v-if="!saving"></i>
              <i class="fas fa-spinner fa-spin" v-else></i>
              {{ saving ? 'Guardando...' : 'Guardar Usuario' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getUsers, createUser, updateUser, deleteUser, getServices } from '../../api'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'

const { showToast } = useToast()
const { showConfirm } = useConfirm()

const users = ref([])
const loading = ref(false)
const showModal = ref(false)
const editingItem = ref(null)
const saving = ref(false)
const formError = ref('')
const allServices = ref([])
const servicesLoading = ref(false)

const defaultForm = () => ({
  name: '',
  email: '',
  password: '',
  role: 'operator',
  services: [],
})

const form = ref(defaultForm())

const getInitials = (name) => {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}

const avatarColors = ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#06b6d4']
const getAvatarStyle = (name) => {
  const idx = (name?.charCodeAt(0) || 0) % avatarColors.length
  return { background: `linear-gradient(135deg, ${avatarColors[idx]}, ${avatarColors[(idx + 2) % avatarColors.length]})` }
}

const loadUsers = async () => {
  loading.value = true
  try {
    const response = await getUsers()
    users.value = response.data
  } catch (error) {
    console.error('Error cargando usuarios:', error)
    showToast('Error al cargar usuarios', 'error')
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

const openModal = async (usr = null) => {
  formError.value = ''
  await loadServices()

  if (usr) {
    editingItem.value = usr
    form.value = {
      name: usr.name,
      email: usr.email,
      password: '',
      role: usr.role,
      services: Array.isArray(usr.services) ? usr.services : [],
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
  if (!form.value.name || !form.value.email) {
    formError.value = 'Nombre y email son obligatorios'
    return
  }
  if (!editingItem.value && !form.value.password) {
    formError.value = 'La contraseña es obligatoria para nuevos usuarios'
    return
  }

  saving.value = true
  try {
    const data = { ...form.value }
    if (data.role === 'admin') data.services = []
    if (editingItem.value && !data.password) delete data.password

    if (editingItem.value) {
      await updateUser(editingItem.value.id, data)
      showToast('Usuario actualizado correctamente')
    } else {
      await createUser(data)
      showToast('Usuario creado correctamente')
    }
    await loadUsers()
    closeModal()
  } catch (error) {
    console.error('Error guardando usuario:', error)
    formError.value = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join(', ')
      : error.response?.data?.message || 'Error al guardar usuario'
  } finally {
    saving.value = false
  }
}

const handleDelete = (usr) => {
  showConfirm(
    'Eliminar Usuario',
    `¿Estás seguro de eliminar al usuario "${usr.name}"?`,
    async () => {
      try {
        await deleteUser(usr.id)
        await loadUsers()
        showToast('Usuario eliminado correctamente')
      } catch (error) {
        console.error('Error eliminando usuario:', error)
        showToast(error.response?.data?.message || 'Error al eliminar usuario', 'error')
      }
    }
  )
}

onMounted(loadUsers)
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
  background: #8b5cf6;
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

.btn-primary:hover { background: #7c3aed; }
.btn-primary:active { transform: scale(0.97); }

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

.table-row {
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.1s;
}

.table-row:last-child { border-bottom: none; }
.table-row:hover { background: #f8fafc; }
.data-table td { padding: 0.875rem 1.25rem; vertical-align: middle; }

.user-name-cell { display: flex; align-items: center; gap: 0.75rem; }

.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.7rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

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

.empty-icon {
  font-size: 2.5rem;
  display: block;
  margin-bottom: 0.75rem;
  color: #cbd5e1;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  font-weight: 600;
}

.role-admin { background: #f3e8ff; color: #7c3aed; }
.role-operator { background: #eff6ff; color: #2563eb; }

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

.form-label {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.required { color: #ef4444; }
.optional-hint {
  font-size: 0.7rem;
  color: #94a3b8;
  font-weight: 400;
  text-transform: none;
  margin-left: 0.25rem;
}

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

.form-input:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,0.1); }

.form-hint {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  color: #94a3b8;
  margin: 0;
}

.mb-2 { margin-bottom: 0.5rem; }

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

.role-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

.role-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  padding: 0.875rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
  transition: all 0.15s;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  color: #64748b;
}

.role-option:hover { background: #f8fafc; }
.role-option--active { border-width: 2px; }
.role-option--admin { border-color: #8b5cf6; background: #faf5ff; color: #7c3aed; }
.role-option--operator { border-color: #3b82f6; background: #eff6ff; color: #2563eb; }

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
  max-height: 200px;
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

.checkbox-input {
  width: 16px;
  height: 16px;
  accent-color: #8b5cf6;
  cursor: pointer;
  flex-shrink: 0;
}

.service-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

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

.btn-save--purple { background: #8b5cf6; }
.btn-save--purple:hover { background: #7c3aed; }
.btn-save:hover { background: #2563eb; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
