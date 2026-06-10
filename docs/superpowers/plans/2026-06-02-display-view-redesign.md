# Display View Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar el template y los estilos de `DisplayView.vue` con el diseño claro para TV: barra superior, panel de turnos 70% y zona de video placeholder 30%.

**Architecture:** Un único archivo Vue modificado. La lógica `<script setup>` se mantiene intacta excepto por eliminar el computed `waitingByService` que ya no se renderiza. El template y `<style scoped>` se reescriben completos.

**Tech Stack:** Vue 3 Composition API, CSS custom properties, `clamp()` para tipografía responsiva, `Plus Jakarta Sans`.

---

### Task 1: Limpiar el script — eliminar computed no usado

**Files:**
- Modify: `frontend/src/views/DisplayView.vue`

- [ ] **Step 1: Abrir el archivo y localizar el computed `waitingByService`**

Está en `frontend/src/views/DisplayView.vue` alrededor de la línea 179–191:

```javascript
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
```

- [ ] **Step 2: Eliminar ese bloque completo**

El resto del script (refs, `lastCalledTurn`, `playNotificationSound`, `loadQueue`, `connectSSE`, `updateClock`, `formatTime`, `onMounted`, `onUnmounted`) se mantiene exactamente igual. No tocar nada más del script.

- [ ] **Step 3: Verificar que el archivo no referencia `waitingByService` en ningún otro lugar**

Buscar en el archivo: si no aparece en el template aún (no lo reemplazamos todavía), el script compila sin errores. El template viejo sí lo usa, pero lo vamos a reemplazar en el paso siguiente.

---

### Task 2: Reemplazar el `<template>` completo

**Files:**
- Modify: `frontend/src/views/DisplayView.vue`

- [ ] **Step 1: Reemplazar TODO el bloque `<template>...</template>` con el siguiente código**

```html
<template>
  <div class="display-root">

    <!-- ═══════════ TOP BAR ═══════════ -->
    <div class="top-bar">
      <div class="bar-clock">{{ currentTime }}</div>
      <div class="bar-date">{{ currentDate }}</div>
      <div class="bar-weather">
        <div class="bar-weather-pill">
          <span class="weather-icon">⛅</span>
          <span class="weather-temp">–°C</span>
          <span class="weather-city">–</span>
        </div>
      </div>
    </div>

    <!-- ═══════════ MAIN AREA ═══════════ -->
    <div class="main-area">

      <!-- ══════════════════════════════════
           PANEL TURNOS — 70%
      ══════════════════════════════════ -->
      <section class="turns-panel">

        <!-- HERO: turno actualmente llamado -->
        <div
          v-if="lastCalledTurn"
          class="hero-card"
          :class="{ 'hero-card--pulsing': lastCalledTurn.status === 'called' }"
          :style="{ '--svc-color': lastCalledTurn.service?.color || '#2563eb' }"
        >
          <div class="hero-body">
            <div class="hero-eyebrow">
              <span class="eyebrow-dot"></span>
              <span class="eyebrow-text">Llamando ahora</span>
            </div>
            <div class="hero-service">{{ lastCalledTurn.service?.name }}</div>
            <div class="hero-number">{{ lastCalledTurn.ticket_number }}</div>
            <div class="hero-station" v-if="lastCalledTurn.window">
              {{ lastCalledTurn.window.name }}
            </div>
            <div class="hero-time">Llamado a las {{ formatTime(lastCalledTurn.called_at) }}</div>
          </div>
          <div class="hero-glow"></div>
        </div>

        <!-- Estado vacío -->
        <div v-else class="hero-empty">
          <div class="hero-empty-icon">📡</div>
          <p class="hero-empty-text">Esperando próximo llamado...</p>
        </div>

        <!-- HISTORIAL: últimos 3 turnos llamados anteriores -->
        <div class="history-section" v-if="recentCalled.length > 1">
          <div class="history-label">Llamados anteriores</div>
          <div class="history-list">
            <div
              v-for="turn in recentCalled.slice(1, 4)"
              :key="turn.id"
              class="history-card"
              :style="{ '--svc-color': turn.service?.color || '#2563eb' }"
            >
              <span class="hist-ticket">{{ turn.ticket_number }}</span>
              <span class="hist-service">{{ turn.service?.name }}</span>
              <span class="hist-station" v-if="turn.window">{{ turn.window.name }}</span>
              <span class="hist-time">{{ formatTime(turn.called_at) }}</span>
            </div>
          </div>
        </div>

      </section>

      <!-- ══════════════════════════════════
           PANEL VIDEO — 30%
      ══════════════════════════════════ -->
      <aside class="video-panel">
        <div class="video-placeholder">
          <div class="video-icon">▶</div>
          <div class="video-title">Tu video aquí</div>
          <div class="video-hint">
            Subí tu video desde el<br>panel de administración
          </div>
          <div class="video-resolution">
            <span>Resolución ideal</span>
            <strong>576 × 1020 px</strong>
          </div>
        </div>
      </aside>

    </div>
  </div>
</template>
```

