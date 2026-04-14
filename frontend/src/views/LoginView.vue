<template>
  <div class="login-root">
    <!-- Background grid pattern -->
    <div class="login-bg">
      <div class="bg-grid"></div>
      <div class="bg-glow bg-glow-1"></div>
      <div class="bg-glow bg-glow-2"></div>
    </div>

    <!-- Card -->
    <div class="login-card">
      <!-- Brand -->
      <div class="login-brand">
        <div class="brand-icon">
          <i class="fas fa-ticket-alt"></i>
        </div>
        <div class="brand-text">
          <h1 class="brand-name">Termy</h1>
          <p class="brand-tagline">Sistema de gestión de turnos</p>
        </div>
      </div>

      <!-- Divider -->
      <div class="login-divider"></div>

      <!-- Form heading -->
      <div class="login-heading">
        <h2 class="login-title">Iniciar sesión</h2>
        <p class="login-subtitle">Accedé al panel de administración</p>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleLogin" class="login-form">
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-envelope"></i>
            Email
          </label>
          <input
            v-model="email"
            type="email"
            required
            class="form-input"
            placeholder="email"
            autocomplete="email"
          />
        </div>

        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-lock"></i>
            Contraseña
          </label>
          <div class="password-wrapper">
            <input
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              required
              class="form-input"
              placeholder="contraseña"
              autocomplete="current-password"
            />
            <button
              type="button"
              class="password-toggle"
              @click="showPassword = !showPassword"
              tabindex="-1"
            >
              <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
            </button>
          </div>
        </div>

        <!-- Error -->
        <Transition name="error-fade">
          <div v-if="error" class="form-error">
            <i class="fas fa-exclamation-circle"></i>
            {{ error }}
          </div>
        </Transition>

        <!-- Submit -->
        <button type="submit" :disabled="loading" class="btn-login">
          <span v-if="!loading">
            Ingresar
            <i class="fas fa-arrow-right"></i>
          </span>
          <span v-else class="btn-loading">
            <i class="fas fa-spinner fa-spin"></i>
            Iniciando sesión...
          </span>
        </button>
      </form>

      <!-- Footer -->
      <div class="login-footer">
        <router-link to="/" class="back-link">
          <i class="fas fa-arrow-left"></i>
          Volver al totem
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { login } from '../api'

const router = useRouter()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)

const handleLogin = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await login({
      email: email.value,
      password: password.value,
    })

    localStorage.setItem('token', response.data.token)
    localStorage.setItem('user', JSON.stringify(response.data.user))

    if (response.data.user.role === 'admin') {
      router.push('/admin')
    } else {
      router.push('/operator')
    }
  } catch (err) {
    console.error('Error en login:', err)
    error.value = err.response?.data?.message || 'Credenciales incorrectas. Revisá tu email y contraseña.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
/* ── Root ──────────────────────────────────────── */
.login-root {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0f172a;
  position: relative;
  overflow: hidden;
  padding: 1rem;
}

/* ── Background ─────────────────────────────────── */
.login-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.bg-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(59,130,246,0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(59,130,246,0.06) 1px, transparent 1px);
  background-size: 40px 40px;
}

.bg-glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.5;
}

.bg-glow-1 {
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, rgba(59,130,246,0.25) 0%, transparent 70%);
  top: -100px;
  right: -100px;
}

.bg-glow-2 {
  width: 300px;
  height: 300px;
  background: radial-gradient(circle, rgba(99,102,241,0.2) 0%, transparent 70%);
  bottom: -80px;
  left: -80px;
}

/* ── Card ───────────────────────────────────────── */
.login-card {
  position: relative;
  z-index: 1;
  background: #fff;
  border-radius: 24px;
  padding: 2.5rem;
  width: 100%;
  max-width: 440px;
  box-shadow:
    0 0 0 1px rgba(255,255,255,0.05),
    0 25px 60px rgba(0,0,0,0.4),
    0 0 80px rgba(59,130,246,0.1);
  animation: cardAppear 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

@keyframes cardAppear {
  from { opacity: 0; transform: translateY(24px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ── Brand ──────────────────────────────────────── */
.login-brand {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.brand-icon {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: #fff;
  box-shadow: 0 4px 16px rgba(59,130,246,0.4);
  flex-shrink: 0;
}

.brand-text { display: flex; flex-direction: column; }

.brand-name {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  line-height: 1.2;
  letter-spacing: -0.02em;
}

.brand-tagline {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.75rem;
  color: #64748b;
  margin: 0.15rem 0 0;
  font-weight: 500;
}

.login-divider {
  height: 1px;
  background: #f1f5f9;
  margin-bottom: 1.75rem;
}

/* ── Heading ────────────────────────────────────── */
.login-heading { margin-bottom: 1.75rem; }

.login-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.35rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 0.25rem;
  letter-spacing: -0.02em;
}

.login-subtitle {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.875rem;
  color: #64748b;
  margin: 0;
}

/* ── Form ───────────────────────────────────────── */
.login-form { display: flex; flex-direction: column; gap: 1.25rem; }

.form-group { display: flex; flex-direction: column; gap: 0.5rem; }

.form-label {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.form-label i { font-size: 0.7rem; color: #94a3b8; }

.form-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
  color: #0f172a;
  background: #f8fafc;
  transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
  outline: none;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: #3b82f6;
  background: #fff;
  box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
}

.form-input::placeholder { color: #94a3b8; }

/* Password toggle */
.password-wrapper { position: relative; }
.password-wrapper .form-input { padding-right: 3rem; }

.password-toggle {
  position: absolute;
  right: 0.875rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0.25rem;
  transition: color 0.15s;
  line-height: 1;
}

.password-toggle:hover { color: #475569; }

/* Error */
.form-error {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  color: #dc2626;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 500;
}

.error-fade-enter-active { animation: errorSlide 0.25s ease; }
.error-fade-leave-active { animation: errorSlide 0.2s ease reverse; }

@keyframes errorSlide {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Submit button */
.btn-login {
  width: 100%;
  padding: 0.85rem;
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s, transform 0.1s, box-shadow 0.15s;
  box-shadow: 0 4px 16px rgba(59,130,246,0.3);
  letter-spacing: 0.01em;
  margin-top: 0.25rem;
}

.btn-login:hover:not(:disabled) {
  opacity: 0.92;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(59,130,246,0.4);
}

.btn-login:active:not(:disabled) { transform: translateY(0); }
.btn-login:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

.btn-login span {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.btn-loading { display: flex; align-items: center; justify-content: center; gap: 0.5rem; }

/* ── Footer ─────────────────────────────────────── */
.login-footer {
  margin-top: 1.5rem;
  text-align: center;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.8rem;
  font-weight: 500;
  color: #94a3b8;
  text-decoration: none;
  transition: color 0.15s;
}

.back-link:hover { color: #3b82f6; }
</style>
