import { reactive } from 'vue'

// Singleton reactivo a nivel de módulo — compartido entre todos los componentes
const confirmModal = reactive({
  show: false,
  title: '',
  message: '',
  confirmLabel: 'Confirmar',
  onConfirm: () => {},
})

export function useConfirm() {
  const showConfirm = (title, message, onConfirm, confirmLabel = 'Eliminar') => {
    confirmModal.title = title
    confirmModal.message = message
    confirmModal.confirmLabel = confirmLabel
    confirmModal.onConfirm = () => {
      confirmModal.show = false
      onConfirm()
    }
    confirmModal.show = true
  }

  const closeConfirm = () => {
    confirmModal.show = false
  }

  return { confirmModal, showConfirm, closeConfirm }
}
