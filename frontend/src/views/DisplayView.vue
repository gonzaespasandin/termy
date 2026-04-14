<template>
  <div class="display-root">

    <!-- ═══════════ BACKGROUND ═══════════ -->
    <div class="display-bg">
      <div class="bg-grid"></div>
      <div class="bg-radial-glow"></div>
    </div>

    <!-- ═══════════ MAIN LAYOUT ═══════════ -->
    <div class="display-layout">

      <!-- ══════════════════════════════════
           LEFT PANEL — 65%
      ══════════════════════════════════ -->
      <section class="panel-left">

        <!-- HERO: turno más reciente llamado -->
        <div class="hero-section">
          <div
            v-if="lastCalledTurn"
            class="hero-card"
            :class="{ 'hero-card--pulsing': lastCalledTurn.status === 'called' }"
            :style="{ '--svc-color': lastCalledTurn.service?.color || '#3b82f6' }"
          >
            <!-- Barra de acento izquierda en color del servicio -->
            <div class="hero-accent-bar"></div>

            <!-- Contenido principal -->
            <div class="hero-body">
              <div class="hero-eyebrow">
                <span class="eyebrow-dot"></span>
                LLAMANDO AHORA
              </div>

              <div
                class="hero-service-name"
                :style="{ color: lastCalledTurn.service?.color || '#3b82f6' }"
              >
                {{ lastCalledTurn.service?.name || '' }}
              </div>

              <div class="hero-ticket-number">
                {{ lastCalledTurn.ticket_number }}
              </div>

              <div class="hero-window" v-if="lastCalledTurn.window">
                <span class="hero-window-label">Ventanilla</span>
                <span class="hero-window-value">{{ lastCalledTurn.window.name }}</span>
              </div>

              <div class="hero-time">{{ formatTime(lastCalledTurn.called_at) }}</div>
            </div>

            <!-- Glow de fondo en color del servicio -->
            <div class="hero-glow-overlay"></div>
          </div>

          <!-- Estado vacío -->
          <div v-else class="hero-empty">
            <div class="hero-empty-icon">
              <i class="fas fa-broadcast-tower"></i>
            </div>
            <p class="hero-empty-text">Esperando próximo llamado...</p>
          </div>
        </div>

        <!-- HISTORIAL: llamados anteriores (índices 1–5) -->
        <div class="history-section" v-if="recentCalled.length > 1">
          <div class="history-header">
            <span class="history-label">Llamados anteriores</span>
          </div>
          <div class="history-list">
            <div
              v-for="turn in recentCalled.slice(1, 6)"
              :key="turn.id"
              class="history-card"
              :style="{ '--svc-color': turn.service?.color || '#3b82f6' }"
            >
              <div class="history-color-dot"></div>
              <div class="history-ticket">{{ turn.ticket_number }}</div>
              <div class="history-service">{{ turn.service?.name }}</div>
              <div class="history-window" v-if="turn.window">
                Ventanilla {{ turn.window.name }}
              </div>
              <div class="history-time">{{ formatTime(turn.called_at) }}</div>
            </div>
          </div>
        </div>

      </section>

      <!-- DIVIDER -->
      <div class="panel-divider"></div>

      <!-- ══════════════════════════════════
           RIGHT PANEL — 35%
      ══════════════════════════════════ -->
      <aside class="panel-right">

        <!-- Reloj -->
        <div class="info-block info-block--clock">
          <div class="clock-time">{{ currentTime }}</div>
          <div class="clock-date">{{ currentDate }}</div>
        </div>

        <div class="info-separator"></div>

        <!-- Turnos en espera -->
        <div class="info-block info-block--waiting">
          <div class="waiting-label">En espera</div>
          <div class="waiting-count">{{ waiting.length }}</div>
          <div class="waiting-sublabel">turnos pendientes</div>
        </div>

        <div class="info-separator"></div>

        <!-- Desglose por servicio -->
        <div class="info-block info-block--services">
          <div class="services-title">Por servicio</div>
          <div class="services-list" v-if="waitingByService.length > 0">
            <div
              v-for="svc in waitingByService"
              :key="svc.name"
              class="service-chip"
              :style="{ '--svc-color': svc.color }"
            >
              <div class="service-chip-dot"></div>
              <span class="service-chip-name">{{ svc.name }}</span>
              <span class="service-chip-count">{{ svc.count }}</span>
            </div>
          </div>
          <div v-else class="services-empty">Sin turnos en espera</div>
        </div>

        <!-- Brand footer -->
        <div class="info-brand">
          <div class="brand-pulse-ring"></div>
          <div class="brand-dot"></div>
          <span class="brand-name">Termy</span>
        </div>

      </aside>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { getQueue } from '../api';

