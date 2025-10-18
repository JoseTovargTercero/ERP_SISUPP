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
  <div class="btn-group gap-1">
      <button class="btn btn-info btn-sm btn-ver" data-id="${value}" title="Ver Detalles">
          <i class="mdi mdi-eye"></i>
      </button>
     
      ${
        row.estado_periodo === "ABIERTO"
          ? `
      <button class="btn btn-success btn-sm btn-editar" data-id="${value}" title="Agregar servicio">
          <i class="mdi mdi-pencil-plus"></i>
      </button>

      <button class="btn btn-warning btn-sm btn-bloquear" data-id="${value}" title="Cerrar Periodo">
          <i class="mdi mdi-lock-check-outline"></i>
      </button>
      <button class="btn btn-danger btn-sm btn-eliminar" data-id="${value}" title="Eliminar">
          <i class="mdi mdi-delete"></i>
      </button>`
          : ""
      }

  </div>`;
};

/**
 * Desactiva la navegacion por columnas
 */

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
    new bootstrap.Tab(document.querySelector("#paso1-tab")).show();
    modalPeriodoMonta.show();
  });

  // Registrar periodo de monta
  $(formPeriodoMonta).on("submit", function (e) {
    e.preventDefault();
    let url = baseUrl + "api/periodos_monta";
    let method = "POST";

    const formData = new FormData(this);
    $.ajax({
      url: url,
      method: method,
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        modalPeriodoMonta.hide();
        Swal.fire("¡Éxito!", response.message, "success");
        $("#tablaPeriodosMonta").bootstrapTable("refresh");
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON);
      },
    });
  });

  // Registrar monta
  $(formServicio).on("submit", function (e) {
    e.preventDefault();
    let url = baseUrl + "api/montas";
    let method = "POST";

    const formData = new FormData(this);
    // envia formData a la consola para debug
    $.ajax({
      url: url,
      method: method,
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        modalServicio.hide();
        Swal.fire("¡Éxito!", response.message, "success");
        $("#tablaPeriodosMonta").bootstrapTable("refresh");
      },
      error: function (xhr) {
        showErrorToast(xhr.responseJSON);
      },
    });
  });

  // Cargar animales por sexo
  populateSelect(
    '#formPeriodoMonta select[name="hembra_id"]',
    `${baseUrl}api/animales?sexo=HEMBRA`,
    "Seleccione Animal (Hembra)",
    "animal_id",
    (item) => `${item.identificador} - (${item.raza || "N/A"})`
  );

  populateSelect(
    '#formPeriodoMonta select[name="verraco_id"]',
    `${baseUrl}api/animales?sexo=MACHO`,
    "Seleccione Animal (MACHO)",
    "animal_id",
    (item) => `${item.identificador} - (${item.raza || "N/A"})`
  );

  // Acciones de los botones en la tabla (Ver, Editar, Eliminar)
  $("#tablaPeriodosMonta").on("click", "button", function () {
    const action = $(this).attr("class");
    const animalId = $(this).data("id");

    if (action.includes("btn-editar")) {
      agregarSevicio(animalId);
    } else if (action.includes("btn-bloquear")) {
      cerrarPeriodo(animalId);
    }
  });

  // --- LÓGICA DE DETALLES Y REGISTROS ANIDADOS ---
  let verificacionGeneticaParentesco = false;

  // ======================================
  // SVG base
  // ======================================
  let width = 600,
    height = 400;
  let vis = d3
    .select("#viz")
    .append("svg")
    .attr("width", width)
    .attr("height", height)
    .append("g")
    .attr("transform", "translate(60, 20)");

  // ======================================
  // Layout del árbol
  // ======================================
  let tree = d3.layout
    .tree()
    .size([width - 150, height - 100])
    // 👇 aumenta la separación entre nodos hermanos y ramas
    .separation(function (a, b) {
      return a.parent === b.parent ? 1.8 : 2.5;
    });

  // Generador de líneas diagonales
  let diagonal = d3.svg.diagonal().projection(function (d) {
    return [d.x, -d.y + 320];
  });

  // VERIFICAR CRUCE
  function verificarArbolGenealogico(verraco, hembra) {
    console.log("Verraco:", verraco, "Hembra:", hembra);

    if (verraco && hembra) {
      $.ajax({
        url: `${baseUrl}api/animales-verificar-cruce`,
        method: "POST",
        contentType: "application/json",
        dataType: "json",
        data: JSON.stringify({
          animal_a: verraco,
          animal_b: hembra,
        }),
        success: function (response) {
          console.log("Respuesta del servidor:" + response);

          // TODO: logica
        },
        error: function (xhr) {
          console.error("Error AJAX:", xhr);
          showErrorToast(
            xhr.responseJSON?.message || "Error inesperado al verificar cruce."
          );
        },
      });
    }
  }

  $(
    '#formPeriodoMonta select[name="verraco_id"], #formPeriodoMonta select[name="hembra_id"]'
  ).on("change", function () {
    const verracoId = $('#formPeriodoMonta select[name="verraco_id"]').val();
    const hembraId = $('#formPeriodoMonta select[name="hembra_id"]').val();
    verificarArbolGenealogico(verracoId, hembraId);
  });

  function ArbolGenealogicos(verraco, hembra) {
    $.ajax({
      url: `${baseUrl}api/animales/arbol/1`,
      method: "POST",
      contentType: "application/json",
      dataType: "json",
      data: JSON.stringify({
        animal_id: verraco,
        animal_id_2: hembra,
      }),
      success: function (response) {
        // imprime response como texto con json.stringify
        // document.write("<pre>" + JSON.stringify(response, null, 2) + "</pre>");

        let treeData = {
          name: "Cerdo base",
          info: "... todos los datos del json original",
          children: [
            {
              name: "Padre",
              info: "cerdo comprado",
            }, // sin padres conocidos (abuelos de cerdo base)
            {
              name: "Madre",
              children: [
                // Madre con padres conocidos
                {
                  name: "A311",
                }, // abuelos de cerdo base
                {
                  name: "A312",
                  children: [
                    // abuelos de cerdo base
                    {
                      name: "A411",
                      info: "",
                    }, // bisabuelo de cerdo base
                  ],
                },
              ],
            },
          ],
        };

        // ======================================
        // Generar nodos y enlaces
        // ======================================
        // let nodes = tree.nodes(treeData); // Informacion del árbol
        let nodes = tree.nodes(response.data[0]); // Informacion del árbol
        let links = tree.links(nodes);

        // Enlaces (líneas)
        vis
          .selectAll("path.link")
          .data(links)
          .enter()
          .append("path")
          .attr("class", "link")
          .attr("d", diagonal)
          .style("fill", "none")
          .style("stroke", "#ccc")
          .style("stroke-width", "1.5px");

        // Nodos
        let node = vis
          .selectAll("g.node")
          .data(nodes)
          .enter()
          .append("g")
          .attr("class", "node")
          .attr("transform", function (d) {
            return "translate(" + d.x + "," + (-d.y + 320) + ")";
          });

        node
          .append("circle")
          .attr("r", 4)
          .style("fill", "#fff")
          .style("stroke", "#000");

        // Texto de los nodos
        node
          .append("foreignObject")
          .attr("x", (d) => (d.children ? -20 : 5))
          .attr("y", -8)
          .attr("width", 100)
          .attr("height", 40)
          .append("xhtml:div")
          .attr("class", "node-label")
          .html((d) => `<small>ABUELO</small><br>${d.name}`);

        // ======================================
        // Colorear nodo A311
        // ======================================
        vis
          .selectAll("g.node")
          .filter(function (d) {
            return d.name === "A311";
          })
          .select("circle")
          .style("fill", "red");
      },
      error: function (xhr) {
        console.error("Error AJAX:", xhr);
        showErrorToast(
          xhr.responseJSON?.message || "Error inesperado al verificar cruce."
        );
      },
    });
  }
  ArbolGenealogicos(
    "j01ae43e-324d-4d90-93f9-01f5316d1a44",
    "r89b4e6a-b25d-4fcd-952e-f3b36cb2786f"
  );

  let currentAnimalIdForDetails = null; // Variable para guardar el ID del animal en vista

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

  // --- FUNCIONES AUXILIARES DE EDICIÓN Y ELIMINACIÓN ---
  function agregarSevicio(animalId) {
    formServicio.reset();
    $("#periodo_id").val(animalId);
    //  $("#animal_id").val(data.animal_id);
    modalServicio.show();
  }

  /*
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
            $("#tablaPeriodosMonta").bootstrapTable("refresh");
          },
          error: function (xhr) {
            showErrorToast(xhr.responseJSON);
          },
        });
      }
    });
  }*/

  // Cerrar periodo de monta
  function cerrarPeriodo(periodo) {
    Swal.fire({
      title: "¿Estás seguro?",
      text: "El periodo de monta se cerrará y no podrá ser modificado.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      cancelButtonColor: "#3085d6",
      confirmButtonText: "Sí, cerrar",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: `${baseUrl}api/periodos_monta/${periodo}/cerrar`,
          method: "POST",
          success: function (response) {
            console.log("RESPONSE " + response); // 👀 Mira qué llega
            Swal.fire("Cerrado", response.mensaje, "success");
            $("#tablaPeriodosMonta").bootstrapTable("refresh");
          },
          error: function (xhr) {
            showErrorToast(xhr.responseJSON);
          },
        });
      }
    });
  }
});
