// agro_tabs_view.js
import { showErrorToast, formatDate } from '../helpers/helpers.js'

// Helper central para rutas API
const api = (path) => `${baseUrl}api/${path}`

// ==========================================================
// == FORMATTERS Y HELPERS PARA BOOTSTRAP TABLE            ==
// ==========================================================

/**
 * Adapta la respuesta de la API al formato que Bootstrap Table espera.
 * La API devuelve { "data": [...] }, y la tabla necesita { "rows": [...] }.
 */
window.responseHandler = (res) => ({
  rows: res.data ?? [],
  total: res.data?.length ?? 0,
})

// Formatters para estados y datos personalizados
window.fincaEstadoFormatter = (v) =>
  v === 'ACTIVA'
    ? '<span class="badge bg-success">Activa</span>'
    : '<span class="badge bg-secondary">Inactiva</span>'
window.apriscoEstadoFormatter = (v) =>
  v === 'ACTIVO'
    ? '<span class="badge bg-success">Activo</span>'
    : '<span class="badge bg-secondary">Inactivo</span>'
window.areaNombreFormatter = (v, row) =>
  `${row.nombre_personalizado || '-'} / ${row.numeracion || '-'}`
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

/**
 * Genera el HTML para los botones de acción.
 * @param {string} tipo - El tipo de entidad (finca, aprisco, etc.).
 * @param {string|number} id - El ID del registro.
 */
const actionBtns = (tipo, id) => `
    <div class="btn-group">
      <button class="btn btn-info btn-sm btn-ver" data-type="${tipo}" data-id="${id}" title="Ver"><i class="mdi mdi-eye"></i></button>
      <button class="btn btn-warning btn-sm btn-editar" data-type="${tipo}" data-id="${id}" title="Editar"><i class="mdi mdi-pencil"></i></button>
      <button class="btn btn-danger btn-sm btn-eliminar" data-type="${tipo}" data-id="${id}" title="Eliminar"><i class="mdi mdi-delete"></i></button>
    </div>`

// Asignación de formatters de acciones para cada tabla
window.fincaAccionesFormatter = (v, row) => actionBtns('finca', v)
window.apriscoAccionesFormatter = (v, row) => actionBtns('aprisco', v)
window.areaAccionesFormatter = (v, row) => actionBtns('area', v)
window.reporteAccionesFormatter = (v, row) => actionBtns('reporte', v)

// ==========================================================
// == LÓGICA PRINCIPAL                                     ==
// ==========================================================

// Banderas para controlar la carga inicial de cada tabla
let IS_FINCA_LOADED, IS_APRISCO_LOADED, IS_AREA_LOADED, IS_REPORTE_LOADED

document.addEventListener('DOMContentLoaded', () => {
  initButtons()
  wireCancelButtons()

  // Hooks de lazy-load para cada pestaña
  document
    .getElementById('pane-fincas')
    ?.addEventListener('lazyload', loadFincasTab)
  document
    .getElementById('pane-apriscos')
    ?.addEventListener('lazyload', loadApriscosTab)
  document
    .getElementById('pane-areas')
    ?.addEventListener('lazyload', loadAreasTab)
  document
    .getElementById('pane-reportes')
    ?.addEventListener('lazyload', loadReportesTab)

  // Refresca la tabla correspondiente cuando se vuelve a una pestaña ya cargada
  document.addEventListener('tab:refresh', ({ detail }) => {
    const { paneId } = detail || {}
    if (paneId === 'pane-fincas' && IS_FINCA_LOADED)
      $('#tablaFincas').bootstrapTable('refresh')
    if (paneId === 'pane-apriscos' && IS_APRISCO_LOADED)
      $('#tablaApriscos').bootstrapTable('refresh')
    if (paneId === 'pane-areas' && IS_AREA_LOADED)
      $('#tablaAreas').bootstrapTable('refresh')
    if (paneId === 'pane-reportes' && IS_REPORTE_LOADED)
      $('#tablaReportes').bootstrapTable('refresh')
  })
})

/* =========================
   Helpers de fetch
========================= */
async function jget(url) {
  const r = await fetch(url)
  const j = await r.json().catch(() => ({}))
  if (!j || j.value !== true) {
    const msg = j?.message || 'Error de servidor'
    throw new Error(msg)
  }
  return j
}

async function jsend(url, method, body) {
  const r = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
  const j = await r.json().catch(() => ({}))
  if (!j || j.value !== true) {
    const msg = j?.message || 'Operación no completada'
    throw new Error(msg)
  }
  return j
}