// ──────────────────────────────
// Reactive state
// ──────────────────────────────
const waiting    = ref([]);
const inProgress = ref([]);
const recentCalled = ref([]);
const currentTime  = ref('');
const currentDate  = ref('');
const lastCalledId = ref(null);

const USE_SSE = false; // Cambiar a true para usar SSE en vez de polling

let eventSource = null;
let intervalId  = null;
let clockId     = null;

// ──────────────────────────────
// Computed
// ──────────────────────────────

// Turno más reciente llamado (héroe de la pantalla)
const lastCalledTurn = computed(() => {
  return recentCalled.value.length > 0 ? recentCalled.value[0] : null;
});

// Conteo de turnos en espera agrupados por servicio
const waitingByService = computed(() => {
  const map = {};
  for (const turn of waiting.value) {
    const key   = turn.service?.name  || 'Sin servicio';
    const color = turn.service?.color || '#64748b';
    if (!map[key]) {
      map[key] = { name: key, color, count: 0 };
    }
    map[key].count++;
  }
  return Object.values(map);
});

// ──────────────────────────────
// Audio notification
// ──────────────────────────────
const playNotificationSound = () => {
  const audioContext = new (window.AudioContext || window.webkitAudioContext)();

  const oscillator = audioContext.createOscillator();
  const gainNode   = audioContext.createGain();

  oscillator.connect(gainNode);
  gainNode.connect(audioContext.destination);

  oscillator.type = 'sine';
  oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
  oscillator.frequency.exponentialRampToValueAtTime(600, audioContext.currentTime + 0.1);

  gainNode.gain.setValueAtTime(0, audioContext.currentTime);
  gainNode.gain.linearRampToValueAtTime(0.3, audioContext.currentTime + 0.05);
  gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

  oscillator.start(audioContext.currentTime);
  oscillator.stop(audioContext.currentTime + 0.5);

  // Segundo tono (eco)
  setTimeout(() => {
    const oscillator2 = audioContext.createOscillator();
    const gainNode2   = audioContext.createGain();

    oscillator2.connect(gainNode2);
    gainNode2.connect(audioContext.destination);

    oscillator2.type = 'sine';
    oscillator2.frequency.setValueAtTime(900, audioContext.currentTime);
    oscillator2.frequency.exponentialRampToValueAtTime(700, audioContext.currentTime + 0.1);

    gainNode2.gain.setValueAtTime(0, audioContext.currentTime);
    gainNode2.gain.linearRampToValueAtTime(0.25, audioContext.currentTime + 0.05);
    gainNode2.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.4);

    oscillator2.start(audioContext.currentTime);
    oscillator2.stop(audioContext.currentTime + 0.4);
  }, 150);
};

