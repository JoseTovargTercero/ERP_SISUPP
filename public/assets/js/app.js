import { hideLoader, showLoader } from './helpers/helpers'

const d = document

d.addEventListener('DOMContentLoaded', async () => {
  console.log('DOM CHARGED')

  $(document).ajaxStart(function () {
    // Esto se llamará automáticamente cuando la primera petición AJAX inicie
    showLoader()
  })

  $(document).ajaxStop(function () {
    // Esto se llamará automáticamente cuando TODAS las peticiones hayan terminado
    hideLoader()
  })
})
