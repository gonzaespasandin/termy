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

    <!-- ═══════════ MODAL 1: CONFIRMAR TURNO (sin DNI) ═══════════ -->
    <transition name="modal-fade">
      <div
        v-if="showConfirmModal && selectedService"
        class="modal-backdrop"
        @click.self="showConfirmModal = false"
      >
        <transition name="modal-scale" appear>
          <div class="modal-card">
            <div class="modal-band" :style="{ backgroundColor: selectedService.color }"></div>

            <div class="modal-body">
              <p class="modal-eyebrow">Está por solicitar un turno para:</p>
              <h2 class="modal-service-name" :style="{ color: selectedService.color }">
                {{ selectedService.name }}
              </h2>

              <div class="modal-actions">
                <button
                  class="btn-primary"
                  :style="{ backgroundColor: selectedService.color }"
                  :disabled="confirming"
                  @click="confirmFromModal"
                >
                  <i :class="confirming ? 'fa fa-spinner fa-spin' : 'fa fa-check-circle'"></i>
                  {{ confirming ? 'Generando turno...' : 'Confirmar Turno' }}
                </button>
                <button class="btn-secondary" @click="showConfirmModal = false">
                  <i class="fa fa-times"></i>
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </transition>

    <!-- ═══════════ MODAL 2: TECLADO NUMÉRICO DNI ═══════════ -->
    <transition name="modal-fade">
      <div
        v-if="selectedService && askCustomerDni"
        class="modal-backdrop"
        @click.self="selectedService = null"
      >
        <transition name="modal-scale" appear>
          <div class="modal-card dni-modal">
            <div class="dni-split">
              <!-- Panel izquierdo (~40%) -->
              <div class="dni-left">
                <div class="dni-band" :style="{ backgroundColor: selectedService.color }"></div>
                <p class="modal-eyebrow">Ingrese su DNI</p>
                <h2 class="dni-service-name" :style="{ color: selectedService.color }">
                  {{ selectedService.name }}
                </h2>

                <div class="dni-display" :class="{ 'dni-display--filled': customerDni.length > 0 }">
                  {{ dniDisplay }}
                </div>

                <div class="dni-left-actions">
                  <button
                    class="btn-primary"
                    :style="{ backgroundColor: selectedService.color }"
                    :disabled="!dniValid || confirming"
                    @click="confirmTurn"
                  >
                    <i :class="confirming ? 'fa fa-spinner fa-spin' : 'fa fa-check-circle'"></i>
                    {{ confirming ? 'Generando...' : 'Confirmar' }}
                  </button>
                  <button class="btn-secondary" @click="selectedService = null">
                    <i class="fa fa-times"></i>
                    Cancelar
                  </button>
                </div>
              </div>

              <!-- Panel derecho (~60%): teclado 3×4 -->
              <div class="dni-right">
                <div class="keypad-grid">
                  <button class="keypad-key" @click="appendDigit(1)">1</button>
                  <button class="keypad-key" @click="appendDigit(2)">2</button>
                  <button class="keypad-key" @click="appendDigit(3)">3</button>
                  <button class="keypad-key" @click="appendDigit(4)">4</button>
                  <button class="keypad-key" @click="appendDigit(5)">5</button>
                  <button class="keypad-key" @click="appendDigit(6)">6</button>
                  <button class="keypad-key" @click="appendDigit(7)">7</button>
                  <button class="keypad-key" @click="appendDigit(8)">8</button>
                  <button class="keypad-key" @click="appendDigit(9)">9</button>
                  <button class="keypad-key keypad-key--action" @click="deleteDigit">
                    <i class="fa fa-delete-left"></i>
                  </button>
                  <button class="keypad-key" @click="appendDigit(0)">0</button>
                  <button
                    class="keypad-key keypad-key--confirm"
                    :style="dniValid ? { backgroundColor: selectedService.color, color: '#fff' } : {}"
                    :disabled="!dniValid"
                    @click="confirmTurn"
                  >
                    <i class="fa fa-check"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </transition>

    <!-- ═══════════ OVERLAY: ERROR DE CONEXIÓN ═══════════ -->
    <transition name="modal-fade">
      <div v-if="showConnectionError" class="connection-error-overlay">
        <div class="connection-error-icon">
          <i class="fa fa-triangle-exclamation"></i>
        </div>
        <h2 class="connection-error-title">No se pudo generar el turno</h2>
        <p class="connection-error-text">Por favor, solicite ayuda a un asistente.</p>
        <div class="connection-error-actions">
          <button
            class="btn-primary connection-error-btn"
            :disabled="confirming"
            @click="confirmTurn"
          >
            <i :class="confirming ? 'fa fa-spinner fa-spin' : 'fa fa-rotate-right'"></i>
            {{ confirming ? 'Reintentando...' : 'Reintentar' }}
          </button>
          <button class="btn-secondary connection-error-btn" @click="cancelConnectionError">
            <i class="fa fa-house"></i>
            Volver al inicio
          </button>
        </div>
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
import { STORAGE_URL } from '../config';

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
const askCustomerDni = ref(true);

