📋 Documentación del Módulo: Gestión de Menú
Este documento describe los endpoints para la administración de los elementos del menú del sistema, permitiendo su creación, consulta, actualización y eliminación, así como un filtrado avanzado.

1. Listar Elementos del Menú
Función: listar()

Endpoint: GET /menus

Descripción: Devuelve una lista de elementos del menú. Permite una serie de filtros a través de parámetros query en la URL para refinar la búsqueda.

Parámetros (Query):

limit (int, opcional, por defecto 100): Número máximo de resultados a devolver.

offset (int, opcional, por defecto 0): Número de resultados a omitir para paginación.

incluirEliminados (int, opcional, 1 o 0): Si es 1, incluye los elementos borrados lógicamente.

categoria (string, opcional): Filtra los elementos por su categoría exacta.

user_level (int, opcional): Filtra los elementos accesibles hasta el nivel de usuario especificado (ej. user_level=5 devuelve todos los menús con user_level <= 5).

q (string, opcional): Búsqueda por texto. Busca coincidencias parciales en los campos nombre y url.

Respuestas Posibles
Éxito (200 OK)
{
  "value": true,
  "message": "Listado de menús obtenido correctamente.",
  "data": [
    {
      "menu_id": "uuid-menu-1",
      "categoria": "Dashboard",
      "nombre": "Inicio",
      "url": "/dashboard",
      "icono": "home-icon",
      "user_level": 0,
      "created_at": "2023-10-27 10:00:00",
      "created_by": "uuid-admin-1"
    }
  ]
}

Error (400 Bad Request): Si se proporciona un user_level inválido.

Error (500 Internal Server Error): Si ocurre un error en la base de datos.

2. Obtener un Elemento del Menú por ID
Función: mostrar()

Endpoint: GET /menus/{menu_id}

Descripción: Devuelve un único elemento del menú identificado por su menu_id.

Parámetros (URL):

menu_id (string, requerido): El UUID del elemento del menú.

Respuestas Posibles
Éxito (200 OK): Devuelve el objeto completo del menú, incluyendo campos de auditoría y borrado.

No Encontrado (404 Not Found): Si el menu_id no existe.

Error (400 Bad Request): Si no se proporciona el menu_id.

3. Crear un Nuevo Elemento del Menú
Función: crear()

Endpoint: POST /menus

Descripción: Crea un nuevo elemento en el menú.

Parámetros (Cuerpo JSON):

categoria (string, requerido)

nombre (string, requerido)

url (string, requerido): Debe ser una URL válida o una ruta relativa (ej. /perfil).

user_level (int, requerido): Nivel de acceso (0-10).

icono (string, opcional): Clase o identificador del ícono.

Respuestas Posibles
Éxito (200 OK):

{
  "value": true,
  "message": "Menú creado correctamente.",
  "data": { "menu_id": "new-uuid-menu" }
}

Error de Validación (400 Bad Request): Si faltan campos requeridos o si url o user_level son inválidos.

Conflicto (409 Conflict): Si ya existe un menú con datos que violan una restricción de unicidad en la base de datos.

4. Actualizar un Elemento del Menú
Función: actualizar()

Endpoint: POST /menus/{menu_id} (usa POST para emular PUT/PATCH).

Descripción: Actualiza uno o más campos de un elemento del menú existente.

Parámetros:

URL: menu_id (string, requerido)

Cuerpo (JSON): Un objeto con los campos a actualizar (ej. { "nombre": "Nuevo Nombre", "user_level": 3 }).

Respuestas Posibles
Éxito (200 OK):

{
  "value": true,
  "message": "Menú actualizado correctamente.",
  "data": { "updated": true }
}

Error de Validación (400 Bad Request): Si no se envía el menu_id, no se proporcionan campos para actualizar, o los datos son inválidos.

Conflicto (409 Conflict): Si la actualización causa un conflicto de unicidad.

5. Eliminar un Elemento del Menú
Función: eliminar()

Endpoint: DELETE /menus/{menu_id}

Descripción: Realiza un borrado lógico (soft delete) de un elemento del menú.

Parámetros (URL):

menu_id (string, requerido).

Respuestas Posibles
Éxito (200 OK):

{
  "value": true,
  "message": "Menú eliminado correctamente.",
  "data": { "deleted": true }
}

Error (400 Bad Request): Si falta el menu_id o el elemento ya fue eliminado.