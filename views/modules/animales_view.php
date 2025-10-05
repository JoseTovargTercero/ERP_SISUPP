<?php
// Simula la obtención de valores para los ENUMs desde la base de datos.
// En una aplicación real, esto podría venir de una consulta a INFORMATION_SCHEMA
// o estar definido en un archivo de configuración.

$enums = [
    'sexo' => ['MACHO', 'HEMBRA'],
    'especie' => ['BOVINO', 'OVINO', 'CAPRINO', 'PORCINO', 'OTRO'],
    'estado_animal' => ['ACTIVO', 'INACTIVO', 'MUERTO', 'VENDIDO'],
    'etapa_productiva' => ['TERNERO', 'LEVANTE', 'CEBA', 'REPRODUCTOR', 'LACTANTE', 'SECA', 'GESTANTE', 'OTRO'],
    'categoria' => ['CRIA', 'MADRE', 'PADRE', 'ENGORDE', 'REEMPLAZO', 'OTRO'],
    'origen' => ['NACIMIENTO', 'COMPRA', 'TRASLADO', 'OTRO'],
    'tipo_movimiento' => ['INGRESO', 'EGRESO', 'TRASLADO', 'VENTA', 'COMPRA', 'NACIMIENTO', 'MUERTE', 'OTRO'],
    'motivo_movimiento' => ['TRASLADO', 'INGRESO', 'EGRESO', 'AISLAMIENTO', 'VENTA', 'OTRO'],
    'estado_movimiento' => ['REGISTRADO', 'ANULADO'],
    'tipo_evento_salud' => ['ENFERMEDAD', 'VACUNACION', 'DESPARASITACION', 'REVISION', 'TRATAMIENTO', 'OTRO'],
    'severidad_salud' => ['LEVE', 'MODERADA', 'GRAVE', 'NO_APLICA'],
    'estado_salud' => ['ABIERTO', 'SEGUIMIENTO', 'CERRADO'],
    'motivo_ubicacion' => ['TRASLADO', 'INGRESO', 'EGRESO', 'AISLAMIENTO', 'VENTA', 'OTRO'],
];
?>

<div class="container-fluid">
    <!-- Título y Botón de Nuevo Animal -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <button type="button" class="btn btn-primary" id="btnNuevoAnimal">
                        <i class="mdi mdi-plus"></i> Registrar Animal
                    </button>
                </div>
                <h4 class="page-title">GESTIÓN DE ANIMALES</h4>
            </div>
        </div>
    </div>

    <!-- Tabla Principal de Animales -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="tablaAnimales" data-toggle="table" data-url="<?php echo BASE_URL; ?>api/animales"
                        data-response-handler="responseHandler" data-pagination="true" data-search="true"
                        data-show-refresh="true" data-show-columns="true" data-locale="es-ES"
                        class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th data-field="identificador" data-sortable="true">Identificador</th>
                                <th data-field="sexo" data-sortable="true">Sexo</th>
                                <th data-field="especie" data-sortable="true">Especie</th>
                                <th data-field="raza" data-sortable="true">Raza</th>
                                <th data-field="ultimo_peso_kg" data-sortable="true" data-formatter="pesoFormatter">
                                    Último Peso</th>
                                <th data-field="ubicacion_actual" data-sortable="true"
                                    data-formatter="ubicacionFormatter">Ubicación Actual</th>
                                <th data-field="animal_id" data-formatter="accionesFormatter" data-halign="center"
                                    data-align="center">Acciones</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Crear/Editar Animal -->
