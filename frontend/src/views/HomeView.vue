<template>
  <div
    class="kiosk-root"
    :style="totemBackgroundStyle"
  >
    <!-- Vignette overlay -->
    <div class="vignette-overlay"></div>

    <!-- Subtle grid pattern -->
    <div class="grid-overlay"></div>

    <!-- Error toast -->
    <transition name="toast-slide">
      <div v-if="errorMessage" class="error-toast">
        <i class="fa fa-exclamation-circle"></i>
        {{ errorMessage }}
      </div>
    </transition>

    <div class="kiosk-layout">

      <!-- ═══════════ HEADER ═══════════ -->
      <header class="kiosk-header">
        <!-- Logo cliente (izquierda) -->
        <div class="header-logo-slot">
          <img
            v-if="totemLogo"
            :src="totemLogo"
            alt="Logo Cliente"
            class="logo-img"
          />
          <div v-else class="logo-placeholder">
            <div class="logo-placeholder-inner">
              <i class="fa fa-building logo-placeholder-icon"></i>
            </div>
          </div>
        </div>

        <!-- Headline central -->
        <div class="header-center">
          <div class="headline-eyebrow">SISTEMA DE TURNOS</div>
          <h1 class="headline-main" :style="{ fontSize: getHeaderFontSize() }">
            Seleccione el servicio
          </h1>
          <div class="headline-accent-bar"></div>
        </div>

        <!-- Reloj (derecha) -->
        <div class="header-clock-slot">
          <div class="clock-time">{{ clockTime }}</div>
          <div class="clock-date">{{ clockDate }}</div>
        </div>
      </header>

      <!-- ═══════════ CONTENIDO ═══════════ -->
      <main class="kiosk-main">

        <!-- Estado cargando -->
        <div v-if="loading" class="loading-state">
          <div class="loading-spinner">
            <div class="spinner-ring"></div>
            <div class="spinner-ring spinner-ring--2"></div>
          </div>
          <p class="loading-text">Cargando servicios...</p>
        </div>

        <!-- Grid de servicios -->
        <div v-else class="services-wrapper">
          <div class="services-grid" :style="getGridStyle()">
            <div
              v-for="(service, index) in services"
              :key="service.id"
              class="service-card"
              :style="{
                '--svc-color': service.color,
                animationDelay: `${index * 70}ms`
              }"
              @click="selectService(service)"
            >
              <!-- Banda de color superior -->
              <div class="card-band" :style="{ backgroundColor: service.color }">
                <div
                  class="card-icon"
                  :style="getIconStyle()"
                >
                  <span class="card-icon-text" :style="{ color: service.color }">
                    {{ service.code }}
                  </span>
                </div>
              </div>

              <!-- Cuerpo de la card -->
              <div class="card-body" :style="getCardBodyStyle()">
                <h3 class="card-name" :style="{ fontSize: getServiceNameFontSize() }">
                  {{ service.name }}
                </h3>
                <p class="card-desc" :style="{ fontSize: getServiceDescFontSize() }">
                  {{ service.description }}
                </p>
                <div class="card-tap-hint">
                  <i class="fa fa-hand-pointer"></i>
                  <span>Toque aquí</span>
                </div>
              </div>

              <!-- Efecto de press -->
              <div class="card-press-overlay"></div>
            </div>
          </div>
        </div>

      </main>
    </div>

    <!-- ═══════════ MODAL: CONFIRMAR TURNO ═══════════ -->
    <transition name="modal-fade">
      <div
        v-if="selectedService && askCustomerName"
        class="modal-backdrop"
        @click.self="selectedService = null"
      >
        <transition name="modal-scale" appear>
          <div class="modal-card">
            <!-- Franja superior con color del servicio -->
            <div class="modal-band" :style="{ backgroundColor: selectedService.color }">
              <div class="modal-band-icon">
                <span :style="{ color: selectedService.color }">{{ selectedService.code }}</span>
              </div>
            </div>

            <div class="modal-body">
              <p class="modal-eyebrow">Está por solicitar un turno para:</p>
              <h2 class="modal-service-name" :style="{ color: selectedService.color }">
                {{ selectedService.name }}
              </h2>

              <div class="modal-input-wrapper">
                <label class="modal-input-label">Su nombre (opcional)</label>
                <input
                  v-model="customerName"
                  type="text"
                  placeholder="Ingrese su nombre..."
                  class="modal-input"
                />
              </div>

              <div class="modal-actions">
                <button
                  class="btn-primary"
                  :style="{ backgroundColor: selectedService.color }"
                  :disabled="confirming"
                  @click="confirmTurn"
                >
                  <i :class="confirming ? 'fa fa-spinner fa-spin' : 'fa fa-check-circle'"></i>
                  {{ confirming ? 'Generando turno...' : 'Confirmar Turno' }}
                </button>
                <button
                  class="btn-secondary"
                  @click="selectedService = null"
                >
                  <i class="fa fa-times"></i>
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </transition>

    <!-- ═══════════ MODAL: TICKET GENERADO ═══════════ -->
    <transition name="modal-fade">
      <div v-if="generatedTurn" class="modal-backdrop">
        <transition name="modal-scale" appear>
          <div class="modal-card ticket-modal">

            <div class="ticket-header">
              <p class="ticket-eyebrow">SU NÚMERO DE TURNO</p>
              <div
                class="ticket-number"
                :style="{ color: generatedTurn.service?.color || '#3b82f6' }"
              >
                {{ generatedTurn.ticket_number }}
              </div>
              <div
                class="ticket-number-glow"
                :style="{ background: `radial-gradient(ellipse, ${generatedTurn.service?.color || '#3b82f6'}33 0%, transparent 70%)` }"
              ></div>
            </div>

            <div class="ticket-info">
              <div class="ticket-info-row">
                <i class="fa fa-check-square ticket-info-icon"></i>
                <span class="ticket-info-text">{{ generatedTurn.service?.name }}</span>
              </div>
              <div class="ticket-info-row">
                <i class="fa fa-clock ticket-info-icon"></i>
                <span class="ticket-info-text">{{ formatTime(generatedTurn.created_at) }}</span>
              </div>
            </div>

            <div class="ticket-divider"></div>

            <!-- Estado de impresión -->
            <div class="ticket-print-status">
              <div v-if="printing" class="print-status print-status--printing">
                <div class="print-status-dot print-status-dot--pulse"></div>
                Imprimiendo ticket...
              </div>
              <div v-else-if="printError" class="print-status print-status--error">
                <div class="print-status-dot print-status-dot--error"></div>
                {{ printError }}
              </div>
              <div v-else-if="printSuccess" class="print-status print-status--success">
                <div class="print-status-dot print-status-dot--success"></div>
                Ticket impreso correctamente
              </div>
              <div v-else class="print-status print-status--idle">
                <div class="print-status-dot print-status-dot--idle"></div>
                Listo para imprimir
              </div>
            </div>

            <div class="modal-actions">
              <button
                class="btn-primary"
                style="background-color: #3b82f6;"
                :disabled="printing"
                @click="resetAndPrint"
              >
                <i class="fa fa-print"></i>
                {{ printing ? 'Imprimiendo...' : 'Imprimir Ticket' }}
              </button>
              <button
                class="btn-secondary"
                @click="closeTicketModal"
              >
                <i class="fa fa-check"></i>
                Cerrar
              </button>
            </div>

          </div>
        </transition>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { getTotemServices, createTurn, getServices } from '../api';
