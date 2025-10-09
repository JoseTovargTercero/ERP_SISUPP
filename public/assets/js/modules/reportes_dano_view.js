import { showErrorToast, formatDate } from '../helpers/helpers.js'

// Helper para construir rutas de la API
const api = (path) => `${baseUrl}api/${path}`

// ==========================================================
// == FORMATTERS Y HELPERS PARA BOOTSTRAP TABLE            ==
// ==========================================================

window.responseHandler = (res) => ({
  rows: res.data ?? [],
  total: res.data?.length ?? 0,
})

window.reporteFechaFormatter = (v) => (v ? formatDate(v) : '-')

window.criticidadFormatter = (v) => {
  if (v === 'ALTA') return '<span class="badge bg-danger">Alta</span>'
  if (v === 'MEDIA')
    return '<span class="badge bg-warning text-dark">Media</span>'
  return '<span class="badge bg-success">Baja</span>'
}

window.reporteEstadoFormatter = (v) => {
  if (v === 'EN_PROCESO')
    return '<span class="badge bg-info text-dark">En Proceso</span>'
  if (v === 'CERRADO') return '<span class="badge bg-secondary">Cerrado</span>'
  return '<span class="badge bg-primary">Abierto</span>'
}

window.reporteAccionesFormatter = (v) => `
    <div class="btn-group">
      <button class="btn btn-info btn-sm btn-ver" data-id="${v}" title="Ver"><i class="mdi mdi-eye"></i></button>
      <button class="btn btn-warning btn-sm btn-editar" data-id="${v}" title="Editar"><i class="mdi mdi-pencil"></i></button>
      <button class="btn btn-danger btn-sm btn-eliminar" data-id="${v}" title="Eliminar"><i class="mdi mdi-delete"></i></button>
    </div>`

// ==========================================================
// == LÓGICA PRINCIPAL                                     ==
// ==========================================================

document.addEventListener('DOMContentLoaded', () => {
  initFiltersAndButtons()
  initReportesTable()
})

/* =========================
   Helpers de Fetch
========================= */
async function jget(url) {
  const r = await fetch(url)
  const j = await r.json().catch(() => ({}))
  if (!j || j.value !== true) throw new Error(j?.message || 'Error de servidor')
  return j
}

async function jsend(url, method, body) {
  const r = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
  const j = await r.json().catch(() => ({}))
  if (!j || j.value !== true)
    throw new Error(j?.message || 'Operación no completada')
  return j
}

async function jdel(url) {
  const r = await fetch(url, { method: 'DELETE' })
  const j = await r.json().catch(() => ({}))
  if (!j || j.value !== true)
    throw new Error(j?.message || 'No se pudo eliminar')
  return j
}

/* =========================
   Función Genérica para Poblar Selects
========================= */
/**
 * Popula un <select> y opcionalmente lo inicializa con Select2.
 * DEVUELVE UNA PROMESA que se resuelve cuando la carga es exitosa.
 */
function populateSelect({
  selector,
  url,
  valueField,
  textField,
  placeholder = 'Seleccione una opción',
  useSelect2 = false,
  select2Options = {},
}) {
  const $select = $(selector)
  if ($select.data('select2')) {
    $select.select2('destroy')
  }
  $select.html(`<option value="">Cargando...</option>`).prop('disabled', true)

  // Retornamos la promesa del AJAX para poder usar await
  return $.ajax({
    url: url,
    method: 'GET',
    dataType: 'json',
    success: function (response) {
      let options = `<option value="">${placeholder}</option>`
      if (response && response.data && response.data.length > 0) {
        response.data.forEach((item) => {
          const text =
            typeof textField === 'function' ? textField(item) : item[textField]
          options += `<option value="${item[valueField]}">${text}</option>`
        })
      }
      $select.html(options).prop('disabled', false)
      if (useSelect2) {
        $select.select2(select2Options)
      }
    },
    error: function () {
      $select
        .html(`<option value="">Error al cargar</option>`)
        .prop('disabled', true)
    },
  })
}