---

### Task 3: Reemplazar el `<style scoped>` completo

**Files:**
- Modify: `frontend/src/views/DisplayView.vue`

- [ ] **Step 1: Reemplazar TODO el bloque `<style scoped>...</style>` con el siguiente código**

```css
<style scoped>
/* ═══════════════════════════════════════
   FUENTE — aplicada a todo el texto
═══════════════════════════════════════ */
.display-root,
.display-root div,
.display-root p,
.display-root span,
.display-root section,
.display-root aside {
  font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
}

/* ═══════════════════════════════════════
   CSS VARIABLES — tema claro
═══════════════════════════════════════ */
.display-root {
  --bg-root:       #f8fafc;
  --bg-card:       #ffffff;
  --bg-topbar:     #1e40af;
  --bg-video:      #1e293b;
  --text-primary:  #0f172a;
  --text-secondary:#334155;
  --text-muted:    #64748b;
  --text-faint:    #94a3b8;
  --divider:       #e2e8f0;
}

/* ═══════════════════════════════════════
   LAYOUT RAÍZ
═══════════════════════════════════════ */
.display-root {
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: var(--bg-root);
  margin: 0;
  padding: 0;
}

/* ═══════════════════════════════════════
   TOP BAR
═══════════════════════════════════════ */
.top-bar {
  flex: 0 0 7%;
  background: var(--bg-topbar);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 2.2%;
}

.bar-clock {
  font-size: clamp(14px, 2.4vw, 28px);
  font-weight: 900;
  color: #ffffff;
  letter-spacing: 0.04em;
  font-variant-numeric: tabular-nums;
}

.bar-date {
  font-size: clamp(8px, 1vw, 13px);
  font-weight: 600;
  color: #bfdbfe;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.bar-weather-pill {
  background: rgba(255, 255, 255, 0.13);
  border-radius: 999px;
  padding: 0.25em 0.9em;
  display: flex;
  align-items: center;
  gap: 0.5em;
}

.weather-icon {
  font-size: clamp(12px, 1.3vw, 18px);
}

.weather-temp {
  font-size: clamp(11px, 1.4vw, 18px);
  font-weight: 900;
  color: #fef08a;
}

.weather-city {
  font-size: clamp(7px, 0.8vw, 11px);
  font-weight: 600;
  color: #bfdbfe;
  letter-spacing: 0.08em;
}

/* ═══════════════════════════════════════
   MAIN AREA
═══════════════════════════════════════ */
.main-area {
  flex: 1;
  display: flex;
  flex-direction: row;
  overflow: hidden;
  background: var(--bg-root);
}

/* ═══════════════════════════════════════
   TURNS PANEL — 70%
═══════════════════════════════════════ */
.turns-panel {
  flex: 0 0 70%;
  display: flex;
  flex-direction: column;
  padding: 2.2% 2.5% 1.8% 2.5%;
  border-right: 1px solid var(--divider);
  overflow: hidden;
}

/* ─── HERO CARD ─── */
.hero-card {
  flex: 1;
  background: var(--bg-card);
  border-radius: 14px;
  border-left: 7px solid var(--svc-color, #2563eb);
  box-shadow: 0 2px 16px rgba(0, 0, 0, 0.07);
  display: flex;
  overflow: hidden;
  position: relative;
  margin-bottom: 2%;
  min-height: 0;
}

.hero-card--pulsing {
  animation: heroPulse 2.5s ease-in-out infinite;
}

@keyframes heroPulse {
  0%, 100% {
    box-shadow:
      0 2px 16px rgba(0, 0, 0, 0.07);
  }
  50% {
    box-shadow:
      0 4px 28px rgba(0, 0, 0, 0.12),
      0 0 0 4px color-mix(in srgb, var(--svc-color, #2563eb) 12%, transparent);
  }
}

.hero-body {
  padding: 5% 6%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  flex: 1;
  position: relative;
  z-index: 1;
}

.hero-eyebrow {
  display: flex;
  align-items: center;
  gap: 0.6em;
  margin-bottom: 1.5%;
}

.eyebrow-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--svc-color, #2563eb);
  flex-shrink: 0;
  animation: dotPulse 1.6s ease-in-out infinite;
}

@keyframes dotPulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.4; transform: scale(1.45); }
}

.eyebrow-text {
  font-size: clamp(8px, 0.85vw, 11px);
  font-weight: 800;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--text-muted);
}

.hero-service {
  font-size: clamp(12px, 1.4vw, 18px);
  font-weight: 700;
  color: var(--svc-color, #2563eb);
  margin-bottom: 0.5%;
}

.hero-number {
  font-size: clamp(52px, 8.5vw, 96px);
  font-weight: 900;
  color: var(--text-primary);
  line-height: 1;
  letter-spacing: -0.03em;
  margin-bottom: 2.5%;
}

.hero-station {
  font-size: clamp(16px, 2vw, 28px);
  font-weight: 900;
  color: var(--text-secondary);
  letter-spacing: -0.01em;
}

.hero-time {
  font-size: clamp(8px, 0.8vw, 10px);
  font-weight: 500;
  color: var(--text-faint);
  margin-top: auto;
  padding-top: 3%;
}

.hero-glow {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  background: radial-gradient(
    ellipse at 88% 50%,
    color-mix(in srgb, var(--svc-color, #2563eb) 7%, transparent) 0%,
    transparent 60%
  );
}

/* Estado vacío */
.hero-empty {
  flex: 1;
  background: var(--bg-card);
  border-radius: 14px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1.2rem;
  opacity: 0.4;
  margin-bottom: 2%;
}

.hero-empty-icon {
  font-size: clamp(32px, 4vw, 56px);
}

.hero-empty-text {
  font-size: clamp(12px, 1.5vw, 20px);
  font-weight: 600;
  color: var(--text-muted);
  margin: 0;
}

/* ─── HISTORIAL ─── */
.history-section {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 1.5%;
}

.history-label {
  font-size: clamp(6px, 0.65vw, 9px);
  font-weight: 700;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--text-faint);
  margin-bottom: 0.5%;
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 1.2%;
}

.history-card {
  background: var(--bg-card);
  border-radius: 10px;
  border-left: 5px solid var(--svc-color, #2563eb);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  padding: 1.8% 3%;
  display: flex;
  align-items: center;
  gap: 3%;
}

.hist-ticket {
  font-size: clamp(13px, 1.6vw, 20px);
  font-weight: 900;
  color: var(--text-primary);
  min-width: 20%;
}

.hist-service {
  font-size: clamp(8px, 0.9vw, 12px);
  font-weight: 600;
  color: var(--text-muted);
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.hist-station {
  font-size: clamp(8px, 0.9vw, 12px);
  font-weight: 700;
  color: var(--text-secondary);
  white-space: nowrap;
}

.hist-time {
  font-size: clamp(7px, 0.72vw, 9px);
  font-weight: 500;
  color: var(--text-faint);
  flex-shrink: 0;
}

/* ═══════════════════════════════════════
   VIDEO PANEL — 30%
═══════════════════════════════════════ */
.video-panel {
  flex: 1;
  background: var(--bg-video);
  display: flex;
  align-items: center;
  justify-content: center;
}

.video-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.8em;
  padding: 2em;
  text-align: center;
}

.video-icon {
  font-size: clamp(28px, 4vw, 56px);
  color: #334155;
  line-height: 1;
}

.video-title {
  font-size: clamp(9px, 1vw, 14px);
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: #475569;
}

.video-hint {
  font-size: clamp(7px, 0.75vw, 10px);
  font-weight: 500;
  color: #334155;
  line-height: 1.7;
}

.video-resolution {
  background: #0f172a;
  border: 1px solid #334155;
  border-radius: 8px;
  padding: 0.5em 1.1em;
  margin-top: 0.4em;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2em;
}

.video-resolution span {
  font-size: clamp(7px, 0.72vw, 9px);
  font-weight: 600;
  letter-spacing: 0.1em;
  color: #64748b;
}

.video-resolution strong {
  font-size: clamp(9px, 1vw, 13px);
  font-weight: 700;
  color: #94a3b8;
}
</style>
```

