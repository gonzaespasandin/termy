import { reactive } from 'vue'

// Singleton reactivo a nivel de módulo — compartido entre todos los componentes
const toast = reactive({
  show: false,
  message: '',
  type: 'success', // 'success' | 'error' | 'info'
})

let toastTimer = null

export function useToast() {
  const showToast = (message, type = 'success') => {
    if (toastTimer) clearTimeout(toastTimer)
    toast.message = message
    toast.type = type
    toast.show = true
    toastTimer = setTimeout(() => {
      toast.show = false
    }, 3500)
  }

  return { toast, showToast }
}
