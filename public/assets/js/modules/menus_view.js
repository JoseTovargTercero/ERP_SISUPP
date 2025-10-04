import { showErrorToast } from '../helpers/helpers.js'

document.addEventListener('DOMContentLoaded', function () {
  $('#categoria').select2({ dropdownParent: $('#modalMenu') })

  // 1. INICIALIZACIÓN DE DATATABLE
  const tablaMenus = $('#tablaMenus').DataTable({
    ajax: {
      url: baseUrl + 'api/menus', // Endpoint para listar menús
      dataSrc: 'data',
    },
    columns: [
      {
        data: 'nombre',
      },
      {
        data: 'categoria',
      },
      {
        data: 'url',
      },
      {
        data: 'user_level',
      },
      {
        data: null, // No se enlaza a un campo específico
        render: function (data, type, row) {
          // Usamos row.menu_id para obtener el ID
          return `
                        <button class="btn btn-info btn-sm btn-ver" data-id="${row.menu_id}" title="Ver Detalles"><i class="mdi mdi-eye"></i></button>
                        <button class="btn btn-warning btn-sm btn-editar" data-id="${row.menu_id}" title="Editar"><i class="mdi mdi-pencil"></i></button>
                        <button class="btn btn-danger btn-sm btn-eliminar" data-id="${row.menu_id}" title="Eliminar"><i class="mdi mdi-delete"></i></button>
                    `
        },
        orderable: false,
        searchable: false,
      },
    ],
    language: {
      url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json',
    },
  })

  const modalMenu = new bootstrap.Modal(document.getElementById('modalMenu'))
  const modalDetallesMenu = new bootstrap.Modal(
    document.getElementById('modalDetallesMenu')
  )
  const formMenu = document.getElementById('formMenu')

  // 2. ABRIR MODAL PARA CREAR NUEVO MENÚ
  $('#btnNuevoMenu').on('click', function () {
    formMenu.reset()
    $('#menu_id').val('')
    $('#modalMenuLabel').text('Crear Nuevo Menú')
    // Restablece Select2 si lo estás usando
    $('#categoria').val('').trigger('change')
    modalMenu.show()
  })

  // 3. LÓGICA DEL FORMULARIO (CREAR Y ACTUALIZAR)
  $('#formMenu').on('submit', function (e) {
    e.preventDefault()
    const menuId = $('#menu_id').val()

    let url = baseUrl + 'api/menus'
    // El método siempre es POST según la documentación (para crear y actualizar)
    let method = 'POST'

    if (menuId) {
      // Para actualizar, la URL incluye el ID
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
        tablaMenus.ajax.reload() // Recargar la tabla
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON)
      },
    })
  })

  // 4. EVENTOS DE LOS BOTONES DE ACCIÓN
  $('#tablaMenus tbody').on('click', 'button', function () {
    const action = $(this).attr('class')
    const menuId = $(this).data('id')

    if (action.includes('btn-ver')) {
      // VER DETALLES
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
      // EDITAR MENÚ
      $.ajax({
        url: `${baseUrl}api/menus/${menuId}`,
        method: 'GET',
        success: function (response) {
          const data = response.data
          $('#menu_id').val(data.menu_id)
          $('#nombre').val(data.nombre)
          $('#categoria').val(data.categoria).trigger('change')
          // Actualiza Select2 si lo usas:
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
      // ELIMINAR MENÚ
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
              tablaMenus.ajax.reload()
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
