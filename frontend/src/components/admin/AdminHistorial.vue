<template>
  <section class="admin-section">
    <div class="section-header">
      <div>
        <p class="section-eyebrow">Registros</p>
        <h2 class="section-title">Historial de Turnos</h2>
      </div>
      <span class="total-badge">{{ pagination.total }} registros</span>
    </div>

    <!-- Filtros -->
    <div class="filter-card">
      <div class="filter-grid">
        <div class="filter-field">
          <label class="field-label">Nº Ticket</label>
          <input v-model="filters.search" type="text" placeholder="Ej: AC-001" class="field-input" @keyup.enter="applyFilters" />
        </div>
        <div class="filter-field">
          <label class="field-label">Estado</label>
          <select v-model="filters.status" class="field-select">
            <option value="">Todos</option>
            <option value="waiting">En espera</option>
            <option value="called">Llamado</option>
            <option value="in_progress">En atención</option>
            <option value="completed">Completado</option>
            <option value="cancelled">Cancelado</option>
            <option value="no_show">No se presentó</option>
          </select>
        </div>
        <div class="filter-field">
          <label class="field-label">Operador</label>
          <select v-model="filters.operator_id" class="field-select">
            <option value="">Todos</option>
            <option v-for="op in operators" :key="op.id" :value="op.id">{{ op.name }}</option>
          </select>
        </div>
        <div class="filter-field">
          <label class="field-label">Servicio</label>
          <select v-model="filters.service_id" class="field-select">
            <option value="">Todos</option>
            <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="filter-field">
          <label class="field-label">Desde</label>
          <input v-model="filters.date_from" type="date" class="field-input" />
        </div>
        <div class="filter-field">
          <label class="field-label">Hasta</label>
          <input v-model="filters.date_to" type="date" class="field-input" />
        </div>
      </div>
      <div class="filter-actions">
        <button class="btn-primary-sm" @click="applyFilters">
          <i class="fas fa-search"></i> Buscar
        </button>
        <button class="btn-ghost-sm" @click="clearFilters">
          <i class="fas fa-times"></i> Limpiar
        </button>
        <button class="btn-ghost-sm" @click="loadHistory(pagination.current_page)" :disabled="loading">
          <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i> Actualizar
        </button>
      </div>
    </div>

    <!-- Tabla -->
    <div class="table-card">
      <table class="data-table">
        <thead>
          <tr>
            <th class="th-expand"></th>
            <th>Ticket</th>
            <th>Servicio</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Operador</th>
            <th>Espera</th>
            <th>Atención</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="9" class="td-loading">
              <i class="fas fa-spinner fa-spin"></i>
              <span>Cargando historial...</span>
            </td>
          </tr>
          <tr v-else-if="historyData.length === 0">
            <td colspan="9" class="td-empty">
              <i class="fas fa-inbox td-empty-icon"></i>
              <p>No hay turnos que mostrar</p>
            </td>
          </tr>
          <template v-else v-for="turn in historyData" :key="turn.id">
            <!-- Fila principal -->
            <tr class="table-row" :class="{ 'table-row--expanded': expandedTurn === turn.id }" @click="toggleExpand(turn.id)">
              <td class="td-expand">
                <i class="fas" :class="expandedTurn === turn.id ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
              </td>
              <td>
                <span class="ticket-mono">{{ turn.ticket_number }}</span>
                <span v-if="turn.priority === 'priority'" class="badge-priority">Prioritario</span>
              </td>
              <td>
                <div class="service-cell">
                  <span class="service-dot" :style="{ backgroundColor: turn.service?.color || '#94a3b8' }"></span>
                  <span>{{ turn.service?.name || 'N/A' }}</span>
                </div>
              </td>
              <td class="td-muted">{{ turn.customer_name || '—' }}</td>
              <td>
                <span class="status-badge" :class="getStatusColor(turn.status)">{{ getStatusLabel(turn.status) }}</span>
              </td>
              <td class="td-muted">{{ turn.operator?.name || '—' }}</td>
              <td class="td-muted">{{ formatDuration(turn.wait_time) }}</td>
              <td class="td-muted">{{ formatDuration(turn.attention_time) }}</td>
              <td class="td-muted">{{ turn.created_at?.split(' ')[0] }}</td>
            </tr>

            <!-- Fila expandida: timeline -->
            <tr v-if="expandedTurn === turn.id" class="timeline-row">
              <td colspan="9">
                <div class="timeline-container">
                  <div class="timeline-left">
                    <h4 class="timeline-heading"><i class="fas fa-stream"></i> Línea de Tiempo</h4>
                    <div class="timeline-track">
                      <div class="timeline-line"></div>
                      <div v-for="(event, idx) in turn.timeline" :key="idx" class="timeline-event">
                        <div class="timeline-dot" :class="getTimelineIconColor(event.color)">
                          <i :class="`fas fa-${event.icon}`"></i>
                        </div>
                        <div class="timeline-content">
                          <div class="timeline-content-header">
                            <span class="timeline-label">{{ event.label }}</span>
                            <span class="timeline-time">{{ event.time_display }}</span>
                          </div>
                          <p class="timeline-desc">{{ event.description }}</p>
                          <p v-if="event.duration" class="timeline-duration">
                            <i class="fas fa-clock"></i> {{ event.duration }}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="timeline-right">
                    <h4 class="timeline-heading"><i class="fas fa-info-circle"></i> Detalles</h4>
                    <div class="detail-card">
                      <div class="detail-row">
                        <span class="detail-label">Totem</span>
                        <span class="detail-value">{{ turn.totem?.name || '—' }}</span>
                      </div>
                      <div class="detail-row">
                        <span class="detail-label">Ventanilla</span>
                        <span class="detail-value">{{ turn.window?.name || '—' }}</span>
                      </div>
                      <div class="detail-row">
                        <span class="detail-label">Tiempo total</span>
                        <span class="detail-value">{{ formatDuration(turn.total_time) }}</span>
                      </div>
                    </div>
                    <div v-if="turn.notes" class="notes-card">
                      <p class="notes-label"><i class="fas fa-comment"></i> Notas</p>
                      <p class="notes-text">{{ turn.notes }}</p>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div v-if="pagination.last_page > 1" class="pagination">
      <span class="pagination-info">
        {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }}–{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
        de {{ pagination.total }}
      </span>
      <div class="pagination-controls">
        <button @click="goToPage(1)" :disabled="pagination.current_page === 1" class="page-btn">
          <i class="fas fa-angle-double-left"></i>
        </button>
        <button @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="page-btn">
          <i class="fas fa-angle-left"></i>
        </button>
        <template v-for="page in pagination.last_page" :key="page">
          <button
            v-if="page === 1 || page === pagination.last_page || (page >= pagination.current_page - 2 && page <= pagination.current_page + 2)"
            @click="goToPage(page)"
            class="page-btn"
            :class="{ 'page-btn--active': page === pagination.current_page }"
          >{{ page }}</button>
          <span v-else-if="page === pagination.current_page - 3 || page === pagination.current_page + 3" class="page-ellipsis">…</span>
        </template>
        <button @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="page-btn">
          <i class="fas fa-angle-right"></i>
        </button>
        <button @click="goToPage(pagination.last_page)" :disabled="pagination.current_page === pagination.last_page" class="page-btn">
          <i class="fas fa-angle-double-right"></i>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getHistory, getOperators, getServices } from '../../api'
