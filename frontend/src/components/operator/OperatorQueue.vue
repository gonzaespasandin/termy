<template>
  <div class="op-queue">
    <!-- Header -->
    <div class="op-queue__header">
      <div class="queue-count-block">
        <span class="queue-count">{{ turns.length }}</span>
        <span class="queue-label">En Espera</span>
      </div>
      <button class="btn-refresh" @click="$emit('refresh')" title="Actualizar cola">
        <i class="fas fa-sync-alt"></i>
      </button>
    </div>

    <!-- Active turn warning banner -->
    <div v-if="currentTurn" class="queue-blocked-banner">
      <i class="fas fa-info-circle"></i>
      Completá el turno activo antes de llamar otro.
    </div>

    <!-- Queue list -->
    <div class="op-queue__list">
      <div
        v-for="turn in sortedTurns"
        :key="turn.id"
        class="queue-card"
        :class="{ 'queue-card--disabled': !!currentTurn }"
        :style="{ borderLeftColor: turn.service.color }"
        @click="!currentTurn && $emit('call-specific', turn)"
      >
        <div class="queue-card__left">
          <span class="queue-ticket">{{ turn.ticket_number }}</span>
        </div>
        <div class="queue-card__center">
          <span class="queue-service-dot" :style="{ background: turn.service.color }"></span>
          <span class="queue-service-name">{{ turn.service.name }}</span>
        </div>
        <div class="queue-card__right">
          <span v-if="turn.priority === 'priority'" class="priority-badge">
            <i class="fas fa-star"></i>
          </span>
          <span class="queue-time">{{ timeAgo(turn.created_at) }}</span>
        </div>
      </div>

      <div v-if="turns.length === 0" class="queue-empty">
        <i class="fas fa-inbox"></i>
        <p>Sin turnos en espera</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  turns: {
    type: Array,
    default: () => [],
  },
  currentTurn: {
    type: Object,
    default: null,
  },
})

defineEmits(['call-specific', 'refresh'])

const sortedTurns = computed(() => {
  return [...props.turns].sort((a, b) => {
    if (a.priority === 'priority' && b.priority !== 'priority') return -1
    if (a.priority !== 'priority' && b.priority === 'priority') return 1
    return new Date(a.created_at) - new Date(b.created_at)
  })
})

const timeAgo = (datetime) => {
  if (!datetime) return '-'
  const diffMs = Date.now() - new Date(datetime).getTime()
  const diffMin = Math.floor(diffMs / 60000)
  if (diffMin < 1) return 'ahora'
  if (diffMin < 60) return `hace ${diffMin} min`
  const h = Math.floor(diffMin / 60)
  const m = diffMin % 60
  return m > 0 ? `hace ${h}h ${m}m` : `hace ${h}h`
}
</script>

<style scoped>
.op-queue {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  overflow: hidden;
  height: 100%;
}

/* Header */
.op-queue__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.queue-count-block {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
}

.queue-count {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 2rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1;
  letter-spacing: -0.03em;
}

.queue-label {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.btn-refresh {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  transition: background 0.15s, color 0.15s;
}

.btn-refresh:hover {
  background: #e2e8f0;
  color: #334155;
}

/* Blocked banner */
.queue-blocked-banner {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.25);
  border-radius: 10px;
  padding: 0.6rem 1rem;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  font-weight: 500;
  color: #d97706;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

/* List */
.op-queue__list {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding-right: 2px;
}

.op-queue__list::-webkit-scrollbar {
  width: 4px;
}

.op-queue__list::-webkit-scrollbar-track {
  background: transparent;
}

.op-queue__list::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 4px;
}

/* Queue card */
.queue-card {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  border: 1px solid #f1f5f9;
  border-left: 4px solid;
  background: #fff;
  cursor: pointer;
  transition: background 0.12s, transform 0.12s, box-shadow 0.12s;
  animation: fadeInUp 0.2s ease both;
}

@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}

.queue-card:hover:not(.queue-card--disabled) {
  background: #f8fafc;
  transform: translateX(3px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.queue-card--disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.queue-card__left {
  flex-shrink: 0;
  min-width: 76px;
}

.queue-ticket {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.02em;
  line-height: 1;
}

.queue-card__center {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.queue-service-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.queue-service-name {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  color: #334155;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.queue-card__right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.2rem;
  flex-shrink: 0;
}

.priority-badge {
  font-size: 0.75rem;
  color: #f59e0b;
  line-height: 1;
}

.queue-time {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  color: #94a3b8;
  white-space: nowrap;
}

/* Empty state */
.queue-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  color: #cbd5e1;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
  padding: 2rem 0;
}

.queue-empty i {
  font-size: 2rem;
}
</style>
