import { showErrorToast, Toast } from '../helpers/helpers.js'

document.addEventListener('DOMContentLoaded', function () {
  // Configuración de Toast con SweetAlert2

  // Manejo del formulario de login
  const formLogin = document.getElementById('formLogin')

  if (formLogin) {
    formLogin.addEventListener('submit', function (e) {
      e.preventDefault() // Evitamos el envío tradicional del formulario

      const loginButton = this.querySelector('button[type="submit"]')
      const originalButtonHtml = loginButton.innerHTML

      // Deshabilitar botón y mostrar un spinner para feedback visual
      loginButton.disabled = true
      loginButton.innerHTML = `
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Ingresando...
            `

      const formData = {
        email: this.email.value,
        contrasena: this.password.value, // El endpoint espera "contrasena"
      }

      $.ajax({
        url: baseUrl + 'system_users/login',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        success: function (response) {
          if (response.value) {
            // Si el login es exitoso
            Toast.fire({
              icon: 'success',
              title: response.message,
            }).then(() => {
              // Redirigir al dashboard o a la página principal
              window.location.href = `${baseUrl}users` // Cambia '/dashboard' por tu ruta deseada
            })
          } else {
            // Si el backend devuelve un error conocido (value: false)
            showErrorToast(response)
          }
        },
        error: function (xhr) {
          // Si hay un error de red o un error HTTP (400, 401, 500)
          showErrorToast(xhr.responseJSON)
        },
        complete: function () {
          // Volver a habilitar el botón y restaurar su contenido original
          loginButton.disabled = false
          loginButton.innerHTML = originalButtonHtml
        },
      })
    })
  }
})
