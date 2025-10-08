import { showErrorToast } from "../helpers/helpers.js";

// --- HELPERS Y FORMATTERS PARA LA TABLA ---

/**
 * Adapta la respuesta de la API al formato que Bootstrap Table espera.
 */
window.responseHandler = function (res) {
  return {
    rows: res.data,
    total: res.data.length, // O idealmente un valor desde la API si hay paginación del lado del servidor
  };
};

/**
 * Formatea la columna de acciones con los botones.
 */
window.accionesFormatter = function (value, row) {
  return `
    <div class="btn-group">
        <button class="btn btn-info btn-sm btn-ver" data-id="" title="Ver Detalles"><i class="mdi mdi-eye"></i></button>
    </div>`;
};

/**
 * Formatea una fecha en formato YYYY-MM-DD a DD/MM/YYYY.
 * @param {string} dateString La fecha en formato YYYY-MM-DD.
 * @returns {string} La fecha formateada o un string vacío.
 */
const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  const date = new Date(dateString + "T00:00:00"); // Asume zona horaria local
  if (isNaN(date.getTime())) return "Fecha inválida";
  return date.toLocaleDateString("es-VE"); // Formato dd/mm/aaaa
};

// TODO: --- LÓGICA PRINCIPAL ---

document.addEventListener("DOMContentLoaded", function () {
  // --- INSTANCIAS DE MODALES ---
  const modalPeriodoMonta = new bootstrap.Modal(
    document.getElementById("modalPeriodoMonta")
  );
  const modalServicio = new bootstrap.Modal(
    document.getElementById("modalServicio")
  );

  // --- FORMULARIOS ---
  const formPeriodoMonta = document.getElementById("formPeriodoMonta");
  const formServicio = document.getElementById("formServicio");

  // --- MANEJO DE LA VISTA PRINCIPAL (TABLA Y CREACIÓN) ---

  // Abrir modal para crear nuevo animal
  $("#btnNuevoPeriodo").on("click", function () {
    formPeriodoMonta.reset();
    modalPeriodoMonta.show();
  });

  // Envío del formulario- POST / CREA
  $(formPeriodoMonta).on("submit", function (e) {
    e.preventDefault();
    //  const animalId = $("#animal_id").val();
    let url = baseUrl + "api/periodos_monta";
    let method = "POST";
    /*
    if (animalId) {
      url = `${baseUrl}api/animales/${animalId}`;
    }*/

    const formData = new FormData(this);
    // envia formData a la consola para debug
    console.log(...formData);
    $.ajax({
      url: url,
      method: method,
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        modalAnimal.hide();
        Swal.fire("¡Éxito!", response.message, "success");
        $("#tablaAnimales").bootstrapTable("refresh");
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON);
      },
    });
  });

  // Acciones de los botones en la tabla (Ver, Editar, Eliminar)
  $("#tablaAnimales").on("click", "button", function () {
    const action = $(this).attr("class");
    const animalId = $(this).data("id");

    if (action.includes("btn-ver")) {
      mostrarDetalles(animalId);
    } else if (action.includes("btn-editar")) {
      editarAnimal(animalId);
    } else if (action.includes("btn-eliminar")) {
      eliminarAnimal(animalId);
    }
  });

  // Preview de la fotografía
  $("#fotografia").on("change", function () {
    if (this.files && this.files[0]) {
      const reader = new FileReader();
      reader.onload = function (e) {
        $("#fotografia-preview").attr("src", e.target.result);
      };
      reader.readAsDataURL(this.files[0]);
    }
  });

  // --- LÓGICA DE DETALLES Y REGISTROS ANIDADOS ---

  let currentAnimalIdForDetails = null; // Variable para guardar el ID del animal en vista

  // Función central para mostrar el modal de detalles
  function mostrarDetalles(animalId) {
    currentAnimalIdForDetails = animalId;
    $("#detalles-content").addClass("d-none");
    $("#detalles-loader").removeClass("d-none");
    // Asegurarse que la primera tab esté activa al abrir
    $('#animalDetailsTab button[data-bs-target="#info"]').tab("show");

    const endpoints = {
      animal: `${baseUrl}api/animales/${animalId}`,
      pesos: `${baseUrl}api/animal_pesos?animal_id=${animalId}`,
      salud: `${baseUrl}api/animal_salud?animal_id=${animalId}`,
      movimientos: `${baseUrl}api/animal_movimientos?animal_id=${animalId}`,
      ubicaciones: `${baseUrl}api/animal_ubicaciones?animal_id=${animalId}`,
    };

    const requests = Object.values(endpoints).map((url) =>
      $.ajax({
        url: url,
        method: "GET",
      })
    );

    Promise.all(requests)
      .then((responses) => {
        const [animalRes, pesosRes, saludRes, movimientosRes, ubicacionesRes] =
          responses;
        populateDetallesModal(
          animalRes.data,
          pesosRes.data,
          saludRes.data,
          movimientosRes.data,
          ubicacionesRes.data
        );
        $("#detalles-loader").addClass("d-none");
        $("#detalles-content").removeClass("d-none");
      })
      .catch((error) => {
        modalDetallesAnimal.hide();
        showErrorToast(
          error.responseJSON || {
            message: "Error cargando los detalles.",
          }
        );
      });
  }

  function populateDetallesModal(
    animal,
    pesos,
    salud,
    movimientos,
    ubicaciones
  ) {
    // Info General
    $("#detalle_identificador_titulo").text(animal.identificador);
    $("#detalle_identificador").text(animal.identificador);
    $("#detalle_sexo").text(animal.sexo);
    $("#detalle_especie").text(animal.especie);
    $("#detalle_raza").text(animal.raza || "N/A");
    $("#detalle_fecha_nacimiento").text(formatDate(animal.fecha_nacimiento));
    $("#detalle_estado").html(
      `<span class="badge bg-primary">${animal.estado}</span>`
    );
    $("#detalle_origen").text(animal.origen);
    $("#detalle_created_at").text(formatDate(animal.created_at));
    $("#detalle_fotografia").attr(
      "src",
      animal.fotografia_url
        ? `${baseUrl}${animal.fotografia_url}`
        : "https://placehold.co/300x300?text=Sin+Foto"
    );

    // Pesos
    let pesosHtml = pesos.length
      ? ""
      : '<tr><td colspan="4" class="text-center">No hay registros de peso.</td></tr>';
    pesos.forEach((p) => {
      pesosHtml += `<tr><td>${formatDate(p.fecha_peso)}</td><td>${
        p.peso_kg
      }</td><td>${p.metodo || "N/A"}</td><td>${
        p.observaciones || "N/A"
      }</td></tr>`;
    });
    $("#tablaDetallesPesos").html(pesosHtml);

    // Salud
    let saludHtml = salud.length
      ? ""
      : '<tr><td colspan="5" class="text-center">No hay eventos de salud registrados.</td></tr>';
    salud.forEach((s) => {
      saludHtml += `<tr><td>${formatDate(s.fecha_evento)}</td><td>${
        s.tipo_evento
      }</td><td>${s.diagnostico || "N/A"}</td><td>${
        s.estado
      }</td><td><button class="btn btn-xs btn-info btn-ver-salud" data-salud-id="${
        s.animal_salud_id
      }">Ver</button></td></tr>`;
    });
    $("#tablaDetallesSalud").html(saludHtml);

    // Movimientos
    let movHtml = movimientos.length
      ? ""
      : '<tr><td colspan="5" class="text-center">No hay movimientos registrados.</td></tr>';
    movimientos.forEach((m) => {
      const origen =
        [m.finca_origen_nombre, m.aprisco_origen_nombre, m.area_origen_nombre]
          .filter(Boolean)
          .join(" / ") || "Externo";
      const destino =
        [
          m.finca_destino_nombre,
          m.aprisco_destino_nombre,
          m.area_destino_nombre,
        ]
          .filter(Boolean)
          .join(" / ") || "Externo";
      movHtml += `<tr><td>${formatDate(m.fecha_mov)}</td><td>${
        m.tipo_movimiento
      }</td><td>${m.motivo}</td><td>${origen}</td><td>${destino}</td></tr>`;
    });
    $("#tablaDetallesMovimientos").html(movHtml);

    // Ubicaciones
    let ubiHtml = ubicaciones.length
      ? ""
      : '<tr><td colspan="5" class="text-center">No hay ubicaciones registradas.</td></tr>';
    ubicaciones.forEach((u) => {
      const ubicacion =
        [u.nombre_finca, u.nombre_aprisco, u.nombre_area]
          .filter(Boolean)
          .join(" / ") || "N/A";
      const estadoClass = u.estado === "ACTIVA" ? "success" : "secondary";
      ubiHtml += `<tr>
                <td>${formatDate(u.fecha_desde)}</td>
                <td>${formatDate(u.fecha_hasta)}</td>
                <td>${ubicacion}</td>
                <td>${u.motivo}</td>
                <td><span class="badge bg-${estadoClass}">${
        u.estado
      }</span></td>
            </tr>`;
    });
    $("#tablaDetallesUbicaciones").html(ubiHtml);
  }

  // Botones para abrir modales de registro desde Detalles
  $("#btnRegistrarPeso").on("click", function () {
    modalDetallesAnimal.hide();
    $("#peso_animal_id").val(currentAnimalIdForDetails);
    $("#fecha_peso").val(new Date().toISOString().slice(0, 10));
    modalRegistroPeso.show();
  });

  $("#btnRegistrarSalud").on("click", function () {
    modalDetallesAnimal.hide();
    formRegistroSalud.reset();
    $("#salud_animal_id").val(currentAnimalIdForDetails);
    $("#fecha_evento").val(new Date().toISOString().slice(0, 10));
    modalRegistroSalud.show();
  });

  $("#btnRegistrarMovimiento").on("click", function () {
    modalDetallesAnimal.hide();
    formRegistroMovimiento.reset();
    $("#movimiento_animal_id").val(currentAnimalIdForDetails);
    $("#fecha_mov").val(new Date().toISOString().slice(0, 10));
    // Cargar fincas al abrir
    populateSelect(
      '#formRegistroMovimiento select[name="finca_origen_id"]',
      `${baseUrl}api/fincas`,
      "Seleccione Finca de Origen",
      "finca_id",
      "nombre"
    );
    populateSelect(
      '#formRegistroMovimiento select[name="finca_destino_id"]',
      `${baseUrl}api/fincas`,
      "Seleccione Finca de Destino",
      "finca_id",
      "nombre"
    );
    // Limpiar selects dependientes
    $(
      '#formRegistroMovimiento select[name="aprisco_origen_id"], #formRegistroMovimiento select[name="area_origen_id"]'
    ).html('<option value="">--</option>');
    $(
      '#formRegistroMovimiento select[name="aprisco_destino_id"], #formRegistroMovimiento select[name="area_destino_id"]'
    ).html('<option value="">--</option>');
    modalRegistroMovimiento.show();
  });

  $("#btnRegistrarUbicacion").on("click", function () {
    modalDetallesAnimal.hide();
    formRegistroUbicacion.reset();
    $("#ubicacion_animal_id").val(currentAnimalIdForDetails);
    $("#fecha_desde_ubicacion").val(new Date().toISOString().slice(0, 10));
    // Cargar fincas al abrir
    populateSelect(
      '#formRegistroUbicacion select[name="finca_id"]',
      `${baseUrl}api/fincas`,
      "Seleccione Finca",
      "finca_id",
      "nombre"
    );
    // Limpiar selects dependientes
    $(
      '#formRegistroUbicacion select[name="aprisco_id"], #formRegistroUbicacion select[name="area_id"]'
    ).html('<option value="">--</option>');
    modalRegistroUbicacion.show();
  });

  // Botones 'cancelar' en los modales de registro para volver a detalles
  $(
    "#btnCancelarRegistroPeso, #btnCancelarRegistroSalud, #btnCancelarRegistroMovimiento, #btnCancelarRegistroUbicacion, #btnCerrarDetalleSalud"
  ).on("click", function () {
    // Cierra el modal actual (el atributo data-bs-dismiss se encarga) y reabre el de detalles
    if (currentAnimalIdForDetails) {
      // Se necesita un pequeño timeout para evitar problemas de superposición de modales
      setTimeout(() => modalDetallesAnimal.show(), 200);
    }
  });

  // --- LÓGICA DEL BOTÓN "VER" DE SALUD ---
  $("#tablaDetallesSalud").on("click", ".btn-ver-salud", function () {
    const saludId = $(this).data("salud-id");
    $.ajax({
      url: `${baseUrl}api/animal_salud/${saludId}`,
      method: "GET",
      success: function (response) {
        const s = response.data;
        $("#detalle_salud_fecha").text(formatDate(s.fecha_evento));
        $("#detalle_salud_tipo").text(s.tipo_evento);
        $("#detalle_salud_diagnostico").text(s.diagnostico || "N/A");
        $("#detalle_salud_severidad").text(
          s.severidad ? s.severidad.replace("_", " ") : "N/A"
        );
        $("#detalle_salud_tratamiento").text(s.tratamiento || "N/A");
        $("#detalle_salud_medicamento").text(s.medicamento || "N/A");
        $("#detalle_salud_dosis").text(s.dosis || "N/A");
        $("#detalle_salud_via").text(s.via_administracion || "N/A");
        $("#detalle_salud_costo").text(s.costo ? `Bs. ${s.costo}` : "N/A");
        $("#detalle_salud_estado").html(
          `<span class="badge bg-info">${s.estado}</span>`
        );
        $("#detalle_salud_revision").text(formatDate(s.proxima_revision));
        $("#detalle_salud_responsable").text(s.responsable || "N/A");
        $("#detalle_salud_observaciones").text(s.observaciones || "N/A");

        modalDetallesAnimal.hide();
        modalDetallesSalud.show();
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON);
      },
    });
  });

  // --- FUNCIONES Y LÓGICA PARA SELECTS DINÁMICOS ---

  /**
   * Populates a select input with data from an API endpoint.
   * @param {string} selector - The jQuery selector for the <select> element.
   * @param {string} url - The API endpoint URL.
   * @param {string} placeholder - The text for the default/placeholder option.
   * @param {string} valueField - The name of the field to use for the option value.
   * @param {string|Function} textField - The name of the field for the option text, or a function to generate it.
   */
  function populateSelect(selector, url, placeholder, valueField, textField) {
    const $select = $(selector);
    $select
      .html(`<option value="">Cargando...</option>`)
      .prop("disabled", true);

    $.ajax({
      url: url,
      method: "GET",
      success: function (response) {
        let options = `<option value="">${placeholder}</option>`;
        if (response.data && response.data.length > 0) {
          response.data.forEach((item) => {
            const text =
              typeof textField === "function"
                ? textField(item)
                : item[textField];
            options += `<option value="${item[valueField]}">${text}</option>`;
          });
        }
        $select.html(options).prop("disabled", false);
      },
      error: function () {
        $select
          .html(`<option value="">Error al cargar</option>`)
          .prop("disabled", true);
      },
    });
  }

  // Lógica para el modal de Movimientos
  $("#formRegistroMovimiento").on(
    "change",
    'select[name="finca_origen_id"]',
    function () {
      const fincaId = $(this).val();
      const $apriscoSelect = $(
        '#formRegistroMovimiento select[name="aprisco_origen_id"]'
      );
      const $areaSelect = $(
        '#formRegistroMovimiento select[name="area_origen_id"]'
      );
      $areaSelect.html('<option value="">--</option>');
      if (fincaId) {
        populateSelect(
          $apriscoSelect,
          `${baseUrl}api/apriscos?finca_id=${fincaId}`,
          "Seleccione Aprisco",
          "aprisco_id",
          "nombre"
        );
      } else {
        $apriscoSelect.html(
          '<option value="">Seleccione Finca primero</option>'
        );
      }
    }
  );

  $("#formRegistroMovimiento").on(
    "change",
    'select[name="aprisco_origen_id"]',
    function () {
      const apriscoId = $(this).val();
      const $areaSelect = $(
        '#formRegistroMovimiento select[name="area_origen_id"]'
      );
      if (apriscoId) {
        populateSelect(
          $areaSelect,
          `${baseUrl}api/areas?aprisco_id=${apriscoId}`,
          "Seleccione Área",
          "area_id",
          (item) =>
            `${item.nombre_personalizado || "Área"} (${
              item.numeracion || "S/N"
            })`
        );
      } else {
        $areaSelect.html(
          '<option value="">Seleccione Aprisco primero</option>'
        );
      }
    }
  );

  $("#formRegistroMovimiento").on(
    "change",
    'select[name="finca_destino_id"]',
    function () {
      const fincaId = $(this).val();
      const $apriscoSelect = $(
        '#formRegistroMovimiento select[name="aprisco_destino_id"]'
      );
      const $areaSelect = $(
        '#formRegistroMovimiento select[name="area_destino_id"]'
      );
      $areaSelect.html('<option value="">--</option>');
      if (fincaId) {
        populateSelect(
          $apriscoSelect,
          `${baseUrl}api/apriscos?finca_id=${fincaId}`,
          "Seleccione Aprisco",
          "aprisco_id",
          "nombre"
        );
      } else {
        $apriscoSelect.html(
          '<option value="">Seleccione Finca primero</option>'
        );
      }
    }
  );

  $("#formRegistroMovimiento").on(
    "change",
    'select[name="aprisco_destino_id"]',
    function () {
      const apriscoId = $(this).val();
      const $areaSelect = $(
        '#formRegistroMovimiento select[name="area_destino_id"]'
      );
      if (apriscoId) {
        populateSelect(
          $areaSelect,
          `${baseUrl}api/areas?aprisco_id=${apriscoId}`,
          "Seleccione Área",
          "area_id",
          (item) =>
            `${item.nombre_personalizado || "Área"} (${
              item.numeracion || "S/N"
            })`
        );
      } else {
        $areaSelect.html(
          '<option value="">Seleccione Aprisco primero</option>'
        );
      }
    }
  );

  // Lógica para el modal de Ubicaciones
  $("#formRegistroUbicacion").on(
    "change",
    'select[name="finca_id"]',
    function () {
      const fincaId = $(this).val();
      const $apriscoSelect = $(
        '#formRegistroUbicacion select[name="aprisco_id"]'
      );
      const $areaSelect = $('#formRegistroUbicacion select[name="area_id"]');
      $areaSelect.html('<option value="">--</option>');
      if (fincaId) {
        populateSelect(
          $apriscoSelect,
          `${baseUrl}api/apriscos?finca_id=${fincaId}`,
          "Seleccione Aprisco",
          "aprisco_id",
          "nombre"
        );
      } else {
        $apriscoSelect.html(
          '<option value="">Seleccione Finca primero</option>'
        );
      }
    }
  );

  $("#formRegistroUbicacion").on(
    "change",
    'select[name="aprisco_id"]',
    function () {
      const apriscoId = $(this).val();
      const $areaSelect = $('#formRegistroUbicacion select[name="area_id"]');
      if (apriscoId) {
        populateSelect(
          $areaSelect,
          `${baseUrl}api/areas?aprisco_id=${apriscoId}`,
          "Seleccione Área",
          "area_id",
          (item) =>
            `${item.nombre_personalizado || "Área"} (${
              item.numeracion || "S/N"
            })`
        );
      } else {
        $areaSelect.html(
          '<option value="">Seleccione Aprisco primero</option>'
        );
      }
    }
  );

  // --- FUNCIONES AUXILIARES DE EDICIÓN Y ELIMINACIÓN ---
  function editarAnimal(animalId) {
    $.ajax({
      url: `${baseUrl}api/animales/${animalId}`,
      method: "GET",
      success: function (response) {
        const data = response.data;
        formAnimal.reset();
        $("#animal_id").val(data.animal_id);
        $("#identificador").val(data.identificador);
        $("#sexo").val(data.sexo);
        $("#especie").val(data.especie);
        $("#raza").val(data.raza);
        $("#fecha_nacimiento").val(data.fecha_nacimiento);
        $("#estado").val(data.estado);
        $("#origen").val(data.origen);
        $("#fotografia-preview").attr(
          "src",
          data.fotografia_url
            ? `${baseUrl}${data.fotografia_url}`
            : "https://placehold.co/200x200?text=Vista+Previa"
        );
        $("#modalAnimalLabel").text("Editar Animal");
        modalAnimal.show();
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON);
      },
    });
  }

  function eliminarAnimal(animalId) {
    Swal.fire({
      title: "¿Estás seguro?",
      text: "El animal será eliminado lógicamente.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: `${baseUrl}api/animales/${animalId}`,
          method: "DELETE",
          success: function (response) {
            Swal.fire("Eliminado", response.message, "success");
            $("#tablaAnimales").bootstrapTable("refresh");
          },
          error: function (xhr) {
            showErrorToast(xhr.responseJSON);
          },
        });
      }
    });
  }
});
