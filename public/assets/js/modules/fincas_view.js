import { showErrorToast, formatDate } from '../helpers/helpers.js'

// Helper central para rutas API
const api = (path) => `${baseUrl}api/${path}`

// ==========================================================
// == FORMATTERS Y HELPERS PARA BOOTSTRAP TABLE            ==
// ==========================================================

/**
 * Adapta la respuesta de la API al formato que Bootstrap Table espera.
 */
window.responseHandler = (res) => ({
  rows: res.data ?? [],
  total: res.data?.length ?? 0,
})

// Formatters
window.fincaEstadoFormatter = (v) =>
  v === 'ACTIVA'
    ? '<span class="badge bg-success">Activa</span>'
    : '<span class="badge bg-secondary">Inactiva</span>'
window.apriscoEstadoFormatter = (v) =>
  v === 'ACTIVO'
    ? '<span class="badge bg-success">Activo</span>'
    : '<span class="badge bg-secondary">Inactivo</span>'
window.recintoEstadoFormatter = (v) =>
  v === 'ACTIVO'
    ? '<span class="badge bg-success">Activo</span>'
    : '<span class="badge bg-secondary">Inactivo</span>'
window.areaNombreFormatter = (v, row) =>
  `${row.nombre_personalizado || '-'} / ${row.numeracion || '-'}`

const actionBtns = (tipo, id) => `
    <div class="btn-group">
      <button class="btn btn-info btn-sm btn-ver" data-type="${tipo}" data-id="${id}" title="Ver"><i class="mdi mdi-eye"></i></button>
      <button class="btn btn-warning btn-sm btn-editar" data-type="${tipo}" data-id="${id}" title="Editar"><i class="mdi mdi-pencil"></i></button>
      <button class="btn btn-danger btn-sm btn-eliminar" data-type="${tipo}" data-id="${id}" title="Eliminar"><i class="mdi mdi-delete"></i></button>
    </div>`

window.fincaAccionesFormatter = (v) => actionBtns('finca', v)
window.apriscoAccionesFormatter = (v) => actionBtns('aprisco', v)
window.areaAccionesFormatter = (v) => actionBtns('area', v)
window.recintoAccionesFormatter = (v) => actionBtns('recinto', v)

// ==========================================================
// == LÓGICA PRINCIPAL                                     ==
// ==========================================================

// Banderas para controlar la carga inicial de cada tabla
let IS_FINCA_LOADED, IS_APRISCO_LOADED, IS_AREA_LOADED, IS_RECINTO_LOADED

