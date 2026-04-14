<template>
  <aside class="admin-sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="brand-icon">
        <i class="fas fa-ticket-alt"></i>
      </div>
      <div class="brand-text">
        <span class="brand-name">Termy</span>
        <span class="brand-sub">Panel Admin</span>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        class="nav-item"
        :class="{ 'nav-item--active': activeTab === tab.id }"
        @click="$emit('tab-change', tab.id)"
      >
        <i :class="tab.icon" class="nav-icon"></i>
        <span class="nav-label">{{ tab.name }}</span>
        <span v-if="activeTab === tab.id" class="nav-active-bar"></span>
      </button>
    </nav>

    <!-- User info + logout -->
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar">
          {{ userInitials }}
        </div>
        <div class="user-info">
          <p class="user-name">{{ user?.name || '—' }}</p>
          <p class="user-email">{{ user?.email || '' }}</p>
        </div>
      </div>
      <button class="btn-logout" @click="$emit('logout')" title="Cerrar sesión">
        <i class="fas fa-sign-out-alt"></i>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  activeTab: {
    type: String,
    required: true,
  },
  user: {
    type: Object,
    default: null,
  },
})

defineEmits(['tab-change', 'logout'])

const tabs = [
  { id: 'dashboard',   name: 'Dashboard',   icon: 'fas fa-chart-bar' },
  { id: 'historial',   name: 'Historial',   icon: 'fas fa-history' },
  { id: 'usuarios',    name: 'Usuarios',    icon: 'fas fa-users' },
  { id: 'perfiles',    name: 'Perfiles',    icon: 'fas fa-layer-group' },
  { id: 'ventanillas', name: 'Ventanillas', icon: 'fas fa-desktop' },
  { id: 'servicios',   name: 'Servicios',   icon: 'fas fa-clipboard-list' },
]

const userInitials = computed(() => {
  if (!props.user?.name) return 'A'
  return props.user.name
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()
})
</script>

<style scoped>
.admin-sidebar {
  width: 240px;
  height: 100vh;
  background: #0f172a;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  position: sticky;
  top: 0;
  z-index: 10;
}

/* Brand */
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.5rem 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
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

.brand-text {
  display: flex;
  flex-direction: column;
}

.brand-name {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1rem;
  font-weight: 700;
  color: #ffffff;
  line-height: 1.2;
  letter-spacing: -0.02em;
}

.brand-sub {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.7rem;
  font-weight: 500;
  color: #475569;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 1rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.nav-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.7rem 1rem;
  border-radius: 10px;
  border: none;
  background: transparent;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
  text-align: left;
  overflow: hidden;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.05);
}

.nav-item--active {
  background: rgba(59, 130, 246, 0.12) !important;
}

.nav-active-bar {
  position: absolute;
  left: 0;
  top: 20%;
  bottom: 20%;
  width: 3px;
  background: #3b82f6;
  border-radius: 0 2px 2px 0;
}

.nav-icon {
  font-size: 0.95rem;
  width: 18px;
  text-align: center;
  color: #64748b;
  flex-shrink: 0;
  transition: color 0.15s ease;
}

.nav-item--active .nav-icon {
  color: #3b82f6;
}

.nav-label {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  font-weight: 500;
  color: #94a3b8;
  transition: color 0.15s ease;
}

.nav-item--active .nav-label {
  color: #e2e8f0;
  font-weight: 600;
}

/* Footer */
.sidebar-footer {
  padding: 1rem 0.75rem;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.sidebar-user {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-width: 0;
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

.user-info {
  min-width: 0;
}

.user-name {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  font-weight: 600;
  color: #e2e8f0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin: 0;
}

.user-email {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.65rem;
  color: #475569;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin: 0;
}

.btn-logout {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(239, 68, 68, 0.08);
  color: #ef4444;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  transition: background 0.15s ease, color 0.15s ease;
  flex-shrink: 0;
}

.btn-logout:hover {
  background: rgba(239, 68, 68, 0.2);
  color: #fca5a5;
}
</style>
