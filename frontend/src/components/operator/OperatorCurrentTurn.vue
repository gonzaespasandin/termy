<template>
  <div class="op-current-turn">
    <!-- Section title -->
    <div class="section-header">
      <span class="section-title">Turno Actual</span>
      <div v-if="currentTurn" class="section-window-hint">
        <i class="fas fa-circle" :class="currentTurn.status === 'in_progress' ? 'dot-green' : 'dot-yellow'"></i>
      </div>
    </div>

    <!-- State transitions wrapper -->
    <Transition name="turn-state" mode="out-in">

      <!-- STATE A: Idle — no active turn -->
      <div v-if="!currentTurn" key="idle" class="state-idle">
        <div class="idle-icon-ring">
          <i class="fas fa-ticket-alt"></i>
        </div>
        <p class="idle-title">Sin turno activo</p>
        <p class="idle-sub">Llamá el próximo turno para comenzar</p>
        <button
          class="btn-call-next"
          :disabled="loading"
          @click="$emit('call-next')"
        >
          <i class="fas fa-bell"></i>
          Llamar Siguiente Turno
        </button>
      </div>

      <!-- STATE B: Called -->
      <div v-else-if="currentTurn.status === 'called'" key="called" class="state-called">
        <div class="status-pill status-pill--called">
          <i class="fas fa-bullhorn"></i>
          LLAMADO
        </div>

        <div class="ticket-number" :style="{ color: currentTurn.service.color }">
          {{ currentTurn.ticket_number }}
        </div>

        <div
          class="service-badge"
          :style="{
            background: currentTurn.service.color + '20',
            color: currentTurn.service.color,
            borderColor: currentTurn.service.color + '40'
          }"
        >
          {{ currentTurn.service.name }}
        </div>

        <div v-if="currentTurn.customer_name" class="customer-row">
          <i class="fas fa-user"></i>
          <span>{{ currentTurn.customer_name }}</span>
        </div>

        <div class="timestamps-grid">
          <div class="ts-item">
            <span class="ts-label">Emitido</span>
            <span class="ts-value">{{ formatTime(currentTurn.issued_at) }}</span>
          </div>
          <div class="ts-item">
            <span class="ts-label">Llamado</span>
            <span class="ts-value ts-value--blue">{{ formatTime(currentTurn.called_at) }}</span>
          </div>
        </div>

        <button class="btn-start" @click="$emit('start-attention')">
          <i class="fas fa-play"></i>
          Iniciar Atención
        </button>

        <div class="btn-row-secondary">
          <button class="btn-noshow" @click="$emit('no-show')">
            <i class="fas fa-user-slash"></i>
            No se presentó
          </button>
          <button class="btn-return" @click="$emit('return-to-queue')">
            <i class="fas fa-undo"></i>
            Devolver a cola
          </button>
        </div>
      </div>

      <!-- STATE C: In Progress -->
      <div v-else-if="currentTurn.status === 'in_progress'" key="inprogress" class="state-inprogress">
        <div class="status-pill status-pill--inprogress">
          <span class="pulse-dot"></span>
          EN ATENCIÓN
        </div>

        <div class="ticket-number" :style="{ color: currentTurn.service.color }">
          {{ currentTurn.ticket_number }}
        </div>

        <div
          class="service-badge"
          :style="{
            background: currentTurn.service.color + '20',
            color: currentTurn.service.color,
            borderColor: currentTurn.service.color + '40'
          }"
        >
          {{ currentTurn.service.name }}
        </div>

        <div v-if="currentTurn.customer_name" class="customer-row">
          <i class="fas fa-user"></i>
          <span>{{ currentTurn.customer_name }}</span>
        </div>

        <div class="elapsed-time">
          <i class="fas fa-stopwatch"></i>
          <span>{{ elapsedLabel }}</span>
        </div>

        <div class="timestamps-grid">
          <div class="ts-item">
            <span class="ts-label">Emitido</span>
            <span class="ts-value">{{ formatTime(currentTurn.issued_at) }}</span>
          </div>
          <div class="ts-item">
            <span class="ts-label">Llamado</span>
            <span class="ts-value ts-value--blue">{{ formatTime(currentTurn.called_at) }}</span>
          </div>
          <div class="ts-item">
            <span class="ts-label">Atención</span>
            <span class="ts-value ts-value--green">{{ formatTime(currentTurn.attended_at) }}</span>
          </div>
        </div>

        <div class="btn-row-primary">
          <button class="btn-complete" @click="$emit('complete')">
            <i class="fas fa-check"></i>
            Completar
          </button>
          <button class="btn-cancel" @click="$emit('cancel')">
            <i class="fas fa-times"></i>
            Cancelar
          </button>
        </div>
      </div>

    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  currentTurn: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['call-next', 'start-attention', 'complete', 'cancel', 'no-show', 'return-to-queue'])

// ── Elapsed time counter ──────────────────────────────────────
const elapsedSeconds = ref(0)
let elapsedInterval = null

const startElapsedTimer = (attendedAt) => {
  if (elapsedInterval) clearInterval(elapsedInterval)
  if (!attendedAt) {
    elapsedSeconds.value = 0
    return
  }
  const tick = () => {
    elapsedSeconds.value = Math.max(0, Math.floor((Date.now() - new Date(attendedAt).getTime()) / 1000))
  }
  tick()
  elapsedInterval = setInterval(tick, 1000)
}

