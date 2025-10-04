// agro_tabs_view.js
import { showErrorToast, formatDate } from '../helpers/helpers.js'

let DT_FINCAS, DT_APRISCOS, DT_AREAS, DT_REPORTES

document.addEventListener('DOMContentLoaded', () => {
  initButtons()

  // Hooks de lazy-load por pane
  document.getElementById('pane-fincas')?.addEventListener('lazyload', loadFincasTab)
  document.getElementById('pane-apriscos')?.addEventListener('lazyload', loadApriscosTab)
  document.getElementById('pane-areas')?.addEventListener('lazyload', loadAreasTab)
  document.getElementById('pane-reportes')?.addEventListener('lazyload', loadReportesTab)

  // Refrescos al volver a un tab ya cargado
  document.addEventListener('tab:refresh', ({ detail }) => {
    const { paneId } = detail || {}
    if (paneId === 'pane-fincas'   && DT_FINCAS)   DT_FINCAS.ajax.reload(null,false)
    if (paneId === 'pane-apriscos' && DT_APRISCOS) DT_APRISCOS.ajax.reload(null,false)
    if (paneId === 'pane-areas'    && DT_AREAS)    DT_AREAS.ajax.reload(null,false)
    if (paneId === 'pane-reportes' && DT_REPORTES) DT_REPORTES.ajax.reload(null,false)
  })
})

/* =========================
   Helpers de fetch JSON
========================= */
async function jget(url){
  const r = await fetch(url)
  const j = await r.json().catch(()=>({}))
  // Adaptado al contrato { value, message, data }
  if (!j || j.value !== true) {
    const msg = j?.message || 'Error de servidor'
    throw new Error(msg)
  }
  return j
}
async function jsend(url, method, body){
  const r = await fetch(url, {
    method,
    headers:{ 'Content-Type':'application/json' },
    body: JSON.stringify(body)
  })
  const j = await r.json().catch(()=>({}))
  if (!j || j.value !== true) {
    const msg = j?.message || 'Operación no completada'
    throw new Error(msg)
  }
  return j
}
async function jdel(url){
  const r = await fetch(url, { method:'DELETE' })
  const j = await r.json().catch(()=>({}))
  if (!j || j.value !== true) {
    const msg = j?.message || 'No se pudo eliminar'
    throw new Error(msg)
  }
  return j
}

/* =========================
   Carga por TAB (lazy)
========================= */
async function loadFincasTab(){
  if (!DT_FINCAS) {
    DT_FINCAS = $('#tablaFincas').DataTable({
      ajax: {
        url: `${baseUrl}fincas`,
        dataSrc: (json) => json?.data ?? []
      },
      columns: [
        { data: 'nombre' },
        { data: 'ubicacion', defaultContent: '' },
        {
          data: 'estado',
          render: v => v === 'ACTIVA'
            ? '<span class="badge bg-success">Activa</span>'
            : '<span class="badge bg-secondary">Inactiva</span>'
        },
        { data: 'finca_id', render: id => actionBtns('finca', id), orderable:false, searchable:false }
      ],
    })
  } else {
    DT_FINCAS.ajax.reload(null,false)
  }
}

async function loadApriscosTab(){
  await cargarFincasSelect('#filtroApriscosFinca', true)

  if (!DT_APRISCOS) {
    DT_APRISCOS = $('#tablaApriscos').DataTable({
      ajax: {
        url: `${baseUrl}apriscos`,
        data: function (d) {
          const fincaId = $('#filtroApriscosFinca').val() || ''
          if (fincaId) d.finca_id = fincaId
        },
        dataSrc: (json) => json?.data ?? []
      },
      columns: [
        // el backend retorna nombre_finca (no finca_nombre)
        { data: 'nombre_finca', defaultContent: '-' },
        { data: 'nombre' },
        {
          data: 'estado',
          render: v => v === 'ACTIVO'
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>'
        },
        { data: 'aprisco_id', render: id => actionBtns('aprisco', id), orderable:false, searchable:false }
      ],
    })
  } else {
    DT_APRISCOS.ajax.reload(null,false)
  }
}

