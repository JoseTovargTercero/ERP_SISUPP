<?php
// Valores para los dropdowns de filtros y formularios
$criticidad_opts = ['BAJA', 'MEDIA', 'ALTA'];
$estado_opts = ['ABIERTO', 'EN_PROCESO', 'CERRADO'];
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <button type="button" class="btn btn-primary" id="btnNuevoReporte">
                        <i class="mdi mdi-plus"></i> Nuevo Reporte
                    </button>
                </div>
                <h4 class="page-title">GESTIÓN DE REPORTES DE DAÑO</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-end mb-3">
                        <div class="d-flex flex-wrap gap-2" style="width: 100%;">
                            <div style="min-width: 150px; flex-grow: 1;">
                                <label class="form-label mb-1">Finca</label>
                                <select id="filtroFinca" class="form-select"></select>
                            </div>
                            <div style="min-width: 150px; flex-grow: 1;">
                                <label class="form-label mb-1">Aprisco</label>
                                <select id="filtroAprisco" class="form-select"></select>
                            </div>
                            <div style="min-width: 150px; flex-grow: 1;">
                                <label class="form-label mb-1">Área</label>
                                <select id="filtroArea" class="form-select"></select>
                            </div>
                            <div style="min-width: 150px; flex-grow: 1;">
                                <label class="form-label mb-1">Estado</label>
                                <select id="filtroEstado" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach ($estado_opts as $opt): ?>
                                        <option value="<?= $opt ?>"><?= ucfirst(strtolower(str_replace('_', ' ', $opt))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="min-width: 150px; flex-grow: 1;">
                                <label class="form-label mb-1">Criticidad</label>
                                <select id="filtroCriticidad" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($criticidad_opts as $opt): ?>
                                        <option value="<?= $opt ?>"><?= ucfirst(strtolower($opt)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <table id="tablaReportes" class="table table-striped table-hover align-middle" style="width:100%"
                        data-toggle="table" data-url="<?php echo BASE_URL; ?>api/reportes_dano"
                        data-response-handler="responseHandler" data-pagination="true" data-search="true"
                        data-show-refresh="true" data-locale="es-ES" data-sort-name="fecha_reporte"
                        data-sort-order="desc">
                        <thead>
                            <tr>
                                <th data-field="fecha_reporte" data-formatter="reporteFechaFormatter"
                                    data-sortable="true">Fecha</th>
                                <th data-field="titulo" data-sortable="true">Título</th>
                                <th data-field="finca_nombre" data-sortable="true">Finca</th>
                                <th data-field="aprisco_nombre" data-sortable="true">Aprisco</th>
                                <th data-field="area_label" data-sortable="true">Área</th>
                                <th data-field="criticidad" data-formatter="criticidadFormatter" data-align="center"
                                    data-sortable="true">Criticidad</th>
                                <th data-field="estado_reporte" data-formatter="reporteEstadoFormatter"
                                    data-align="center" data-sortable="true">Estado</th>
                                <th data-field="reporte_id" data-formatter="reporteAccionesFormatter"
                                    data-align="center">Acciones</th>
                            </tr>
                        </thead>
                    </table>
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
                <h5 class="modal-title" id="modalDetalleLabel">Detalles del Reporte</h5>
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

<div class="modal fade" id="modalReporte" tabindex="-1" aria-labelledby="modalReporteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalReporteLabel">Nuevo Reporte de Daño</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formReporte">
                <div class="modal-body">
                    <input type="hidden" id="reporte_id" name="reporte_id">
                    <p class="text-muted">La ubicación es opcional, pero ayuda a identificar el problema rápidamente.
                    </p>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label">Finca</label>
                            <select class="form-select" id="finca_id" name="finca_id"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Aprisco</label>
                            <select class="form-select" id="aprisco_id" name="aprisco_id"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Área</label>
                            <select class="form-select" id="area_id" name="area_id"></select>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Título</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required></textarea>
                    </div>

                    <div class="row g-2 mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Criticidad</label>
                            <select class="form-select" id="criticidad" name="criticidad" required>
                                <?php foreach ($criticidad_opts as $opt): ?>
                                    <option value="<?= $opt ?>"><?= ucfirst(strtolower($opt)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select class="form-select" id="estado_reporte" name="estado_reporte" required>
                                <?php foreach ($estado_opts as $opt): ?>
                                    <option value="<?= $opt ?>"><?= ucfirst(strtolower(str_replace('_', ' ', $opt))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
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
        grid-template-columns: repeat(2, 1fr);
        gap: .75rem;
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
<script type="module" src="<?= BASE_URL ?>public/assets/js/modules/reportes_dano_view.js"></script>