<div class="modal fade" id="modalAnimal" tabindex="-1" aria-labelledby="modalAnimalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAnimalLabel">Registrar Nuevo Animal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAnimal" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="animal_id" name="animal_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="identificador" class="form-label">Identificador</label>
                            <input type="text" class="form-control" id="identificador" name="identificador" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sexo" class="form-label">Sexo</label>
                            <select class="form-select" id="sexo" name="sexo" required>
                                <?php foreach ($enums['sexo'] as $value): ?>
                                    <option value="<?php echo $value; ?>"><?php echo ucfirst(strtolower($value)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="especie" class="form-label">Especie</label>
                            <select class="form-select" id="especie" name="especie" required>
                                <?php foreach ($enums['especie'] as $value): ?>
                                    <option value="<?php echo $value; ?>"><?php echo ucfirst(strtolower($value)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="raza" class="form-label">Raza</label>
                            <input type="text" class="form-control" id="raza" name="raza">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estado" class="form-label">Estado del Animal</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <?php foreach ($enums['estado_animal'] as $value): ?>
                                    <option value="<?php echo $value; ?>"><?php echo ucfirst(strtolower($value)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="origen" class="form-label">Origen</label>
                            <select class="form-select" id="origen" name="origen" required>
                                <?php foreach ($enums['origen'] as $value): ?>
                                    <option value="<?php echo $value; ?>"><?php echo ucfirst(strtolower($value)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fotografia" class="form-label">Fotografía (Opcional)</label>
                            <input type="file" class="form-control" id="fotografia" name="fotografia"
                                accept="image/png, image/jpeg, image/webp">
                        </div>
                        <div class="col-md-12 text-center">
                            <img id="fotografia-preview" src="https://placehold.co/200x200?text=Vista+Previa"
                                class="img-fluid rounded mt-2" style="max-height: 200px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalles del Animal -->
<div class="modal fade" id="modalDetallesAnimal" tabindex="-1" aria-labelledby="modalDetallesAnimalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetallesAnimalLabel">Detalles del Animal: <span
                        id="detalle_identificador_titulo"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="detalles-loader" class="text-center">
                    <div class="spinner-border" role="status"></div>
                </div>
                <div id="detalles-content" class="d-none">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" id="animalDetailsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info"
                                type="button" role="tab" aria-controls="info" aria-selected="true">Información
                                General</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="pesos-tab" data-bs-toggle="tab" data-bs-target="#pesos"
                                type="button" role="tab" aria-controls="pesos" aria-selected="false">Pesos</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="salud-tab" data-bs-toggle="tab" data-bs-target="#salud"
                                type="button" role="tab" aria-controls="salud" aria-selected="false">Salud</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="movimientos-tab" data-bs-toggle="tab"
                                data-bs-target="#movimientos" type="button" role="tab" aria-controls="movimientos"
                                aria-selected="false">Movimientos</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="ubicaciones-tab" data-bs-toggle="tab"
                                data-bs-target="#ubicaciones" type="button" role="tab" aria-controls="ubicaciones"
                                aria-selected="false">Ubicaciones</button>
                        </li>
                    </ul>

                    <!-- Tab panes -->
                    <div class="tab-content mt-3" id="animalDetailsTabContent">
                        <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                            <div class="row">
                                <div class="col-md-8">
                                    <p><strong>Identificador:</strong> <span id="detalle_identificador"></span></p>
                                    <p><strong>Sexo:</strong> <span id="detalle_sexo"></span></p>
                                    <p><strong>Especie:</strong> <span id="detalle_especie"></span></p>
                                    <p><strong>Raza:</strong> <span id="detalle_raza"></span></p>
                                    <p><strong>Fecha Nacimiento:</strong> <span id="detalle_fecha_nacimiento"></span>
                                    </p>
                                    <p><strong>Estado:</strong> <span id="detalle_estado"></span></p>
                                    <p><strong>Origen:</strong> <span id="detalle_origen"></span></p>
                                    <p><strong>Creado el:</strong> <span id="detalle_created_at"></span></p>
                                </div>
                                <div class="col-md-4">
                                    <img id="detalle_fotografia" src="https://placehold.co/300x300?text=Sin+Foto"
                                        class="img-fluid rounded">
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="pesos" role="tabpanel" aria-labelledby="pesos-tab">
                            <button class="btn btn-success btn-sm mb-2" id="btnRegistrarPeso"><i
                                    class="mdi mdi-weight-kilogram"></i> Registrar Peso</button>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Peso (kg)</th>
                                            <th>Método</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaDetallesPesos"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="salud" role="tabpanel" aria-labelledby="salud-tab">
                            <button class="btn btn-success btn-sm mb-2" id="btnRegistrarSalud"><i
                                    class="mdi mdi-medical-bag"></i> Registrar Evento de Salud</button>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Tipo</th>
                                            <th>Diagnóstico</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaDetallesSalud"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="movimientos" role="tabpanel" aria-labelledby="movimientos-tab">
                            <button class="btn btn-success btn-sm mb-2" id="btnRegistrarMovimiento"><i
                                    class="mdi mdi-swap-horizontal"></i> Registrar Movimiento</button>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Tipo</th>
                                            <th>Motivo</th>
                                            <th>Origen</th>
                                            <th>Destino</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaDetallesMovimientos"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="ubicaciones" role="tabpanel" aria-labelledby="ubicaciones-tab">
                            <button class="btn btn-success btn-sm mb-2" id="btnRegistrarUbicacion"><i
                                    class="mdi mdi-map-marker"></i> Registrar Ubicación</button>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Desde</th>
                                            <th>Hasta</th>
                                            <th>Ubicación</th>
                                            <th>Motivo</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaDetallesUbicaciones"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Registrar Peso -->
<div class="modal fade" id="modalRegistroPeso" tabindex="-1" aria-labelledby="modalRegistroPesoLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRegistroPesoLabel">Registrar Peso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formRegistroPeso">
                <div class="modal-body">
                    <input type="hidden" name="animal_id" id="peso_animal_id">
                    <div class="mb-3">
                        <label for="fecha_peso" class="form-label">Fecha del Pesaje</label>
                        <input type="date" class="form-control" id="fecha_peso" name="fecha_peso" required>
                    </div>
                    <div class="row">
                        <div class="col-8">
                            <label for="peso_kg" class="form-label">Peso</label>
                            <input type="number" step="0.01" class="form-control" id="peso_kg" name="peso_kg" required>
                        </div>
                        <div class="col-4">
                            <label for="unidad" class="form-label">Unidad</label>
                            <select class="form-select" id="unidad" name="unidad" required>
                                <option value="KG">KG</option>
                                <option value="LB">LB</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="metodo" class="form-label">Método</label>
                        <input type="text" class="form-control" id="metodo" name="metodo">
                    </div>
                    <div class="mb-3">
                        <label for="observaciones_peso" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones_peso" name="observaciones"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                        id="btnCancelarRegistroPeso">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Peso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Registrar Evento de Salud -->
<div class="modal fade" id="modalRegistroSalud" tabindex="-1" aria-labelledby="modalRegistroSaludLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRegistroSaludLabel">Registrar Evento de Salud</h5><button type="button"
                    class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formRegistroSalud">
                <div class="modal-body">
                    <input type="hidden" name="animal_id" id="salud_animal_id">
                    <div class="row">
                        <!-- Fila 1: Fecha y Tipo -->
                        <div class="col-md-6 mb-3">
                            <label for="fecha_evento" class="form-label">Fecha del Evento</label>
                            <input type="date" class="form-control" id="fecha_evento" name="fecha_evento" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tipo_evento" class="form-label">Tipo de Evento</label>
                            <select class="form-select" id="tipo_evento" name="tipo_evento" required>
                                <?php foreach ($enums['tipo_evento_salud'] as $value): ?>
                                    <option value="<?php echo $value; ?>"><?php echo ucfirst(strtolower($value)); ?>
                                    </option><?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Fila 2: Diagnóstico y Severidad -->
                        <div class="col-md-6 mb-3">
                            <label for="diagnostico" class="form-label">Diagnóstico</label>
                            <input type="text" class="form-control" id="diagnostico" name="diagnostico">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="severidad" class="form-label">Severidad</label>
                            <select class="form-select" id="severidad" name="severidad">
                                <?php foreach ($enums['severidad_salud'] as $value): ?>
                                    <option value="<?php echo $value; ?>">
                                        <?php echo str_replace('_', ' ', ucfirst(strtolower($value))); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Fila 3: Tratamiento -->
                        <div class="col-md-12 mb-3">
                            <label for="tratamiento" class="form-label">Tratamiento</label>
                            <textarea class="form-control" id="tratamiento" name="tratamiento" rows="2"></textarea>
                        </div>

                        <!-- Fila 4: Medicamento, Dosis, Vía -->
                        <div class="col-md-6 mb-3">
                            <label for="medicamento" class="form-label">Medicamento</label>
                            <input type="text" class="form-control" id="medicamento" name="medicamento">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="dosis" class="form-label">Dosis</label>
                            <input type="text" class="form-control" id="dosis" name="dosis">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="via_administracion" class="form-label">Vía Admin.</label>
                            <input type="text" class="form-control" id="via_administracion" name="via_administracion">
                        </div>

                        <!-- Fila 5: Costo y Estado -->
                        <div class="col-md-6 mb-3">
                            <label for="costo" class="form-label">Costo</label>
                            <input type="number" step="0.01" class="form-control" id="costo" name="costo"
                                placeholder="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="estado_salud" class="form-label">Estado del Caso</label>
                            <select class="form-select" id="estado_salud" name="estado">
                                <?php foreach ($enums['estado_salud'] as $value): ?>
                                    <option value="<?php echo $value; ?>"><?php echo ucfirst(strtolower($value)); ?>
                                    </option><?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Fila 6: Próxima Revisión y Responsable -->
                        <div class="col-md-6 mb-3">
                            <label for="proxima_revision" class="form-label">Próxima Revisión</label>
                            <input type="date" class="form-control" id="proxima_revision" name="proxima_revision">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="responsable" class="form-label">Responsable</label>
                            <input type="text" class="form-control" id="responsable" name="responsable">
                        </div>

                        <!-- Fila 7: Observaciones -->
                        <div class="col-md-12 mb-3">
                            <label for="observaciones_salud" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones_salud" name="observaciones"
                                rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                        id="btnCancelarRegistroSalud">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Evento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalles del Evento de Salud -->
<div class="modal fade" id="modalDetallesSalud" tabindex="-1" aria-labelledby="modalDetallesSaludLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetallesSaludLabel">Detalles de Evento de Salud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Fecha del Evento:</strong> <span id="detalle_salud_fecha"></span></p>
                <p><strong>Tipo de Evento:</strong> <span id="detalle_salud_tipo"></span></p>
                <p><strong>Diagnóstico:</strong> <span id="detalle_salud_diagnostico"></span></p>
                <p><strong>Severidad:</strong> <span id="detalle_salud_severidad"></span></p>
                <p><strong>Tratamiento:</strong> <span id="detalle_salud_tratamiento"></span></p>
                <p><strong>Medicamento:</strong> <span id="detalle_salud_medicamento"></span></p>
                <p><strong>Dosis:</strong> <span id="detalle_salud_dosis"></span></p>
                <p><strong>Vía de Administración:</strong> <span id="detalle_salud_via"></span></p>
                <p><strong>Costo:</strong> <span id="detalle_salud_costo"></span></p>
                <p><strong>Estado:</strong> <span id="detalle_salud_estado"></span></p>
                <p><strong>Próxima Revisión:</strong> <span id="detalle_salud_revision"></span></p>
                <p><strong>Responsable:</strong> <span id="detalle_salud_responsable"></span></p>
                <p><strong>Observaciones:</strong> <span id="detalle_salud_observaciones"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    id="btnCerrarDetalleSalud">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Registrar Movimiento -->
<div class="modal fade" id="modalRegistroMovimiento" tabindex="-1" role="dialog"
    aria-labelledby="modalRegistroMovimientoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRegistroMovimientoLabel">Registrar Movimiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formRegistroMovimiento">
                <div class="modal-body">
                    <input type="hidden" name="animal_id" id="movimiento_animal_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_mov" class="form-label">Fecha Movimiento</label>
                            <input type="date" class="form-control" id="fecha_mov" name="fecha_mov" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tipo_movimiento" class="form-label">Tipo Movimiento</label>
                            <select class="form-select" id="tipo_movimiento" name="tipo_movimiento" required>
                                <?php foreach ($enums['tipo_movimiento'] as $value): ?>
                                    <option value="<?php echo $value; ?>"><?php echo ucfirst(strtolower($value)); ?>
                                    </option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="motivo_movimiento" class="form-label">Motivo</label>
                            <select class="form-select" id="motivo_movimiento" name="motivo">
                                <?php foreach ($enums['motivo_movimiento'] as $value): ?>
                                    <option value="<?php echo $value; ?>"><?php echo ucfirst(strtolower($value)); ?>
                                    </option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="costo_movimiento" class="form-label">Costo (Opcional)</label>
                            <input type="number" step="0.01" class="form-control" id="costo_movimiento" name="costo">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Origen</h6>
                            <p><em>(Requerido para Egresos, Ventas, Muertes, Traslados)</em></p>
                            <div class="mb-3"><label class="form-label">Finca Origen</label><select
                                    name="finca_origen_id" class="form-select"></select></div>
                            <div class="mb-3"><label class="form-label">Aprisco Origen</label><select
                                    name="aprisco_origen_id" class="form-select"></select></div>
                            <div class="mb-3"><label class="form-label">Área Origen</label><select name="area_origen_id"
                                    class="form-select"></select></div>
                        </div>
                        <div class="col-md-6">
                            <h6>Destino</h6>
                            <p><em>(Requerido para Ingresos, Compras, Nacimientos, Traslados)</em></p>
                            <div class="mb-3"><label class="form-label">Finca Destino</label><select
                                    name="finca_destino_id" class="form-select"></select></div>
                            <div class="mb-3"><label class="form-label">Aprisco Destino</label><select
                                    name="aprisco_destino_id" class="form-select"></select></div>
                            <div class="mb-3"><label class="form-label">Área Destino</label><select
                                    name="area_destino_id" class="form-select"></select></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="observaciones_movimiento" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones_movimiento" name="observaciones"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                        id="btnCancelarRegistroMovimiento">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Movimiento</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal: Registrar Ubicación -->
<div class="modal fade" id="modalRegistroUbicacion" tabindex="-1" role="dialog"
    aria-labelledby="modalRegistroUbicacionLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRegistroUbicacionLabel">Registrar Nueva Ubicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formRegistroUbicacion">
                <div class="modal-body">
                    <input type="hidden" name="animal_id" id="ubicacion_animal_id">
                    <p class="alert alert-info">Al registrar una nueva ubicación activa, la anterior (si existe) se
                        cerrará automáticamente.</p>
                    <div class="mb-3">
                        <label for="fecha_desde_ubicacion" class="form-label">Fecha Desde</label>
                        <input type="date" class="form-control" id="fecha_desde_ubicacion" name="fecha_desde" required>
                    </div>
                    <div class="mb-3">
                        <label for="motivo_ubicacion" class="form-label">Motivo</label>
                        <select class="form-select" id="motivo_ubicacion" name="motivo" required>
                            <?php foreach ($enums['motivo_ubicacion'] as $value): ?>
                                <option value="<?php echo $value; ?>"><?php echo ucfirst(strtolower($value)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Finca</label><select name="finca_id"
                            class="form-select"></select></div>
                    <div class="mb-3"><label class="form-label">Aprisco</label><select name="aprisco_id"
                            class="form-select"></select></div>
                    <div class="mb-3"><label class="form-label">Área</label><select name="area_id"
                            class="form-select"></select></div>
                    <div class="mb-3">
                        <label for="observaciones_ubicacion" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones_ubicacion" name="observaciones"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                        id="btnCancelarRegistroUbicacion">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Ubicación</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
    const baseUrl = "<?php echo BASE_URL; ?>";
</script>
<script type="module" src="<?= BASE_URL ?>public/assets/js/modules/animales_view.js"></script>