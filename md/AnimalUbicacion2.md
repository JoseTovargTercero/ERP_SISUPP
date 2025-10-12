📋 Documentación del Módulo: Ubicaciones de Animales
Este documento describe los endpoints para gestionar el historial de ubicaciones de un animal. Una "ubicación" representa el período de tiempo que un animal pasa en un recinto, área, aprisco o finca específica.

1. Listar Ubicaciones
Función: listar()

Endpoint: GET /animal_ubicaciones

Descripción: Devuelve una lista de registros de ubicación, con múltiples opciones de filtrado.

Parámetros (Query):

limit / offset (int, opcional): Para paginación.

incluirEliminados (int, 1 o 0): Incluye registros borrados lógicamente.

animal_id (string, opcional): Filtra por un animal específico.

finca_id / aprisco_id / area_id / recinto_id (string, opcional): Filtra por ubicación.

desde / hasta (string YYYY-MM-DD, opcional): Filtra por rango de fechas en que la ubicación estuvo activa.

soloActivas (int, 1 o 0): Si es 1, devuelve solo las ubicaciones que no tienen fecha_hasta (es decir, la ubicación actual).

Respuestas Posibles
Éxito (200 OK)
{
  "value": true,
  "message": "Listado de ubicaciones obtenido correctamente.",
  "data": [
    {
      "animal_ubicacion_id": "uuid-ubicacion-1",
      "animal_id": "uuid-animal-1",
      "animal_identificador": "001",
      "finca_id": "uuid-finca-1",
      "nombre_finca": "Finca Principal",
      "aprisco_id": null,
      "fecha_desde": "2023-10-01",
      "fecha_hasta": "2023-10-27",
      "motivo": "TRASLADO",
      "estado": "INACTIVA",
      // ... más campos
    }
  ]
}

Error (400 Bad Request): Si un parámetro de fecha es inválido.

2. Obtener Ubicación por ID
Función: mostrar()

Endpoint: GET /animal_ubicaciones/{animal_ubicacion_id}

Descripción: Devuelve un registro de ubicación específico.

Parámetros (URL):

animal_ubicacion_id (string, requerido).

Respuestas Posibles
Éxito (200 OK): Devuelve el objeto de la ubicación.

No Encontrado (404 Not Found): Si el ID no existe.

3. Obtener Ubicación Actual de un Animal
Función: actual()

Endpoint: GET /animal_ubicaciones/actual/{animal_id}

Descripción: Devuelve el registro de ubicación activo (fecha_hasta es NULL) para un animal específico.

Parámetros (URL):

animal_id (string, requerido).

Respuestas Posibles
Éxito (200 OK): Devuelve el objeto de la ubicación activa.

No Encontrado (404 Not Found): Si el animal no tiene una ubicación activa.

4. Crear un Nuevo Registro de Ubicación
Función: crear()

Endpoint: POST /animal_ubicaciones

Descripción: Crea un nuevo registro de ubicación. El sistema valida que las ubicaciones (finca, aprisco, etc.) existan y que no se cree una ubicación activa si ya existe otra para el mismo animal. El campo estado se asigna automáticamente (ACTIVA si fecha_hasta es NULL, INACTIVA en caso contrario).

Parámetros (Cuerpo JSON):

animal_id (string, requerido)

fecha_desde (string YYYY-MM-DD, requerido)

finca_id / aprisco_id / area_id / recinto_id (string, al menos uno es recomendado)

fecha_hasta (string YYYY-MM-DD, opcional)

motivo (string, opcional, por defecto OTRO): TRASLADO, INGRESO, EGRESO, AISLAMIENTO, VENTA, OTRO.

observaciones (string, opcional)

Respuestas Posibles
Éxito (200 OK): Devuelve el animal_ubicacion_id del nuevo registro.

Error de Validación (400 Bad Request): Si faltan campos o las fechas son inválidas.

Conflicto (409 Conflict): Si ya existe una ubicación activa para el animal.

5. Actualizar un Registro de Ubicación
Función: actualizar()

Endpoint: POST /animal_ubicaciones/{animal_ubicacion_id}

Descripción: Actualiza un registro de ubicación. Similar a crear, el estado se ajusta automáticamente según fecha_hasta.

Parámetros:

URL: animal_ubicacion_id (string, requerido)

Cuerpo (JSON): Objeto con los campos a actualizar.

Respuestas Posibles
Éxito (200 OK): Confirma la actualización.

Error (400, 409): Por validaciones o conflictos.

6. Cerrar una Ubicación Activa
Función: cerrar()

Endpoint: POST /animal_ubicaciones/{animal_ubicacion_id}/cerrar

Descripción: Establece la fecha_hasta a un registro de ubicación que está activo, marcándolo como INACTIVA. Es la acción recomendada antes de crear una nueva ubicación activa para un animal.

Parámetros:

URL: animal_ubicacion_id (string, requerido)

Cuerpo (JSON):

fecha_hasta (string YYYY-MM-DD, opcional): Si no se envía, se usa la fecha actual.

Respuestas Posibles
Éxito (200 OK): Devuelve los datos actualizados.

No Encontrado (404 Not Found): Si el ID no existe o la ubicación ya estaba cerrada.

7. Eliminar un Registro de Ubicación
Función: eliminar()

Endpoint: DELETE /animal_ubicaciones/{animal_ubicacion_id}

Descripción: Realiza un borrado lógico (soft delete).

Parámetros (URL):

animal_ubicacion_id (string, requerido).

Respuestas Posibles
Éxito (200 OK): Confirma la eliminación.

Error (400 Bad Request): Si el registro ya estaba eliminado.