---

### Task 4: Verificar en el navegador

**Files:** ninguno — solo verificación visual

- [ ] **Step 1: Correr el servidor de desarrollo si no está corriendo**

```bash
cd frontend
npm run dev
```

Abrir `http://192.168.0.51:5173/display` (o la IP configurada) en el navegador a pantalla completa.

- [ ] **Step 2: Verificar checklist visual**

- [ ] Barra superior azul oscura con reloj, fecha y "–°C" visible
- [ ] Fondo del área principal blanco/gris claro (no negro)
- [ ] Si hay turnos en cola: el hero muestra número grande en texto oscuro, borde izquierdo en color del servicio, y el nombre de la estación sin "Ventanilla" prefijo
- [ ] Si no hay turnos: se ve el estado vacío con icono 📡 y texto tenue
- [ ] Los cards del historial tienen borde en el color del servicio correspondiente
- [ ] Panel derecho oscuro con ▶ y "576 × 1020 px"
- [ ] El reloj avanza segundo a segundo
- [ ] Abrir otras rutas (`/`, `/login`) y confirmar que no hay errores JS en consola

- [ ] **Step 3: Confirmar que no hay warnings en la consola de Vite**

Si aparece `waitingByService is not defined` → el computed no fue eliminado correctamente en Task 1. Volver y eliminarlo.

---

### Task 5: Commit

- [ ] **Step 1: Commitear**

```bash
cd frontend
git add src/views/DisplayView.vue
git commit -m "feat(display): redesign for light theme TV display

- Light color scheme (#f8fafc bg, white cards, blue top bar)
- Top bar: clock + date + weather placeholder
- Turns panel 70%: large hero card + 3-item history
- Window name shows window.name directly, no fixed label
- Video panel 30%: 9:16 placeholder with resolution hint
- Removes waitingByService and service breakdown section

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```