async function loadAreasTab(){
  await cargarFincasSelect('#filtroAreasFinca', true)
  await cargarApriscosSelect('#filtroAreasAprisco','', true)

  if (!DT_AREAS) {
    DT_AREAS = $('#tablaAreas').DataTable({
      ajax: {
        url: `${baseUrl}areas`,
        data: function (d) {
          const fincaId = $('#filtroAreasFinca').val() || ''
          const apriscoId = $('#filtroAreasAprisco').val() || ''
          if (fincaId)   d.finca_id = fincaId
          if (apriscoId) d.aprisco_id = apriscoId
        },
        dataSrc: (json) => json?.data ?? []
      },
      columns: [
        // usar siempre nombres, nunca UUID
        { data: 'nombre_finca',   defaultContent:'-' },
        { data: 'nombre_aprisco', defaultContent:'-' },
        { data: 'tipo_area' },
        { 
          data: null, 
          render: (row)=> (row.nombre_personalizado || '-') + ' / ' + (row.numeracion || '-') 
        },
        {
          data: 'estado',
          render: v => v === 'ACTIVA'
            ? '<span class="badge bg-success">Activa</span>'
            : '<span class="badge bg-secondary">Inactiva</span>'
        },
        { data: 'area_id', render: id => actionBtns('area', id), orderable:false, searchable:false }
      ],
    })
  } else {
    DT_AREAS.ajax.reload(null,false)
  }
}

async function loadReportesTab(){
  await cargarFincasSelect('#filtroRepFinca', true)
  await cargarApriscosSelect('#filtroRepAprisco','', true)
  await cargarAreasSelect('#filtroRepArea','', true)

  if (!DT_REPORTES) {
    DT_REPORTES = $('#tablaReportes').DataTable({
      ajax: {
        url: `${baseUrl}reportes_dano`,
        data: function (d) {
          const f = $('#filtroRepFinca').val() || ''
          const a = $('#filtroRepAprisco').val() || ''
          const r = $('#filtroRepArea').val() || ''
          const e = $('#filtroRepEstado').val() || ''
          const c = $('#filtroRepCrit').val() || ''
          if (f) d.finca_id = f
          if (a) d.aprisco_id = a
          if (r) d.area_id = r
          if (e) d.estado_reporte = e
          if (c) d.criticidad = c
        },
        dataSrc: (json) => json?.data ?? []
      },
      columns: [
        { data: 'fecha_reporte', render: v => v ? formatDate(v) : '-' },
        { data: 'titulo' },
        { data: 'finca_nombre',   defaultContent:'-' },
        { data: 'aprisco_nombre', defaultContent:'-' },
        { data: 'area_label',     defaultContent:'-' },
        { data: 'criticidad', render: v => critBadge(v) },
        { data: 'estado_reporte', render: v => estadoRepBadge(v) },
        { data: 'reporte_id', render: id => actionBtns('reporte', id), orderable:false, searchable:false }
      ],
      order: [[0,'desc']],
    })
  } else {
    DT_REPORTES.ajax.reload(null,false)
  }
}