import * as printService from '../services/printService';

// ──────────────────────────────
// Reloj
// ──────────────────────────────
const clockTime = ref('');
const clockDate = ref('');
let clockInterval = null;

const updateClock = () => {
  const now = new Date();
  clockTime.value = now.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
  clockDate.value = now.toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long' });
};

// ──────────────────────────────
// Estado del totem
// ──────────────────────────────
const totemConfigured = ref(false);
const totemCode = ref('');
const totemId = ref(null);
const totemName = ref('');
const totemData = ref(null);
const askCustomerName = ref(true);

// Estado de servicios y turnos
const services = ref([]);
const loading = ref(true);
const selectedService = ref(null);
const customerName = ref('');
const generatedTurn = ref(null);

// Estado de impresión
const printing = ref(false);
const printError = ref('');
const printSuccess = ref(false);

// Guard anti-spam: evita generar múltiples turnos antes de que la API responda
const confirming = ref(false);

const isElectronApp = ref(false);
const errorMessage = ref('');

const showError = (message) => {
  errorMessage.value = message;
  setTimeout(() => { errorMessage.value = ''; }, 4000);
};

// ──────────────────────────────
// Grid layout
// ──────────────────────────────
const getGridLayout = () => {
  const count = services.value.length;
  if (count === 0) return { cols: 1, rows: 1 };
  if (count <= 3) return { cols: 3, rows: 1 };
  if (count <= 6) return { cols: 3, rows: 2 };
  if (count <= 9) return { cols: 3, rows: 3 };
  if (count <= 12) return { cols: 4, rows: 3 };
  if (count <= 15) return { cols: 5, rows: 3 };
  return { cols: 6, rows: Math.ceil(count / 6) };
};

