<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <div class="page-title-box">
        <div class="page-title-right">
          <button type="button" class="btn btn-success" id="btnRefrescarTodo">
            <i class="mdi mdi-refresh"></i> Refrescar todo
          </button>
        </div>
        <h4 class="page-title">Gestión Agro — Fincas, Apriscos y Áreas</h4>
      </div>
    </div>
  </div>

  <ul class="nav nav-tabs" id="agroTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-fincas" data-bs-toggle="tab" data-bs-target="#pane-fincas" type="button"
        role="tab">Fincas</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-apriscos" data-bs-toggle="tab" data-bs-target="#pane-apriscos" type="button"
        role="tab">Apriscos</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-areas" data-bs-toggle="tab" data-bs-target="#pane-areas" type="button"
        role="tab">Áreas</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-recintos" data-bs-toggle="tab" data-bs-target="#pane-recintos" type="button"
        role="tab">Recintos</button>
    </li>
  </ul>

  <div class="tab-content p-0">
    <div class="tab-pane fade show active" id="pane-fincas" role="tabpanel" aria-labelledby="tab-fincas">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Fincas</h5>
            <button class="btn btn-primary" id="btnNuevaFinca"><i class="mdi mdi-plus"></i> Nueva Finca</button>
          </div>
          <table id="tablaFincas" class="table table-striped table-hover align-middle" style="width:100%"
            data-pagination="true" data-search="true" data-locale="es-ES">
            <thead>
              <tr>
                <th data-field="nombre" data-sortable="true">Nombre</th>
                <th data-field="ubicacion">Ubicación</th>
                <th data-field="estado" data-formatter="fincaEstadoFormatter" data-align="center">Estado</th>
                <th data-field="finca_id" data-formatter="fincaAccionesFormatter" data-align="center">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="pane-apriscos" role="tabpanel" aria-labelledby="tab-apriscos">
      <div class="card">
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 justify-content-between align-items-end mb-2">
            <div class="d-flex flex-wrap gap-2">
              <div>
                <label class="form-label mb-1">Filtrar por Finca</label>
                <select id="filtroApriscosFinca" class="form-select">
                  <option value="">Todas</option>
                </select>
              </div>
            </div>
            <button class="btn btn-primary" id="btnNuevoAprisco"><i class="mdi mdi-plus"></i> Nuevo Aprisco</button>
          </div>

          <table id="tablaApriscos" class="table table-striped table-hover align-middle" style="width:100%"
            data-pagination="true" data-locale="es-ES">
            <thead>
              <tr>
                <th data-field="nombre_finca" data-sortable="true">Finca</th>
                <th data-field="nombre" data-sortable="true">Nombre</th>
                <th data-field="estado" data-formatter="apriscoEstadoFormatter" data-align="center">Estado</th>
                <th data-field="aprisco_id" data-formatter="apriscoAccionesFormatter" data-align="center">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="pane-areas" role="tabpanel" aria-labelledby="tab-areas">
      <div class="card">
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 justify-content-between align-items-end mb-2">
            <div class="d-flex flex-wrap gap-2">
              <div>
                <label class="form-label mb-1">Filtrar por Finca</label>
                <select id="filtroAreasFinca" class="form-select">
                  <option value="">Todas</option>
                </select>
              </div>
              <div>
                <label class="form-label mb-1">Filtrar por Aprisco</label>
                <select id="filtroAreasAprisco" class="form-select">
                  <option value="">Todos</option>
                </select>
              </div>
            </div>
            <button class="btn btn-primary" id="btnNuevaArea"><i class="mdi mdi-plus"></i> Nueva Área</button>
          </div>

          <table id="tablaAreas" class="table table-striped table-hover align-middle" style="width:100%"
            data-pagination="true" data-locale="es-ES">
            <thead>
              <tr>
                <th data-field="nombre_finca" data-sortable="true">Finca</th>
                <th data-field="nombre_aprisco" data-sortable="true">Aprisco</th>
                <th data-field="tipo_area" data-sortable="true">Tipo</th>
                <th data-formatter="areaNombreFormatter">Nombre/Número</th>
                <th data-field="estado" data-formatter="fincaEstadoFormatter" data-align="center">Estado</th>
                <th data-field="area_id" data-formatter="areaAccionesFormatter" data-align="center">Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Novedad: Pane Recintos -->
    <div class="tab-pane fade" id="pane-recintos" role="tabpanel" aria-labelledby="tab-recintos">
      <div class="card">
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 justify-content-between align-items-end mb-2">
            <div class="d-flex flex-wrap gap-2">
              <div>
                <label class="form-label mb-1">Filtrar por Finca</label>
                <select id="filtroRecintosFinca" class="form-select">
                  <option value="">Todas</option>
                </select>
              </div>
              <div>
                <label class="form-label mb-1">Filtrar por Aprisco</label>
                <select id="filtroRecintosAprisco" class="form-select">
                  <option value="">Todos</option>
                </select>
              </div>
              <div>
                <label class="form-label mb-1">Filtrar por Área</label>
                <select id="filtroRecintosArea" class="form-select">
                  <option value="">Todas</option>
                </select>
              </div>
            </div>
            <button class="btn btn-primary" id="btnNuevoRecinto"><i class="mdi mdi-plus"></i> Nuevo Recinto</button>
          </div>

          <table id="tablaRecintos" class="table table-striped table-hover align-middle" style="width:100%"
            data-pagination="true" data-locale="es-ES">
            <thead>
              <tr>
                <th data-field="codigo_recinto" data-sortable="true">Código</th>
                <th data-field="nombre_area" data-sortable="true">Área</th>
                <th data-field="nombre_aprisco" data-sortable="true">Aprisco</th>
                <th data-field="nombre_finca" data-sortable="true">Finca</th>
                <th data-field="capacidad" data-align="center">Capacidad</th>
                <th data-field="estado" data-formatter="recintoEstadoFormatter" data-align="center">Estado</th>
                <th data-field="recinto_id" data-formatter="recintoAccionesFormatter" data-align="center">Acciones</th>
              </tr>
            </thead>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

