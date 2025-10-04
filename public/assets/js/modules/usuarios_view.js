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
          switch (
            String(data) // Convertido a String para comparación segura
          ) {
            case '0':
              return 'Administrador'
            case '1':
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
            <div class="btn-group">
                <button class="btn btn-info btn-sm btn-ver" data-id="${data}" title="Ver Detalles"><i class="mdi mdi-eye"></i></button>
                <button class="btn btn-warning btn-sm btn-editar" data-id="${data}" title="Editar"><i class="mdi mdi-pencil"></i></button>
                
                <button class="btn btn-success btn-sm btn-permisos" data-id="${data}" data-nombre="${row.nombre}" data-nivel="${row.nivel}" title="Asignar Permisos"><i class="mdi mdi-lock-open-outline"></i></button>

                <button class="btn btn-danger btn-sm btn-eliminar" data-id="${data}" title="Eliminar"><i class="mdi mdi-delete"></i></button>
            </div>
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

  // Inicialización del nuevo modal
  const modalPermisos = new bootstrap.Modal(
    document.getElementById('modalPermisos')
  )

  const renderizarAcordeonPermisos = (todosLosMenus, permisosUsuario) => {
    const contenedorAcordeon = $('#accordionPermisos')
    contenedorAcordeon.html('')

    const permisosAsignados = new Set(
      permisosUsuario.map((p) => p.menu.menu_id)
    )
    const menusPorCategoria = todosLosMenus.reduce((acc, menu) => {
      const categoria = menu.categoria || 'Sin Categoría'
      if (!acc[categoria]) acc[categoria] = []
      acc[categoria].push(menu)
      return acc
    }, {})

    Object.keys(menusPorCategoria).forEach((categoria, index) => {
      const collapseId = `collapse-${index}`
      const headerId = `header-${index}`

      const isFirst = index === 0
      const linkClass = isFirst ? '' : 'collapsed'
      const expandedState = isFirst ? 'true' : 'false'
      const collapseClass = isFirst ? 'show' : ''

      const checkboxesHtml = menusPorCategoria[categoria]
        .map((menu) => {
          const isChecked = permisosAsignados.has(menu.menu_id) ? 'checked' : ''
          return `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="${menu.menu_id}" id="menu-${menu.menu_id}" ${isChecked}>
                        <label class="form-check-label" for="menu-${menu.menu_id}">${menu.nombre}</label>
                    </div>`
        })
        .join('')

      // Nueva estructura HTML con el ícono de flecha
      const itemHtml = `
                <div class="card mb-0">
                    <div class="card-header" id="${headerId}">
                        <h5 class="m-0">
                            <a class="custom-accordion-title ${linkClass} d-block py-1"
                                data-bs-toggle="collapse" href="#${collapseId}"
                                aria-expanded="${expandedState}" aria-controls="${collapseId}">
                                ${categoria.toLocaleUpperCase()}
                                <i class="mdi mdi-chevron-down accordion-arrow"></i>
                            </a>
                        </h5>
                    </div>
                    <div id="${collapseId}" class="collapse ${collapseClass}"
                        aria-labelledby="${headerId}" data-bs-parent="#accordionPermisos">
                        <div class="card-body">
                            ${checkboxesHtml}
                        </div>
                    </div>
                </div>
            `
      contenedorAcordeon.append(itemHtml)
    })
  }
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

    let url = baseUrl + 'api/system_users' // <-- CAMBIO AQUÍ
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
    const userName = $(this).data('nombre')
    const userLevel = $(this).data('nivel')

    if (action.includes('btn-ver')) {
      // VER DETALLES
      $.ajax({
        url: baseUrl + `api/system_users/${userId}`, // <-- CAMBIO AQUÍ
        method: 'GET',
        success: function (response) {
          const data = response.data

          $('#detalle_nombre').text(data.nombre)
          $('#detalle_email').text(data.email)
          $('#detalle_nivel').text(
            data.nivel == 0 ? 'Administrador' : 'Usuario'
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
    } else if (action.includes('btn-permisos')) {
      // ===============================================================
      // ==      LÓGICA ACTUALIZADA PARA MANEJAR NIVEL 0      ==
      // ===============================================================
      $('#permisos_user_id').val(userId)
      $('#modalPermisosLabel span').text(userName)
      modalPermisos.show()

      // Comparamos como string para evitar problemas con tipos de datos (e.g., 0 vs "0")
      if (String(userLevel) === '0') {
        // Si es admin, mostrar mensaje y ocultar botón de guardar
        const adminMessage = `
                    <div class="alert alert-info" role="alert">
                        <i class="mdi mdi-account-star me-2"></i>
                        Este usuario es <strong>Administrador</strong> y ya posee acceso a todos los módulos.
                    </div>
                `
        $('#accordionPermisos').html(adminMessage)
        $('#btnGuardarPermisos').hide() // Ocultar el botón
      } else {
        // Si no es admin, cargar los permisos como antes
        $('#accordionPermisos').html(
          '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>'
        )
        $('#btnGuardarPermisos').show() // Asegurarse de que el botón esté visible

        Promise.all([
          $.ajax({ url: baseUrl + '/api/menus' }),
          $.ajax({ url: baseUrl + `/api/users-permisos/user/${userId}` }),
        ])
          .then(function (responses) {
            renderizarAcordeonPermisos(responses[0].data, responses[1].data)
          })
          .catch(function (error) {
            $('#accordionPermisos').html(
              '<p class="text-danger text-center">Error al cargar los permisos.</p>'
            )
            showErrorToast({
              message: 'No se pudieron cargar los datos de permisos.',
            })
          })
      }
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

  $('#btnGuardarPermisos').on('click', function () {
    const userId = $('#permisos_user_id').val()
    const menuIdsSeleccionados = []

    // Recolectar todos los IDs de los menús seleccionados (checkboxes marcados)
    $('#accordionPermisos .form-check-input:checked').each(function () {
      menuIdsSeleccionados.push($(this).val())
    })

    const payload = {
      user_id: userId,
      menu_ids: menuIdsSeleccionados,
    }

    // Usar el endpoint para asignar permisos
    $.ajax({
      url: baseUrl + 'api/users-permisos', // Endpoint POST para asignar
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      success: function (response) {
        if (response.value) {
          modalPermisos.hide()
          Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: 'Permisos actualizados correctamente.',
          })
        } else {
          showErrorToast(response)
        }
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON)
      },
    })
  })
})
