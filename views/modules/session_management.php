<div class="container-fluid">
  <!-- Header -->
  <div class="row mb-3">
    <div class="col-12 d-flex align-items-center justify-content-between">
      <h4 class="page-title mb-0">
        <i class="mdi mdi-shield-account-outline me-2"></i>Gestión de Sesiones
      </h4>
      <div class="d-flex gap-2">
        <button id="btnRefrescarSesiones" class="btn btn-outline-primary">
          <i class="mdi mdi-refresh"></i> Refrescar
        </button>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Estado de sesión</label>
          <select id="filtroStatus" class="form-select">
            <option value="">Todos</option>
            <option value="ACTIVE">Activo</option>
            <option value="ENDED">Finalizado</option>
            <option value="LOCKED">Bloqueado</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Resultado de login</label>
          <select id="filtroSuccess" class="form-select">
            <option value="">Todos</option>
            <option value="1">Exitoso</option>
            <option value="0">Fallido</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Desde (fecha/hora)</label>
          <input type="datetime-local" id="filtroDesde" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Hasta (fecha/hora)</label>
          <input type="datetime-local" id="filtroHasta" class="form-control" />
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button id="btnAplicarFiltros" class="btn btn-primary">
          <i class="mdi mdi-filter-variant"></i> Aplicar
        </button>
        <button id="btnLimpiarFiltros" class="btn btn-light">
          <i class="mdi mdi-broom"></i> Limpiar
        </button>
      </div>
    </div>
  </div>

  <!-- Tabla -->
  <div class="card">
    <div class="card-body">
      <table
        id="sessionManagementTable"
        class="table table-striped"
        data-toggle="table"
        data-search="true"
        data-show-refresh="false"
        data-pagination="true"
        data-page-size="10"
        data-page-list="[10, 25, 50, 100]"
        data-side-pagination="client"
        data-response-handler="sessionResponseHandler"
        data-ajax="loadSessions"
      >
        <thead class="table-light">
          <tr>
            <th data-field="session_id" data-visible="false">ID</th>
            <th data-field="login_time" data-formatter="dateTimeFormatter" data-sortable="true">Login</th>
            <th data-field="logout_time" data-formatter="dateTimeFormatter" data-sortable="true">Logout</th>
            <th data-field="user_name" data-formatter="userFormatter" data-sortable="true">Usuario</th>
            <th data-field="user_type" data-formatter="typeFormatter" data-sortable="true">Tipo</th>
            <th data-field="login_success" data-formatter="successFormatter" data-sortable="true">Resultado</th>
            <th data-field="session_status" data-formatter="statusFormatter" data-sortable="true">Estado</th>
            <th data-field="ip_address" data-formatter="ipFormatter" data-sortable="true">IP</th>
            <th data-field="location" data-formatter="locationFormatter" data-sortable="false">Ubicación</th>
            <th data-field="device" data-formatter="deviceFormatter" data-sortable="false">Dispositivo</th>
            <th data-field="acciones" data-formatter="accionesSesionFormatter" data-align="center">Acciones</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalleSesion" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title">
          <i class="mdi mdi-shield-key-outline me-2"></i>Detalle de Sesión
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="detalleSesionLoader" class="text-center my-4 d-none">
          <div class="spinner-border" role="status"></div>
        </div>

        <div id="detalleSesionContent" class="d-none">
          <div class="row g-3">
            <div class="col-lg-4">
              <div class="card h-100">
                <div class="card-body">
                  <h6 class="text-uppercase text-muted mb-3">Usuario</h6>
                  <div class="mb-2"><strong>ID:</strong> <span id="d_user_id"></span></div>
                  <div class="mb-2"><strong>Nombre:</strong> <span id="d_full_name"></span></div>
                  <div class="mb-2"><strong>Usuario:</strong> <span id="d_user_name"></span></div>
                  <div class="mb-2"><strong>Tipo:</strong> <span id="d_user_type"></span></div>
                  <div class="mb-2"><strong>Estado:</strong> <span id="d_session_status"></span></div>
                  <div class="mb-2"><strong>Login:</strong> <span id="d_login_time"></span></div>
                  <div class="mb-2"><strong>Logout:</strong> <span id="d_logout_time"></span></div>
                  <div class="mb-2"><strong>Inactividad:</strong> <span id="d_inactivity_duration"></span></div>
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <div class="card h-100">
                <div class="card-body">
                  <h6 class="text-uppercase text-muted mb-3">Red y Geolocalización</h6>
                  <div class="mb-2"><strong>IP:</strong> <span id="d_ip_address"></span></div>
                  <div class="mb-2"><strong>Host:</strong> <span id="d_hostname"></span></div>
                  <div class="mb-2"><strong>Ciudad:</strong> <span id="d_city"></span></div>
                  <div class="mb-2"><strong>Región:</strong> <span id="d_region"></span></div>
                  <div class="mb-2"><strong>País:</strong> <span id="d_country"></span></div>
                  <div class="mb-2"><strong>ZIP:</strong> <span id="d_zipcode"></span></div>
                  <div class="mb-2">
                    <strong>Coordenadas:</strong> <span id="d_coordinates"></span>
                    <a id="d_maps_link" class="ms-1" target="_blank" rel="noopener"></a>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <div class="card h-100">
                <div class="card-body">
                  <h6 class="text-uppercase text-muted mb-3">Cliente</h6>
                  <div class="mb-2"><strong>SO:</strong> <span id="d_os"></span></div>
                  <div class="mb-2"><strong>Navegador:</strong> <span id="d_browser"></span></div>
                  <div class="mb-2"><strong>Agente:</strong>
                    <div class="d-flex justify-content-between align-items-center">
                      <span id="d_user_agent" class="text-break"></span>
                      <button id="btnCopyUA" class="btn btn-sm btn-outline-secondary ms-2" title="Copiar UA">
                        <i class="mdi mdi-content-copy"></i>
                      </button>
                    </div>
                  </div>
                  <div class="mb-2"><strong>Device ID:</strong> <span id="d_device_id"></span></div>
                  <div class="mb-2"><strong>Tipo Dispositivo:</strong> <span id="d_device_type"></span></div>
                  <div class="mb-2"><strong>Creado:</strong> <span id="d_created_at"></span></div>
                  <div class="mb-2"><strong>Resultado Login:</strong> <span id="d_login_success"></span></div>
                  <div class="mb-2"><strong>Causa de Falla:</strong> <span id="d_failure_reason"></span></div>
                </div>
              </div>
            </div>
          </div> <!-- row -->
        </div> <!-- content -->
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<script>
    const baseUrl = "<?php echo BASE_URL; ?>";
</script>
<script type="module" src="<?= BASE_URL ?>/public/assets/js/modules/session_management.js"></script>