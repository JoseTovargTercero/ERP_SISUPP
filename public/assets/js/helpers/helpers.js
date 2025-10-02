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