// ──────────────────────────────
// Queue loading (polling)
// ──────────────────────────────
const loadQueue = async () => {
  try {
    const response = await getQueue();
    waiting.value    = response.data.waiting    || [];
    inProgress.value = response.data.in_progress || [];

    const newRecentCalled = response.data.recent_called || [];

    if (newRecentCalled.length > 0) {
      const newFirstId = newRecentCalled[0].id;

      if (
        lastCalledId.value !== null &&
        newFirstId !== lastCalledId.value &&
        newRecentCalled[0].status === 'called'
      ) {
        playNotificationSound();
        console.log('🆕 Nuevo turno llamado:', newRecentCalled[0].ticket_number);
      }

      lastCalledId.value = newFirstId;
    }

    recentCalled.value = newRecentCalled;
  } catch (error) {
    console.error('Error cargando cola:', error);
  }
};

// ──────────────────────────────
// SSE (disponible, deshabilitado por defecto)
// ──────────────────────────────
const connectSSE = () => {
  if (eventSource) {
    eventSource.close();
  }

  eventSource = new EventSource('http://192.168.0.51:8000/api/sse/display');

  eventSource.onmessage = (event) => {
    try {
      const data = JSON.parse(event.data);
      waiting.value    = data.waiting    || [];
      inProgress.value = data.in_progress || [];

      const newRecentCalled = data.recent_called || [];

      if (newRecentCalled.length > 0) {
        const newFirstId = newRecentCalled[0].id;

        if (
          lastCalledId.value !== null &&
          newFirstId !== lastCalledId.value &&
          newRecentCalled[0].status === 'called'
        ) {
          playNotificationSound();
          console.log('🆕 Nuevo turno llamado (SSE):', newRecentCalled[0].ticket_number);
        }

        lastCalledId.value = newFirstId;
      }

      recentCalled.value = newRecentCalled;
    } catch (error) {
      console.error('Error parseando SSE:', error);
    }
  };

  eventSource.onerror = (error) => {
    console.error('❌ Error SSE, volviendo a polling...', error);
    eventSource.close();
    eventSource = null;
    intervalId = setInterval(loadQueue, 3000);
  };

  eventSource.onopen = () => {
    console.log('🟢 SSE conectado');
  };
};

// ──────────────────────────────
// Clock
// ──────────────────────────────
const updateClock = () => {
  const now = new Date();
  currentTime.value = now.toLocaleTimeString('es-AR', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  });
  currentDate.value = now.toLocaleDateString('es-AR', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

// ──────────────────────────────
// Utilities
// ──────────────────────────────
const formatTime = (datetime) => {
  if (!datetime) return '';
  return new Date(datetime).toLocaleTimeString('es-AR', {
    hour: '2-digit',
    minute: '2-digit',
  });
};

// ──────────────────────────────
// Lifecycle
// ──────────────────────────────
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

onUnmounted(() => {
  if (eventSource) eventSource.close();
  if (intervalId)  clearInterval(intervalId);
  if (clockId)     clearInterval(clockId);
});
</script>

<style scoped>
/* ═══════════════════════════════════════
   FUENTE — Solo en elementos de texto,
   NO en <i> para preservar Font Awesome
═══════════════════════════════════════ */
.display-root div,
.display-root p,
.display-root span,
.display-root aside,
.display-root section {
  font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
}

/* ═══════════════════════════════════════
   CSS VARIABLES
═══════════════════════════════════════ */
.display-root {
  --color-bg:             #0a0f1e;
  --color-panel-bg:       rgba(255, 255, 255, 0.03);
  --color-divider:        rgba(255, 255, 255, 0.08);
  --color-text-primary:   #f1f5f9;
  --color-text-secondary: #94a3b8;
  --color-text-muted:     #475569;
  --color-accent:         #3b82f6;
}

/* ═══════════════════════════════════════
   LAYOUT RAÍZ
═══════════════════════════════════════ */
.display-root {
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  position: relative;
  background: var(--color-bg);
  margin: 0;
  padding: 0;
}

/* ═══════════════════════════════════════
   BACKGROUND — Grid + Glow
═══════════════════════════════════════ */
.display-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 0;
}

.bg-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(59, 130, 246, 0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(59, 130, 246, 0.04) 1px, transparent 1px);
  background-size: 80px 80px;
}