/* =========================
   Botones / Eventos
========================= */
function initButtons() {
  document.getElementById('btnRefrescarTodo').addEventListener('click', () => {
    DT_FINCAS?.ajax.reload(null,false)
    DT_APRISCOS?.ajax.reload(null,false)
    DT_AREAS?.ajax.reload(null,false)
    DT_REPORTES?.ajax.reload(null,false)
  })

  /* ---- Crear nuevos ---- */
  $('#btnNuevaFinca').on('click', () => {
    resetFincaForm(); $('#modalFincaLabel').text('Crear Nueva Finca'); new bootstrap.Modal('#modalFinca').show()
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
    await cargarApriscosSelect('#rep_aprisco_id', $('#rep_finca_id').val(), true)
    await cargarAreasSelect('#rep_area_id', $('#rep_aprisco_id').val(), true)
    $('#modalReporteLabel').text('Nuevo Reporte de Daño')
    new bootstrap.Modal('#modalReporte').show()
  })

  /* ---- Submit forms ---- */
  $('#formFinca').on('submit', submitFinca)
  $('#formAprisco').on('submit', submitAprisco)
  $('#formArea').on('submit', submitArea)
  $('#formReporte').on('submit', submitReporte)

  /* ---- Delegación acciones ---- */
  $(document).on('click', 'button.btn-ver,button.btn-editar,button.btn-eliminar', handleRowAction)

  /* ---- Filtros en cascada ---- */
  $('#filtroApriscosFinca').on('change', ()=> DT_APRISCOS?.ajax.reload())

  $('#filtroAreasFinca').on('change', async function(){
    await cargarApriscosSelect('#filtroAreasAprisco', this.value || '', true)
    DT_AREAS?.ajax.reload()
  })
  $('#filtroAreasAprisco').on('change', ()=> DT_AREAS?.ajax.reload())

  $('#filtroRepFinca').on('change', async function(){
    await cargarApriscosSelect('#filtroRepAprisco', this.value || '', true)
    $('#filtroRepArea').empty().append(new Option('Todas',''))
    DT_REPORTES?.ajax.reload()
  })
  $('#filtroRepAprisco').on('change', async function(){
    await cargarAreasSelect('#filtroRepArea', this.value || '', true)
    DT_REPORTES?.ajax.reload()
  })
  $('#filtroRepArea,#filtroRepEstado,#filtroRepCrit').on('change', ()=> DT_REPORTES?.ajax.reload())

  /* ---- Cascadas en modales ---- */
  $('#rep_finca_id').on('change', async function(){
    await cargarApriscosSelect('#rep_aprisco_id', this.value, true)
    await cargarAreasSelect('#rep_area_id', $('#rep_aprisco_id').val(), true)
  })
  $('#rep_aprisco_id').on('change', async function(){
    await cargarAreasSelect('#rep_area_id', this.value, true)
  })
  $('#area_finca_id').on('change', async function(){
    await cargarApriscosSelect('#area_aprisco_id', this.value)
  })
}

