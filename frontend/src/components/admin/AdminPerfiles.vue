<template>
  <div class="section-content">
    <!-- Header -->
    <div class="section-header">
      <div>
        <h2 class="section-title">Perfiles de Totem</h2>
        <p class="section-subtitle">Conjuntos de servicios para cada totem físico</p>
      </div>
      <button class="btn-primary" @click="openModal()">
        <i class="fas fa-plus"></i>
        Nuevo Perfil
      </button>
    </div>

    <!-- Info box -->
    <div class="info-box">
      <i class="fas fa-info-circle info-icon"></i>
      <div>
        <p>Un <strong>perfil</strong> es un conjunto de servicios asignado a un totem físico.
        Ponés el <strong>código</strong> del perfil en el archivo <code>.env</code> del totem
        y mostrará solo esos servicios con la identidad visual configurada.</p>
      </div>
    </div>

    <!-- Tabla -->
    <div class="table-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Ubicación</th>
            <th>Servicios</th>
            <th>Pide nombre</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="empty-cell">
              <i class="fas fa-spinner fa-spin"></i>
              Cargando perfiles...
            </td>
          </tr>
          <tr v-else-if="profiles.length === 0">
            <td colspan="7" class="empty-cell">
              <i class="fas fa-layer-group empty-icon"></i>
              <p>No hay perfiles creados</p>
            </td>
          </tr>
          <tr v-else v-for="profile in profiles" :key="profile.id" class="table-row">
            <td>
              <span class="code-badge">{{ profile.code }}</span>
            </td>
            <td>
              <div class="profile-name-cell">
                <div class="profile-color-bar" :style="getBackgroundStyle(profile)"></div>
                <span class="cell-primary">{{ profile.name }}</span>
              </div>
            </td>
            <td class="cell-secondary">{{ profile.location || '—' }}</td>
            <td>
              <span v-if="!profile.service_names || profile.service_names.length === 0" class="cell-muted">Sin servicios</span>
              <div v-else class="services-list">
                <span v-for="name in profile.service_names.slice(0, 3)" :key="name" class="service-pill">{{ name }}</span>
                <span v-if="profile.service_names.length > 3" class="service-pill service-more">+{{ profile.service_names.length - 3 }}</span>
              </div>
            </td>
            <td>
              <span class="status-badge" :class="profile.ask_customer_name !== false ? 'status-yes' : 'status-no'">
                {{ profile.ask_customer_name !== false ? 'Sí' : 'No' }}
              </span>
            </td>
            <td>
              <span class="status-badge" :class="profile.is_active ? 'status-active' : 'status-inactive'">
                {{ profile.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button class="btn-action btn-edit" title="Editar" @click="openModal(profile)">
                  <i class="fas fa-pencil-alt"></i>
                </button>
                <button class="btn-action btn-delete" title="Eliminar" @click="handleDelete(profile)">
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
        <div class="modal-container" ref="modalContainer">
          <div class="modal-accent" style="background: #f59e0b;"></div>

          <div class="modal-header">
            <div class="modal-title-group">
              <div class="modal-icon" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                <i class="fas fa-layer-group"></i>
              </div>
              <h3 class="modal-title">{{ editingItem ? 'Editar Perfil' : 'Nuevo Perfil' }}</h3>
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

            <!-- Sección: Información básica -->
            <div class="form-section">
              <h4 class="form-section-title">
                <i class="fas fa-id-card"></i>
                Información del perfil
              </h4>

              <div class="form-group">
                <label class="form-label">Nombre <span class="required">*</span></label>
                <input v-model="form.name" type="text" class="form-input" placeholder="Ej: Principal, Pediatría, Laboratorio" />
              </div>

              <div class="form-group">
                <label class="form-label">Código <span class="required">*</span></label>
                <input
                  v-model="form.code"
                  type="text"
                  maxlength="50"
                  class="form-input font-mono uppercase"
                  placeholder="Ej: TOTEM-PRINCIPAL"
                  @input="form.code = form.code.toUpperCase()"
                />
                <p class="form-hint">Este código se coloca en el <code>.env</code> del totem físico.</p>
              </div>

              <div class="form-group">
                <label class="form-label">Ubicación <span class="optional-hint">(opcional)</span></label>
                <input v-model="form.location" type="text" class="form-input" placeholder="Ej: Recepción principal, Piso 2" />
              </div>
            </div>

            <!-- Sección: Servicios -->
            <div class="form-section">
              <h4 class="form-section-title">
                <i class="fas fa-clipboard-list"></i>
                Servicios a mostrar <span class="required">*</span>
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

            <!-- Sección: Comportamiento -->
            <div class="form-section">
              <h4 class="form-section-title">
                <i class="fas fa-sliders-h"></i>
                Comportamiento
              </h4>

              <div class="form-group">
                <label class="toggle-label">
                  <div class="toggle-switch" :class="{ 'toggle-on': form.is_active }" @click="form.is_active = !form.is_active">
                    <div class="toggle-thumb"></div>
                  </div>
                  <div>
                    <span class="toggle-text">Perfil activo</span>
                    <p class="toggle-hint">Los perfiles inactivos no son accesibles desde el totem</p>
                  </div>
                </label>
              </div>

              <div class="form-group">
                <label class="toggle-label">
                  <div class="toggle-switch" :class="{ 'toggle-on': form.ask_customer_name }" @click="form.ask_customer_name = !form.ask_customer_name" style="--toggle-color: #f59e0b;">
                    <div class="toggle-thumb"></div>
                  </div>
                  <div>
                    <span class="toggle-text">Pedir nombre del cliente</span>
                    <p class="toggle-hint">Si está desactivado, el ticket se imprime directamente sin solicitar nombre</p>
                  </div>
                </label>
              </div>
            </div>

            <!-- Sección: Personalización -->
            <div class="form-section">
              <h4 class="form-section-title">
                <i class="fas fa-palette"></i>
                Identidad visual
              </h4>

              <!-- Logo -->
              <div class="form-group">
                <label class="form-label">Logo de la empresa</label>
                <div v-if="form.logoPreview || form.logo" class="logo-preview-container">
                  <img :src="form.logoPreview || getLogoUrl(form.logo)" alt="Logo" class="logo-preview" />
                  <button type="button" class="remove-logo-btn" @click="removeLogo">
                    <i class="fas fa-times"></i>
                    Quitar logo
                  </button>
                </div>
                <label class="file-upload-area">
                  <input
                    type="file"
                    @change="handleLogoUpload"
                    accept="image/jpeg,image/png,image/webp,image/jpg"
                    class="file-input-hidden"
                  />
                  <i class="fas fa-cloud-upload-alt upload-icon"></i>
                  <span>Subir imagen</span>
                  <span class="upload-hint">JPG, PNG, WEBP · máx. 2MB</span>
                </label>
                <p class="form-hint">Medidas recomendadas: 400×200px (relación 2:1). Preferible PNG con fondo transparente.</p>
              </div>

              <!-- Tipo de fondo -->
              <div class="form-group">
                <label class="form-label">Tipo de fondo</label>
                <div class="bg-type-selector">
                  <button
                    type="button"
                    class="bg-type-option"
                    :class="{ 'bg-type-option--active': form.background_type === 'gradient' }"
                    @click="form.background_type = 'gradient'"
                  >
                    <i class="fas fa-fill-drip"></i>
                    Gradiente
                  </button>
                  <button
                    type="button"
                    class="bg-type-option"
                    :class="{ 'bg-type-option--active': form.background_type === 'solid' }"
                    @click="form.background_type = 'solid'"
                  >
                    <i class="fas fa-square"></i>
                    Color sólido
                  </button>
                </div>
              </div>

              <!-- Colores -->
              <div class="form-grid-2">
                <div class="form-group">
                  <label class="form-label">
                    {{ form.background_type === 'gradient' ? 'Color inicial' : 'Color de fondo' }}
                  </label>
                  <div class="color-picker-row">
                    <input v-model="form.background_color_1" type="color" class="color-input" />
                    <input v-model="form.background_color_1" type="text" class="form-input font-mono" style="flex:1" />
                  </div>
                </div>
                <div class="form-group" v-if="form.background_type === 'gradient'">
                  <label class="form-label">Color final</label>
                  <div class="color-picker-row">
                    <input v-model="form.background_color_2" type="color" class="color-input" />
                    <input v-model="form.background_color_2" type="text" class="form-input font-mono" style="flex:1" />
                  </div>
                </div>
              </div>

              <!-- Preview -->
              <div class="form-group">
                <label class="form-label">Vista previa del fondo</label>
                <div class="bg-preview" :style="getBackgroundStyle(form)">
                  <div class="bg-preview-overlay">
                    <img v-if="form.logoPreview || form.logo" :src="form.logoPreview || getLogoUrl(form.logo)" alt="Logo preview" class="bg-preview-logo" />
                    <p v-else class="bg-preview-placeholder">Vista previa</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn-cancel" @click="closeModal">Cancelar</button>
            <button class="btn-save btn-save--amber" :disabled="saving" @click="save">
              <i class="fas fa-check" v-if="!saving"></i>
              <i class="fas fa-spinner fa-spin" v-else></i>
              {{ saving ? 'Guardando...' : 'Guardar Perfil' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getTotems, createTotem, updateTotem, deleteTotem, getServices } from '../../api'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'

const { showToast } = useToast()
const { showConfirm } = useConfirm()

const profiles = ref([])
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
  code: '',
  location: '',
  services: [],
  is_active: true,
  ask_customer_name: true,
  logo: null,
  logoFile: null,
  logoPreview: null,
  background_type: 'gradient',
  background_color_1: '#667eea',
  background_color_2: '#764ba2',
})

const form = ref(defaultForm())

const getLogoUrl = (logoPath) => {
  if (!logoPath) return ''
  return `http://192.168.0.51:8000/storage/${logoPath}`
}

const getBackgroundStyle = (data) => {
  if (data.background_type === 'gradient') {
    return { background: `linear-gradient(135deg, ${data.background_color_1} 0%, ${data.background_color_2} 100%)` }
  }
  return { backgroundColor: data.background_color_1 }
}

const scrollModalToTop = () => {
  setTimeout(() => {
    if (modalContainer.value) {
      modalContainer.value.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }, 100)
}

const loadProfiles = async () => {
  loading.value = true
  try {
    const response = await getTotems()
    profiles.value = response.data
  } catch (error) {
    console.error('Error cargando perfiles:', error)
    showToast('Error al cargar perfiles', 'error')
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

const handleLogoUpload = (event) => {
  const file = event.target.files[0]
  if (!file) return

  if (file.size > 2 * 1024 * 1024) {
    formError.value = 'El logo no debe superar los 2MB'
    scrollModalToTop()
    return
  }

  form.value.logoFile = file
  const reader = new FileReader()
  reader.onload = (e) => { form.value.logoPreview = e.target.result }
  reader.readAsDataURL(file)
}

const removeLogo = () => {
  form.value.logo = null
  form.value.logoFile = null
  form.value.logoPreview = null
}

const openModal = async (profile = null) => {
  formError.value = ''
  await loadServices()

  if (profile) {
    editingItem.value = profile
    form.value = {
      name: profile.name,
      code: profile.code,
      location: profile.location || '',
      services: Array.isArray(profile.services) ? profile.services : [],
      is_active: profile.is_active !== undefined ? profile.is_active : true,
      ask_customer_name: profile.ask_customer_name !== undefined ? profile.ask_customer_name : true,
      logo: profile.logo || null,
      logoFile: null,
      logoPreview: null,
      background_type: profile.background_type || 'gradient',
      background_color_1: profile.background_color_1 || '#667eea',
      background_color_2: profile.background_color_2 || '#764ba2',
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
    if (form.value.logoFile) {
      const formData = new FormData()
      formData.append('name', form.value.name)
      formData.append('code', form.value.code)
      formData.append('location', form.value.location || '')
      form.value.services.forEach((id) => formData.append('services[]', id))
      formData.append('is_active', form.value.is_active ? '1' : '0')
      formData.append('ask_customer_name', form.value.ask_customer_name ? '1' : '0')
      formData.append('logo', form.value.logoFile)
      formData.append('background_type', form.value.background_type)
      formData.append('background_color_1', form.value.background_color_1)
      if (form.value.background_type === 'gradient') {
        formData.append('background_color_2', form.value.background_color_2)
      }

      if (editingItem.value) {
        formData.append('_method', 'PUT')
        await updateTotem(editingItem.value.id, formData)
      } else {
        await createTotem(formData)
      }
    } else {
      const data = {
        name: form.value.name,
        code: form.value.code,
        location: form.value.location || '',
        services: form.value.services,
        is_active: form.value.is_active,
        ask_customer_name: form.value.ask_customer_name,
        background_type: form.value.background_type,
        background_color_1: form.value.background_color_1,
      }
      if (form.value.background_type === 'gradient') {
        data.background_color_2 = form.value.background_color_2
      }

      if (editingItem.value) {
        await updateTotem(editingItem.value.id, data)
      } else {
        await createTotem(data)
      }
    }

    showToast('Perfil guardado correctamente')
    await loadProfiles()
    closeModal()
  } catch (error) {
    console.error('Error guardando perfil:', error)
    formError.value = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join(', ')
      : error.response?.data?.message || 'Error al guardar perfil'
    scrollModalToTop()
  } finally {
    saving.value = false
  }
}

const handleDelete = (profile) => {
  showConfirm(
    'Eliminar Perfil',
    `¿Estás seguro de eliminar el perfil "${profile.name}"? Esta acción no se puede deshacer.`,
    async () => {
      try {
        await deleteTotem(profile.id)
        await loadProfiles()
        showToast('Perfil eliminado correctamente')
      } catch (error) {
        console.error('Error eliminando perfil:', error)
        showToast(error.response?.data?.message || 'Error al eliminar perfil', 'error')
      }
    }
  )
}

onMounted(loadProfiles)
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
  background: #f59e0b;
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

.btn-primary:hover { background: #d97706; }
.btn-primary:active { transform: scale(0.97); }

.info-box {
  display: flex;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 12px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  color: #92400e;
  line-height: 1.5;
}

.info-icon { color: #d97706; font-size: 1rem; margin-top: 0.1rem; flex-shrink: 0; }
.info-box code { background: #fef3c7; padding: 0.1rem 0.4rem; border-radius: 4px; font-family: monospace; font-size: 0.8rem; }

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

.cell-primary {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  color: #0f172a;
}

.cell-secondary { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.85rem; color: #64748b; }
.cell-muted { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.8rem; color: #94a3b8; font-style: italic; }

.empty-cell { padding: 3rem; text-align: center; color: #94a3b8; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.9rem; }
.empty-icon { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; color: #cbd5e1; }

.profile-name-cell { display: flex; align-items: center; gap: 0.75rem; }
.profile-color-bar { width: 4px; height: 32px; border-radius: 2px; flex-shrink: 0; }

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
.status-yes { background: #dbeafe; color: #2563eb; }
.status-no { background: #f1f5f9; color: #64748b; }

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
  max-width: 600px;
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
  position: sticky;
  top: 0;
  background: #fff;
  z-index: 1;
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

.modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 0; }

.modal-footer {
  display: flex;
  gap: 0.75rem;
  padding: 1.25rem 1.5rem;
  border-top: 1px solid #f1f5f9;
  justify-content: flex-end;
  position: sticky;
  bottom: 0;
  background: #fff;
}

/* Form sections */
.form-section {
  padding: 1.25rem 0;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-section:last-child { border-bottom: none; }

.form-section-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
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

.form-input:focus { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.1); }

.form-hint { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.75rem; color: #94a3b8; margin: 0; }
.form-hint code { background: #f1f5f9; padding: 0.1rem 0.3rem; border-radius: 4px; font-family: monospace; }

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
  margin-bottom: 0.5rem;
}

/* Services checkboxes */
.loading-services { padding: 0.75rem; color: #94a3b8; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.85rem; }
.no-services-msg { padding: 0.75rem; color: #94a3b8; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.85rem; font-style: italic; }

.services-checkboxes {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
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
.checkbox-input { width: 16px; height: 16px; accent-color: #f59e0b; cursor: pointer; flex-shrink: 0; }
.service-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.service-code { margin-left: auto; font-family: monospace; font-size: 0.75rem; color: #94a3b8; }

/* Toggle */
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

.toggle-on { background: #f59e0b; }

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
.toggle-text { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.875rem; font-weight: 600; color: #0f172a; display: block; }
.toggle-hint { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.75rem; color: #94a3b8; margin: 0.2rem 0 0; }

/* Logo upload */
.logo-preview-container {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
}

.logo-preview { height: 48px; max-width: 120px; object-fit: contain; border-radius: 6px; }

.remove-logo-btn {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.75rem;
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  border-radius: 8px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.remove-logo-btn:hover { background: #fee2e2; }

.file-upload-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  padding: 1.25rem;
  border: 2px dashed #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  color: #475569;
  text-align: center;
}

.file-upload-area:hover { border-color: #f59e0b; background: #fffbeb; }
.file-input-hidden { display: none; }

.upload-icon { font-size: 1.5rem; color: #cbd5e1; }
.upload-hint { font-size: 0.75rem; color: #94a3b8; font-weight: 400; }

/* BG type selector */
.bg-type-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

.bg-type-option {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.625rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  transition: all 0.15s;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  color: #64748b;
}

.bg-type-option--active { border-color: #f59e0b; background: #fffbeb; color: #d97706; }

/* Color picker */
.color-picker-row { display: flex; align-items: center; gap: 0.5rem; }

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

/* BG preview */
.bg-preview {
  height: 100px;
  border-radius: 12px;
  border: 1px solid rgba(0,0,0,0.1);
  overflow: hidden;
  position: relative;
}

.bg-preview-overlay {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bg-preview-logo { max-height: 60px; max-width: 140px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); }
.bg-preview-placeholder { color: rgba(255,255,255,0.5); font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.85rem; }

/* Footer buttons */
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

.btn-save--amber { background: #f59e0b; }
.btn-save--amber:hover { background: #d97706; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.font-mono { font-family: 'JetBrains Mono', 'Courier New', monospace !important; }
.uppercase { text-transform: uppercase; }
</style>