document.addEventListener('DOMContentLoaded', () => {
  initButtons()
  wireCancelButtons()

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
    .getElementById('pane-recintos')
    ?.addEventListener('lazyload', loadRecintosTab)

  document.addEventListener('tab:refresh', ({ detail }) => {
    const { paneId } = detail || {}
    if (paneId === 'pane-fincas' && IS_FINCA_LOADED)
      $('#tablaFincas').bootstrapTable('refresh')
    if (paneId === 'pane-apriscos' && IS_APRISCO_LOADED)
      $('#tablaApriscos').bootstrapTable('refresh')
    if (paneId === 'pane-areas' && IS_AREA_LOADED)
      $('#tablaAreas').bootstrapTable('refresh')
    if (paneId === 'pane-recintos' && IS_RECINTO_LOADED)
      $('#tablaRecintos').bootstrapTable('refresh')
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

async function loadRecintosTab() {
  await cargarFincasSelect('#filtroRecintosFinca', true)
  await cargarApriscosSelect('#filtroRecintosAprisco', '', true)
  await cargarAreasSelect('#filtroRecintosArea', '', true)

  if (!IS_RECINTO_LOADED) {
    $('#tablaRecintos').bootstrapTable({
      url: api('recintos'),
      responseHandler: window.responseHandler,
      queryParams: (params) => {
        const areaId = $('#filtroRecintosArea').val() || ''
        if (areaId) params.area_id = areaId
        return params
      },
    })
    IS_RECINTO_LOADED = true
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

  // ---- Envío de formularios (submit) ----
  $('#formFinca').on('submit', submitFinca)
  $('#formAprisco').on('submit', submitAprisco)
  $('#formArea').on('submit', submitArea)

  $('#btnNuevoRecinto').on('click', async () => {
    resetRecintoForm()
    await cargarFincasSelect('#recinto_finca_id')
    await cargarApriscosSelect(
      '#recinto_aprisco_id',
      $('#recinto_finca_id').val()
    )
    await cargarAreasSelect('#recinto_area_id', $('#recinto_aprisco_id').val())
    $('#modalRecintoLabel').text('Crear Nuevo Recinto')
    new bootstrap.Modal('#modalRecinto').show()
  })

  // ---- Envío de formularios ----
  $('#formFinca').on('submit', submitFinca)
  $('#formAprisco').on('submit', submitAprisco)
  $('#formArea').on('submit', submitArea)
  $('#formRecinto').on('submit', submitRecinto)

  // ---- Delegación de eventos ----
  $(document).on(
    'click',
    'button.btn-ver,button.btn-editar,button.btn-eliminar',
    handleRowAction
  )

  // ---- Filtros en cascada ----
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

  $('#filtroRecintosFinca').on('change', async function () {
    await cargarApriscosSelect('#filtroRecintosAprisco', this.value || '', true)
    // Disparamos el change de aprisco para que recargue las áreas
    $('#filtroRecintosAprisco').trigger('change')
  })
  $('#filtroRecintosAprisco').on('change', async function () {
    await cargarAreasSelect('#filtroRecintosArea', this.value || '', true)
    $('#tablaRecintos').bootstrapTable('refresh')
  })
  $('#filtroRecintosArea').on('change', () =>
    $('#tablaRecintos').bootstrapTable('refresh')
  )

  // ---- Selects en cascada en modales ----
  $('#area_finca_id').on('change', async function () {
    await cargarApriscosSelect('#area_aprisco_id', this.value)
  })

  $('#recinto_finca_id').on('change', async function () {
    await cargarApriscosSelect('#recinto_aprisco_id', this.value)
    $('#recinto_aprisco_id').trigger('change')
  })
  $('#recinto_aprisco_id').on('change', async function () {
    await cargarAreasSelect('#recinto_area_id', this.value)
  })
}

/* =========================
   Manejo de Modales
========================= */
function wireCancelButtons() {
  $(document).on('click', '.btn-cancelar', function (e) {
    e.preventDefault()
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
    const { data = [] } = await jget(api('fincas'))
    const $sel = $(selector).empty()
    if (includeEmpty)
      $sel.append(new Option(selector.includes('filtro') ? 'Todas' : '', ''))
    data.forEach((x) => $sel.append(new Option(x.nombre, x.finca_id)))
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function cargarApriscosSelect(selector, fincaId, includeEmpty = false) {
  try {
    const url = fincaId ? api(`apriscos?finca_id=${fincaId}`) : api('apriscos')
    const { data = [] } = await jget(url)
    const $sel = $(selector).empty()
    if (includeEmpty)
      $sel.append(new Option(selector.includes('filtro') ? 'Todos' : '', ''))
    data.forEach((x) => $sel.append(new Option(x.nombre, x.aprisco_id)))
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function cargarAreasSelect(selector, apriscoId, includeEmpty = false) {
  try {
    const url = apriscoId ? api(`areas?aprisco_id=${apriscoId}`) : api('areas')
    const { data = [] } = await jget(url)
    const $sel = $(selector).empty()
    if (includeEmpty)
      $sel.append(new Option(selector.includes('filtro') ? 'Todas' : '', ''))
    data.forEach((x) => {
      const label =
        x.nombre_personalizado ||
        (x.numeracion ? `Área ${x.numeracion}` : x.area_id.substring(0, 8))
      $sel.append(new Option(label, x.area_id))
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

async function submitRecinto(e) {
  e.preventDefault()
  const body = {
    recinto_id: $('#recinto_id').val() || undefined,
    area_id: $('#recinto_area_id').val(),
    capacidad: $('#recinto_capacidad').val() || null,
    estado: $('#recinto_estado').val(),
    observaciones: $('#recinto_observaciones').val() || null,
  }
  const isEdit = !!body.recinto_id
  const url = isEdit ? api(`recintos/${body.recinto_id}`) : api('recintos')
  try {
    const res = await jsend(url, 'POST', body)
    bootstrap.Modal.getInstance(document.getElementById('modalRecinto')).hide()
    $('#tablaRecintos').bootstrapTable('refresh')
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
  if (tipo === 'recinto') return 'recintos'
  return ''
}

function reloadBT(tipo) {
  if (tipo === 'finca') $('#tablaFincas').bootstrapTable('refresh')
  else if (tipo === 'aprisco') $('#tablaApriscos').bootstrapTable('refresh')
  else if (tipo === 'area') $('#tablaAreas').bootstrapTable('refresh')
  else if (tipo === 'recinto') $('#tablaRecintos').bootstrapTable('refresh')
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
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
    })

    if (!ok.isConfirmed) return

    try {
      const res = await jdel(api(`${tipoRoute(tipo)}/${id}`))
      reloadBT(tipo)
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

  try {
    const { data: row } = await jget(api(`${tipoRoute(tipo)}/${id}`))

    if ($btn.hasClass('btn-ver')) {
      const modalEl = document.getElementById('modalDetalle')
      const modal =
        bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)
      document.getElementById('modalDetalleLabel').innerText = `Detalle de ${
        tipo.charAt(0).toUpperCase() + tipo.slice(1)
      }`
      document.getElementById('modalDetalleBody').innerHTML = renderDetailCard(
        tipo,
        row
      )
      modal.show()
    } else if ($btn.hasClass('btn-editar')) {
      await openEditModal(tipo, row)
    }
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

/* =========================
   Renderizado de Detalles y Modales
========================= */
function renderDetailCard(tipo, d = {}) {
  const V = (x) => x ?? '-'

  if (tipo === 'finca') {
    return `<div class="detail-card text-start"><div class="detail-grid">
              <div><span class="label">Nombre</span><span class="value">${V(
                d.nombre
              )}</span></div>
              <div><span class="label">Estado</span><span class="value">${window.fincaEstadoFormatter(
                d.estado
              )}</span></div>
              <div><span class="label">Ubicación</span><span class="value">${V(
                d.ubicacion
              )}</span></div>
              <div><span class="label">Creado</span><span class="value">${formatDate(
                d.created_at
              )}</span></div>
            </div></div>`
  }
  if (tipo === 'aprisco') {
    return `<div class="detail-card text-start"><div class="detail-grid">
              <div><span class="label">Finca</span><span class="value">${V(
                d.nombre_finca
              )}</span></div>
              <div><span class="label">Estado</span><span class="value">${window.apriscoEstadoFormatter(
                d.estado
              )}</span></div>
              <div><span class="label">Nombre</span><span class="value">${V(
                d.nombre
              )}</span></div>
              <div><span class="label">Creado</span><span class="value">${formatDate(
                d.created_at
              )}</span></div>
            </div></div>`
  }
  if (tipo === 'area') {
    return `<div class="detail-card text-start"><div class="detail-grid">
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
              <div><span class="label">Estado</span><span class="value">${window.fincaEstadoFormatter(
                d.estado
              )}</span></div>
              <div><span class="label">Creado</span><span class="value">${formatDate(
                d.created_at
              )}</span></div>
            </div></div>`
  }
  if (tipo === 'recinto') {
    return `<div class="detail-card text-start"><div class="detail-grid">
              <div><span class="label">Código</span><span class="value">${
                d.codigo_recinto
              }</span></div>
              <div><span class="label">Estado</span><span class="value">${window.recintoEstadoFormatter(
                d.estado
              )}</span></div>
              <div><span class="label">Capacidade</span><span class="value">${
                d.capacidad ?? '-'
              }</span></div>
              <div><span class="label">Área</span><span class="value">${
                d.area_nombre_personalizado ?? '-'
              }</span></div>
              <div><span class="label">Aprisco</span><span class="value">${
                d.aprisco_nombre ?? '-'
              }</span></div>
              <div><span class="label">Finca</span><span class="value">${
                d.finca_nombre ?? '-'
              }</span></div>
              <div style="grid-column:1/-1"><span class="label">Observacións</span><div class="value">${
                d.observaciones ?? '-'
              }</div></div>
            </div></div>`
  }
  return ''
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
    await cargarApriscosSelect('#area_aprisco_id', d.finca_id)
    $('#area_aprisco_id').val(d.aprisco_id)
    $('#area_tipo_area').val(d.tipo_area)
    $('#area_nombre_personalizado').val(d.nombre_personalizado || '')
    $('#area_numeracion').val(d.numeracion || '')
    $('#area_estado').val(d.estado)
    $('#modalAreaLabel').text('Editar Área')
    new bootstrap.Modal('#modalArea').show()
  } else if (tipo === 'recinto') {
    resetRecintoForm()
    await cargarFincasSelect('#recinto_finca_id')
    $('#recinto_finca_id').val(d.finca_id)
    await cargarApriscosSelect('#recinto_aprisco_id', d.finca_id)
    $('#recinto_aprisco_id').val(d.aprisco_id)
    await cargarAreasSelect('#recinto_area_id', d.aprisco_id)
    $('#recinto_area_id').val(d.area_id)

    $('#recinto_id').val(d.recinto_id)
    $('#recinto_capacidad').val(d.capacidad || '')
    $('#recinto_estado').val(d.estado)
    $('#recinto_observaciones').val(d.observaciones || '')

    $('#modalRecintoLabel').text('Editar Recinto')
    new bootstrap.Modal('#modalRecinto').show()
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

function resetRecintoForm() {
  $('#formRecinto')[0]?.reset()
  $('#recinto_id').val('')
}
