<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <button type="button" class="btn btn-primary" id="btnNuevoMenu">
                        <i class="mdi mdi-plus"></i> Nuevo Menú
                    </button>
                </div>
                <h4 class="page-title">GESTIÓN DE MENÚS</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="tablaMenus" data-toggle="table" data-url="<?php echo BASE_URL; ?>api/menus"
                        data-response-handler="responseHandler" data-pagination="true" data-search="true"
                        data-show-refresh="true" data-show-columns="true" data-locale="es-ES"
                        class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th data-field="nombre" data-sortable="true">Nombre</th>
                                <th data-field="categoria" data-sortable="true">Categoría</th>
                                <th data-field="url" data-sortable="true">URL</th>
                                <th data-field="user_level" data-sortable="true" data-halign="center"
                                    data-align="center">Nivel Acceso</th>
                                <th data-field="menu_id" data-formatter="accionesFormatter" data-halign="center"
                                    data-align="center">Acciones</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMenu" tabindex="-1" aria-labelledby="modalMenuLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalMenuLabel">Crear Nuevo Menú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formMenu">
                <div class="modal-body">
                    <input type="hidden" id="menu_id" name="menu_id">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required
                            placeholder="Ej: Gestión de Animales">
                    </div>

                    <div class="mb-3">
                        <label for="categoria" class="form-label">Categoría</label>
                        <select class="form-select" id="categoria" name="categoria" required style="width: 100%;">
                            <option value="">Seleccione una categoría...</option>
                            <option value="area">Área</option>
                            <option value="finca">Finca</option>
                            <option value="aprisco">Aprisco</option>
                            <option value="reporte_dano">Reporte de Daño</option>
                            <option value="montas">Montas</option>
                            <option value="partos">Partos</option>
                            <option value="animales">Animales</option>
                            <option value="alertas">Alertas</option>
                            <option value="usuarios">Usuarios</option>
                            <option value="respaldos">Respaldos</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="url" class="form-label">URL</label>
                        <input type="text" class="form-control" id="url" name="url" required
                            placeholder="Ej: /animales/listado">
                    </div>

                    <div class="mb-3">
                        <label for="icono" class="form-label">Ícono (Opcional)</label>
                        <input type="text" class="form-control" id="icono" name="icono" placeholder="Ej: mdi mdi-sheep">
                    </div>

                    <div class="mb-3">
                        <label for="user_level" class="form-label">Nivel de Acceso</label>
                        <input type="number" class="form-control" id="user_level" name="user_level" required min="0"
                            max="10" value="0">
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

<div class="modal fade" id="modalDetallesMenu" tabindex="-1" aria-labelledby="modalDetallesMenuLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetallesMenuLabel">Detalles del Menú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>ID:</strong> <span id="detalle_menu_id"></span></p>
                <p><strong>Nombre:</strong> <span id="detalle_nombre"></span></p>
                <p><strong>Categoría:</strong> <span id="detalle_categoria"></span></p>
                <p><strong>URL:</strong> <span id="detalle_url"></span></p>
                <p><strong>Ícono:</strong> <span id="detalle_icono"></span></p>
                <p><strong>Nivel de Acceso:</strong> <span id="detalle_user_level"></span></p>
                <p><strong>Creado el:</strong> <span id="detalle_created_at"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const baseUrl = "<?php echo BASE_URL; ?>";
</script>
<script type="module" src="<?= BASE_URL ?>public/assets/js/modules/menus_view.js"></script>