/* =========================
   Carga de selects (dinámicos)
========================= */
async function cargarFincasSelect(selector, includeEmpty=false) {
  try {
    const resp = await jget(`${baseUrl}fincas`)
    const list = resp?.data ?? []
    const $sel = $(selector)
    $sel.empty()
    if (includeEmpty) $sel.append(new Option(selector.includes('filtro') ? 'Todas' : '', ''))
    list.forEach(x => $sel.append(new Option(x.nombre, x.finca_id)))
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function cargarApriscosSelect(selector, fincaId, includeEmpty=false) {
  try {
    const url = fincaId 
      ? `${baseUrl}apriscos?finca_id=${encodeURIComponent(fincaId)}`
      : `${baseUrl}apriscos`
    const resp = await jget(url)
    const list = resp?.data ?? []
    const $sel = $(selector)
    $sel.empty()
    if (includeEmpty) $sel.append(new Option(selector.includes('filtro') ? 'Todos' : '', ''))
    // usar nombre (no UUID)
    list.forEach(x => $sel.append(new Option(x.nombre, x.aprisco_id)))
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function cargarAreasSelect(selector, apriscoId, includeEmpty=false) {
  try {
    const url = apriscoId 
      ? `${baseUrl}areas?aprisco_id=${encodeURIComponent(apriscoId)}`
      : `${baseUrl}areas`
    const resp = await jget(url)
    const list = resp?.data ?? []
    const $sel = $(selector)
    $sel.empty()
    if (includeEmpty) $sel.append(new Option(selector.includes('filtro') ? 'Todas' : '', ''))
    list.forEach(x => {
      // etiqueta agradable: nombre_personalizado / numeracion (o "Área" + numeración)
      const nice = x.nombre_personalizado || (x.numeracion ? `Área ${x.numeracion}` : x.area_id)
      $sel.append(new Option(nice, x.area_id))
    })
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

/* =========================
   Submit handlers (POST)
========================= */
async function submitFinca(e){
  e.preventDefault()
  const body = {
    finca_id: $('#finca_id').val() || undefined,
    nombre: $('#finca_nombre').val(),
    ubicacion: $('#finca_ubicacion').val(),
    estado: $('#finca_estado').val(),
  }
  const isEdit = !!body.finca_id
  const url    = isEdit ? `${baseUrl}fincas/${body.finca_id}` : `${baseUrl}fincas`
  try {
    const res = await jsend(url, 'POST', body)
    bootstrap.Modal.getInstance(document.getElementById('modalFinca')).hide()
    DT_FINCAS?.ajax.reload(null,false)
    Swal.fire('Éxito', res.message || 'Guardado', 'success')
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function submitAprisco(e){
  e.preventDefault()
  const body = {
    aprisco_id: $('#aprisco_id').val() || undefined,
    finca_id: $('#aprisco_finca_id').val(),
    nombre: $('#aprisco_nombre').val(),
    estado: $('#aprisco_estado').val(),
  }
  const isEdit = !!body.aprisco_id
  const url    = isEdit ? `${baseUrl}apriscos/${body.aprisco_id}` : `${baseUrl}apriscos`
  try {
    const res = await jsend(url, 'POST', body)
    bootstrap.Modal.getInstance(document.getElementById('modalAprisco')).hide()
    DT_APRISCOS?.ajax.reload(null,false)
    Swal.fire('Éxito', res.message || 'Guardado', 'success')
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function submitArea(e){
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
  const url    = isEdit ? `${baseUrl}areas/${body.area_id}` : `${baseUrl}areas`
  try {
    const res = await jsend(url, 'POST', body)
    bootstrap.Modal.getInstance(document.getElementById('modalArea')).hide()
    DT_AREAS?.ajax.reload(null,false)
    Swal.fire('Éxito', res.message || 'Guardado', 'success')
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

async function submitReporte(e){
  e.preventDefault()
  const body = {
    reporte_id: $('#reporte_id').val() || undefined,
    finca_id:   $('#rep_finca_id').val() || null,
    aprisco_id: $('#rep_aprisco_id').val() || null,
    area_id:    $('#rep_area_id').val() || null,
    titulo: $('#rep_titulo').val(),
    descripcion: $('#rep_descripcion').val(),
    criticidad: $('#rep_criticidad').val(),
    estado_reporte: $('#rep_estado').val(),
  }
  const isEdit = !!body.reporte_id
  const url    = isEdit ? `${baseUrl}reportes_dano/${body.reporte_id}` : `${baseUrl}reportes_dano`
  try {
    const res = await jsend(url, 'POST', body)
    bootstrap.Modal.getInstance(document.getElementById('modalReporte')).hide()
    DT_REPORTES?.ajax.reload(null,false)
    Swal.fire('Éxito', res.message || 'Guardado', 'success')
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

/* =========================
   Acciones ver/editar/eliminar
========================= */
function actionBtns(tipo, id) {
  return `
    <div class="btn-group">
      <button class="btn btn-info btn-sm btn-ver" data-type="${tipo}" data-id="${id}" title="Ver"><i class="mdi mdi-eye"></i></button>
      <button class="btn btn-warning btn-sm btn-editar" data-type="${tipo}" data-id="${id}" title="Editar"><i class="mdi mdi-pencil"></i></button>
      <button class="btn btn-danger btn-sm btn-eliminar" data-type="${tipo}" data-id="${id}" title="Eliminar"><i class="mdi mdi-delete"></i></button>
    </div>
  `
}

function critBadge(v) {
  if (v === 'ALTA')  return '<span class="badge bg-danger">Alta</span>'
  if (v === 'MEDIA') return '<span class="badge bg-warning text-dark">Media</span>'
  return '<span class="badge bg-success">Baja</span>'
}
function estadoRepBadge(v) {
  if (v === 'EN_PROCESO') return '<span class="badge bg-info text-dark">En Proceso</span>'
  if (v === 'CERRADO')    return '<span class="badge bg-secondary">Cerrado</span>'
  return '<span class="badge bg-primary">Abierto</span>'
}

async function handleRowAction(e){
  const $btn = $(e.currentTarget)
  const tipo = $btn.data('type')
  const id   = $btn.data('id')

  if ($btn.hasClass('btn-eliminar')) {
    const ok = await Swal.fire({
      title: '¿Eliminar?',
      text: 'No podrás revertir esta acción.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    })
    if (!ok.isConfirmed) return

    try {
      const url = `${baseUrl}${tipoRoute(tipo)}/${id}`
      const res = await jdel(url)
      reloadDT(tipo)
      Swal.fire('Eliminado', res.message || 'Registro eliminado', 'success')
      if (tipo !== 'reporte') await refreshTabSelects(tipo)
    } catch (err) {
      showErrorToast({ message: err.message })
    }
    return
  }

  // Ver / Editar → obtener registro
  try {
    const url = `${baseUrl}${tipoRoute(tipo)}/${id}`
    const res = await jget(url)         // { value, message, data }
    const row = res.data
    if ($btn.hasClass('btn-ver')) {
      Swal.fire({
        title: 'Detalle',
        html: renderDetailCard(tipo, row),
        width: 720,
        showCloseButton: true
      })
    } else if ($btn.hasClass('btn-editar')) {
      await openEditModal(tipo, row)
    }
  } catch (err) {
    showErrorToast({ message: err.message })
  }
}

function tipoRoute(tipo){
  if (tipo==='finca')   return 'fincas'
  if (tipo==='aprisco') return 'apriscos'
  if (tipo==='area')    return 'areas'
  return 'reportes_dano'
}
function reloadDT(tipo){
  if (tipo==='finca') DT_FINCAS?.ajax.reload(null,false)
  else if (tipo==='aprisco') DT_APRISCOS?.ajax.reload(null,false)
  else if (tipo==='area') DT_AREAS?.ajax.reload(null,false)
  else DT_REPORTES?.ajax.reload(null,false)
}
async function refreshTabSelects(tipo){
  if (tipo==='finca'){
    await cargarFincasSelect('#filtroApriscosFinca', true)
    await cargarFincasSelect('#filtroAreasFinca',   true)
    await cargarFincasSelect('#filtroRepFinca',     true)
  } else if (tipo==='aprisco'){
    const fincaAreas = $('#filtroAreasFinca').val() || ''
    await cargarApriscosSelect('#filtroAreasAprisco', fincaAreas, true)

    const fincaRep = $('#filtroRepFinca').val() || ''
    await cargarApriscosSelect('#filtroRepAprisco', fincaRep, true)
  } else if (tipo==='area'){
    const apriscoRep = $('#filtroRepAprisco').val() || ''
    await cargarAreasSelect('#filtroRepArea', apriscoRep, true)
  }
}

/* =========================
   Vista de detalle bonita
========================= */
function renderDetailCard(tipo, d = {}){
  const V = (x) => (x ?? '-')  // helper
  if (tipo === 'finca') {
    return `
      <div class="detail-card">
        <div class="detail-grid">
          <div><span class="label">Nombre</span><span class="value">${V(d.nombre)}</span></div>
          <div><span class="label">Estado</span><span class="value">${badgeEstadoFinca(d.estado)}</span></div>
          <div><span class="label">Ubicación</span><span class="value">${V(d.ubicacion)}</span></div>
          <div><span class="label">Creado</span><span class="value">${d.created_at ? formatDate(d.created_at) : '-'}</span></div>
        </div>
      </div>
    `
  }
  if (tipo === 'aprisco') {
    return `
      <div class="detail-card">
        <div class="detail-grid">
          <div><span class="label">Finca</span><span class="value">${V(d.nombre_finca)}</span></div>
          <div><span class="label">Estado</span><span class="value">${badgeEstadoAprisco(d.estado)}</span></div>
          <div><span class="label">Nombre</span><span class="value">${V(d.nombre)}</span></div>
          <div><span class="label">Creado</span><span class="value">${d.created_at ? formatDate(d.created_at) : '-'}</span></div>
        </div>
      </div>
    `
  }
  if (tipo === 'area') {
    return `
      <div class="detail-card">
        <div class="detail-grid">
          <div><span class="label">Finca</span><span class="value">${V(d.nombre_finca)}</span></div>
          <div><span class="label">Aprisco</span><span class="value">${V(d.nombre_aprisco)}</span></div>
          <div><span class="label">Tipo</span><span class="value">${V(d.tipo_area)}</span></div>
          <div><span class="label">Nombre/Numeración</span><span class="value">${V(d.nombre_personalizado)} / ${V(d.numeracion)}</span></div>
          <div><span class="label">Estado</span><span class="value">${badgeEstadoFinca(d.estado)}</span></div>
          <div><span class="label">Creado</span><span class="value">${d.created_at ? formatDate(d.created_at) : '-'}</span></div>
        </div>
      </div>
    `
  }
  // reporte
  return `
    <div class="detail-card">
      <div class="detail-grid">
        <div><span class="label">Título</span><span class="value">${V(d.titulo)}</span></div>
        <div><span class="label">Fecha</span><span class="value">${d.fecha_reporte ? formatDate(d.fecha_reporte) : '-'}</span></div>
        <div><span class="label">Finca</span><span class="value">${V(d.finca_nombre)}</span></div>
        <div><span class="label">Aprisco</span><span class="value">${V(d.aprisco_nombre)}</span></div>
        <div><span class="label">Área</span><span class="value">${V(d.area_label)}</span></div>
        <div><span class="label">Criticidad</span><span class="value">${critBadge(V(d.criticidad))}</span></div>
        <div><span class="label">Estado</span><span class="value">${estadoRepBadge(V(d.estado_reporte))}</span></div>
        <div style="grid-column:1/-1"><span class="label">Descripción</span><div class="value" style="white-space:pre-wrap">${V(d.descripcion)}</div></div>
      </div>
    </div>
  `
}
function badgeEstadoFinca(v){
  return v === 'ACTIVA'
    ? '<span class="badge bg-success">Activa</span>'
    : '<span class="badge bg-secondary">Inactiva</span>'
}
function badgeEstadoAprisco(v){
  return v === 'ACTIVO'
    ? '<span class="badge bg-success">Activo</span>'
    : '<span class="badge bg-secondary">Inactivo</span>'
}

/* =========================
   Abrir modal edición
========================= */
async function openEditModal(tipo, d){
  if (tipo==='finca'){
    resetFincaForm()
    $('#finca_id').val(d.finca_id)
    $('#finca_nombre').val(d.nombre)
    $('#finca_ubicacion').val(d.ubicacion || '')
    $('#finca_estado').val(d.estado)
    $('#modalFincaLabel').text('Editar Finca')
    new bootstrap.Modal('#modalFinca').show()
  } else if (tipo==='aprisco'){
    resetApriscoForm()
    await cargarFincasSelect('#aprisco_finca_id')
    $('#aprisco_id').val(d.aprisco_id)
    $('#aprisco_finca_id').val(d.finca_id)
    $('#aprisco_nombre').val(d.nombre)
    $('#aprisco_estado').val(d.estado)
    $('#modalApriscoLabel').text('Editar Aprisco')
    new bootstrap.Modal('#modalAprisco').show()
  } else if (tipo==='area'){
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
  } else { // reporte
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
   Reset forms
========================= */
function resetFincaForm(){ $('#formFinca')[0].reset(); $('#finca_id').val('') }
function resetApriscoForm(){ $('#formAprisco')[0].reset(); $('#aprisco_id').val('') }
function resetAreaForm(){ $('#formArea')[0].reset(); $('#area_id').val('') }
function resetReporteForm(){ $('#formReporte')[0].reset(); $('#reporte_id').val('') }

/* =========================
   Util
========================= */
function escapeHtml(str){ 
  return str ? str.replace(/[&<>"']/g, s=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[s])) : '' 
}
