<template>
  <section class="admin-section">
    <!-- Header -->
    <div class="section-header">
      <div>
        <p class="section-eyebrow">Resumen operativo</p>
        <h2 class="section-title">Dashboard</h2>
      </div>
      <button class="btn-refresh" @click="loadStats" :disabled="loading">
        <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
        Actualizar
      </button>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
      <div class="kpi-card kpi-card--blue">
        <div class="kpi-body">
          <p class="kpi-label">Turnos Hoy</p>
          <p class="kpi-value">{{ stats.today?.total ?? '—' }}</p>
        </div>
        <div class="kpi-icon-wrap kpi-icon-wrap--blue">
          <i class="fas fa-ticket-alt"></i>
        </div>
      </div>
      <div class="kpi-card kpi-card--yellow">
        <div class="kpi-body">
          <p class="kpi-label">En Espera</p>
          <p class="kpi-value">{{ stats.today?.waiting ?? '—' }}</p>
        </div>
        <div class="kpi-icon-wrap kpi-icon-wrap--yellow">
          <i class="fas fa-clock"></i>
        </div>
      </div>
      <div class="kpi-card kpi-card--green">
        <div class="kpi-body">
          <p class="kpi-label">Atendiendo</p>
          <p class="kpi-value">{{ stats.today?.attending ?? '—' }}</p>
        </div>
        <div class="kpi-icon-wrap kpi-icon-wrap--green">
          <i class="fas fa-user-check"></i>
        </div>
      </div>
      <div class="kpi-card kpi-card--purple">
        <div class="kpi-body">
          <p class="kpi-label">Completados</p>
          <p class="kpi-value">{{ stats.today?.completed ?? '—' }}</p>
        </div>
        <div class="kpi-icon-wrap kpi-icon-wrap--purple">
          <i class="fas fa-check-circle"></i>
        </div>
      </div>
      <div class="kpi-card kpi-card--red">
        <div class="kpi-body">
          <p class="kpi-label">Cancelados</p>
          <p class="kpi-value">{{ stats.today?.cancelled ?? '—' }}</p>
        </div>
        <div class="kpi-icon-wrap kpi-icon-wrap--red">
          <i class="fas fa-times-circle"></i>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="charts-row">
      <!-- Bar chart: Turnos por servicio -->
      <div class="chart-card chart-card--wide">
        <div class="chart-card-header">
          <h3 class="chart-title">
            <i class="fas fa-chart-bar chart-title-icon"></i>
            Turnos por Servicio (Hoy)
          </h3>
        </div>
        <div class="chart-body">
          <div v-if="!stats.by_service || stats.by_service.length === 0" class="chart-empty">
            <i class="fas fa-chart-bar chart-empty-icon"></i>
            <p>Sin datos para hoy</p>
          </div>
          <canvas v-else ref="barChartRef" class="chart-canvas"></canvas>
        </div>
      </div>

      <!-- Donut chart: Distribución de estados -->
      <div class="chart-card">
        <div class="chart-card-header">
          <h3 class="chart-title">
            <i class="fas fa-chart-pie chart-title-icon"></i>
            Distribución
          </h3>
        </div>
        <div class="chart-body chart-body--donut">
          <div v-if="!stats.today?.total" class="chart-empty">
            <i class="fas fa-chart-pie chart-empty-icon"></i>
            <p>Sin datos</p>
          </div>
          <template v-else>
            <canvas ref="donutChartRef" class="chart-canvas-donut"></canvas>
            <div class="donut-legend">
              <div class="legend-item">
                <span class="legend-dot" style="background:#facc15"></span>
                <span>Espera <strong>{{ stats.today?.waiting ?? 0 }}</strong></span>
              </div>
              <div class="legend-item">
                <span class="legend-dot" style="background:#22c55e"></span>
                <span>Atendiendo <strong>{{ stats.today?.attending ?? 0 }}</strong></span>
              </div>
              <div class="legend-item">
                <span class="legend-dot" style="background:#a855f7"></span>
                <span>Completados <strong>{{ stats.today?.completed ?? 0 }}</strong></span>
              </div>
              <div class="legend-item">
                <span class="legend-dot" style="background:#ef4444"></span>
                <span>Cancelados <strong>{{ stats.today?.cancelled ?? 0 }}</strong></span>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Bottom Row -->
    <div class="bottom-row">
      <!-- Tiempo promedio -->
      <div class="info-card">
        <div class="info-card-header">
          <i class="fas fa-stopwatch info-card-icon" style="color:#f97316"></i>
          <h3 class="info-card-title">Tiempo Promedio</h3>
        </div>
        <div class="avg-time-display">
          <span class="avg-time-value">{{ stats.avg_wait_time ?? '—' }}</span>
          <span class="avg-time-unit">min de espera</span>
        </div>
      </div>

      <!-- Últimos atendidos -->
      <div class="info-card info-card--wide">
        <div class="info-card-header">
          <i class="fas fa-history info-card-icon" style="color:#22c55e"></i>
          <h3 class="info-card-title">Últimos Atendidos</h3>
        </div>
        <div v-if="!stats.recent_completed || stats.recent_completed.length === 0" class="info-empty">
          Sin turnos completados
        </div>
        <div v-else class="recent-list">
          <div v-for="(turn, idx) in stats.recent_completed" :key="idx" class="recent-item">
            <span class="recent-ticket">{{ turn.ticket_number }}</span>
            <span class="recent-service">{{ turn.service_name }}</span>
            <span class="recent-time">{{ turn.completed_at }}</span>
          </div>
        </div>
      </div>

      <!-- Sistema -->
      <div class="info-card">
        <div class="info-card-header">
          <i class="fas fa-cogs info-card-icon" style="color:#64748b"></i>
          <h3 class="info-card-title">Sistema</h3>
        </div>
        <div class="system-stats">
          <div class="system-stat">
            <span class="system-stat-value" style="color:#3b82f6">{{ stats.totals?.services ?? '—' }}</span>
            <span class="system-stat-label">Servicios</span>
          </div>
          <div class="system-stat">
            <span class="system-stat-value" style="color:#22c55e">{{ stats.totals?.operators ?? '—' }}</span>
            <span class="system-stat-label">Operadores</span>
          </div>
          <div class="system-stat">
            <span class="system-stat-value" style="color:#a855f7">{{ stats.totals?.profiles ?? '—' }}</span>
            <span class="system-stat-label">Perfiles</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { getDashboardStats } from '../../api'