const getGridStyle = () => {
  const layout = getGridLayout();
  const count = services.value.length;
  const gap = count <= 6 ? '1.75rem' : count <= 9 ? '1.25rem' : '0.875rem';
  return {
    display: 'grid',
    gridTemplateColumns: `repeat(${layout.cols}, 1fr)`,
    gridTemplateRows: `repeat(${layout.rows}, 1fr)`,
    gap: gap,
  };
};

const getCardBodyStyle = () => {
  const count = services.value.length;
  const padding = count <= 6 ? '1.25rem 1.25rem 1rem' : count <= 9 ? '1rem 1rem 0.75rem' : '0.75rem 0.75rem 0.5rem';
  return { padding };
};

const getIconStyle = () => {
  const count = services.value.length;
  let size, fontSize;
  if (count <= 3)       { size = '5rem';   fontSize = '2rem'; }
  else if (count <= 6)  { size = '4.25rem'; fontSize = '1.75rem'; }
  else if (count <= 9)  { size = '3.5rem'; fontSize = '1.5rem'; }
  else                  { size = '2.75rem'; fontSize = '1.1rem'; }
  return { width: size, height: size, fontSize: fontSize };
};

const getHeaderFontSize = () => {
  const count = services.value.length;
  if (count <= 6) return '2rem';
  if (count <= 9) return '1.75rem';
  return '1.5rem';
};

const getServiceNameFontSize = () => {
  const count = services.value.length;
  if (count <= 3) return '1.6rem';
  if (count <= 6) return '1.4rem';
  if (count <= 9) return '1.2rem';
  return '1rem';
};

const getServiceDescFontSize = () => {
  const count = services.value.length;
  if (count <= 3) return '1rem';
  if (count <= 6) return '0.9rem';
  if (count <= 9) return '0.8rem';
  return '0.7rem';
};

const totemLogo = computed(() => {
  if (!totemData.value?.logo) return null;
  return `http://192.168.0.51:8000/storage/${totemData.value.logo}`;
});

const totemBackgroundStyle = computed(() => {
  if (!totemData.value) {
    return { background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' };
  }
  if (totemData.value.background_type === 'gradient') {
    const c1 = totemData.value.background_color_1 || '#667eea';
    const c2 = totemData.value.background_color_2 || '#764ba2';
    return { background: `linear-gradient(135deg, ${c1} 0%, ${c2} 100%)` };
  } else {
    return { backgroundColor: totemData.value.background_color_1 || '#667eea' };
  }
});

// ──────────────────────────────
// Lifecycle
// ──────────────────────────────
onMounted(async () => {
  updateClock();
  clockInterval = setInterval(updateClock, 1000);

  isElectronApp.value = printService.isElectron();

  if (isElectronApp.value) {
    const electronConfig = await printService.getTotemConfig();
    if (electronConfig && electronConfig.totemCode !== 'TOTEM-DEFAULT') {
      totemCode.value = electronConfig.totemCode;
      await loadTotemServices();
      return;
    }
  }

  await loadAllServices();
});

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval);
});

// ──────────────────────────────
// API
// ──────────────────────────────
const loadTotemServices = async () => {
  loading.value = true;
  try {
    const response = await getTotemServices(totemCode.value);
    totemData.value = response.data.totem;
    totemId.value = response.data.totem.id;
    totemName.value = response.data.totem.name;
    services.value = response.data.services;
    totemConfigured.value = true;
    askCustomerName.value = response.data.totem.ask_customer_name !== false;
  } catch (error) {
    console.error('Error cargando servicios del totem:', error);
    showError('Error al cargar servicios del totem. Mostrando todos los servicios.');
    await loadAllServices();
  } finally {
    loading.value = false;
  }
};

