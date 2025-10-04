import { showErrorToast } from '../helpers/helpers.js'

// Asegúrate de que este código se ejecuta después de que el DOM esté completamente cargado.
document.addEventListener('DOMContentLoaded', function () {
  // Inicialización de la DataTable
  const tablaUsuarios = $('#tablaUsuarios').DataTable({
    ajax: {
      url: baseUrl + 'api/system_users', // <-- CAMBIO AQUÍ
      dataSrc: 'data',
    },
    columns: [
      { data: 'nombre' },
      { data: 'email' },
      {
        data: 'nivel',
        render: function (data, type, row) {
          // Puedes mapear los niveles a nombres más descriptivos
          switch (data) {
            case 1:
              return 'Administrador'
            case 2:
              return 'Usuario'
            default:
              return 'Desconocido'
          }
        },
      },
      {
        data: 'estado',
        render: function (data, type, row) {
          return data == 1
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-danger">Inactivo</span>'
        },
      },
      {
        data: 'user_id',
        render: function (data, type, row) {
          return `
                        <button class="btn btn-info btn-sm btn-ver" data-id="${data}" title="Ver Detalles"><i class="mdi mdi-eye"></i></button>
                        <button class="btn btn-warning btn-sm btn-editar" data-id="${data}" title="Editar"><i class="mdi mdi-pencil"></i></button>
                        <button class="btn btn-danger btn-sm btn-eliminar" data-id="${data}" title="Eliminar"><i class="mdi mdi-delete"></i></button>
                    `
        },
        orderable: false,
        searchable: false,
      },
    ],
    language: {
      // Traducción opcional para DataTables
      url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json',
    },
  })

  const modalUsuario = new bootstrap.Modal(
    document.getElementById('modalUsuario')
  )
  const modalDetalles = new bootstrap.Modal(
    document.getElementById('modalDetalles')
  )
  const formUsuario = document.getElementById('formUsuario')

  // 1. ABRIR MODAL PARA CREAR NUEVO USUARIO
  $('#btnNuevoUsuario').on('click', function () {
    formUsuario.reset()
    $('#user_id').val('')
    $('#modalUsuarioLabel').text('Crear Nuevo Usuario')
    $('#contrasena').prop('required', true) // La contraseña es requerida al crear
    modalUsuario.show()
  })

  // 2. LÓGICA DEL FORMULARIO (CREAR Y ACTUALIZAR)
  $('#formUsuario').on('submit', function (e) {
    e.preventDefault()
    const userId = $('#user_id').val()

    let url = baseUrl + 'system_users' // <-- CAMBIO AQUÍ
    let method = 'POST'

    if (userId) {
      // Si hay ID, es una actualización
      url = baseUrl + `api/system_users/${userId}` // <-- CAMBIO AQUÍ
      method = 'PUT'
    }
    // Serializar datos del formulario a un objeto JSON
    const formData = {}
    $(this)
      .serializeArray()
      .forEach((item) => {
        formData[item.name] = item.value
      })

    // Para PUT, no enviar contraseña si está vacía
    if (method === 'PUT' && !formData.contrasena) {
      delete formData.contrasena
    }

    $.ajax({
      url: url,
      method: method,
      contentType: 'application/json',
      data: JSON.stringify(formData),
      success: function (response) {
        modalUsuario.hide()
        Swal.fire({
          icon: 'success',
          title: '¡Éxito!',
          text: response.message,
        })
        tablaUsuarios.ajax.reload() // Recargar la tabla
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON)
      },
    })
  })

  // 3. EVENTOS DE LOS BOTONES DE ACCIÓN (usando delegación de eventos)
  $('#tablaUsuarios tbody').on('click', 'button', function () {
    const action = $(this).attr('class')
    const userId = $(this).data('id')

    if (action.includes('btn-ver')) {
      // VER DETALLES
      $.ajax({
        url: baseUrl + `api/system_users/${userId}`, // <-- CAMBIO AQUÍ
        method: 'GET',
        success: function (response) {
          const data = response.data
          $('#detalle_user_id').text(data.user_id)
          $('#detalle_nombre').text(data.nombre)
          $('#detalle_email').text(data.email)
          $('#detalle_nivel').text(
            data.nivel == 1 ? 'Administrador' : 'Usuario'
          )
          $('#detalle_estado').html(
            data.estado == 1
              ? '<span class="badge bg-success">Activo</span>'
              : '<span class="badge bg-danger">Inactivo</span>'
          )
          $('#detalle_created_at').text(
            new Date(data.created_at).toLocaleString()
          )
          $('#detalle_updated_at').text(
            data.updated_at ? new Date(data.updated_at).toLocaleString() : 'N/A'
          )
          modalDetalles.show()
        },
        error: function (xhr) {
          showErrorToast(xhr.responseJSON)
        },
      })
    } else if (action.includes('btn-editar')) {
      // EDITAR USUARIO
      $.ajax({
        url: baseUrl + `api/system_users/${userId}`, // <-- CAMBIO AQUi
        method: 'GET',
        success: function (response) {
          const data = response.data
          $('#user_id').val(data.user_id)
          $('#nombre').val(data.nombre)
          $('#email').val(data.email)
          $('#nivel').val(data.nivel)
          $('#estado').val(data.estado)
          $('#contrasena').val('')
          $('#contrasena').prop('required', false) // La contraseña es opcional al editar
          $('#modalUsuarioLabel').text('Editar Usuario')
          modalUsuario.show()
        },
        error: function (xhr) {
          showErrorToast(xhr.responseJSON)
        },
      })
    } else if (action.includes('btn-eliminar')) {
      // ELIMINAR USUARIO
      Swal.fire({
        title: '¿Estás seguro?',
        text: 'No podrás revertir esta acción.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: baseUrl + `api/system_users/${userId}`, // <-- CAMBIO AQUÍ
            method: 'DELETE',
            success: function (response) {
              Swal.fire('Eliminado', response.message, 'success')
              tablaUsuarios.ajax.reload()
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