import { useToast } from '../../composables/useToast'

const { showToast } = useToast()

const stats = ref({})
const loading = ref(false)
const barChartRef = ref(null)
const donutChartRef = ref(null)

let barInstance = null
let donutInstance = null

const loadStats = async () => {
  loading.value = true
  try {
    const res = await getDashboardStats()
    stats.value = res.data
  } catch {
    showToast('Error al cargar estadísticas', 'error')
  } finally {
    loading.value = false
  }
}

const destroyCharts = () => {
  if (barInstance) { barInstance.destroy(); barInstance = null }
  if (donutInstance) { donutInstance.destroy(); donutInstance = null }
}

const buildCharts = () => {
  if (!window.Chart) return

  destroyCharts()

  // Bar chart — turnos por servicio
  if (barChartRef.value && stats.value.by_service?.length) {
    const labels = stats.value.by_service.map((s) => s.service_name)
    const totals = stats.value.by_service.map((s) => s.total)
    const completed = stats.value.by_service.map((s) => s.completed)
    const waiting = stats.value.by_service.map((s) => s.waiting)
    const colors = stats.value.by_service.map((s) => s.service_color || '#3b82f6')

    barInstance = new window.Chart(barChartRef.value, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Completados',
            data: completed,
            backgroundColor: colors.map((c) => c + 'cc'),
            borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
            borderSkipped: 'bottom',
          },
          {
            label: 'En espera',
            data: waiting,
            backgroundColor: colors.map((c) => c + '44'),
            borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
            borderSkipped: 'bottom',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
            labels: {
              font: { family: 'Plus Jakarta Sans', size: 12 },
              boxWidth: 12,
              boxHeight: 12,
              padding: 16,
            },
          },
          tooltip: {
            callbacks: {
              title: (items) => items[0].label,
              label: (item) => ` ${item.dataset.label}: ${item.raw}`,
            },
          },
        },
        scales: {
          x: {
            stacked: false,
            grid: { display: false },
            ticks: { font: { family: 'Plus Jakarta Sans', size: 12 } },
          },
          y: {
            beginAtZero: true,
            grid: { color: '#f1f5f9' },
            ticks: {
              stepSize: 1,
              font: { family: 'Plus Jakarta Sans', size: 12 },
            },
          },
        },
      },
    })
  }

  // Donut chart — distribución de estados
  if (donutChartRef.value && stats.value.today?.total) {
    const { waiting = 0, attending = 0, completed = 0, cancelled = 0 } = stats.value.today

    donutInstance = new window.Chart(donutChartRef.value, {
      type: 'doughnut',
      data: {
        labels: ['En Espera', 'Atendiendo', 'Completados', 'Cancelados'],
        datasets: [
          {
            data: [waiting, attending, completed, cancelled],
            backgroundColor: ['#facc15', '#22c55e', '#a855f7', '#ef4444'],
            borderColor: '#ffffff',
            borderWidth: 3,
            hoverOffset: 6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (item) => ` ${item.label}: ${item.raw}`,
            },
          },
        },
      },
    })
  }
}

