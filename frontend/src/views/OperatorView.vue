<template>
  <div class="operator-layout">
    <!-- Header -->
    <OperatorHeader
      :user="user"
      :assignedWindow="assignedWindow"
      @logout="handleLogout"
    />

    <!-- Body -->
    <div class="operator-body" :class="{ 'operator-body--error': windowError && !assignedWindow }">
      <!-- No window assigned error -->
      <OperatorNoWindow
        v-if="windowError && !assignedWindow"
        :error="windowError"
      />

      <!-- Working area -->
      <template v-else-if="assignedWindow">
        <OperatorCurrentTurn
          :currentTurn="currentTurn"
          :loading="false"
          @call-next="callNext"
          @start-attention="startAttention"
          @complete="completeTurn"
          @cancel="cancelTurn"
          @no-show="markNoShow"
          @return-to-queue="returnToQueue"
        />
        <OperatorQueue
          :turns="waitingTurns"
          :currentTurn="currentTurn"
          @call-specific="callSpecific"
          @refresh="loadQueue"
        />
      </template>
    </div>

    <!-- Toast notification (singleton) -->
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

    <!-- Confirm modal (singleton) -->
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
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { getOperatorQueue, getUser, logout as apiLogout, getWindowByIp } from '../api'
import api from '../api'
import { useToast } from '../composables/useToast'
import { useConfirm } from '../composables/useConfirm'

import OperatorHeader from '../components/operator/OperatorHeader.vue'
import OperatorCurrentTurn from '../components/operator/OperatorCurrentTurn.vue'
import OperatorQueue from '../components/operator/OperatorQueue.vue'
import OperatorNoWindow from '../components/operator/OperatorNoWindow.vue'

const router = useRouter()
const user = ref(null)
const assignedWindow = ref(null)
const windowError = ref('')
const currentTurn = ref(null)
const waitingTurns = ref([])
const USE_SSE = false
let intervalId = null
let eventSource = null

const { toast, showToast } = useToast()
const { confirmModal, showConfirm, closeConfirm } = useConfirm()

// ── Queue loading ──────────────────────────────────────────────
const loadQueue = async () => {
  if (!assignedWindow.value) return

  try {
    const response = await getOperatorQueue()
    waitingTurns.value = response.data.waiting || []

    if (response.data.my_turn) {
      currentTurn.value = response.data.my_turn
    } else {
      currentTurn.value = null
    }
  } catch (error) {
    console.error('Error cargando cola:', error)
  }
}

// ── Turn actions ───────────────────────────────────────────────
const callNext = async () => {
  if (!assignedWindow.value) {
    showToast('No tienes ventanilla asignada. No puedes llamar turnos.', 'error')
    return
  }
  if (currentTurn.value) {
    showToast('Ya tienes un turno activo. Complétalo o cancélalo primero.', 'error')
    return
  }
  if (waitingTurns.value.length === 0) {
    showToast('No hay turnos en espera', 'error')
    return
  }

  const sortedTurns = [...waitingTurns.value].sort((a, b) => {
    if (a.priority === 'priority' && b.priority !== 'priority') return -1
    if (a.priority !== 'priority' && b.priority === 'priority') return 1
    return new Date(a.created_at) - new Date(b.created_at)
  })

  await callSpecific(sortedTurns[0])
}

const callSpecific = async (turn) => {
  if (!assignedWindow.value) {
    showToast('No tienes ventanilla asignada. No puedes llamar turnos.', 'error')
    return
  }
  if (currentTurn.value) {
    showToast('Ya tienes un turno activo. Complétalo o cancélalo primero.', 'error')
    return
  }

  try {
    const response = await api.post(`/turns/${turn.id}/call`, {
      window_id: assignedWindow.value.id,
    })
    currentTurn.value = response.data
    await loadQueue()
  } catch (error) {
    console.error('Error llamando turno:', error)
    showToast(error.response?.data?.message || 'Error al llamar turno', 'error')
  }
}

const startAttention = async () => {
  try {
    const response = await api.post(`/turns/${currentTurn.value.id}/attend`)
    currentTurn.value = response.data
    showToast('Atención iniciada')
  } catch (error) {
    console.error('Error iniciando atención:', error)
    showToast('Error al iniciar atención', 'error')
  }
}