async function jdel(url) {
  const r = await fetch(url, { method: 'DELETE' })
  const j = await r.json().catch(() => ({}))
  if (!j || j.value !== true) {
    const msg = j?.message || 'No se pudo eliminar'
    throw new Error(msg)
  }
  return j
}

/* =========================
   Carga diferida por Pestaña (TAB)
========================= */
async function loadFincasTab() {
  if (!IS_FINCA_LOADED) {
    $('#tablaFincas').bootstrapTable({
      url: api('fincas'),
      responseHandler: window.responseHandler,
    })
    IS_FINCA_LOADED = true
  } else {
    $('#tablaFincas').bootstrapTable('refresh')
  }
}

async function loadApriscosTab() {
  await cargarFincasSelect('#filtroApriscosFinca', true)
  if (!IS_APRISCO_LOADED) {
    $('#tablaApriscos').bootstrapTable({
      url: api('apriscos'),
      responseHandler: window.responseHandler,
      queryParams: (params) => {
        const fincaId = $('#filtroApriscosFinca').val() || ''
        if (fincaId) params.finca_id = fincaId
        return params
      },
    })
    IS_APRISCO_LOADED = true
  } else {
    $('#tablaApriscos').bootstrapTable('refresh')
  }
}

async function loadAreasTab() {
  await cargarFincasSelect('#filtroAreasFinca', true)
  await cargarApriscosSelect('#filtroAreasAprisco', '', true)
  if (!IS_AREA_LOADED) {
    $('#tablaAreas').bootstrapTable({
      url: api('areas'),
      responseHandler: window.responseHandler,
      queryParams: (params) => {
        const fincaId = $('#filtroAreasFinca').val() || ''
        const apriscoId = $('#filtroAreasAprisco').val() || ''
        if (fincaId) params.finca_id = fincaId
        if (apriscoId) params.aprisco_id = apriscoId
        return params
      },
    })
    IS_AREA_LOADED = true
  } else {
    $('#tablaAreas').bootstrapTable('refresh')
  }
}

async function loadReportesTab() {
  await cargarFincasSelect('#filtroRepFinca', true)
  await cargarApriscosSelect('#filtroRepAprisco', '', true)
  await cargarAreasSelect('#filtroRepArea', '', true)
  if (!IS_REPORTE_LOADED) {
    $('#tablaReportes').bootstrapTable({
      url: api('reportes_dano'),
      responseHandler: window.responseHandler,
      queryParams: (params) => {
        const f = $('#filtroRepFinca').val() || ''
        const a = $('#filtroRepAprisco').val() || ''
        const r = $('#filtroRepArea').val() || ''
        const e = $('#filtroRepEstado').val() || ''
        const c = $('#filtroRepCrit').val() || ''
        if (f) params.finca_id = f
        if (a) params.aprisco_id = a
        if (r) params.area_id = r
        if (e) params.estado_reporte = e
        if (c) params.criticidad = c
        return params
      },
    })
    IS_REPORTE_LOADED = true
  } else {
    $('#tablaReportes').bootstrapTable('refresh')
  }
}