const loadAllServices = async () => {
  loading.value = true;
  try {
    const response = await getServices();
    services.value = response.data;
    totemName.value = 'Todos los servicios';
  } catch (error) {
    console.error('Error cargando servicios:', error);
    showError('Error al cargar servicios');
  } finally {
    loading.value = false;
  }
};

const selectService = async (service) => {
  if (confirming.value) return;
  selectedService.value = service;
  customerName.value = '';
  if (!askCustomerName.value) {
    await confirmTurn();
  }
};

const confirmTurn = async () => {
  if (confirming.value) return;
  confirming.value = true;
  try {
    const finalCustomerName = askCustomerName.value ? (customerName.value || '') : '';
    const response = await createTurn({
      service_id: selectedService.value.id,
      customer_name: finalCustomerName,
      totem_id: totemId.value,
    });
    generatedTurn.value = response.data;
    selectedService.value = null;
    await printTicket();
  } catch (error) {
    console.error('Error creando turno:', error);
    showError('Error al crear turno');
  } finally {
    confirming.value = false;
  }
};

const printTicket = async () => {
  if (!generatedTurn.value) return;
  printing.value = true;
  printError.value = '';
  printSuccess.value = false;
  try {
    const finalCustomerName = askCustomerName.value ? (customerName.value || '') : '';
    const result = await printService.printTicket({
      ticket_number: generatedTurn.value.ticket_number,
      service_name: generatedTurn.value.service.name,
      customer_name: finalCustomerName,
    });
    if (result.success) {
      printSuccess.value = true;
    } else {
      throw new Error(result.error || 'Error al imprimir');
    }
  } catch (error) {
    printError.value = error.message || 'Error desconocido al imprimir';
  } finally {
    printing.value = false;
  }
};

const resetAndPrint = async () => { await printTicket(); };

const closeTicketModal = () => {
  generatedTurn.value = null;
  customerName.value = '';
  printError.value = '';
  printSuccess.value = false;
};

const formatTime = (datetime) => {
  return new Date(datetime).toLocaleTimeString('es-AR', {
    hour: '2-digit',
    minute: '2-digit'
  });
};
</script>

<style scoped>
/* ═══════════════════════════════════════
   FUENTE
═══════════════════════════════════════ */
*,
*::before,
*::after {
  box-sizing: border-box;
}

/* Font aplicada solo a elementos de texto — NO a <i> para no romper Font Awesome */
.kiosk-root,
.kiosk-root div,
.kiosk-root p,
.kiosk-root h1,
.kiosk-root h2,
.kiosk-root h3,
.kiosk-root span,
.kiosk-root button,
.kiosk-root input,
.kiosk-root label {
  font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
}

/* ═══════════════════════════════════════
   LAYOUT RAÍZ
═══════════════════════════════════════ */
.kiosk-root {
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  position: relative;
  margin: 0;
  padding: 0;
}

.vignette-overlay {
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at center, transparent 40%, rgba(0,0,0,0.35) 100%);
  pointer-events: none;
  z-index: 1;
}

.grid-overlay {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
  background-size: 60px 60px;
  pointer-events: none;
  z-index: 1;
}

.kiosk-layout {
  position: relative;
  z-index: 2;
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: 1.5rem 2rem;
}

/* ═══════════════════════════════════════
   HEADER
═══════════════════════════════════════ */
.kiosk-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 11vh;
  margin-bottom: 1.25rem;
  gap: 1.5rem;
  flex-shrink: 0;
}

.header-logo-slot {
  width: 18%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.logo-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3));
}

.logo-placeholder {
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.12);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-placeholder-inner {
  text-align: center;
}

.logo-placeholder-icon {
  font-size: 2rem;
  color: rgba(255,255,255,0.5);
}

.header-center {
  flex: 1;
  text-align: center;
}

.headline-eyebrow {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.25em;
  color: rgba(255,255,255,0.6);
  text-transform: uppercase;
  margin-bottom: 0.35rem;
}

.headline-main {
  font-weight: 800;
  color: #ffffff;
  line-height: 1.1;
  margin: 0 0 0.5rem;
  text-shadow: 0 2px 16px rgba(0,0,0,0.3);
  letter-spacing: -0.02em;
}

.headline-accent-bar {
  width: 60px;
  height: 3px;
  background: rgba(255,255,255,0.6);
  border-radius: 2px;
  margin: 0 auto;
}