/* =========================
   Inicialización
========================= */
function initReportesTable() {
  $('#tablaReportes').bootstrapTable({
    queryParams: (params) => {
      const f = $('#filtroFinca').val() || ''
      const a = $('#filtroAprisco').val() || ''
      const r = $('#filtroArea').val() || ''
      const e = $('#filtroEstado').val() || ''
      const c = $('#filtroCriticidad').val() || ''
      if (f) params.finca_id = f
      if (a) params.aprisco_id = a
      if (r) params.area_id = r
      if (e) params.estado_reporte = e
      if (c) params.criticidad = c
      return params
    },
  })
}

function initFiltersAndButtons() {
  const areaTextField = (item) =>
    item.nombre_personalizado ||
    (item.numeracion ? `Área ${item.numeracion}` : item.area_id.substring(0, 8))

  // Carga inicial de filtros y activación de Select2
  populateSelect({
    selector: '#filtroFinca',
    url: api('fincas'),
    placeholder: 'Todas',
    valueField: 'finca_id',
    textField: 'nombre',
    useSelect2: true,
  })
  populateSelect({
    selector: '#filtroAprisco',
    url: api('apriscos'),
    placeholder: 'Todos',
    valueField: 'aprisco_id',
    textField: 'nombre',
    useSelect2: true,
  })
  populateSelect({
    selector: '#filtroArea',
    url: api('areas'),
    placeholder: 'Todas',
    valueField: 'area_id',
    textField: areaTextField,
    useSelect2: true,
  })
  $('#filtroEstado, #filtroCriticidad').select2() // Activar Select2 para los filtros estáticos

  // Botón para crear nuevo reporte
  $('#btnNuevoReporte').on('click', async () => {
    resetReporteForm()
    $('#modalReporteLabel').text('Nuevo Reporte de Daño')
    await populateSelect({
      selector: '#finca_id',
      url: api('fincas'),
      placeholder: 'Ninguna',
      valueField: 'finca_id',
      textField: 'nombre',
    })
    await populateSelect({
      selector: '#aprisco_id',
      url: api('apriscos'),
      placeholder: 'Ninguno',
      valueField: 'aprisco_id',
      textField: 'nombre',
    })
    await populateSelect({
      selector: '#area_id',
      url: api('areas'),
      placeholder: 'Ninguna',
      valueField: 'area_id',
      textField: areaTextField,
    })
    new bootstrap.Modal('#modalReporte').show()
  })

  // Envío del formulario
  $('#formReporte').on('submit', submitReporte)

  // Delegación de eventos para botones de acción
  $(document).on(
    'click',
    'button.btn-ver, button.btn-editar, button.btn-eliminar',
    handleRowAction
  )

  // Filtros en cascada (LÓGICA CORREGIDA)
  $('#filtroFinca').on('change', async function () {
    const fincaId = this.value || ''
    const apriscoUrl = fincaId
      ? api(`apriscos?finca_id=${fincaId}`)
      : api('apriscos')

    await populateSelect({
      selector: '#filtroAprisco',
      url: apriscoUrl,
      placeholder: 'Todos',
      valueField: 'aprisco_id',
      textField: 'aprisco_nombre',
      useSelect2: true,
    })

    // Disparamos el 'change' del aprisco para que actualice las áreas
    $('#filtroAprisco').trigger('change')
  })

  $('#filtroAprisco').on('change', async function () {
    const apriscoId = this.value || ''
    const areaUrl = apriscoId
      ? api(`areas?aprisco_id=${apriscoId}`)
      : api('areas')

    await populateSelect({
      selector: '#filtroArea',
      url: areaUrl,
      placeholder: 'Todas',
      valueField: 'area_id',
      textField: areaTextField,
      useSelect2: true,
    })

    $('#tablaReportes').bootstrapTable('refresh')
  })

  $('#filtroArea, #filtroEstado, #filtroCriticidad').on('change', () => {
    // La tabla se refresca aquí solo si no es parte de la cascada inicial
    // El refresh principal lo maneja el change de #filtroAprisco
    if (!$(this).is('#filtroFinca')) {
      $('#tablaReportes').bootstrapTable('refresh')
    }
  })

  // Selects en cascada dentro del modal
  $('#finca_id').on('change', async function () {
    const fincaId = this.value || ''
    const apriscoUrl = fincaId
      ? api(`apriscos?finca_id=${fincaId}`)
      : api('apriscos')
    await populateSelect({
      selector: '#aprisco_id',
      url: apriscoUrl,
      placeholder: 'Ninguno',
      valueField: 'aprisco_id',
      textField: 'nombre',
    })
    // Al cambiar finca, reseteamos area, ya que depende de aprisco
    const apriscoId = $('#aprisco_id').val() || ''
    const areaUrl = apriscoId
      ? api(`areas?aprisco_id=${apriscoId}`)
      : api('areas')
    await populateSelect({
      selector: '#area_id',
      url: areaUrl,
      placeholder: 'Ninguna',
      valueField: 'area_id',
      textField: areaTextField,
    })
  })

  $('#aprisco_id').on('change', async function () {
    const apriscoId = this.value || ''
    const areaUrl = apriscoId
      ? api(`areas?aprisco_id=${apriscoId}`)
      : api('areas')
    await populateSelect({
      selector: '#area_id',
      url: areaUrl,
      placeholder: 'Ninguna',
      valueField: 'area_id',
      textField: areaTextField,
    })
  })
}

