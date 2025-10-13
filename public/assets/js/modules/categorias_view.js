import { showErrorToast, showSuccessToast } from '../helpers/helpers.js'

// === FUNCIONES GLOBALES REQUERIDAS POR BOOTSTRAP-TABLE ===
// Estas funciones deben estar en el scope global (window) para que los atributos data-* las encuentren.

// Función para adaptar la respuesta de la API
window.responseHandler = (res) => ({ rows: res.data, total: res.data.length })

// Formateador para el ícono de arrastre (reutilizable)
window.dragHandleFormatter = () =>
  '<i class="mdi mdi-drag-vertical" style="cursor: grab;"></i>'

// Asigna un ID único a cada fila <tr> para el drag-and-drop
window.rowAttrFunc = (row, index) => ({ 'data-id': row.menu_id || row.nombre })

// Formateador para el nombre de la categoría (lo hace más visible)
window.nombreCategoriaFormatter = (value, row) => {
  const count = row.item_count || 0
  // Cambia de color si el conteo es cero para que sea menos prominente
  const badgeColor = count > 0 ? 'bg-secondary' : 'bg-light text-dark'

  return `
        <div class="d-flex justify-content-between align-items-center">
            <strong class="text-primary text-uppercase">${value}</strong>
            <span class="badge rounded-pill ${badgeColor}">${count} ítems</span>
        </div>
    `
}

// Formateador para el botón de "Gestionar Ítems" en la tabla de categorías
window.accionesCategoriaFormatter = (value) => `
    <button class="btn btn-info btn-sm btn-gestionar-items" data-categoria="${value}">
        <i class="mdi mdi-format-list-bulleted-square"></i> Gestionar Ítems
    </button>`

// Formateador para los botones de acción en la tabla de ítems (dentro del modal)
window.accionesItemFormatter = (value, row) => `
    <div class="btn-group">
        <button class="btn btn-warning btn-sm btn-editar-item" data-id="${value}" title="Editar"><i class="mdi mdi-pencil"></i></button>
        <button class="btn btn-danger btn-sm btn-eliminar-item" data-id="${value}" title="Eliminar"><i class="mdi mdi-delete"></i></button>
    </div>`

// === LÓGICA PRINCIPAL DEL DOCUMENTO ===
document.addEventListener('DOMContentLoaded', () => {
  // Referencias a los elementos del DOM
  const $tablaCategorias = $('#tablaCategorias')
  const $tablaItems = $('#tablaItems')
  const modalItems = new bootstrap.Modal(document.getElementById('modalItems'))
  const modalFormularioItem = new bootstrap.Modal(
    document.getElementById('modalFormularioItem')
  )
  const formItem = document.getElementById('formItem')

  // --- GESTIÓN DE CATEGORÍAS (VISTA PRINCIPAL) ---

  $tablaCategorias.on('reorder-row.bs.table', (e, newOrder) => {
    const orderedNombres = newOrder.map((row) => row.nombre)
    $.ajax({
      url: `${baseUrl}api/menus-categorias/reordenar`,
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(orderedNombres),
      success: (res) => showSuccessToast(res.message),
      error: (xhr) => {
        showErrorToast(xhr.responseJSON)
        $tablaCategorias.bootstrapTable('refresh')
      },
    })
  })

  $tablaCategorias.on('click', '.btn-gestionar-items', function () {
    const categoriaNombre = $(this).data('categoria')

    // Guardar la categoría actual en el modal y en el botón "Nuevo Ítem"
    $('#modalItems').data('categoria', categoriaNombre)
    $('#btnNuevoItem').data('categoria', categoriaNombre)

    // Configurar el título del modal
    $('#modalItemsLabel').html(
      `Ítems de la categoría: <strong class="text-primary text-uppercase">${categoriaNombre}</strong>`
    )

    // Destruir la tabla de ítems si ya existía para evitar conflictos
    $tablaItems.bootstrapTable('destroy')

    // Inicializar la tabla de ítems con los datos de la categoría seleccionada
    $tablaItems.bootstrapTable({
      url: `${baseUrl}api/menus?categoria=${categoriaNombre}`,
      responseHandler: window.responseHandler,
      classes: 'table table-hover',
      sidePagination: 'client', // Paginación del lado del cliente es mejor para listas cortas
    })

    modalItems.show()
  })

  // --- GESTIÓN DE ÍTEMS (DENTRO DEL MODAL) ---

  $tablaItems.on('reorder-row.bs.table', (e, newOrder) => {
    const orderedIds = newOrder.map((row) => row.menu_id)
    $.ajax({
      url: `${baseUrl}api/menus-reordenar`,
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(orderedIds),
      success: (res) => showSuccessToast(res.message),
      error: (xhr) => {
        showErrorToast(xhr.responseJSON)
        $tablaItems.bootstrapTable('refresh')
      },
    })
  })

  // Botón "Nuevo Ítem" dentro del modal de ítems
  $('#btnNuevoItem').on('click', function () {
    formItem.reset()
    $('#menu_id').val('')
    const categoria = $(this).data('categoria')
    $('#categoria').val(categoria) // Asignar la categoría actual al campo oculto del formulario
    $('#modalFormularioItemLabel').text('Crear Nuevo Ítem')
    modalFormularioItem.show()
  })

  // Acciones de Editar y Eliminar para los ítems
  $tablaItems.on('click', 'button', function () {
    const action = $(this).attr('class')
    const itemId = $(this).data('id')

    if (action.includes('btn-editar-item')) {
      $.get(`${baseUrl}api/menus/${itemId}`, (response) => {
        const data = response.data
        $('#menu_id').val(data.menu_id)
        $('#nombre').val(data.nombre)
        $('#url').val(data.url)
        $('#icono').val(data.icono)
        $('#user_level').val(data.user_level)
        $('#orden').val(data.orden)
        $('#categoria').val(data.categoria)
        $('#modalFormularioItemLabel').text('Editar Ítem')
        modalFormularioItem.show()
      })
    } else if (action.includes('btn-eliminar-item')) {
      Swal.fire({
        title: '¿Estás seguro?',
        text: 'El ítem será eliminado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: `${baseUrl}api/menus/${itemId}`,
            method: 'DELETE',
            success: (res) => {
              Swal.fire('Eliminado', res.message, 'success')
              $tablaItems.bootstrapTable('refresh')
            },
            error: (xhr) => showErrorToast(xhr.responseJSON),
          })
        }
      })
    }
  })

  // Envío del formulario de crear/editar ítem
  $(formItem).on('submit', function (e) {
    e.preventDefault()
    const menuId = $('#menu_id').val()
    let url = `${baseUrl}api/menus`
    if (menuId) {
      url = `${baseUrl}api/menus/${menuId}`
    }

    const formData = {}
    $(this)
      .serializeArray()
      .forEach((item) => (formData[item.name] = item.value))

    $.ajax({
      url: url,
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(formData),
      success: (res) => {
        modalFormularioItem.hide()
        Swal.fire('¡Éxito!', res.message, 'success')
        $tablaItems.bootstrapTable('refresh') // Refrescar la tabla de ítems
      },
      error: (xhr) => showErrorToast(xhr.responseJSON),
    })
  })
})