import { useToast } from '../../composables/useToast'

const { showToast } = useToast()

const historyData = ref([])
const loading = ref(false)
const expandedTurn = ref(null)
const operators = ref([])
const services = ref([])

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
})

const filters = reactive({
  search: '',
  status: '',
  operator_id: '',
  service_id: '',
  date_from: '',
  date_to: '',
})

const loadHistory = async (page = 1) => {
  loading.value = true
  try {
    const params = { page, per_page: pagination.value.per_page, ...filters }
    Object.keys(params).forEach((k) => {
      if (params[k] === '' || params[k] === null) delete params[k]
    })
    const res = await getHistory(params)
    historyData.value = res.data.data
    pagination.value = {
      current_page: res.data.current_page,
      last_page: res.data.last_page,
      per_page: res.data.per_page,
      total: res.data.total,
    }
  } catch {
    showToast('Error al cargar historial', 'error')
  } finally {
    loading.value = false
  }
}

const applyFilters = () => loadHistory(1)

const clearFilters = () => {
  filters.search = ''
  filters.status = ''
  filters.operator_id = ''
  filters.service_id = ''
  filters.date_from = ''
  filters.date_to = ''
  loadHistory(1)
}

const goToPage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) loadHistory(page)
}

const toggleExpand = (id) => {
  expandedTurn.value = expandedTurn.value === id ? null : id
}

