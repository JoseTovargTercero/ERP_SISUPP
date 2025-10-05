import { showErrorToast } from '../helpers/helpers.js'

/**
 * Adapta la respuesta de tu API al formato que Bootstrap Table espera.
 * Tu API -> { "data": [...] }, Bootstrap Table -> { "rows": [...] }.
 */
window.responseHandler = function (res) {
  return {
    rows: res.data,
    total: res.data.length,
  }
}

/**
 * Genera el HTML para los botones de acción de cada fila.
 * @param {string} value - El menu_id de la fila actual (definido en data-field).
 * @param {object} row - El objeto de datos completo para la fila.
 */
window.accionesFormatter = function (value, row) {
  return `
    <div class="btn-group">
        <button class="btn btn-info btn-sm btn-ver" data-id="${value}" title="Ver Detalles"><i class="mdi mdi-eye"></i></button>
        <button class="btn btn-warning btn-sm btn-editar" data-id="${value}" title="Editar"><i class="mdi mdi-pencil"></i></button>
        <button class="btn btn-danger btn-sm btn-eliminar" data-id="${value}" title="Eliminar"><i class="mdi mdi-delete"></i></button>
    </div>
    `
}

document.addEventListener('DOMContentLoaded', function () {
  // Inicialización de Select2 (sin cambios)
  $('#categoria').select2({ dropdownParent: $('#modalMenu') })

  // --- INICIALIZACIÓN DE DATATABLE ELIMINADA ---

  const modalMenu = new bootstrap.Modal(document.getElementById('modalMenu'))
  const modalDetallesMenu = new bootstrap.Modal(
    document.getElementById('modalDetallesMenu')
  )
  const formMenu = document.getElementById('formMenu')

  // 2. ABRIR MODAL PARA CREAR NUEVO MENÚ (Sin cambios)
  $('#btnNuevoMenu').on('click', function () {
    formMenu.reset()
    $('#menu_id').val('')
    $('#modalMenuLabel').text('Crear Nuevo Menú')
    $('#categoria').val('').trigger('change')
    modalMenu.show()
  })

  // 3. LÓGICA DEL FORMULARIO (CREAR Y ACTUALIZAR)
  $('#formMenu').on('submit', function (e) {
    e.preventDefault()
    const menuId = $('#menu_id').val()
    let url = baseUrl + 'api/menus'
    let method = 'POST'

    if (menuId) {
      url = `${baseUrl}api/menus/${menuId}`
    }

    const formData = {}
    $(this)
      .serializeArray()
      .forEach((item) => {
        formData[item.name] = item.value
      })

    $.ajax({
      url: url,
      method: method,
      contentType: 'application/json',
      data: JSON.stringify(formData),
      success: function (response) {
        modalMenu.hide()
        Swal.fire({
          icon: 'success',
          title: '¡Éxito!',
          text: response.message,
        })
        // CAMBIO: Así se recarga la tabla
        $('#tablaMenus').bootstrapTable('refresh')
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON)
      },
    })
  })

  // 4. EVENTOS DE LOS BOTONES DE ACCIÓN (Sin cambios en la lógica interna)
  $('#tablaMenus').on('click', 'button', function () {
    const action = $(this).attr('class')
    const menuId = $(this).data('id')

    if (action.includes('btn-ver')) {
      $.ajax({
        url: `${baseUrl}api/menus/${menuId}`,
        method: 'GET',
        success: function (response) {
          const data = response.data
          $('#detalle_menu_id').text(data.menu_id)
          $('#detalle_nombre').text(data.nombre)
          $('#detalle_categoria').text(data.categoria)
          $('#detalle_url').text(data.url)
          $('#detalle_icono').text(data.icono || 'No especificado')
          $('#detalle_user_level').text(data.user_level)
          $('#detalle_created_at').text(
            new Date(data.created_at).toLocaleString()
          )
          modalDetallesMenu.show()
        },
        error: function (xhr) {
          showErrorToast(xhr.responseJSON)
        },
      })
    } else if (action.includes('btn-editar')) {
      $.ajax({
        url: `${baseUrl}api/menus/${menuId}`,
        method: 'GET',
        success: function (response) {
          const data = response.data
          $('#menu_id').val(data.menu_id)
          $('#nombre').val(data.nombre)
          $('#categoria').val(data.categoria).trigger('change')
          $('#url').val(data.url)
          $('#icono').val(data.icono)
          $('#user_level').val(data.user_level)
          $('#modalMenuLabel').text('Editar Menú')
          modalMenu.show()
        },
        error: function (xhr) {
          showErrorToast(xhr.responseJSON)
        },
      })
    } else if (action.includes('btn-eliminar')) {
      Swal.fire({
        title: '¿Estás seguro?',
        text: 'El menú será eliminado lógicamente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: `${baseUrl}api/menus/${menuId}`,
            method: 'DELETE',
            success: function (response) {
              Swal.fire('Eliminado', response.message, 'success')
              // CAMBIO: Así se recarga la tabla
              $('#tablaMenus').bootstrapTable('refresh')
            },
            error: function (xhr) {
              showErrorToast(xhr.responseJSON)
            },
          })
        }
      })
    }
  })
})