.bg-radial-glow {
  position: absolute;
  width: 60vw;
  height: 60vh;
  top: 50%;
  left: 32%;
  transform: translate(-50%, -50%);
  background: radial-gradient(ellipse, rgba(59, 130, 246, 0.07) 0%, transparent 65%);
  filter: blur(60px);
}

/* ═══════════════════════════════════════
   MAIN LAYOUT — Dos columnas
═══════════════════════════════════════ */
.display-layout {
  position: relative;
  z-index: 1;
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: row;
  padding: 2rem 2.5rem;
  gap: 0;
}

.panel-left {
  flex: 0 0 65%;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  overflow: hidden;
  padding-right: 2.5rem;
}

.panel-divider {
  flex: 0 0 1px;
  background: var(--color-divider);
  align-self: stretch;
  margin: 0 0.25rem;
}

.panel-right {
  flex: 0 0 35%;
  display: flex;
  flex-direction: column;
  padding-left: 2.5rem;
  overflow: hidden;
}

/* ═══════════════════════════════════════
   LEFT PANEL — HERO CARD
═══════════════════════════════════════ */
.hero-section {
  flex-shrink: 0;
}

.hero-card {
  position: relative;
  display: flex;
  overflow: hidden;
  border-radius: 20px;
  min-height: 38vh;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.hero-card--pulsing {
  animation: heroGlow 2s ease-in-out infinite;
}

.hero-accent-bar {
  flex: 0 0 10px;
  background: var(--svc-color, #3b82f6);
}

.hero-body {
  flex: 1;
  padding: 2.25rem 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  position: relative;
  z-index: 1;
}

.hero-eyebrow {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--color-text-secondary);
}

.eyebrow-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--svc-color, #3b82f6);
  display: inline-block;
  flex-shrink: 0;
  animation: dotPulse 1.5s ease-in-out infinite;
}

.hero-service-name {
  font-size: 1.6rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  line-height: 1.2;
}

.hero-ticket-number {
  font-size: clamp(5rem, 10vw, 9rem);
  font-weight: 900;
  color: var(--color-text-primary);
  letter-spacing: -0.03em;
  line-height: 1;
}

.hero-window {
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
  margin-top: 0.25rem;
}