// ── Utilidades ─────────────────────────────
const getStatusLabel = (status) => {
  const map = {
    waiting: 'En espera', called: 'Llamado', in_progress: 'En atención',
    completed: 'Completado', cancelled: 'Cancelado', no_show: 'No se presentó',
  }
  return map[status] || status
}

const getStatusColor = (status) => {
  const map = {
    waiting:     'status--yellow',
    called:      'status--blue',
    in_progress: 'status--green',
    completed:   'status--purple',
    cancelled:   'status--red',
    no_show:     'status--gray',
  }
  return map[status] || 'status--gray'
}

const getTimelineIconColor = (color) => {
  const map = {
    blue: 'dot--blue', yellow: 'dot--yellow', green: 'dot--green',
    purple: 'dot--purple', red: 'dot--red', gray: 'dot--gray',
  }
  return map[color] || 'dot--gray'
}

const formatDuration = (minutes) => {
  if (minutes === null || minutes === undefined) return '—'
  const m = Math.round(minutes)
  if (m < 60) return `${m} min`
  const h = Math.floor(m / 60)
  const rem = m % 60
  return rem > 0 ? `${h}h ${rem}m` : `${h}h`
}

onMounted(async () => {
  await Promise.all([
    loadHistory(),
    getOperators().then((r) => { operators.value = r.data }).catch(() => {}),
    getServices().then((r) => { services.value = r.data }).catch(() => {}),
  ])
})
</script>

<style scoped>
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

.total-badge {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
}