.header-clock-slot {
  width: 18%;
  height: 100%;
  background: rgba(0,0,0,0.25);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  padding: 0.5rem 1rem;
}

.clock-time {
  font-size: 2.25rem;
  font-weight: 700;
  color: #ffffff;
  line-height: 1;
  letter-spacing: -0.02em;
  text-shadow: 0 0 20px rgba(255,255,255,0.3);
}

.clock-date {
  font-size: 0.7rem;
  font-weight: 500;
  color: rgba(255,255,255,0.65);
  text-transform: capitalize;
  margin-top: 0.25rem;
  text-align: center;
  letter-spacing: 0.03em;
}

/* ═══════════════════════════════════════
   MAIN
═══════════════════════════════════════ */
.kiosk-main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 0;
}

.services-wrapper {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.services-grid {
  width: 100%;
  height: 100%;
}

/* ═══════════════════════════════════════
   CARDS DE SERVICIO
═══════════════════════════════════════ */
.service-card {
  background: #ffffff;
  border-radius: 20px;
  overflow: hidden;
  cursor: pointer;
  position: relative;
  display: flex;
  flex-direction: column;
  box-shadow:
    0 4px 20px rgba(0,0,0,0.18),
    0 1px 4px rgba(0,0,0,0.1);
  transition: transform 0.12s ease, box-shadow 0.12s ease;
  /* Animación de entrada */
  animation: card-enter 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
  will-change: transform;
  -webkit-tap-highlight-color: transparent;
  user-select: none;
}

@keyframes card-enter {
  from {
    opacity: 0;
    transform: translateY(24px) scale(0.94);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.service-card:active {
  transform: scale(0.94);
  box-shadow:
    0 2px 10px rgba(0,0,0,0.2),
    0 1px 3px rgba(0,0,0,0.1);
}

.service-card:active .card-press-overlay {
  opacity: 1;
}

.card-press-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.08);
  opacity: 0;
  transition: opacity 0.1s ease;
  pointer-events: none;
  z-index: 3;
}

/* Banda de color superior */
.card-band {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 42%;
}

/* Icono circular en la banda */
.card-icon {
  background: #ffffff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow:
    0 4px 16px rgba(0,0,0,0.2),
    0 0 0 3px rgba(255,255,255,0.4);
  flex-shrink: 0;
}

.card-icon-text {
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.02em;
}

/* Cuerpo de la card */
.card-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 0;
}

.card-name {
  font-weight: 700;
  color: #1a202c;
  line-height: 1.2;
  margin: 0 0 0.3rem;
  letter-spacing: -0.02em;
}

.card-desc {
  color: #718096;
  font-weight: 400;
  line-height: 1.35;
  margin: 0 0 auto;
  flex: 1;
}

.card-tap-hint {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  color: var(--svc-color);
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  opacity: 0.8;
  margin-top: 0.5rem;
}

.card-tap-hint i {
  font-size: 0.65rem;
}

/* ═══════════════════════════════════════
   LOADING STATE
═══════════════════════════════════════ */
.loading-state {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
}

.loading-spinner {
  position: relative;
  width: 70px;
  height: 70px;
}

