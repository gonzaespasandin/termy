<template>
  <div class="admin-layout">
    <!-- Sidebar -->
    <AdminSidebar
      :activeTab="activeTab"
      :user="user"
      @tab-change="activeTab = $event"
      @logout="handleLogout"
    />

    <!-- Main area -->
    <div class="admin-main">
      <!-- Content -->
      <main class="admin-content">
        <AdminDashboard v-if="activeTab === 'dashboard'" />
        <AdminHistorial v-else-if="activeTab === 'historial'" />
        <AdminUsuarios v-else-if="activeTab === 'usuarios'" />
        <AdminPerfiles v-else-if="activeTab === 'perfiles'" />
        <AdminVentanillas v-else-if="activeTab === 'ventanillas'" />
        <AdminServicios v-else-if="activeTab === 'servicios'" />
      </main>
    </div>

    <!-- Toast notification (global, singleton) -->
    <Transition name="toast">
      <div v-if="toast.show" class="toast-notification" :class="`toast-${toast.type}`">
        <div class="toast-icon">
          <i v-if="toast.type === 'success'" class="fas fa-check-circle"></i>
          <i v-else-if="toast.type === 'error'" class="fas fa-exclamation-circle"></i>
          <i v-else class="fas fa-info-circle"></i>
        </div>
        <span class="toast-message">{{ toast.message }}</span>
      </div>
    </Transition>

    <!-- Confirm modal (global, singleton) -->
    <Teleport to="body">
      <div v-if="confirmModal.show" class="confirm-backdrop">
        <div class="confirm-dialog">
          <div class="confirm-icon-wrap">
            <div class="confirm-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
          </div>
          <h3 class="confirm-title">{{ confirmModal.title }}</h3>
          <p class="confirm-message">{{ confirmModal.message }}</p>
          <div class="confirm-actions">
            <button class="confirm-btn-cancel" @click="closeConfirm">Cancelar</button>
            <button class="confirm-btn-confirm" @click="confirmModal.onConfirm">
              {{ confirmModal.confirmLabel }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getUser, logout as apiLogout } from '../api'
import { useToast } from '../composables/useToast'
import { useConfirm } from '../composables/useConfirm'

import AdminSidebar from '../components/admin/AdminSidebar.vue'
import AdminDashboard from '../components/admin/AdminDashboard.vue'
import AdminHistorial from '../components/admin/AdminHistorial.vue'
import AdminUsuarios from '../components/admin/AdminUsuarios.vue'
import AdminPerfiles from '../components/admin/AdminPerfiles.vue'
import AdminVentanillas from '../components/admin/AdminVentanillas.vue'
import AdminServicios from '../components/admin/AdminServicios.vue'

const router = useRouter()
const user = ref(null)
const activeTab = ref('dashboard')

const { toast } = useToast()
const { confirmModal, closeConfirm } = useConfirm()

const loadUser = async () => {
  try {
    const response = await getUser()
    user.value = response.data
    if (user.value.role !== 'admin') {
      router.push('/operator')
    }
  } catch (error) {
    console.error('Error cargando usuario:', error)
    router.push('/login')
  }
}

const handleLogout = async () => {
  try {
    await apiLogout()
  } catch (error) {
    console.error('Error en logout:', error)
  } finally {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    router.push('/login')
  }
}

onMounted(loadUser)
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────── */
.admin-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: #f8fafc;
}

.admin-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

.admin-content {
  flex: 1;
  overflow-y: auto;
  padding: 2rem 2.5rem;
}

/* ── Toast ─────────────────────────────────────── */
.toast-notification {
  position: fixed;
  top: 1.5rem;
  right: 1.5rem;
  z-index: 99999;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1.25rem;
  border-radius: 12px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  max-width: 380px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.15);
  pointer-events: none;
}

.toast-success { background: #0f172a; color: #fff; border-left: 4px solid #10b981; }
.toast-error   { background: #0f172a; color: #fff; border-left: 4px solid #ef4444; }
.toast-info    { background: #0f172a; color: #fff; border-left: 4px solid #3b82f6; }

.toast-icon { font-size: 1rem; flex-shrink: 0; }
.toast-success .toast-icon { color: #10b981; }
.toast-error .toast-icon   { color: #ef4444; }
.toast-info .toast-icon    { color: #3b82f6; }

.toast-message { line-height: 1.4; }

/* Toast transitions */
.toast-enter-active { animation: slideInRight 0.3s ease; }
.toast-leave-active { animation: slideOutRight 0.25s ease forwards; }

@keyframes slideInRight {
  from { transform: translateX(100%); opacity: 0; }
  to   { transform: translateX(0);    opacity: 1; }
}

@keyframes slideOutRight {
  from { transform: translateX(0);    opacity: 1; }
  to   { transform: translateX(100%); opacity: 0; }
}

/* ── Confirm dialog ─────────────────────────────── */
.confirm-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99998;
  padding: 1rem;
  backdrop-filter: blur(4px);
}

.confirm-dialog {
  background: #fff;
  border-radius: 20px;
  max-width: 420px;
  width: 100%;
  padding: 2rem 2rem 1.75rem;
  box-shadow: 0 25px 60px rgba(0,0,0,0.2);
  text-align: center;
}

.confirm-icon-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 1.25rem;
}

.confirm-icon {
  width: 56px;
  height: 56px;
  background: #fef3c7;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  color: #d97706;
}

.confirm-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.1rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 0.5rem;
}

.confirm-message {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  color: #64748b;
  margin: 0 0 1.75rem;
  line-height: 1.5;
}

.confirm-actions {
  display: flex;
  gap: 0.75rem;
}

.confirm-btn-cancel {
  flex: 1;
  padding: 0.7rem 1rem;
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

.confirm-btn-cancel:hover { background: #e2e8f0; color: #334155; }

.confirm-btn-confirm {
  flex: 1;
  padding: 0.7rem 1rem;
  background: #ef4444;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.confirm-btn-confirm:hover { background: #dc2626; }
</style>