</div>
</div>

<div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDetalleLabel">Detalles</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modalDetalleBody">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalFinca" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalFincaLabel">Crear Nueva Finca</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formFinca">
        <div class="modal-body">
          <input type="hidden" id="finca_id" name="finca_id">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" class="form-control" id="finca_nombre" name="nombre" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Ubicación</label>
            <input type="text" class="form-control" id="finca_ubicacion" name="ubicacion">
          </div>
          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select class="form-select" id="finca_estado" name="estado" required>
              <option value="ACTIVA">Activa</option>
              <option value="INACTIVA">Inactiva</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalAprisco" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalApriscoLabel">Crear Nuevo Aprisco</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formAprisco">
        <div class="modal-body">
          <input type="hidden" id="aprisco_id" name="aprisco_id">
          <div class="mb-3">
            <label class="form-label">Finca</label>
            <select class="form-select" id="aprisco_finca_id" name="finca_id" required></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Nombre del Aprisco</label>
            <input type="text" class="form-control" id="aprisco_nombre" name="nombre" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select class="form-select" id="aprisco_estado" name="estado" required>
              <option value="ACTIVO">Activo</option>
              <option value="INACTIVO">Inactivo</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalArea" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalAreaLabel">Crear Nueva Área</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formArea">
        <div class="modal-body">
          <input type="hidden" id="area_id" name="area_id">
          <div class="mb-3">
            <label class="form-label">Finca</label>
            <select class="form-select" id="area_finca_id" required></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Aprisco</label>
            <select class="form-select" id="area_aprisco_id" name="aprisco_id" required></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Tipo</label>
            <select class="form-select" id="area_tipo_area" name="tipo_area" required>
              <option value="LEVANTE_CEBA">Levante/Ceba</option>
              <option value="GESTACION">Gestación</option>
              <option value="MATERNIDAD">Maternidad</option>
              <option value="REPRODUCCION">Reproducción</option>
              <option value="CHIQUERO">Chiquero</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Nombre Personalizado / Numeración</label>
            <div class="d-flex gap-2">
              <input type="text" class="form-control" id="area_nombre_personalizado" name="nombre_personalizado"
                placeholder="Opcional">
              <input type="text" class="form-control" id="area_numeracion" name="numeracion" placeholder="Opcional">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select class="form-select" id="area_estado" name="estado" required>
              <option value="ACTIVA">Activa</option>
              <option value="INACTIVA">Inactiva</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Novedad: Modal Recinto -->
<div class="modal fade" id="modalRecinto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalRecintoLabel">Crear Nuevo Recinto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="formRecinto">
        <div class="modal-body">
          <input type="hidden" id="recinto_id" name="recinto_id">

          <div class="alert alert-info">El código del recinto se generará automáticamente basado en el área
            seleccionada.</div>

          <div class="mb-3">
            <label class="form-label">Finca</label>
            <select class="form-select" id="recinto_finca_id" required></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Aprisco</label>
            <select class="form-select" id="recinto_aprisco_id" required></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Área</label>
            <select class="form-select" id="recinto_area_id" name="area_id" required></select>
          </div>

          <div class="mb-3">
            <label class="form-label">Capacidad (opcional)</label>
            <input type="number" class="form-control" id="recinto_capacidad" name="capacidad" min="0">
          </div>

          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select class="form-select" id="recinto_estado" name="estado" required>
              <option value="ACTIVO">Activo</option>
              <option value="INACTIVO">Inactivo</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Observaciones (opcional)</label>
            <textarea class="form-control" id="recinto_observaciones" name="observaciones" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-cancelar" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>


<style>
  .detail-card .label {
    color: #6b7280;
    font-size: .875rem;
    display: block;
    margin-bottom: .125rem;
  }

  .detail-card .value {
    font-weight: 600;
    color: #111827;
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem .75rem;
  }

  @media (max-width:576px) {
    .detail-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<script>
  const baseUrl = "<?= BASE_URL ?>";
</script>

<script>
  // Lazy-load por tab
  (function () {
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('#agroTabs .nav-link').forEach(btn => {
        btn.addEventListener('shown.bs.tab', (ev) => {
          const paneId = ev.target.getAttribute('data-bs-target').substring(1);
          const pane = document.getElementById(paneId);
          if (!pane) return;

          if (!pane.dataset.loaded) {
            pane.dispatchEvent(new Event('lazyload'));
            pane.dataset.loaded = '1';
          } else {
            document.dispatchEvent(new CustomEvent('tab:refresh', { detail: { paneId } }));
          }
        });
      });
    });

    window.addEventListener('load', () => {
      const activeBtn = document.querySelector('#agroTabs .nav-link.active');
      const targetSelector = activeBtn?.getAttribute('data-bs-target') || '#pane-fincas';
      const pane = document.querySelector(targetSelector);
      if (pane && !pane.dataset.loaded) {
        pane.dispatchEvent(new Event('lazyload'));
        pane.dataset.loaded = '1';
      }
    });
  })();
</script>

<script type="module" src="<?= BASE_URL ?>public/assets/js/modules/fincas_view.js"></script>