// Estado de servicios y turnos
const services = ref([]);
const loading = ref(true);
const selectedService = ref(null);
const customerDni = ref('');
const showConfirmModal = ref(false);
const generatedTurn = ref(null);
const showConnectionError = ref(false);

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
// DNI — formato XX.XXX.XXX, máx 8 dígitos
// ──────────────────────────────
const formatDni = (raw) => {
  const d = String(raw).replace(/\D/g, '').slice(0, 8);
  if (d.length <= 2) return d;
  if (d.length <= 5) return `${d.slice(0, 2)}.${d.slice(2)}`;
  return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5)}`;
};
const dniDisplay = computed(() => (customerDni.value ? formatDni(customerDni.value) : '__.___.___'));
const dniValid = computed(() => customerDni.value.length === 8);
const appendDigit = (d) => { if (customerDni.value.length < 8) customerDni.value += String(d); };
const deleteDigit = () => { customerDni.value = customerDni.value.slice(0, -1); };

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
  return `${STORAGE_URL}/${totemData.value.logo}`;
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
    askCustomerDni.value = response.data.totem.ask_customer_dni !== false;
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

const selectService = (service) => {
  if (confirming.value) return;
  selectedService.value = service;
  customerDni.value = '';
  if (askCustomerDni.value) {
    // Feature 2: el modal del teclado DNI se abre vía v-if (selectedService && askCustomerDni)
    return;
  }
  // Feature 1: pedir confirmación antes de generar el turno
  showConfirmModal.value = true;
};

const confirmFromModal = async () => {
  showConfirmModal.value = false;
  await confirmTurn();
};

// Distingue errores de red/timeout/5xx (servidor inalcanzable) de errores
// de validación (4xx), que se siguen mostrando como toast.
const isConnectionError = (error) => {
  return !error.response || error.response.status >= 500;
};

const confirmTurn = async () => {
  if (confirming.value) return;
  confirming.value = true;
  try {
    const finalCustomerDni = askCustomerDni.value ? (customerDni.value || '') : '';
    const response = await createTurn({
      service_id: selectedService.value.id,
      customer_dni: finalCustomerDni,
      totem_id: totemId.value,
    });
    generatedTurn.value = response.data;
    selectedService.value = null;
    showConnectionError.value = false;
    await printTicket();
  } catch (error) {
    console.error('Error creando turno:', error);
    if (isConnectionError(error)) {
      showConnectionError.value = true;
    } else {
      showError('Error al crear turno');
    }
  } finally {
    confirming.value = false;
  }
};

const cancelConnectionError = () => {
  showConnectionError.value = false;
  selectedService.value = null;
  customerDni.value = '';
  showConfirmModal.value = false;
};

const printTicket = async () => {
  if (!generatedTurn.value) return;
  printing.value = true;
  printError.value = '';
  printSuccess.value = false;
  try {
    const finalCustomerDni = askCustomerDni.value ? (customerDni.value || '') : '';
    const result = await printService.printTicket({
      ticket_number: generatedTurn.value.ticket_number,
      service_name: generatedTurn.value.service.name,
      customer_dni: finalCustomerDni,
    });
    if (result.success) {
      printSuccess.value = true;
    } else {
      throw new Error(result.error || 'Error al imprimir');
    }
  } catch (error) {
    const ticket = generatedTurn.value.ticket_number;
    if (error.message === 'SIN_PAPEL') {
      printError.value = `⚠️ Sin papel en la impresora. Su turno ${ticket} fue registrado — avise a un asistente para recargar la impresora y recibir su comprobante.`;
    } else if (error.message === 'OFFLINE') {
      printError.value = `⚠️ Impresora desconectada. Su turno ${ticket} fue registrado — avise a un asistente para recibir su comprobante.`;
    } else if (error.message === 'ERROR_IMPRESORA') {
      printError.value = `⚠️ Error en la impresora. Su turno ${ticket} fue registrado — avise a un asistente para recibir su comprobante.`;
    } else {
      printError.value = `No se pudo imprimir el ticket. Su turno ${ticket} fue registrado — avise a un asistente para recibir su comprobante.`;
    }
  } finally {
    printing.value = false;
  }
};

const resetAndPrint = async () => { await printTicket(); };

const closeTicketModal = () => {
  generatedTurn.value = null;
  customerDni.value = '';
  showConfirmModal.value = false;
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

/* ═══════════════════════════════════════
   MODAL DNI — SPLIT HORIZONTAL + TECLADO
═══════════════════════════════════════ */
.dni-modal {
  max-width: 880px;
}

.dni-split {
  display: flex;
  align-items: stretch;
  min-height: 480px;
}

/* Panel izquierdo (~40%) */
.dni-left {
  flex: 0 0 40%;
  position: relative;
  padding: 2.5rem 2rem 2rem;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #f1f5f9;
}

.dni-band {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 8px;
}

.dni-service-name {
  font-size: 1.75rem;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.03em;
  margin: 0.25rem 0 1.75rem;
}

.dni-display {
  font-size: 2.6rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: #cbd5e1;
  text-align: center;
  padding: 1.1rem 0.75rem;
  border: 2px dashed #e2e8f0;
  border-radius: 16px;
  background: #f8fafc;
  font-variant-numeric: tabular-nums;
  user-select: none;
}

.dni-display--filled {
  color: #1a202c;
  border-style: solid;
  border-color: #cbd5e1;
  background: #ffffff;
}

.dni-left-actions {
  margin-top: auto;
  padding-top: 1.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

/* Panel derecho (~60%): teclado */
.dni-right {
  flex: 1;
  padding: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.keypad-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: repeat(4, 1fr);
  gap: 1rem;
  width: 100%;
  height: 100%;
}

.keypad-key {
  border: none;
  border-radius: 18px;
  background: #f1f5f9;
  color: #1a202c;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 2.2rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 84px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  transition: transform 0.08s ease, background 0.12s ease;
  -webkit-tap-highlight-color: transparent;
  user-select: none;
}

.keypad-key:active {
  transform: scale(0.92);
  background: #e2e8f0;
}

.keypad-key--action {
  background: #fef3c7;
  color: #b45309;
  font-size: 1.8rem;
}

.keypad-key--action:active {
  background: #fde68a;
}

.keypad-key--confirm {
  background: #dcfce7;
  color: #15803d;
  font-size: 1.8rem;
}

.keypad-key--confirm:active {
  transform: scale(0.92);
}

.keypad-key--confirm:disabled {
  background: #f1f5f9;
  color: #cbd5e1;
  cursor: not-allowed;
  transform: none;
}

/* ═══════════════════════════════════════
   OVERLAY: ERROR DE CONEXIÓN
═══════════════════════════════════════ */
.connection-error-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 2rem;
  color: #ffffff;
}

.connection-error-icon {
  font-size: 5rem;
  color: #ef4444;
  margin-bottom: 1.5rem;
  animation: connection-error-pulse 2s infinite;
}

@keyframes connection-error-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.7; transform: scale(0.95); }
}

.connection-error-title {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0 0 1rem;
}

.connection-error-text {
  font-size: 1.4rem;
  color: rgba(255, 255, 255, 0.8);
  margin: 0 0 2.5rem;
}

.connection-error-actions {
  display: flex;
  gap: 1rem;
  width: 100%;
  max-width: 480px;
}

.connection-error-btn {
  font-size: 1.3rem;
  padding: 1.25rem 1.5rem;
}
</style>