const completeTurn = async () => {
  try {
    await api.post(`/turns/${currentTurn.value.id}/complete`)
    currentTurn.value = null
    await loadQueue()
    showToast('Turno completado')
  } catch (error) {
    console.error('Error completando turno:', error)
    showToast('Error al completar turno', 'error')
  }
}

const cancelTurn = () => {
  showConfirm(
    'Cancelar Turno',
    '¿Está seguro de cancelar este turno?',
    async () => {
      try {
        await api.post(`/turns/${currentTurn.value.id}/cancel`)
        currentTurn.value = null
        await loadQueue()
        showToast('Turno cancelado')
      } catch (error) {
        console.error('Error cancelando turno:', error)
        showToast('Error al cancelar turno', 'error')
      }
    },
    'Confirmar'
  )
}

const markNoShow = () => {
  showConfirm(
    'Cliente no se presentó',
    '¿Confirma que el cliente no se presentó?',
    async () => {
      try {
        await api.post(`/turns/${currentTurn.value.id}/no-show`)
        currentTurn.value = null
        await loadQueue()
        showToast('Turno marcado como no presentado')
      } catch (error) {
        console.error('Error marcando no show:', error)
        showToast('Error al marcar turno', 'error')
      }
    },
    'Confirmar'
  )
}

const returnToQueue = () => {
  showConfirm(
    'Devolver a cola',
    '¿Desea devolver este turno a la cola de espera?',
    async () => {
      try {
        await api.post(`/turns/${currentTurn.value.id}/return-to-queue`)
        currentTurn.value = null
        await loadQueue()
        showToast('Turno devuelto a la cola')
      } catch (error) {
        console.error('Error devolviendo turno:', error)
        showToast('Error al devolver turno', 'error')
      }
    },
    'Confirmar'
  )
}

// ── User & window loading ──────────────────────────────────────
const loadUser = async () => {
  try {
    const response = await getUser()
    user.value = response.data
  } catch (error) {
    console.error('Error cargando usuario:', error)
    router.push('/login')
  }
}

const loadWindow = async () => {
  try {
    const response = await getWindowByIp()
    assignedWindow.value = response.data
    windowError.value = ''
  } catch (error) {
    console.error('Error cargando ventanilla:', error)
    windowError.value = error.response?.data?.error || 'Error al cargar ventanilla'
    showToast(
      `No hay ventanilla asignada a esta PC. IP: ${error.response?.data?.ip || 'desconocida'}`,
      'error'
    )
  }
}

// ── SSE (disabled by default) ──────────────────────────────────
const connectSSE = () => {
  const token = localStorage.getItem('token')
  if (!token) return
  if (eventSource) eventSource.close()

  eventSource = new EventSource(`http://192.168.0.51:8000/api/sse/operator?token=${token}`)

  eventSource.onmessage = (event) => {
    try {
      const data = JSON.parse(event.data)
      waitingTurns.value = data.waiting || []

      if (data.in_progress && data.in_progress.length > 0) {
        currentTurn.value = data.in_progress[0]
      } else if (currentTurn.value && currentTurn.value.status === 'completed') {
        currentTurn.value = null
      }
    } catch (error) {
      console.error('Error parseando SSE:', error)
    }
  }

  eventSource.onerror = () => {
    eventSource.close()
    eventSource = null
    intervalId = setInterval(loadQueue, 5000)
  }
}

// ── Logout ─────────────────────────────────────────────────────
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

// ── Lifecycle ──────────────────────────────────────────────────
onMounted(async () => {
  await loadUser()
  await loadWindow()
  loadQueue()

  if (USE_SSE) {
    connectSSE()
  } else {
    intervalId = setInterval(loadQueue, 5000)
  }
})

onUnmounted(() => {
  if (eventSource) eventSource.close()
  if (intervalId) clearInterval(intervalId)
})
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────────── */
.operator-layout {
  display: flex;
  flex-direction: column;
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  background: #f8fafc;
  font-family: 'Plus Jakarta Sans', sans-serif;
}

.operator-body {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  padding: 1.5rem;
  overflow: hidden;
}

.operator-body--error {
  display: flex;
}

/* ── Toast ─────────────────────────────────────────────────── */
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
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
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

/* ── Confirm dialog ─────────────────────────────────────────── */
.confirm-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
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
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
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

.confirm-btn-cancel:hover {
  background: #e2e8f0;
  color: #334155;
}

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

.confirm-btn-confirm:hover {
  background: #dc2626;
}
</style>