/* =========================
   Eventos de Botones y Filtros
========================= */
function initButtons() {
  $('#btnRefrescarTodo').on('click', () => {
    if (IS_FINCA_LOADED) $('#tablaFincas').bootstrapTable('refresh')
    if (IS_APRISCO_LOADED) $('#tablaApriscos').bootstrapTable('refresh')
    if (IS_AREA_LOADED) $('#tablaAreas').bootstrapTable('refresh')
    if (IS_REPORTE_LOADED) $('#tablaReportes').bootstrapTable('refresh')
  })

  // ---- Botones para crear nuevos registros ----
  $('#btnNuevaFinca').on('click', () => {
    resetFincaForm()
    $('#modalFincaLabel').text('Crear Nueva Finca')
    new bootstrap.Modal('#modalFinca').show()
  })
  $('#btnNuevoAprisco').on('click', async () => {
    resetApriscoForm()
    await cargarFincasSelect('#aprisco_finca_id')
    $('#modalApriscoLabel').text('Crear Nuevo Aprisco')
    new bootstrap.Modal('#modalAprisco').show()
  })
  $('#btnNuevaArea').on('click', async () => {
    resetAreaForm()
    await cargarFincasSelect('#area_finca_id')
    await cargarApriscosSelect('#area_aprisco_id', $('#area_finca_id').val())
    $('#modalAreaLabel').text('Crear Nueva Área')
    new bootstrap.Modal('#modalArea').show()
  })
  $('#btnNuevoReporte').on('click', async () => {
    resetReporteForm()
    await cargarFincasSelect('#rep_finca_id', true)
    await cargarApriscosSelect(
      '#rep_aprisco_id',
      $('#rep_finca_id').val(),
      true
    )
    await cargarAreasSelect('#rep_area_id', $('#rep_aprisco_id').val(), true)
    $('#modalReporteLabel').text('Nuevo Reporte de Daño')
    new bootstrap.Modal('#modalReporte').show()
  })

  // ---- Envío de formularios (submit) ----
  $('#formFinca').on('submit', submitFinca)
  $('#formAprisco').on('submit', submitAprisco)
  $('#formArea').on('submit', submitArea)
  $('#formReporte').on('submit', submitReporte)

  // ---- Delegación de eventos para botones de acción en las filas ----
  $(document).on(
    'click',
    'button.btn-ver,button.btn-editar,button.btn-eliminar',
    handleRowAction
  )

  // ---- Filtros en cascada que recargan las tablas ----
  $('#filtroApriscosFinca').on('change', () =>
    $('#tablaApriscos').bootstrapTable('refresh')
  )

  $('#filtroAreasFinca').on('change', async function () {
    await cargarApriscosSelect('#filtroAreasAprisco', this.value || '', true)
    $('#tablaAreas').bootstrapTable('refresh')
  })
  $('#filtroAreasAprisco').on('change', () =>
    $('#tablaAreas').bootstrapTable('refresh')
  )

  $('#filtroRepFinca').on('change', async function () {
    await cargarApriscosSelect('#filtroRepAprisco', this.value || '', true)
    $('#filtroRepArea').empty().append(new Option('Todas', ''))
    $('#tablaReportes').bootstrapTable('refresh')
  })
  $('#filtroRepAprisco').on('change', async function () {
    await cargarAreasSelect('#filtroRepArea', this.value || '', true)
    $('#tablaReportes').bootstrapTable('refresh')
  })
  $('#filtroRepArea,#filtroRepEstado,#filtroRepCrit').on('change', () =>
    $('#tablaReportes').bootstrapTable('refresh')
  )

  // ---- Selects en cascada dentro de los modales ----
  $('#rep_finca_id').on('change', async function () {
    await cargarApriscosSelect('#rep_aprisco_id', this.value, true)
    await cargarAreasSelect('#rep_area_id', $('#rep_aprisco_id').val(), true)
  })
  $('#rep_aprisco_id').on('change', async function () {
    await cargarAreasSelect('#rep_area_id', this.value, true)
  })
  $('#area_finca_id').on('change', async function () {
    await cargarApriscosSelect('#area_aprisco_id', this.value)
  })
}

/* =========================
   Manejo de Modales
========================= */
function wireCancelButtons() {
  $(document).on('click', '.btn-cancelar', function (e) {
    e.preventDefault()
    e.stopPropagation()
    if (!this.getAttribute('type')) this.setAttribute('type', 'button')
    const modalEl = this.closest('.modal')
    if (modalEl) {
      const inst =
        bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)
      inst.hide()
    }
  })
}

