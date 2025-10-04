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
            <div class="btn-group">
                <button class="btn btn-info btn-sm btn-ver" data-id="${data}" title="Ver Detalles"><i class="mdi mdi-eye"></i></button>
                <button class="btn btn-warning btn-sm btn-editar" data-id="${data}" title="Editar"><i class="mdi mdi-pencil"></i></button>
                
                <button class="btn btn-success btn-sm btn-permisos" data-id="${data}" data-nombre="${row.nombre}" title="Asignar Permisos"><i class="mdi mdi-lock-open-outline"></i></button>

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

  // Función para renderizar el acordeón de permisos
  const renderizarAcordeonPermisos = (todosLosMenus, permisosUsuario) => {
    const contenedorAcordeon = $('#accordionPermisos')
    contenedorAcordeon.html('') // Limpiar contenido anterior

    // Crear un Set con los menu_id que el usuario ya tiene para una búsqueda rápida
    const permisosAsignados = new Set(
      permisosUsuario.map((p) => p.menu.menu_id)
    )

    // Agrupar todos los menús por categoría
    const menusPorCategoria = todosLosMenus.reduce((acc, menu) => {
      const categoria = menu.categoria || 'Sin Categoría'
      if (!acc[categoria]) {
        acc[categoria] = []
      }
      acc[categoria].push(menu)
      return acc
    }, {})

    // Generar el HTML del acordeón
    Object.keys(menusPorCategoria).forEach((categoria, index) => {
      const collapseId = `collapse-${index}`
      const headerId = `header-${index}`

      // Construir la lista de checkboxes para esta categoría
      const checkboxesHtml = menusPorCategoria[categoria]
        .map((menu) => {
          const isChecked = permisosAsignados.has(menu.menu_id) ? 'checked' : ''
          return `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${menu.menu_id}" id="menu-${menu.menu_id}" ${isChecked}>
                    <label class="form-check-label" for="menu-${menu.menu_id}">
                        ${menu.nombre}
                    </label>
                </div>
            `
        })
        .join('')

      const itemHtml = `
            <div class="accordion-item">
                <h2 class="accordion-header" id="${headerId}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                        ${categoria}
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${headerId}" data-bs-parent="#accordionPermisos">
                    <div class="accordion-body">
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
    const userName = $(this).data('nombre') // Obtenemos el nombre del data attribute

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
    } else if (action.includes('btn-permisos')) {
      // <-- NUEVO BLOQUE
      // ABRIR Y PREPARAR MODAL DE PERMISOS
      $('#permisos_user_id').val(userId)
      $('#modalPermisosLabel span').text(userName)
      $('#accordionPermisos').html(
        '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>'
      )
      modalPermisos.show()

      // Realizar dos llamadas AJAX en paralelo para obtener todos los menús y los permisos del usuario
      Promise.all([
        $.ajax({ url: baseUrl + 'api/menus' }), // Endpoint para obtener todos los menús
        $.ajax({ url: baseUrl + `api/users-permisos/user/${userId}` }), // Endpoint para los permisos del usuario
      ])
        .then(function (responses) {
          const todosLosMenus = responses[0].data
          const permisosUsuario = responses[1].data

          // Una vez que ambas llamadas terminan, renderizar el contenido
          renderizarAcordeonPermisos(todosLosMenus, permisosUsuario)
        })
        .catch(function (error) {
          $('#accordionPermisos').html(
            '<p class="text-danger text-center">Error al cargar los permisos.</p>'
          )
          console.error('Error al obtener datos de permisos:', error)
          showErrorToast({
            message: 'No se pudieron cargar los datos de permisos.',
          })
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
      url: baseUrl + 'users-permisos', // Endpoint POST para asignar
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      success: function (response) {
        modalPermisos.hide()
        Swal.fire({
          icon: 'success',
          title: '¡Éxito!',
          text: 'Permisos actualizados correctamente.',
        })
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON)
      },
    })
  })
})
