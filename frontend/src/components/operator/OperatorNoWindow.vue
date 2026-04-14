<template>
  <div class="no-window-wrap">
    <div class="no-window-card">
      <div class="no-window-icon">
        <i class="fas fa-plug"></i>
      </div>
      <h2 class="no-window-title">Esta PC no está asignada a ninguna ventanilla</h2>
      <p class="no-window-msg">{{ error }}</p>
      <p class="no-window-detail">No puede operar turnos sin una ventanilla asignada.</p>
      <div class="no-window-steps">
        <p class="steps-heading">Para resolver esto:</p>
        <ol>
          <li>Contacte al administrador del sistema</li>
          <li>Solicite que asigne una ventanilla a esta IP en el panel de administración (tab "Ventanillas")</li>
          <li>Recargue esta página después de la asignación</li>
        </ol>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  error: {
    type: String,
    default: '',
  },
})
</script>

<style scoped>
.no-window-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.no-window-card {
  max-width: 520px;
  width: 100%;
  background: #fff;
  border: 1px solid #fecaca;
  border-radius: 20px;
  padding: 3rem 2.5rem;
  box-shadow: 0 4px 24px rgba(239, 68, 68, 0.08);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.no-window-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(239, 68, 68, 0.08);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: #ef4444;
  margin-bottom: 0.25rem;
}

.no-window-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.2rem;
  font-weight: 700;
  color: #b91c1c;
  margin: 0;
  line-height: 1.3;
}

.no-window-msg {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
  color: #ef4444;
  margin: 0;
}

.no-window-detail {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  color: #64748b;
  margin: 0;
}

.no-window-steps {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 12px;
  padding: 1rem 1.5rem;
  text-align: left;
  width: 100%;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  color: #7f1d1d;
  margin-top: 0.5rem;
}

.steps-heading {
  font-weight: 700;
  margin: 0 0 0.5rem;
}

.no-window-steps ol {
  margin: 0;
  padding-left: 1.2rem;
  line-height: 1.9;
}
</style>
