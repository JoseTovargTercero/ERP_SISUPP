<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <button type="button" class="btn btn-primary" id="btnNuevoUsuario">
                        <i class="mdi mdi-plus"></i> Nuevo Usuario
                    </button>
                </div>
                <h4 class="page-title">USUARIOS</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="tablaUsuarios" data-toggle="table" data-url="<?php echo BASE_URL; ?>api/system_users"
                        data-response-handler="responseHandler" data-pagination="true" data-search="true"
                        data-show-refresh="true" data-show-columns="true" data-locale="es-ES"
                        class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th data-field="nombre" data-sortable="true">Nombre</th>
                                <th data-field="email" data-sortable="true">Email</th>
                                <th data-field="nivel" data-formatter="nivelFormatter" data-halign="center"
                                    data-align="center">Nivel</th>
                                <th data-field="estado" data-formatter="estadoFormatter" data-halign="center"
                                    data-align="center">Estado</th>
                                <th data-field="user_id" data-formatter="accionesFormatter" data-halign="center"
                                    data-align="center">Acciones</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUsuarioLabel">Crear Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formUsuario">
                <div class="modal-body">
                    <input type="hidden" id="user_id" name="user_id">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="contrasena" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="contrasena" name="contrasena">
                        <small class="form-text text-muted">Dejar en blanco si no desea cambiarla.</small>
                    </div>

                    <div class="mb-3">
                        <label for="nivel" class="form-label">Nivel</label>
                        <select class="form-select" id="nivel" name="nivel" required>
                            <option value="0">Administrador</option>
                            <option value="1">Usuario</option>

                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="estado" name="estado" value="1" checked>
                            <label class="form-check-label" for="estado">Activo</label>
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

<div class="modal fade" id="modalDetalles" tabindex="-1" aria-labelledby="modalDetallesLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetallesLabel">Detalles del Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Nombre:</strong> <span id="detalle_nombre"></span></p>
                <p><strong>Email:</strong> <span id="detalle_email"></span></p>
                <p><strong>Nivel:</strong> <span id="detalle_nivel"></span></p>
                <p><strong>Estado:</strong> <span id="detalle_estado"></span></p>
                <p><strong>Creado el:</strong> <span id="detalle_created_at"></span></p>
                <p><strong>Actualizado el:</strong> <span id="detalle_updated_at"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPermisos" tabindex="-1" aria-labelledby="modalPermisosLabel" aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPermisosLabel">Asignar Permisos para: <span></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="permisos_user_id">
                <p class="text-muted">Selecciona los módulos a los que el usuario tendrá acceso.</p>

                <div class="accordion custom-accordion" id="accordionPermisos">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarPermisos">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<script>
    const baseUrl = "<?php echo BASE_URL; ?>";
</script>
<script type="module" src="<?= BASE_URL ?>/public/assets/js/modules/usuarios_view.js"></script>