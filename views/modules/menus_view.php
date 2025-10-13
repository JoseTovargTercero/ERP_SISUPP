<link rel="stylesheet"
    href="https://unpkg.com/bootstrap-table@1.22.1/dist/extensions/reorder-rows/bootstrap-table-reorder-rows.css">

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">ORGANIZACIÓN DEL MENÚ</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Arrastra las categorías para ordenarlas</h5>
                    <table id="tablaCategorias" data-toggle="table"
                        data-url="<?php echo BASE_URL; ?>api/menus-categorias" data-response-handler="responseHandler"
                        data-reorderable-rows="true" data-use-row-attr-func="true" data-drag-handle=".drag-handle"
                        class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th data-field="drag" data-formatter="dragHandleFormatter" class="drag-handle"
                                    style="width: 5%;"></th>
                                <th data-field="nombre" data-formatter="nombreCategoriaFormatter">Categoría</th>
                                <th data-field="nombre" data-formatter="accionesCategoriaFormatter" data-align="right">
                                    Acciones</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalItems" tabindex="-1" aria-labelledby="modalItemsLabel" aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalItemsLabel">Gestionar Ítems</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-end mb-3">
                    <button id="btnNuevoItem" class="btn btn-primary"><i class="mdi mdi-plus"></i> Nuevo Ítem</button>
                </div>
                <table id="tablaItems" data-reorderable-rows="true" data-use-row-attr-func="true"
                    data-drag-handle=".drag-handle" class="table">
                    <thead>
                        <tr>
                            <th data-field="drag" data-formatter="dragHandleFormatter" class="drag-handle"
                                style="width: 5%;"></th>
                            <th data-field="nombre">Nombre</th>
                            <th data-field="url">URL</th>
                            <th data-field="user_level" data-align="center">Nivel</th>
                            <th data-field="menu_id" data-formatter="accionesItemFormatter" data-align="center">Acciones
                            </th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalFormularioItem" tabindex="-1" aria-labelledby="modalFormularioItemLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalFormularioItemLabel">Crear Nuevo Ítem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formItem">
                <div class="modal-body">
                    <input type="hidden" id="menu_id" name="menu_id">

                    <input type="hidden" id="categoria" name="categoria">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="url" class="form-label">URL</label>
                        <input type="text" class="form-control" id="url" name="url" required>
                    </div>
                    <div class="mb-3">
                        <label for="icono" class="form-label">Ícono (Opcional)</label>
                        <input type="text" class="form-control" id="icono" name="icono">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="user_level" class="form-label">Nivel Acceso</label>
                            <input type="number" class="form-control" id="user_level" name="user_level" required min="0"
                                max="10" value="1">
                        </div>
                        <div class="col-md-6">
                            <label for="orden" class="form-label">Orden</label>
                            <input type="number" class="form-control" id="orden" name="orden" required min="0"
                                value="0">
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



<script>
    const baseUrl = "<?php echo BASE_URL; ?>";
</script>
<script type="module" src="<?= BASE_URL ?>public/assets/js/modules/categorias_view.js"></script>