/* =========================
   Manejadores de Acciones
========================= */
async function submitReporte(e) {
  e.preventDefault()
  const body = {
    reporte_id: $('#reporte_id').val() || undefined,
    finca_id: $('#finca_id').val() || null,
    aprisco_id: $('#aprisco_id').val() || null,
    area_id: $('#area_id').val() || null,
    titulo: $('#titulo').val(),
    descripcion: $('#descripcion').val(),
    criticidad: $('#criticidad').val(),
    estado_reporte: $('#estado_reporte').val(),
  }
  const isEdit = !!body.reporte_id
  const url = isEdit
    ? api(`reportes_dano/${body.reporte_id}`)
    : api('reportes_dano')
  try {
    const res = await jsend(url, 'POST', body)
    bootstrap.Modal.getInstance(document.getElementById('modalReporte')).hide()
    $('#tablaReportes').bootstrapTable('refresh')
    Swal.fire('Éxito', res.message || 'Reporte guardado', 'success')
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function handleRowAction(e) {
  const $btn = $(e.currentTarget)
  const id = $btn.data('id')
  const url = api(`reportes_dano/${id}`)

  if ($btn.hasClass('btn-eliminar')) {
    const ok = await Swal.fire({
      title: '¿Estás seguro?',
      text: 'El reporte será eliminado (borrado lógico).',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
    })
    if (!ok.isConfirmed) return
    try {
      const res = await jdel(url)
      $('#tablaReportes').bootstrapTable('refresh')
      Swal.fire('¡Eliminado!', res.message, 'success')
    } catch (err) {
      showErrorToast({ message: err.message })
    }
    return
  }

  try {
    const { data: reporte } = await jget(url)
    if ($btn.hasClass('btn-ver')) {
      const modalEl = document.getElementById('modalDetalle')
      const modal =
        bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)
      document.getElementById('modalDetalleBody').innerHTML =
        renderDetailCard(reporte)
      modal.show()
    } else if ($btn.hasClass('btn-editar')) {
      await openEditModal(reporte)
    }
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

/* =========================
   Renderizado y Reseteo
========================= */
function renderDetailCard(d = {}) {
  const V = (x) => x ?? '-'
  // CORREGIDO para usar los nombres de campo de la API
  return `
      <div class="detail-card text-start">
        <div class="detail-grid">
          <div><span class="label">Título</span><span class="value">${V(
            d.titulo
          )}</span></div>
          <div><span class="label">Fecha Reporte</span><span class="value">${reporteFechaFormatter(
            d.fecha_reporte
          )}</span></div>
          <div><span class="label">Finca</span><span class="value">${V(
            d.finca_nombre
          )}</span></div>
          <div><span class="label">Aprisco</span><span class="value">${V(
            d.aprisco_nombre
          )}</span></div>
          <div><span class="label">Área</span><span class="value">${V(
            d.area_label
          )}</span></div>
          <div><span class="label">Criticidad</span><span class="value">${criticidadFormatter(
            V(d.criticidad)
          )}</span></div>
          <div><span class="label">Estado</span><span class="value">${reporteEstadoFormatter(
            V(d.estado_reporte)
          )}</span></div>
          ${
            d.fecha_cierre
              ? `<div><span class="label">Fecha Cierre</span><span class="value">${reporteFechaFormatter(
                  d.fecha_cierre
                )}</span></div>`
              : ''
          }
          <div style="grid-column:1/-1"><span class="label">Descripción</span><div class="value" style="white-space:pre-wrap; max-height: 150px; overflow-y: auto;">${V(
            d.descripcion
          )}</div></div>
        </div>
      </div>`
}

async function openEditModal(d) {
  resetReporteForm()
  const areaTextField = (item) =>
    item.nombre_personalizado ||
    (item.numeracion ? `Área ${item.numeracion}` : item.area_id.substring(0, 8))
  const modal = new bootstrap.Modal('#modalReporte')

  // Opciones para los selects dentro del modal
  const selectOptions = {
    useSelect2: true,
    select2Options: { dropdownParent: $('#formReporte') },
  }

  // 1. Cargar fincas y establecer valor
  await populateSelect({
    ...selectOptions,
    selector: '#finca_id',
    url: api('fincas'),
    placeholder: 'Ninguna',
    valueField: 'finca_id',
    textField: 'finca_nombre',
  })
  $('#finca_id')
    .val(d.finca_id || '')
    .trigger('change')

  // 2. Cargar apriscos y establecer valor
  const apriscoUrl = d.finca_id
    ? api(`apriscos?finca_id=${d.finca_id}`)
    : api('apriscos')
  await populateSelect({
    ...selectOptions,
    selector: '#aprisco_id',
    url: apriscoUrl,
    placeholder: 'Ninguno',
    valueField: 'aprisco_id',
    textField: 'aprisco_nombre',
  })
  $('#aprisco_id')
    .val(d.aprisco_id || '')
    .trigger('change')

  // 3. Cargar áreas y establecer valor
  const areaUrl = d.aprisco_id
    ? api(`areas?aprisco_id=${d.aprisco_id}`)
    : api('areas')
  await populateSelect({
    ...selectOptions,
    selector: '#area_id',
    url: areaUrl,
    placeholder: 'Ninguna',
    valueField: 'area_id',
    textField: areaTextField,
  })
  $('#area_id')
    .val(d.area_id || '')
    .trigger('change')

  // 4. Llenar el resto del formulario
  $('#reporte_id').val(d.reporte_id)
  $('#titulo').val(d.titulo)
  $('#descripcion').val(d.descripcion || '')
  $('#criticidad').val(d.criticidad)
  $('#estado_reporte').val(d.estado_reporte)

  $('#modalReporteLabel').text('Editar Reporte de Daño')
  modal.show()
}

function resetReporteForm() {
  $('#formReporte')[0]?.reset()
  $('#reporte_id').val('')
  // Forzar a los select2 a mostrar el placeholder
  $('#finca_id, #aprisco_id, #area_id').val(null).trigger('change')
}
