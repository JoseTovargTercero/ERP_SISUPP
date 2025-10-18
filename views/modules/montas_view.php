<?php

// Verificaciones para hembras:
// - Edad mínima: 8 meses
// - Estado de preñez: No preñada

?>


<div class="container-fluid">
    <!-- Título y Botón de Nuevo Periodo -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <button type="button" class="btn btn-primary" id="btnNuevoPeriodo">
                        <i class="mdi mdi-plus"></i> Nuevo Periodo de Monta
                    </button>
                </div>
                <h4 class="page-title">Gestión Agro — Registro de montas</h4>
            </div>
        </div>
    </div>

    <!-- Tabla Principal -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="tablaPeriodosMonta" data-toggle="table"
                        data-url="<?php echo BASE_URL; ?>api/periodos_monta"
                        data-response-handler="responseHandler" data-pagination="true" data-search="true"
                        data-show-refresh="true" data-show-columns="true" data-locale="es-ES"
                        class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th data-field="verraco_identificador" data-sortable="true">Verraco</th>
                                <th data-field="hembra_identificador" data-sortable="true">Hembra</th>
                                <th data-field="cantidad_montas" data-align="center">N° Servicios</th>
                                <th data-field="fecha_inicio" data-sortable="true">Inicio</th>
                                <th data-field="fecha_ultima_monta" data-sortable="true">Último Servicio</th>
                                <th data-field="estado_periodo" data-align="center">Estado</th>
                                <th data-field="periodo_id" data-formatter="accionesFormatter" data-align="center">Acciones</th>
                            </tr>
                        </thead>
                    </table>


                </div>
            </div>
        </div>
    </div>
</div>



<!-- Modal: Registrar Periodo de Monta -->
<div class="modal fade" id="modalPeriodoMonta" tabindex="-1" aria-labelledby="modalPeriodoMontaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formPeriodoMonta">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPeriodoMontaLabel">Registrar Nuevo Periodo de Monta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <!-- Wizard Steps -->
                    <ul class="nav nav-tabs" id="wizardTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active disabled" id="paso1-tab" data-bs-toggle="tab" data-bs-target="#paso1" type="button"
                                role="tab">1. Selección de Animales</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link disabled" id="paso2-tab" data-bs-toggle="tab" data-bs-target="#paso2" type="button"
                                role="tab">2. Información del Periodo</button>
                        </li>
                    </ul>

                    <div class="tab-content mt-3">
                        <!-- Paso 1 -->
                        <div class="tab-pane fade show active" id="paso1" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="verraco_id" class="form-label">Seleccionar Verraco</label>
                                    <select class="form-select" id="verraco_id" name="verraco_id" required>
                                        <option value="">Seleccione...</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="hembra_id" class="form-label">Seleccionar Hembra</label>
                                    <select class="form-select" id="hembra_id" name="hembra_id" required>
                                        <option value="">Seleccione...</option>

                                    </select>
                                </div>



                                <div class="col-md-12">
                                    <div class="alert alert-info mt-2" id="infoCruce" style="display:none;">
                                        <strong>Origen Genético:</strong> <span id="origenGenetico"></span>
                                    </div>
                                </div>





                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-primary" id="btnSiguientePaso">Siguiente</button>
                            </div>
                        </div>

                        <!-- Paso 2 -->
                        <div class="tab-pane fade" id="paso2" role="tabpanel">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="observaciones" class="form-label">Observaciones</label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"
                                        placeholder="Notas sobre comportamiento, condiciones o manejo"></textarea>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-secondary me-2" id="btnAnteriorPaso">Atrás</button>
                                <button type="submit" class="btn btn-success">Guardar Periodo</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="viz"></div>
<style>
    .node-label {
        font-size: 12px;
        background: rgb(224, 224, 224, 0.5);
        width: fit-content;
        padding: 5px;
        text-align: center;
    }

    .node-label small {
        font-size: 10px;
        color: rgb(183, 183, 183);
    }
</style>

<!-- Modal: Registrar Servicio (Monta Individual) -->
<div class="modal fade" id="modalServicio" tabindex="-1" aria-labelledby="modalServicioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formServicio">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalServicioLabel">Registrar Servicio de Monta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row">

                        <div class="col-md-12 mb-3">
                            <label for="fecha_servicio" class="form-label">Fecha del Servicio</label>
                            <input type="date" class="form-control" id="fecha_servicio" name="fecha_servicio" required>
                        </div>


                        <div class="col-md-12 mb-3">
                            <label for="observacion_servicio" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observacion_servicio" name="observacion_servicio" rows="3"
                                placeholder="Ej: la hembra no presentó rechazo, comportamiento normal..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <input type="hidden" id="periodo_id" name="periodo_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar Servicio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://d3js.org/d3.v3.min.js"></script>

<script>
    const baseUrl = "<?= BASE_URL ?>";



    // Mapa de UUID a enteros
    const uuidMap = new Map();
    let nextId = 1;

    function uuidToInt(uuid) {
        if (!uuid) return null;
        if (!uuidMap.has(uuid)) {
            uuidMap.set(uuid, nextId++);
        }
        return uuidMap.get(uuid);
    }


    // --- Wizard ---
    document.getElementById('btnSiguientePaso').addEventListener('click', () => {
        if (!document.getElementById('verraco_id').value || !document.getElementById('hembra_id').value) {
            Swal.fire('Error', 'Debe seleccionar un verraco y una hembra.', 'error');
            return;
        }
        if (verificacionGeneticaParentesco) {
            Swal.fire('Atención', `El cruce seleccionado presenta parentesco genético directo. 
                Por favor, elija animales sin relación para evitar problemas de consanguinidad.`, 'warning');
            return;
        }
        const paso2Tab = new bootstrap.Tab(document.querySelector('#paso2-tab'));
        paso2Tab.show();
    });

    document.getElementById('btnAnteriorPaso').addEventListener('click', () => {
        const paso1Tab = new bootstrap.Tab(document.querySelector('#paso1-tab'));
        paso1Tab.show();
    });
</script>


<script type="module" src="<?= BASE_URL ?>/public/assets/js/modules/montas_view.js"></script>