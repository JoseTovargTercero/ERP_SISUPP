import { showErrorToast } from '../helpers/helpers.js';

const modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalleSesion'));

const BASE = typeof baseUrl !== 'undefined' ? baseUrl : '/'; // compat
const API = `${BASE}api/session_management`;

/* =================== Helpers =================== */

const pad2 = (n) => String(n).padStart(2, '0');

const formatDateTime = (value) => {
  if (!value) return 'N/A';
  // admite 'YYYY-MM-DD HH:MM:SS' o ISO
  const d = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return 'Fecha inválida';
  const yyyy = d.getFullYear();
  const mm = pad2(d.getMonth() + 1);
  const dd = pad2(d.getDate());
  const hh = pad2(d.getHours());
  const mi = pad2(d.getMinutes());
  return `${dd}/${mm}/${yyyy} ${hh}:${mi}`;
};

const badge = (text, cls) => `<span class="badge bg-${cls}">${text}</span>`;

/* =================== Bootstrap Table Hooks =================== */

/** Respuesta -> filas */
window.sessionResponseHandler = function (res) {
  const rows = Array.isArray(res?.data) ? res.data : [];
  // Campos calculados para mostrar bonito en columnas "location" y "device"
  rows.forEach(r => {
    r.location = {
      city: r.city, region: r.region, country: r.country, zipcode: r.zipcode
    };
    r.device = {
      os: r.os, browser: r.browser, device_type: r.device_type
    };
  });
  return { rows, total: rows.length };
};

/** Carga con filtros */
window.loadSessions = function (params) {
  const q = new URLSearchParams();

  const status = document.getElementById('filtroStatus').value;
  const success = document.getElementById('filtroSuccess').value;
  const desde = document.getElementById('filtroDesde').value;
  const hasta = document.getElementById('filtroHasta').value;

  if (status) q.set('session_status', status);
  if (success !== '') q.set('login_success', success);
  if (desde) q.set('from', desde.replace('T', ' '));
  if (hasta) q.set('to', hasta.replace('T', ' '));

  $.ajax({
    url: `${API}?${q.toString()}`,
    method: 'GET',
    dataType: 'json',
    success: (res) => params.success(res),
    error: (xhr) => {
      params.error(xhr);
      showErrorToast(xhr.responseJSON || { message: 'Error cargando sesiones.' });
    }
  });
};

/* =================== Column Formatters =================== */

window.dateTimeFormatter = (value) => formatDateTime(value);

window.userFormatter = (value, row) => {
  const full = row.full_name || '';
  const user = value || '';
  const id = row.user_id || '';
  const lines = [];
  if (full) lines.push(`<div><strong>${full}</strong></div>`);
  if (user) lines.push(`<div class="text-muted small">@${user}</div>`);
  if (id)   lines.push(`<div class="text-muted small">${id}</div>`);
  return lines.join('');
};

window.typeFormatter = (value) => {
  if (!value) return 'N/A';
  const map = { administrator: 'danger', admin: 'danger', user: 'primary' };
  const key = String(value).toLowerCase();
  return badge(key.toUpperCase(), map[key] || 'secondary');
};

window.successFormatter = (value) => {
  const ok = value === 1 || value === true || value === '1';
  return ok ? badge('OK', 'success') : badge('FALLÓ', 'danger');
};

window.statusFormatter = (value) => {
  const val = (value || '').toUpperCase();
  const cls = val === 'ACTIVE' ? 'info' : (val === 'ENDED' ? 'secondary' : 'warning');
  return badge(val || 'N/A', cls);
};

window.ipFormatter = (value, row) => {
  const host = row.hostname ? `<span class="text-muted small d-block">${row.hostname}</span>` : '';
  return `<div>${value || 'N/A'}${host}</div>`;
};

window.locationFormatter = (value) => {
  if (!value) return '<span class="text-muted">N/A</span>';
  const parts = [value.city, value.region, value.country].filter(Boolean).join(', ');
  const zip = value.zipcode ? ` <span class="text-muted">(${value.zipcode})</span>` : '';
  return parts ? `${parts}${zip}` : '<span class="text-muted">N/A</span>';
};

window.deviceFormatter = (value) => {
  if (!value) return '<span class="text-muted">N/A</span>';
  const os = value.os || 'SO?';
  const br = value.browser || 'Navegador?';
  const tp = value.device_type || '—';
  return `<div><strong>${os}</strong> · ${br}<div class="text-muted small">${tp}</div></div>`;
};