const elapsedLabel = computed(() => {
  const s = elapsedSeconds.value
  if (s < 60) return `${s} seg`
  const m = Math.floor(s / 60)
  const rem = s % 60
  if (m < 60) return rem > 0 ? `${m} min ${rem} seg` : `${m} min`
  const h = Math.floor(m / 60)
  const mins = m % 60
  return mins > 0 ? `${h}h ${mins}m` : `${h}h`
})

watch(
  () => props.currentTurn?.attended_at,
  (newVal) => {
    startElapsedTimer(newVal || null)
  },
  { immediate: true }
)

onUnmounted(() => {
  if (elapsedInterval) clearInterval(elapsedInterval)
})

// ── Helpers ───────────────────────────────────────────────────
const formatTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleTimeString('es-AR', {
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<style scoped>
/* ── Root card ─────────────────────────────────────────────── */
.op-current-turn {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  padding: 1.75rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  height: 100%;
  overflow: hidden;
}

/* ── Section header ────────────────────────────────────────── */
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.section-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.07em;
}

.dot-green { color: #10b981; font-size: 0.55rem; }
.dot-yellow { color: #f59e0b; font-size: 0.55rem; }

/* ── Transition ────────────────────────────────────────────── */
.turn-state-enter-active,
.turn-state-leave-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}
.turn-state-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.turn-state-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* ── STATE A: Idle ─────────────────────────────────────────── */
.state-idle {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.875rem;
  padding: 1rem 0 1.5rem;
}

.idle-icon-ring {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: rgba(59, 130, 246, 0.07);
  border: 2px dashed rgba(59, 130, 246, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: rgba(59, 130, 246, 0.4);
  margin-bottom: 0.25rem;
}

.idle-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.1rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.idle-sub {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  color: #94a3b8;
  margin: 0;
  text-align: center;
}

.btn-call-next {
  width: 100%;
  height: 56px;
  margin-top: 0.75rem;
  border: none;
  border-radius: 14px;
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  color: #fff;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);
  transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
  letter-spacing: 0.01em;
}

.btn-call-next:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 22px rgba(99, 102, 241, 0.45);
}

.btn-call-next:active:not(:disabled) {
  transform: translateY(0);
}

.btn-call-next:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

/* ── Shared turn states ────────────────────────────────────── */
.state-called,
.state-inprogress {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  overflow-y: auto;
}

.state-called::-webkit-scrollbar,
.state-inprogress::-webkit-scrollbar {
  width: 4px;
}
.state-called::-webkit-scrollbar-thumb,
.state-inprogress::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 4px;
}

/* Status pills */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 6px 18px;
  border-radius: 20px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.07em;
}

.status-pill--called {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-pill--inprogress {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  animation: pulse-dot 1.4s ease-in-out infinite;
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.4; transform: scale(0.65); }
}

/* Ticket number */
.ticket-number {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 7rem;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.04em;
  text-align: center;
}

/* Service badge */
.service-badge {
  display: inline-block;
  padding: 5px 16px;
  border-radius: 10px;
  border: 1px solid;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
}

/* Customer row */
.customer-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.95rem;
  font-weight: 600;
  color: #334155;
}

.customer-row i {
  color: #94a3b8;
  font-size: 0.85rem;
}

/* Timestamps */
.timestamps-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem 1rem;
  width: 100%;
  border-top: 1px solid #f1f5f9;
  padding-top: 0.75rem;
}

.state-inprogress .timestamps-grid {
  grid-template-columns: 1fr 1fr 1fr;
}

.ts-item {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  align-items: center;
}

.ts-label {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.68rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.ts-value {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
}

.ts-value--blue  { color: #3b82f6; }
.ts-value--green { color: #10b981; }

/* Elapsed time */
.elapsed-time {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
  font-weight: 600;
  color: #64748b;
  background: #f8fafc;
  padding: 0.5rem 1.25rem;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
}

.elapsed-time i {
  color: #10b981;
}

/* ── Called state buttons ──────────────────────────────────── */
.btn-start {
  width: 100%;
  height: 52px;
  border: none;
  border-radius: 12px;
  background: #3b82f6;
  color: #fff;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
  transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
  box-shadow: 0 3px 12px rgba(59, 130, 246, 0.3);
}

.btn-start:hover {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4);
}

.btn-row-secondary {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  width: 100%;
}

.btn-noshow,
.btn-return {
  height: 44px;
  border-radius: 10px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  transition: background 0.15s, color 0.15s;
}

.btn-noshow {
  background: #f8fafc;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.btn-noshow:hover {
  background: #e2e8f0;
  color: #334155;
}

.btn-return {
  background: rgba(245, 158, 11, 0.08);
  color: #d97706;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.btn-return:hover {
  background: rgba(245, 158, 11, 0.16);
}

/* ── In progress state buttons ─────────────────────────────── */
.btn-row-primary {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 0.75rem;
  width: 100%;
}

.btn-complete {
  height: 52px;
  border: none;
  border-radius: 12px;
  background: #10b981;
  color: #fff;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
  box-shadow: 0 3px 12px rgba(16, 185, 129, 0.3);
}

.btn-complete:hover {
  background: #059669;
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
}

.btn-cancel {
  height: 52px;
  border: 2px solid #ef4444;
  border-radius: 12px;
  background: transparent;
  color: #ef4444;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  transition: background 0.15s, color 0.15s;
}

.btn-cancel:hover {
  background: rgba(239, 68, 68, 0.08);
}
</style>
