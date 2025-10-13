import { showErrorToast, showSuccessToast } from '../helpers/helpers.js'

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

/**
 * Formateador para la columna de arrastre. Muestra un ícono.
 */

window.dragHandleFormatter = function (value, row, index) {
  return '<i class="mdi mdi-drag-vertical" style="cursor: grab;"></i>'
}

/**
 * Función requerida por la extensión para asignar un ID único a cada <tr>.
 */
window.rowAttrFunc = function (row, index) {
  return {
    'data-id': row.menu_id,
  }
}

/**
 * Se ejecuta después de que el usuario arrastra y suelta una fila.
 * @param {Array} newData El array de datos de la tabla en el nuevo orden.
 */
window.onReorderRow = function (newData) {
  // 1. Mapeamos el array para obtener solo los IDs en el nuevo orden.
  const orderedIds = newData.map((row) => row.menu_id)

  // 2. Enviamos el nuevo orden a la API.
  $.ajax({
    url: baseUrl + 'api/menus/reordenar',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify(orderedIds),
    success: function (response) {
      // Usamos un toast para una notificación menos intrusiva.
      showSuccessToast(response.message)
      // Recargamos la tabla para asegurar que los números de 'orden' se actualicen.
      $('#tablaMenus').bootstrapTable('refresh')
    },
    error: function (xhr) {
      showErrorToast(xhr.responseJSON)
      // Si falla, es importante recargar para revertir el cambio visual.
      $('#tablaMenus').bootstrapTable('refresh')
    },
  })
}

document.addEventListener('DOMContentLoaded', function () {
  const $table = $('#tablaMenus') // Guardamos la referencia a la tabla
  const modalMenu = new bootstrap.Modal(document.getElementById('modalMenu'))
  const modalDetallesMenu = new bootstrap.Modal(
    document.getElementById('modalDetallesMenu')
  )
  const formMenu = document.getElementById('formMenu')

  $('#categoria').select2({ dropdownParent: $('#modalMenu') })

  // ABRIR MODAL PARA CREAR (sin cambios)
  $('#btnNuevoMenu').on('click', function () {
    formMenu.reset()
    $('#menu_id').val('')
    $('#modalMenuLabel').text('Crear Nuevo Menú')
    $('#categoria').val('').trigger('change')
    modalMenu.show()
  })

  // LÓGICA DEL FORMULARIO (sin cambios)
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
        $('#tablaMenus').bootstrapTable('refresh')
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON)
      },
    })
  })

  $table.on('reorder-row.bs.table', function (e, newOrder) {
    // El segundo argumento 'newOrder' es el array con los datos en el nuevo orden.
    const orderedIds = newOrder.map((row) => row.menu_id)

    // Hacemos la llamada AJAX para guardar el nuevo orden.
    $.ajax({
      url: baseUrl + 'api/menus-reordenar',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(orderedIds),
      success: function (response) {
        showSuccessToast(response.message)
        // No es necesario refrescar aquí, la tabla ya está visualmente ordenada.
        // Pero si quieres actualizar los números de la columna "Orden", sí debes hacerlo.
        $table.bootstrapTable('refresh')
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON)
        // Si falla, refrescamos para revertir el cambio visual a como estaba antes.
        $table.bootstrapTable('refresh')
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
          // MODIFICADO: Llenar el campo de orden al editar
          $('#orden').val(data.orden)
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