watch(
  stats,
  async () => {
    await nextTick()
    buildCharts()
  },
  { deep: true }
)

onMounted(async () => {
  await loadStats()
  await nextTick()
  buildCharts()
})

onUnmounted(destroyCharts)
</script>

<style scoped>
/* ── Layout ── */
.admin-section {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  font-family: 'Plus Jakarta Sans', sans-serif;
}

.section-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
}

.section-eyebrow {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #94a3b8;
  margin: 0 0 0.2rem;
}

.section-title {
  font-size: 1.75rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.03em;
  margin: 0;
}

.btn-refresh {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.55rem 1.1rem;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #ffffff;
  color: #475569;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}

.btn-refresh:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.btn-refresh:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* ── KPI Cards ── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 1rem;
}

.kpi-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 1.25rem 1.25rem 1.25rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 1px solid #f1f5f9;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
  position: relative;
  overflow: hidden;
}

.kpi-card::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  border-radius: 16px 0 0 16px;
}

.kpi-card--blue::before  { background: #3b82f6; }
.kpi-card--yellow::before{ background: #facc15; }
.kpi-card--green::before { background: #22c55e; }
.kpi-card--purple::before{ background: #a855f7; }
.kpi-card--red::before   { background: #ef4444; }

.kpi-body {
  display: flex;
  flex-direction: column;
}

.kpi-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0 0 0.25rem;
}

.kpi-value {
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  margin: 0;
}

.kpi-card--blue  .kpi-value { color: #3b82f6; }
.kpi-card--yellow .kpi-value{ color: #ca8a04; }
.kpi-card--green .kpi-value { color: #16a34a; }
.kpi-card--purple .kpi-value{ color: #9333ea; }
.kpi-card--red   .kpi-value { color: #dc2626; }

.kpi-icon-wrap {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.kpi-icon-wrap--blue   { background: #eff6ff; color: #3b82f6; }
.kpi-icon-wrap--yellow { background: #fefce8; color: #ca8a04; }
.kpi-icon-wrap--green  { background: #f0fdf4; color: #16a34a; }
.kpi-icon-wrap--purple { background: #faf5ff; color: #9333ea; }
.kpi-icon-wrap--red    { background: #fef2f2; color: #dc2626; }

/* ── Charts ── */
.charts-row {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1rem;
}

.chart-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
  overflow: hidden;
}

.chart-card-header {
  padding: 1.25rem 1.5rem 0;
}

.chart-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.chart-title-icon {
  color: #3b82f6;
  font-size: 0.9rem;
}

.chart-body {
  padding: 1rem 1.5rem 1.5rem;
  height: 220px;
  position: relative;
}

.chart-body--donut {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  height: auto;
  padding-bottom: 1.25rem;
}

.chart-canvas {
  width: 100% !important;
  height: 100% !important;
}

.chart-canvas-donut {
  width: 160px !important;
  height: 160px !important;
}

.chart-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #cbd5e1;
  gap: 0.75rem;
}

.chart-empty-icon {
  font-size: 2rem;
}

.chart-empty p {
  font-size: 0.9rem;
  margin: 0;
}

.donut-legend {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  width: 100%;
  padding: 0 0.5rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  color: #475569;
}

.legend-item strong {
  color: #0f172a;
  margin-left: auto;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* ── Bottom Row ── */
.bottom-row {
  display: grid;
  grid-template-columns: 200px 1fr 240px;
  gap: 1rem;
}

.info-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
  padding: 1.25rem 1.5rem;
}

.info-card-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.info-card-icon {
  font-size: 1rem;
}

.info-card-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.avg-time-display {
  text-align: center;
  padding: 0.75rem 0;
}

.avg-time-value {
  display: block;
  font-size: 3rem;
  font-weight: 900;
  color: #f97316;
  letter-spacing: -0.04em;
  line-height: 1;
}

.avg-time-unit {
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 500;
}

.info-empty {
  text-align: center;
  color: #cbd5e1;
  font-size: 0.85rem;
  padding: 0.5rem 0;
}

.recent-list {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.recent-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.55rem 0;
  border-bottom: 1px solid #f8fafc;
  font-size: 0.85rem;
}

.recent-item:last-child {
  border-bottom: none;
}

.recent-ticket {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  font-weight: 700;
  color: #0f172a;
  min-width: 60px;
}

.recent-service {
  flex: 1;
  color: #475569;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.recent-time {
  color: #94a3b8;
  font-size: 0.75rem;
  white-space: nowrap;
}

.system-stats {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 0.25rem;
}

.system-stat {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.625rem 0.875rem;
  background: #f8fafc;
  border-radius: 10px;
}

.system-stat-value {
  font-size: 1.4rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1;
}

.system-stat-label {
  font-size: 0.72rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
</style>