.spinner-ring {
  position: absolute;
  inset: 0;
  border: 3px solid transparent;
  border-top-color: rgba(255,255,255,0.9);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.spinner-ring--2 {
  inset: 10px;
  border-top-color: rgba(255,255,255,0.4);
  animation-duration: 0.75s;
  animation-direction: reverse;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-text {
  color: rgba(255,255,255,0.85);
  font-size: 1.5rem;
  font-weight: 500;
}

/* ═══════════════════════════════════════
   ERROR TOAST
═══════════════════════════════════════ */
.error-toast {
  position: fixed;
  top: 1.5rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 100;
  background: #ef4444;
  color: white;
  padding: 1rem 2rem;
  border-radius: 50px;
  font-size: 1.1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  box-shadow: 0 8px 32px rgba(239,68,68,0.4);
  white-space: nowrap;
}

/* ═══════════════════════════════════════
   MODALES
═══════════════════════════════════════ */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 2rem;
}

.modal-card {
  background: #ffffff;
  border-radius: 28px;
  width: 100%;
  max-width: 600px;
  overflow: hidden;
  box-shadow:
    0 24px 80px rgba(0,0,0,0.4),
    0 8px 32px rgba(0,0,0,0.2);
}

.modal-band {
  height: 8px;
}

.modal-band-icon {
  display: none; /* solo la franja de color */
}

.modal-body {
  padding: 2.5rem 3rem 2.5rem;
  text-align: center;
}

.modal-eyebrow {
  font-size: 0.85rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #94a3b8;
  margin: 0 0 0.75rem;
}

.modal-service-name {
  font-size: 2.5rem;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.03em;
  margin: 0 0 2rem;
}

.modal-input-wrapper {
  text-align: left;
  margin-bottom: 2rem;
}

.modal-input-label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 0.5rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.modal-input {
  width: 100%;
  padding: 1rem 1.25rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 1.35rem;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 500;
  color: #1a202c;
  outline: none;
  transition: border-color 0.2s ease;
  background: #f8fafc;
}

.modal-input:focus {
  border-color: #94a3b8;
  background: #ffffff;
}

.modal-input::placeholder {
  color: #cbd5e1;
}

.modal-actions {
  display: flex;
  gap: 1rem;
  margin-top: 0;
}

.btn-primary {
  flex: 1;
  padding: 1.1rem 1.5rem;
  border: none;
  border-radius: 14px;
  font-size: 1.2rem;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 700;
  color: #ffffff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  transition: opacity 0.15s ease, transform 0.1s ease;
  -webkit-tap-highlight-color: transparent;
  box-shadow: 0 4px 16px rgba(0,0,0,0.2);
}

.btn-primary:active {
  opacity: 0.85;
  transform: scale(0.97);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  flex: 1;
  padding: 1.1rem 1.5rem;
  border: 2px solid #e2e8f0;
  border-radius: 14px;
  font-size: 1.2rem;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  transition: background 0.15s ease, transform 0.1s ease;
  -webkit-tap-highlight-color: transparent;
}

.btn-secondary:active {
  background: #e2e8f0;
  transform: scale(0.97);
}

/* ═══════════════════════════════════════
   TICKET MODAL
═══════════════════════════════════════ */
.ticket-modal {
  max-width: 560px;
}

.ticket-header {
  position: relative;
  text-align: center;
  padding: 2.5rem 2.5rem 1.5rem;
  background: #f8fafc;
  border-bottom: 1px solid #f1f5f9;
}

.ticket-eyebrow {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: #94a3b8;
  margin: 0 0 0.75rem;
}

.ticket-number {
  font-size: clamp(5rem, 12vw, 8rem);
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.05em;
  position: relative;
  z-index: 1;
  margin-bottom: 0.25rem;
}

.ticket-number-glow {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
}

.ticket-info {
  padding: 1.25rem 2.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.ticket-info-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: #475569;
}

.ticket-info-icon {
  width: 18px;
  color: #94a3b8;
  flex-shrink: 0;
}

.ticket-info-text {
  font-size: 1.15rem;
  font-weight: 600;
}

.ticket-divider {
  height: 1px;
  background: #f1f5f9;
  margin: 0;
}

.ticket-print-status {
  padding: 1rem 2.5rem;
  background: #f8fafc;
}

.print-status {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 1rem;
  font-weight: 600;
  padding: 0.6rem 0;
}

.print-status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.print-status--printing  { color: #3b82f6; }
.print-status--error     { color: #ef4444; }
.print-status--success   { color: #22c55e; }
.print-status--idle      { color: #94a3b8; }

.print-status-dot--pulse  { background: #3b82f6; animation: dot-pulse 1s infinite; }
.print-status-dot--error  { background: #ef4444; }
.print-status-dot--success{ background: #22c55e; }
.print-status-dot--idle   { background: #cbd5e1; }

@keyframes dot-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.4; transform: scale(0.7); }
}

.ticket-modal .modal-actions {
  padding: 1.25rem 2.5rem 2rem;
}

/* ═══════════════════════════════════════
   TRANSICIONES
═══════════════════════════════════════ */
/* Toast */
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translate(-50%, -24px);
}

/* Modal backdrop */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.25s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

/* Modal card */
.modal-scale-enter-active,
.modal-scale-leave-active {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-scale-enter-from {
  opacity: 0;
  transform: scale(0.88) translateY(20px);
}
.modal-scale-leave-to {
  opacity: 0;
  transform: scale(0.92) translateY(12px);
}
</style>