/* =========================
   Carga dinámica de Selects
========================= */
async function cargarFincasSelect(selector, includeEmpty = false) {
  try {
    const resp = await jget(api('fincas'))
    const list = resp?.data ?? []
    const $sel = $(selector)
    $sel.empty()
    if (includeEmpty)
      $sel.append(new Option(selector.includes('filtro') ? 'Todas' : '', ''))
    list.forEach((x) => $sel.append(new Option(x.nombre, x.finca_id)))
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function cargarApriscosSelect(selector, fincaId, includeEmpty = false) {
  try {
    const url = fincaId
      ? api(`apriscos?finca_id=${encodeURIComponent(fincaId)}`)
      : api('apriscos')
    const resp = await jget(url)
    const list = resp?.data ?? []
    const $sel = $(selector)
    $sel.empty()
    if (includeEmpty)
      $sel.append(new Option(selector.includes('filtro') ? 'Todos' : '', ''))
    list.forEach((x) => $sel.append(new Option(x.nombre, x.aprisco_id)))
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function cargarAreasSelect(selector, apriscoId, includeEmpty = false) {
  try {
    const url = apriscoId
      ? api(`areas?aprisco_id=${encodeURIComponent(apriscoId)}`)
      : api('areas')
    const resp = await jget(url)
    const list = resp?.data ?? []
    const $sel = $(selector)
    $sel.empty()
    if (includeEmpty)
      $sel.append(new Option(selector.includes('filtro') ? 'Todas' : '', ''))
    list.forEach((x) => {
      const nice =
        x.nombre_personalizado ||
        (x.numeracion ? `Área ${x.numeracion}` : x.area_id)
      $sel.append(new Option(nice, x.area_id))
    })
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

/* =========================
   Manejadores de Submit (Formularios)
========================= */
async function submitFinca(e) {
  e.preventDefault()
  const body = {
    finca_id: $('#finca_id').val() || undefined,
    nombre: $('#finca_nombre').val(),
    ubicacion: $('#finca_ubicacion').val(),
    estado: $('#finca_estado').val(),
  }
  const isEdit = !!body.finca_id
  const url = isEdit ? api(`fincas/${body.finca_id}`) : api('fincas')
  try {
    const res = await jsend(url, 'POST', body)
    bootstrap.Modal.getInstance(document.getElementById('modalFinca')).hide()
    $('#tablaFincas').bootstrapTable('refresh')
    Swal.fire('Éxito', res.message || 'Guardado', 'success')
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function submitAprisco(e) {
  e.preventDefault()
  const body = {
    aprisco_id: $('#aprisco_id').val() || undefined,
    finca_id: $('#aprisco_finca_id').val(),
    nombre: $('#aprisco_nombre').val(),
    estado: $('#aprisco_estado').val(),
  }
  const isEdit = !!body.aprisco_id
  const url = isEdit ? api(`apriscos/${body.aprisco_id}`) : api('apriscos')
  try {
    const res = await jsend(url, 'POST', body)
    bootstrap.Modal.getInstance(document.getElementById('modalAprisco')).hide()
    $('#tablaApriscos').bootstrapTable('refresh')
    Swal.fire('Éxito', res.message || 'Guardado', 'success')
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function submitArea(e) {
  e.preventDefault()
  const body = {
    area_id: $('#area_id').val() || undefined,
    aprisco_id: $('#area_aprisco_id').val(),
    tipo_area: $('#area_tipo_area').val(),
    nombre_personalizado: $('#area_nombre_personalizado').val(),
    numeracion: $('#area_numeracion').val(),
    estado: $('#area_estado').val(),
  }
  const isEdit = !!body.area_id
  const url = isEdit ? api(`areas/${body.area_id}`) : api('areas')
  try {
    const res = await jsend(url, 'POST', body)
    bootstrap.Modal.getInstance(document.getElementById('modalArea')).hide()
    $('#tablaAreas').bootstrapTable('refresh')
    Swal.fire('Éxito', res.message || 'Guardado', 'success')
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function submitReporte(e) {
  e.preventDefault()
  const body = {
    reporte_id: $('#reporte_id').val() || undefined,
    finca_id: $('#rep_finca_id').val() || null,
    aprisco_id: $('#rep_aprisco_id').val() || null,
    area_id: $('#rep_area_id').val() || null,
    titulo: $('#rep_titulo').val(),
    descripcion: $('#rep_descripcion').val(),
    criticidad: $('#rep_criticidad').val(),
    estado_reporte: $('#rep_estado').val(),
  }
  const isEdit = !!body.reporte_id
  const url = isEdit
    ? api(`reportes_dano/${body.reporte_id}`)
    : api('reportes_dano')
  try {
    const res = await jsend(url, 'POST', body)
    bootstrap.Modal.getInstance(document.getElementById('modalReporte')).hide()
    $('#tablaReportes').bootstrapTable('refresh')
    Swal.fire('Éxito', res.message || 'Guardado', 'success')
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

/* =========================
   Acciones (Ver/Editar/Eliminar)
========================= */
function tipoRoute(tipo) {
  if (tipo === 'finca') return 'fincas'
  if (tipo === 'aprisco') return 'apriscos'
  if (tipo === 'area') return 'areas'
  return 'reportes_dano'
}

function reloadBT(tipo) {
  if (tipo === 'finca') $('#tablaFincas').bootstrapTable('refresh')
  else if (tipo === 'aprisco') $('#tablaApriscos').bootstrapTable('refresh')
  else if (tipo === 'area') $('#tablaAreas').bootstrapTable('refresh')
  else $('#tablaReportes').bootstrapTable('refresh')
}

async function handleRowAction(e) {
  const $btn = $(e.currentTarget)
  const tipo = $btn.data('type')
  const id = $btn.data('id')

  if ($btn.hasClass('btn-eliminar')) {
    const ok = await Swal.fire({
      title: '¿Estás seguro?',
      text: 'No podrás revertir esta acción.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
    })

    if (!ok.isConfirmed) return

    try {
      const url = api(`${tipoRoute(tipo)}/${id}`)
      const res = await jdel(url)
      reloadBT(tipo) // Recarga la tabla correcta
      Swal.fire(
        '¡Eliminado!',
        res.message || 'El registro ha sido eliminado.',
        'success'
      )
    } catch (err) {
      showErrorToast({ message: err.message })
    }
    return
  }

  // Lógica para Ver y Editar
  try {
    const url = api(`${tipoRoute(tipo)}/${id}`)
    const res = await jget(url)
    const row = res.data

    if ($btn.hasClass('btn-ver')) {
      // ==========================================================
      // ==      AQUÍ LA LÓGICA ACTUALIZADA PARA EL MODAL        ==
      // ==========================================================
      const modalEl = document.getElementById('modalDetalle')
      const modal =
        bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)

      // Inyectar el título y el contenido en el modal
      document.getElementById('modalDetalleLabel').innerText = `Detalle de ${
        tipo.charAt(0).toUpperCase() + tipo.slice(1)
      }`
      document.getElementById('modalDetalleBody').innerHTML = renderDetailCard(
        tipo,
        row
      )

      // Mostrar el modal
      modal.show()
    } else if ($btn.hasClass('btn-editar')) {
      await openEditModal(tipo, row)
    }
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

function badgeEstadoFinca(v) {
  return v === 'ACTIVA'
    ? '<span class="badge bg-success">Activa</span>'
    : '<span class="badge bg-secondary">Inactiva</span>'
}

function badgeEstadoAprisco(v) {
  return v === 'ACTIVO'
    ? '<span class="badge bg-success">Activo</span>'
    : '<span class="badge bg-secondary">Inactivo</span>'
}

/* =========================
   Renderizado de Detalles y Modales
========================= */
function renderDetailCard(tipo, d = {}) {
  const V = (x) => x ?? '-' // Helper para mostrar '-' si el valor es nulo

  if (tipo === 'finca') {
    return `
      <div class="detail-card text-start">
        <div class="detail-grid">
          <div><span class="label">Nombre</span><span class="value">${V(
            d.nombre
          )}</span></div>
          <div><span class="label">Estado</span><span class="value">${badgeEstadoFinca(
            d.estado
          )}</span></div>
          <div><span class="label">Ubicación</span><span class="value">${V(
            d.ubicacion
          )}</span></div>
          <div><span class="label">Creado</span><span class="value">${
            d.created_at ? formatDate(d.created_at) : '-'
          }</span></div>
        </div>
      </div>
    `
  }
  if (tipo === 'aprisco') {
    return `
      <div class="detail-card text-start">
        <div class="detail-grid">
          <div><span class="label">Finca</span><span class="value">${V(
            d.nombre_finca
          )}</span></div>
          <div><span class="label">Estado</span><span class="value">${badgeEstadoAprisco(
            d.estado
          )}</span></div>
          <div><span class="label">Nombre</span><span class="value">${V(
            d.nombre
          )}</span></div>
          <div><span class="label">Creado</span><span class="value">${
            d.created_at ? formatDate(d.created_at) : '-'
          }</span></div>
        </div>
      </div>
    `
  }
  if (tipo === 'area') {
    return `
      <div class="detail-card text-start">
        <div class="detail-grid">
          <div><span class="label">Finca</span><span class="value">${V(
            d.nombre_finca
          )}</span></div>
          <div><span class="label">Aprisco</span><span class="value">${V(
            d.nombre_aprisco
          )}</span></div>
          <div><span class="label">Tipo</span><span class="value">${V(
            d.tipo_area
          )}</span></div>
          <div><span class="label">Nombre/Numeración</span><span class="value">${V(
            d.nombre_personalizado || '-'
          )} / ${V(d.numeracion || '-')}</span></div>
          <div><span class="label">Estado</span><span class="value">${badgeEstadoFinca(
            d.estado
          )}</span></div>
          <div><span class="label">Creado</span><span class="value">${
            d.created_at ? formatDate(d.created_at) : '-'
          }</span></div>
        </div>
      </div>
    `
  }
  // Por defecto, es un reporte
  return `
    <div class="detail-card text-start">
      <div class="detail-grid">
        <div><span class="label">Título</span><span class="value">${V(
          d.titulo
        )}</span></div>
        <div><span class="label">Fecha</span><span class="value">${
          d.fecha_reporte ? formatDate(d.fecha_reporte) : '-'
        }</span></div>
        <div><span class="label">Finca</span><span class="value">${V(
          d.finca_nombre
        )}</span></div>
        <div><span class="label">Aprisco</span><span class="value">${V(
          d.aprisco_nombre
        )}</span></div>
        <div><span class="label">Área</span><span class="value">${V(
          d.area_label
        )}</span></div>
        <div><span class="label">Criticidad</span><span class="value">${window.criticidadFormatter(
          V(d.criticidad)
        )}</span></div>
        <div><span class="label">Estado</span><span class="value">${window.reporteEstadoFormatter(
          V(d.estado_reporte)
        )}</span></div>
        <div style="grid-column:1/-1"><span class="label">Descripción</span><div class="value" style="white-space:pre-wrap; max-height: 150px; overflow-y: auto;">${V(
          d.descripcion
        )}</div></div>
      </div>
    </div>
  `
}
async function openEditModal(tipo, d) {
  if (tipo === 'finca') {
    resetFincaForm()
    $('#finca_id').val(d.finca_id)
    $('#finca_nombre').val(d.nombre)
    $('#finca_ubicacion').val(d.ubicacion || '')
    $('#finca_estado').val(d.estado)
    $('#modalFincaLabel').text('Editar Finca')
    new bootstrap.Modal('#modalFinca').show()
  } else if (tipo === 'aprisco') {
    resetApriscoForm()
    await cargarFincasSelect('#aprisco_finca_id')
    $('#aprisco_id').val(d.aprisco_id)
    $('#aprisco_finca_id').val(d.finca_id)
    $('#aprisco_nombre').val(d.nombre)
    $('#aprisco_estado').val(d.estado)
    $('#modalApriscoLabel').text('Editar Aprisco')
    new bootstrap.Modal('#modalAprisco').show()
  } else if (tipo === 'area') {
    resetAreaForm()
    await cargarFincasSelect('#area_finca_id')
    $('#area_id').val(d.area_id)
    $('#area_finca_id').val(d.finca_id)
    await cargarApriscosSelect('#area_aprisco_id', $('#area_finca_id').val())
    $('#area_aprisco_id').val(d.aprisco_id)
    $('#area_tipo_area').val(d.tipo_area)
    $('#area_nombre_personalizado').val(d.nombre_personalizado || '')
    $('#area_numeracion').val(d.numeracion || '')
    $('#area_estado').val(d.estado)
    $('#modalAreaLabel').text('Editar Área')
    new bootstrap.Modal('#modalArea').show()
  } else {
    // reporte
    resetReporteForm()
    await cargarFincasSelect('#rep_finca_id', true)
    await cargarApriscosSelect('#rep_aprisco_id', d.finca_id, true)
    await cargarAreasSelect('#rep_area_id', d.aprisco_id, true)
    $('#reporte_id').val(d.reporte_id)
    $('#rep_finca_id').val(d.finca_id || '')
    $('#rep_aprisco_id').val(d.aprisco_id || '')
    $('#rep_area_id').val(d.area_id || '')
    $('#rep_titulo').val(d.titulo)
    $('#rep_descripcion').val(d.descripcion || '')
    $('#rep_criticidad').val(d.criticidad)
    $('#rep_estado').val(d.estado_reporte)
    $('#modalReporteLabel').text('Editar Reporte de Daño')
    new bootstrap.Modal('#modalReporte').show()
  }
}

/* =========================
   Reseteo de Formularios
========================= */
function resetFincaForm() {
  $('#formFinca')[0]?.reset()
  $('#finca_id').val('')
}
function resetApriscoForm() {
  $('#formAprisco')[0]?.reset()
  $('#aprisco_id').val('')
}
function resetAreaForm() {
  $('#formArea')[0]?.reset()
  $('#area_id').val('')
}
function resetReporteForm() {
  $('#formReporte')[0]?.reset()
  $('#reporte_id').val('')
}
