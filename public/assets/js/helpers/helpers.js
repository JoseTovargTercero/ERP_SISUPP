// LOADERS

// Función para mostrar el loader con overlay
export function showLoader() {
  let overlay = document.getElementById('custom-loader-overlay')
  if (!overlay) {
    overlay = document.createElement('div')
    overlay.id = 'custom-loader-overlay'
    overlay.style.display = 'none'
    overlay.innerHTML = `
      <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Loading...</span>
      </div>
    `
    document.body.insertBefore(overlay, document.body.firstChild)
  }

  // Quitar foco de cualquier elemento activo para evitar interacción
  if (document.activeElement) {
    document.activeElement.blur()
  }

  overlay.style.display = 'flex'
  setTimeout(() => {
    overlay.classList.add('show')
  }, 10)
}

// Función para ocultar el loader con animación
export function hideLoader() {
  const overlay = document.getElementById('custom-loader-overlay')
  if (!overlay) return

  // Quitar clase show para iniciar transición de opacidad a 0
  overlay.classList.remove('show')

  // Al terminar la transición (300ms) ocultar el overlay
  setTimeout(() => {
    overlay.style.display = 'none'
  }, 300)
}

// =================================================================================
// NUEVO: Configuración de Toast con SweetAlert2
// =================================================================================
export const Toast = Swal.mixin({
  toast: true,
  position: 'bottom-end',
  showConfirmButton: false,
  timer: 1500,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.addEventListener('mouseenter', Swal.stopTimer)
    toast.addEventListener('mouseleave', Swal.resumeTimer)
  },
})

// =================================================================================
// NUEVO: Función reutilizable para mostrar errores en un Toast
// =================================================================================
export function showErrorToast(response) {
  let message = 'Ocurrió un error inesperado.' // Mensaje genérico
  if (response && response.message) {
    message = response.message // Mensaje del backend si existe
  }
  Toast.fire({
    icon: 'error',
    title: message,
  })
}

export function showSuccessToast(response) {
  let message = 'Operación realizada con éxito.' // Mensaje genérico
  if (response && response.message) {
    message = response.message // Mensaje del backend si existe
  }
  Toast.fire({
    icon: 'success',
    title: message,
  })
}

// función para formatear fecha a DD/MM/YYYY usando tolocaleDateString
export function formatDate(dateString) {
  const options = { year: 'numeric', month: '2-digit', day: '2-digit' }
  return new Date(dateString).toLocaleDateString('es-ES', options)
}
