<template>
  <header class="op-header">
    <!-- Left: brand -->
    <div class="op-header__brand">
      <div class="brand-icon">
        <i class="fas fa-ticket-alt"></i>
      </div>
      <span class="brand-name">Turnero</span>
      <span class="brand-divider">|</span>
      <span class="brand-sub">Panel Operador</span>
    </div>

    <!-- Right: window + clock + user + logout -->
    <div class="op-header__right">
      <div v-if="assignedWindow" class="window-badge">
        <i class="fas fa-desktop"></i>
        {{ assignedWindow.name }} · #{{ assignedWindow.number }}
      </div>
      <span v-else class="window-badge window-badge--error">
        <i class="fas fa-exclamation-triangle"></i>
        Sin ventanilla
      </span>

      <span class="op-clock">{{ clock }}</span>

      <div class="user-section">
        <div class="user-avatar">{{ userInitials }}</div>
        <span class="user-name">{{ user?.name || '...' }}</span>
      </div>

      <button class="btn-logout" @click="$emit('logout')" title="Cerrar sesión">
        <i class="fas fa-sign-out-alt"></i>
      </button>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  user: {
    type: Object,
    default: null,
  },
  assignedWindow: {
    type: Object,
    default: null,
  },
})

defineEmits(['logout'])

const clock = ref('')
let clockInterval = null

const formatClock = () => {
  const now = new Date()
  const h = String(now.getHours()).padStart(2, '0')
  const m = String(now.getMinutes()).padStart(2, '0')
  clock.value = `${h}:${m}`
}

const userInitials = computed(() => {
  if (!props.user?.name) return 'OP'
  return props.user.name
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()
})

onMounted(() => {
  formatClock()
  clockInterval = setInterval(formatClock, 1000)
})

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval)
})
</script>

<style scoped>
.op-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.75rem;
  height: 64px;
  background: #0f172a;
  flex-shrink: 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

/* Brand */
.op-header__brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.brand-icon {
  width: 38px;
  height: 38px;
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  color: #fff;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.brand-name {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1rem;
  font-weight: 700;
  color: #ffffff;
  letter-spacing: -0.02em;
}

.brand-divider {
  color: #1e293b;
  font-size: 1.2rem;
  line-height: 1;
  margin: 0 0.1rem;
}

.brand-sub {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.7rem;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.07em;
}

/* Right section */
.op-header__right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.window-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 5px 14px;
  border-radius: 20px;
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.25);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  font-weight: 600;
}

.window-badge--error {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.25);
}

.op-clock {
  font-family: 'Courier New', Courier, monospace;
  font-size: 0.95rem;
  font-weight: 600;
  color: #475569;
  letter-spacing: 0.06em;
  min-width: 44px;
  text-align: center;
}

.user-section {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0 0.25rem;
}

.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.7rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

.user-name {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 500;
  color: #94a3b8;
}

.btn-logout {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(239, 68, 68, 0.08);
  color: #ef4444;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  transition: background 0.15s, color 0.15s;
  flex-shrink: 0;
}

.btn-logout:hover {
  background: rgba(239, 68, 68, 0.2);
  color: #fca5a5;
}
</style>
