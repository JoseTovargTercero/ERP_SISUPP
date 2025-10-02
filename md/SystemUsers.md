📋 Documentación del Módulo: Usuarios del Sistema
Este documento detalla los endpoints para la gestión de usuarios internos del sistema (administradores, moderadores, etc.).

1. Listar Usuarios del Sistema
Función: listar()

Endpoint: GET /system_users

Descripción: Devuelve una lista paginada de usuarios del sistema. Por defecto, excluye a los usuarios eliminados lógicamente.

Parámetros (Query):

limit (int, opcional, por defecto 100): Número máximo de registros a devolver.

offset (int, opcional, por defecto 0): Número de registros a omitir para la paginación.

incluirEliminados (int, opcional, 0 o 1): Si es 1, incluye usuarios eliminados lógicamente en la respuesta.

Respuestas Posibles
Éxito (200 OK)
{
  "value": true,
  "message": "Listado obtenido correctamente.",
  "data": [
    {
      "user_id": "uuid-user-1",
      "nombre": "Admin General",
      "email": "admin@example.com",
      "nivel": 1,
      "estado": 1,
      "created_at": "2023-10-27 10:00:00",
      "created_by": "uuid-creator-1",
      "updated_at": null,
      "updated_by": null
    }
  ]
}

Error (500 Internal Server Error): Si ocurre un error en la base de datos.

2. Obtener un Usuario por ID
Función: mostrar()

Endpoint: GET /system_users/{user_id}

Descripción: Busca y devuelve los detalles de un único usuario del sistema a partir de su UUID.

Parámetros (URL):

user_id (string, requerido): El UUID del usuario.

Respuestas Posibles
Éxito (200 OK):

{
  "value": true,
  "message": "Usuario encontrado.",
  "data": {
    "user_id": "uuid-user-1",
    "nombre": "Admin General",
    "email": "admin@example.com",
    "nivel": 1,
    "estado": 1,
    "created_at": "2023-10-27 10:00:00",
    "created_by": "uuid-creator-1",
    "updated_at": null,
    "updated_by": null,
    "deleted_at": null,
    "deleted_by": null
  }
}

No Encontrado (404 Not Found): Si el user_id no corresponde a ningún usuario.

Error de Parámetro (400 Bad Request): Si no se provee el user_id.

3. Crear un Nuevo Usuario
Función: crear()

Endpoint: POST /system_users

Descripción: Crea un nuevo usuario en el sistema. La contraseña se almacena hasheada.

Parámetros (Cuerpo JSON):

nombre (string, requerido)

email (string, requerido)

contrasena (string, requerido)

nivel (int, requerido): Nivel de permisos del usuario.

estado (int, opcional, por defecto 1): 1 para activo, 0 para inactivo.

Respuestas Posibles
Éxito (200 OK):

{
  "value": true,
  "message": "Usuario creado correctamente.",
  "data": { "user_id": "new-uuid-user" }
}

Conflicto (409 Conflict): Si el correo electrónico ya está registrado.

Error de Validación (400 Bad Request): Si faltan campos requeridos.

4. Actualizar un Usuario
Función: actualizar()

Endpoint: PUT /system_users/{user_id}

Descripción: Actualiza los datos de un usuario existente. Solo se modifican los campos enviados en el cuerpo de la solicitud. Si se envía contrasena, se hashea y actualiza.

Parámetros:

URL: user_id (string, requerido)

Cuerpo (JSON, todos opcionales):

nombre (string)

email (string)

contrasena (string): Enviar solo si se desea cambiar.

nivel (int)

estado (int)

Respuestas Posibles
Éxito (200 OK):

{
  "value": true,
  "message": "Usuario actualizado correctamente.",
  "data": { "updated": true }
}

Conflicto (409 Conflict): Si se intenta cambiar el email a uno que ya existe.

Error de Parámetro (400 Bad Request): Si no se provee el user_id o no hay campos para actualizar.

5. Eliminar un Usuario
Función: eliminar()

Endpoint: DELETE /system_users/{user_id}

Descripción: Realiza un borrado lógico (soft delete) de un usuario, estableciendo la fecha y hora actual en el campo deleted_at.

Parámetros (URL):

user_id (string, requerido).

Respuestas Posibles
Éxito (200 OK):

{
  "value": true,
  "message": "Usuario eliminado correctamente.",
  "data": { "deleted": true }
}

Error (400 Bad Request): Si falta el user_id o si el usuario ya fue eliminado.


6. Iniciar Sesión
Función: login()

Endpoint: POST /system_users/login

Descripción: Autentica a un usuario del sistema a partir de su correo y contraseña. Solo permite el acceso a usuarios activos que no hayan sido eliminados.

Parámetros (Cuerpo JSON):

email (string, requerido): El correo electrónico del usuario.

contrasena (string, requerido): La contraseña del usuario.

Respuestas Posibles
Éxito (200 OK)
{
  "value": true,
  "message": "Inicio de sesión exitoso.",
  "data": {
    "user_id": "uuid-user-1",
    "nombre": "Admin General",
    "email": "admin@example.com",
    "nivel": 1,
    "estado": 1
  }
}

Error
400 Bad Request: Si faltan los campos email o contrasena.

{
  "value": false,
  "message": "Correo y contraseña son obligatorios.",
  "data": null
}

401 Unauthorized: Si las credenciales son incorrectas, el usuario no existe, está inactivo o ha sido eliminado.

{
  "value": false,
  "message": "Credenciales inválidas o usuario inactivo.",
  "data": null
}

500 Internal Server Error: Si ocurre un error inesperado en el servidor.