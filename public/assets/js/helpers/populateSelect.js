// === NUEVA FUNCIÓN populateSelect ===
/**
 * Popula un elemento <select> con datos de una URL y opcionalmente lo inicializa con Select2.
 *
 * @param {object} config - Objeto de configuración.
 * @param {string} config.selector - El selector CSS o objeto jQuery para el <select>.
 * @param {string} config.url - La URL para obtener los datos en formato JSON.
 * @param {string} config.valueField - El nombre del campo del objeto de datos para el `value` de la opción.
 * @param {string|Function} config.textField - El nombre del campo para el texto de la opción, o una función que recibe el item y retorna el texto.
 * @param {string} [config.placeholder='Seleccione una opción'] - El texto del placeholder.
 * @param {boolean} [config.useSelect2=false] - Si es `true`, inicializa el select con Select2.
 * @param {object} [config.select2Options={}] - Un objeto con opciones para pasar a Select2 (ej. { dropdownParent: $('#myModal') }).
 */
export function populateSelect({
  selector,
  url,
  valueField,
  textField,
  placeholder = 'Seleccione una opción',
  useSelect2 = false,
  select2Options = {},
}) {
  const $select = $(selector)

  if ($select.data('select2')) {
    $select.select2('destroy')
  }

  $select.html(`<option value="">Cargando...</option>`).prop('disabled', true)

  return $.ajax({
    url: url,
    method: 'GET',
    dataType: 'json',
    success: function (response) {
      let options = `<option value="">${placeholder}</option>`
      if (response && response.data && response.data.length > 0) {
        response.data.forEach((item) => {
          const text =
            typeof textField === 'function' ? textField(item) : item[textField]
          options += `<option value="${item[valueField]}">${text}</option>`
        })
      }
      $select.html(options).prop('disabled', false)

      if (useSelect2) {
        $select.select2(select2Options)
      }
    },
    error: function (jqXHR, textStatus, errorThrown) {
      console.error(
        'Error al cargar datos para el select:',
        textStatus,
        errorThrown
      )
      $select
        .html(`<option value="">Error al cargar</option>`)
        .prop('disabled', true)
    },
  })
}