window.accionesSesionFormatter = (value, row) => {
  return `
    <div class="btn-group">
      <button class="btn btn-info btn-sm btn-ver-sesion" data-id="${row.session_id}" title="Ver Detalles">
        <i class="mdi mdi-eye"></i>
      </button>
    </div>
  `;
};

/* =================== UI Events =================== */

document.addEventListener('DOMContentLoaded', function () {
  // Botones filtros
  $('#btnAplicarFiltros').on('click', () => {
    $('#sessionManagementTable').bootstrapTable('refresh', { silent: true });
  });
  $('#btnLimpiarFiltros').on('click', () => {
    $('#filtroStatus').val('');
    $('#filtroSuccess').val('');
    $('#filtroDesde').val('');
    $('#filtroHasta').val('');
    $('#sessionManagementTable').bootstrapTable('refresh', { silent: true });
  });

  // Refrescar
  $('#btnRefrescarSesiones').on('click', () => {
    $('#sessionManagementTable').bootstrapTable('refresh');
  });

  // Click acciones
  $('#sessionManagementTable').on('click', '.btn-ver-sesion', function () {
    const id = $(this).data('id');
    abrirDetalleSesion(id);
  });

  // Copiar User-Agent
  $('#btnCopyUA').on('click', () => {
    const txt = $('#d_user_agent').text();
    navigator.clipboard.writeText(txt).then(() => {
      Swal.fire({ toast: true, timer: 1500, showConfirmButton: false, icon: 'success', title: 'User-Agent copiado' });
    });
  });
});

/* =================== Detalle =================== */

function abrirDetalleSesion(sessionId) {
  // loader ON
  $('#detalleSesionLoader').removeClass('d-none');
  $('#detalleSesionContent').addClass('d-none');
  modalDetalle.show();

  $.ajax({
    url: `${API}/${sessionId}`,
    method: 'GET',
    dataType: 'json',
    success: (res) => {
      const s = res?.data || {};
      // Usuario
      $('#d_user_id').text(s.user_id || '—');
      $('#d_full_name').text(s.full_name || '—');
      $('#d_user_name').text(s.user_name || '—');
      $('#d_user_type').html(window.typeFormatter(s.user_type));

      const st = window.statusFormatter(s.session_status);
      $('#d_session_status').html(st);

      $('#d_login_time').text(formatDateTime(s.login_time));
      $('#d_logout_time').text(formatDateTime(s.logout_time));
      $('#d_inactivity_duration').text(s.inactivity_duration ?? '—');

      // Red / Geo
      $('#d_ip_address').text(s.ip_address || '—');
      $('#d_hostname').text(s.hostname || '—');
      $('#d_city').text(s.city || '—');
      $('#d_region').text(s.region || '—');
      $('#d_country').text(s.country || '—');
      $('#d_zipcode').text(s.zipcode || '—');

      const coords = s.coordinates || '';
      $('#d_coordinates').text(coords || '—');
      const $maps = $('#d_maps_link');
      if (coords && coords.includes(',')) {
        const [lat, lng] = coords.split(',').map(x => x.trim());
        $maps.text('Ver en Mapa').attr('href', `https://maps.google.com/?q=${lat},${lng}`).removeClass('d-none');
      } else {
        $maps.text('').attr('href', '#').addClass('d-none');
      }

      // Cliente
      $('#d_os').text(s.os || '—');
      $('#d_browser').text(s.browser || '—');
      $('#d_user_agent').text(s.user_agent || '—');
      $('#d_device_id').text(s.device_id || '—');
      $('#d_device_type').text(s.device_type || '—');
      $('#d_created_at').text(formatDateTime(s.created_at));

      const ok = (s.login_success === 1 || s.login_success === true || s.login_success === '1');
      $('#d_login_success').html(ok ? badge('OK', 'success') : badge('FALLÓ', 'danger'));
      $('#d_failure_reason').text(s.failure_reason || (ok ? '—' : 'Desconocida'));

      // loader OFF -> content ON
      $('#detalleSesionLoader').addClass('d-none');
      $('#detalleSesionContent').removeClass('d-none');
    },
    error: (xhr) => {
      showErrorToast(xhr.responseJSON || { message: 'No se pudo cargar el detalle.' });
      modalDetalle.hide();
    }
  });
}