.hero-window-label {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.hero-window-value {
  font-size: 2.2rem;
  font-weight: 800;
  color: var(--color-text-primary);
}

.hero-time {
  font-size: 1rem;
  font-weight: 500;
  color: var(--color-text-muted);
  margin-top: auto;
  padding-top: 0.5rem;
}

.hero-glow-overlay {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background: radial-gradient(
    ellipse at 80% 50%,
    color-mix(in srgb, var(--svc-color, #3b82f6) 12%, transparent) 0%,
    transparent 60%
  );
}

/* Estado vacío */
.hero-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 38vh;
  gap: 1.5rem;
  opacity: 0.4;
}

.hero-empty-icon {
  font-size: 5rem;
  color: var(--color-text-secondary);
}

.hero-empty-text {
  font-size: 1.8rem;
  font-weight: 600;
  color: var(--color-text-secondary);
  margin: 0;
}

/* ═══════════════════════════════════════
   LEFT PANEL — HISTORIAL
═══════════════════════════════════════ */
.history-section {
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-height: 0;
}

.history-header {
  flex-shrink: 0;
}

.history-label {
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  overflow: hidden;
}

.history-card {
  background: rgba(255, 255, 255, 0.035);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-left: 4px solid var(--svc-color, #3b82f6);
  border-radius: 12px;
  padding: 0.8rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.history-card:nth-child(1) { animation: slideInTop 0.35s 0ms   ease both; }
.history-card:nth-child(2) { animation: slideInTop 0.35s 60ms  ease both; }
.history-card:nth-child(3) { animation: slideInTop 0.35s 120ms ease both; }
.history-card:nth-child(4) { animation: slideInTop 0.35s 180ms ease both; }
.history-card:nth-child(5) { animation: slideInTop 0.35s 240ms ease both; }

.history-color-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--svc-color, #3b82f6);
  flex-shrink: 0;
}

.history-ticket {
  font-size: 1.7rem;
  font-weight: 900;
  color: var(--color-text-primary);
  min-width: 8rem;
}

.history-service {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-text-secondary);
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.history-window {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text-primary);
  white-space: nowrap;
}

.history-time {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text-muted);
  flex-shrink: 0;
}

/* ═══════════════════════════════════════
   RIGHT PANEL — INFO BLOCKS
═══════════════════════════════════════ */
.info-block {
  padding: 1.5rem 0;
  flex-shrink: 0;
}

.info-separator {
  height: 1px;
  background: var(--color-divider);
  flex-shrink: 0;
}

/* Reloj */
.info-block--clock {
  text-align: center;
  padding-top: 0.75rem;
}

.clock-time {
  font-size: clamp(3rem, 5vw, 4.5rem);
  font-weight: 900;
  color: var(--color-text-primary);
  letter-spacing: 0.03em;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.clock-date {
  font-size: 0.95rem;
  font-weight: 500;
  color: var(--color-text-secondary);
  margin-top: 0.5rem;
  text-transform: capitalize;
}

/* Turnos en espera */
.info-block--waiting {
  text-align: center;
}

.waiting-label {
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--color-text-muted);
  margin-bottom: 0.35rem;
}

.waiting-count {
  font-size: clamp(3.5rem, 6vw, 5.5rem);
  font-weight: 900;
  color: var(--color-accent);
  line-height: 1;
}

.waiting-sublabel {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--color-text-muted);
  margin-top: 0.3rem;
}

/* Desglose por servicio */
.info-block--services {
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  padding-bottom: 0;
}

.services-title {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--color-text-muted);
  margin-bottom: 0.85rem;
  flex-shrink: 0;
}

.services-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  overflow: hidden;
}

.service-chip {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 10px;
  padding: 0.65rem 1rem;
}

.service-chip-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--svc-color, #3b82f6);
  flex-shrink: 0;
}

.service-chip-name {
  flex: 1;
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.service-chip-count {
  font-size: 1.2rem;
  font-weight: 900;
  color: var(--color-text-primary);
}

.services-empty {
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--color-text-muted);
}

/* Brand footer */
.info-brand {
  margin-top: auto;
  padding: 1.5rem 0 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  position: relative;
  flex-shrink: 0;
}

.brand-pulse-ring {
  position: absolute;
  width: 28px;
  height: 28px;
  border: 2px solid rgba(59, 130, 246, 0.3);
  border-radius: 50%;
  animation: brandRing 2.5s ease-in-out infinite;
}

.brand-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--color-accent);
  position: relative;
  z-index: 1;
}

.brand-name {
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.35em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

/* ═══════════════════════════════════════
   ANIMACIONES
═══════════════════════════════════════ */

/* Hero card — glow pulsante en color del servicio */
@keyframes heroGlow {
  0%, 100% {
    box-shadow:
      0 8px 40px rgba(0, 0, 0, 0.4),
      0 0 60px color-mix(in srgb, var(--svc-color, #3b82f6) 20%, transparent);
  }
  50% {
    box-shadow:
      0 12px 60px rgba(0, 0, 0, 0.5),
      0 0 100px color-mix(in srgb, var(--svc-color, #3b82f6) 35%, transparent);
  }
}

/* Dot del eyebrow — señal de atención */
@keyframes dotPulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.5; transform: scale(1.35); }
}

/* Cards del historial — slide desde arriba */
@keyframes slideInTop {
  from { opacity: 0; transform: translateY(-12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Brand ring — respiración suave */
@keyframes brandRing {
  0%, 100% { transform: scale(1); opacity: 0.3; }
  50%       { transform: scale(1.6); opacity: 0; }
}
</style>