/* ── Filter card ── */
.filter-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  padding: 1.25rem 1.5rem;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 0.875rem;
  margin-bottom: 1rem;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.field-label {
  font-size: 0.72rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.field-input,
.field-select {
  padding: 0.5rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  color: #0f172a;
  background: #f8fafc;
  outline: none;
  transition: border-color 0.15s, background 0.15s;
}

.field-input:focus,
.field-select:focus {
  border-color: #3b82f6;
  background: #fff;
}

.filter-actions {
  display: flex;
  gap: 0.6rem;
}

.btn-primary-sm {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  background: #3b82f6;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-primary-sm:hover { background: #2563eb; }

.btn-ghost-sm {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1rem;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-ghost-sm:hover { background: #e2e8f0; }
.btn-ghost-sm:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Table ── */
.table-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table thead tr {
  background: #f8fafc;
  border-bottom: 1px solid #f1f5f9;
}

.data-table th {
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.72rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  white-space: nowrap;
}

.th-expand { width: 40px; }

.table-row {
  border-bottom: 1px solid #f8fafc;
  cursor: pointer;
  transition: background 0.1s;
}

.table-row:hover { background: #f8fafc; }
.table-row--expanded { background: #eff6ff !important; }

.data-table td {
  padding: 0.875rem 1rem;
  font-size: 0.875rem;
}

.td-expand {
  text-align: center;
  color: #94a3b8;
  font-size: 0.75rem;
  width: 40px;
}

.ticket-mono {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  font-weight: 700;
  font-size: 0.95rem;
  color: #0f172a;
}

.badge-priority {
  margin-left: 0.4rem;
  font-size: 0.65rem;
  background: #fef2f2;
  color: #dc2626;
  padding: 0.15rem 0.45rem;
  border-radius: 20px;
  font-weight: 600;
}

.service-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.service-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.td-muted { color: #64748b; }

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.65rem;
  border-radius: 20px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.status--yellow { background: #fef9c3; color: #92400e; }
.status--blue   { background: #dbeafe; color: #1d4ed8; }
.status--green  { background: #dcfce7; color: #15803d; }
.status--purple { background: #f3e8ff; color: #7e22ce; }
.status--red    { background: #fee2e2; color: #b91c1c; }
.status--gray   { background: #f1f5f9; color: #475569; }

.td-loading, .td-empty {
  text-align: center;
  padding: 3rem;
  color: #94a3b8;
}

.td-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.td-empty-icon { font-size: 2rem; display: block; margin-bottom: 0.5rem; }

/* ── Timeline expanded row ── */
.timeline-row {
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.timeline-container {
  padding: 1.5rem 1.5rem 1.5rem 2.5rem;
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 2rem;
}

.timeline-heading {
  font-size: 0.8rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0 0 1rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.timeline-track {
  position: relative;
  padding-left: 1.75rem;
}

.timeline-line {
  position: absolute;
  left: 8px;
  top: 4px;
  bottom: 4px;
  width: 2px;
  background: #e2e8f0;
  border-radius: 1px;
}

.timeline-event {
  position: relative;
  margin-bottom: 1rem;
  display: flex;
  gap: 0.75rem;
}

.timeline-event:last-child { margin-bottom: 0; }

.timeline-dot {
  position: absolute;
  left: -1.75rem;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 0.55rem;
  border: 2px solid #f8fafc;
  flex-shrink: 0;
  top: 2px;
}

.dot--blue   { background: #3b82f6; }
.dot--yellow { background: #facc15; }
.dot--green  { background: #22c55e; }
.dot--purple { background: #a855f7; }
.dot--red    { background: #ef4444; }
.dot--gray   { background: #94a3b8; }

.timeline-content {
  background: #fff;
  border: 1px solid #f1f5f9;
  border-radius: 10px;
  padding: 0.6rem 0.875rem;
  flex: 1;
}

.timeline-content-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.2rem;
}

.timeline-label {
  font-weight: 700;
  font-size: 0.85rem;
  color: #1e293b;
}

.timeline-time {
  font-size: 0.72rem;
  color: #94a3b8;
}

.timeline-desc {
  font-size: 0.82rem;
  color: #64748b;
  margin: 0;
}

.timeline-duration {
  font-size: 0.72rem;
  color: #94a3b8;
  margin: 0.25rem 0 0;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.detail-card, .notes-card {
  background: #fff;
  border: 1px solid #f1f5f9;
  border-radius: 10px;
  padding: 0.875rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.35rem 0;
  border-bottom: 1px solid #f8fafc;
  font-size: 0.85rem;
}

.detail-row:last-child { border-bottom: none; }

.detail-label { color: #94a3b8; font-weight: 500; }
.detail-value { color: #0f172a; font-weight: 600; }

.notes-card { margin-top: 0.75rem; }
.notes-label { font-size: 0.75rem; font-weight: 700; color: #64748b; margin: 0 0 0.4rem; }
.notes-text { font-size: 0.85rem; color: #475569; margin: 0; }

/* ── Pagination ── */
.pagination {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #f1f5f9;
  padding: 0.875rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.pagination-info {
  font-size: 0.85rem;
  color: #64748b;
}

.pagination-controls {
  display: flex;
  gap: 0.3rem;
  align-items: center;
}

.page-btn {
  min-width: 34px;
  height: 34px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  color: #475569;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.1s, border-color 0.1s, color 0.1s;
}

.page-btn:hover:not(:disabled) { background: #f8fafc; border-color: #cbd5e1; }
.page-btn--active { background: #3b82f6 !important; color: #fff !important; border-color: #3b82f6 !important; font-weight: 700; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-ellipsis { color: #94a3b8; padding: 0 0.25rem; }
</style>
