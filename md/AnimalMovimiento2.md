📋 Documentación del Módulo: Movimientos de Animales
Este documento detalla los endpoints para registrar y consultar los movimientos de los animales, como traslados, ingresos, egresos, ventas, etc. Un "movimiento" es un evento puntual en una fecha específica.

1. Listar Movimientos
Función: listar()

Endpoint: GET /animal_movimientos

Descripción: Devuelve una lista de movimientos con un sistema de filtrado muy extenso para trazar el historial.

Parámetros (Query):

limit / offset (int, opcional): Para paginación (límite máximo 500).

incluirEliminados (int, 1 o 0): Incluye registros borrados.

animal_id (string, opcional): Filtra por animal.

tipo_movimiento (string, opcional): INGRESO, EGRESO, TRASLADO, VENTA, COMPRA, NACIMIENTO, MUERTE, OTRO.

motivo (string, opcional): TRASLADO, INGRESO, EGRESO, AISLAMIENTO, VENTA, OTRO.

estado (string, opcional): REGISTRADO, ANULADO.

desde / hasta (string YYYY-MM-DD, opcional): Rango de fecha_mov.

finca_origen_id, aprisco_origen_id, area_origen_id, recinto_id_origen (string, opcional): Filtran por la ubicación de origen exacta.

finca_destino_id, aprisco_destino_id, area_destino_id, recinto_id_destino (string, opcional): Filtran por la ubicación de destino exacta.

Respuestas Posibles
Éxito (200 OK)
{
  "value": true,
  "message": "Listado de movimientos obtenido correctamente.",
  "data": [
    {
      "animal_movimiento_id": "uuid-movimiento-1",
      "animal_id": "uuid-animal-1",
      "animal_identificador": "001",
      "fecha_mov": "2023-10-27",
      "tipo_movimiento": "TRASLADO",
      "motivo": "TRASLADO",
      "estado": "REGISTRADO",
      "finca_origen_id": "uuid-finca-1",
      "finca_origen": "Finca Principal",
      "finca_destino_id": "uuid-finca-2",
      "finca_destino": "Finca Secundaria",
      // ... más campos
    }
  ]
}

Error (400 Bad Request): Si algún parámetro de filtro es inválido.

2. Obtener Movimiento por ID
Función: mostrar()

Endpoint: GET /animal_movimientos/{animal_movimiento_id}

Descripción: Devuelve un único registro de movimiento.

Parámetros (URL):

animal_movimiento_id (string, requerido).

Respuestas Posibles
Éxito (200 OK): Devuelve el objeto del movimiento.

No Encontrado (404 Not Found): Si el ID no existe.

3. Crear un Nuevo Movimiento
Función: crear()

Endpoint: POST /animal_movimientos

Descripción: Crea un nuevo registro de movimiento. El modelo valida la jerarquía de las ubicaciones (ej. que un recinto pertenezca al área correcta) y aplica reglas según el tipo de movimiento (ej. un TRASLADO requiere origen y destino).

Parámetros (Cuerpo JSON):

animal_id (string, requerido)

fecha_mov (string YYYY-MM-DD, requerido)

tipo_movimiento (string, requerido)

motivo (string, opcional, por defecto OTRO)

estado (string, opcional, por defecto REGISTRADO)

finca_origen_id, aprisco_origen_id, area_origen_id, recinto_id_origen (string, opcional)

finca_destino_id, aprisco_destino_id, area_destino_id, recinto_id_destino (string, opcional)

costo (numeric, opcional)

documento_ref (string, opcional)

observaciones (string, opcional)

Respuestas Posibles
Éxito (201 Created): Devuelve el animal_movimiento_id del nuevo registro.

Error de Validación (400 Bad Request): Si faltan campos, las fechas son inválidas, la jerarquía de ubicaciones es incorrecta, o no se cumplen las reglas de origen/destino para el tipo de movimiento.

Conflicto (409 Conflict): Si una FK (animal, finca, etc.) no existe.

4. Actualizar un Movimiento
Función: actualizar()

Endpoint: POST /animal_movimientos/{animal_movimiento_id}

Descripción: Actualiza uno o más campos de un movimiento existente. Se aplican las mismas validaciones que en la creación.

Parámetros:

URL: animal_movimiento_id (string, requerido)

Cuerpo (JSON): Objeto con los campos a actualizar.

Respuestas Posibles
Éxito (200 OK): Confirma la actualización.

Error (400, 409): Por validaciones o conflictos.

5. Eliminar un Movimiento
Función: eliminar()

Endpoint: DELETE /animal_movimientos/{animal_movimiento_id}

Descripción: Realiza un borrado lógico (soft delete).

Parámetros (URL):

animal_movimiento_id (string, requerido).

Respuestas Posibles
Éxito (200 OK): Confirma la eliminación.

Error (400 Bad Request): Si el registro ya estaba eliminado.