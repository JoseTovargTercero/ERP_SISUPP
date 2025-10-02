# 🐑 Documentación del Módulo: Apriscos

Este documento define los endpoints y reglas del módulo **Apriscos** dentro de ERP Ganado. Incluye lectura con filtros, creación, actualización, cambio de estado y borrado lógico. Se basa en el `ApriscoModel`, `ApriscoController` y las rutas provistas (solo **GET/POST/DELETE**).

---

## 1) Listar Apriscos

**Función (Controller):** `listar()`  
**Endpoint:** `GET /apriscos`  
**Descripción:** Devuelve una lista paginada de apriscos. Por defecto excluye los eliminados lógicamente (`a.deleted_at IS NULL`). Permite filtrar por `finca_id` y devuelve el nombre de la finca asociada.

### Parámetros (Query)
- `limit` *(int, opcional, por defecto 100)*  
- `offset` *(int, opcional, por defecto 0)*  
- `incluirEliminados` *(int, opcional: 0|1, por defecto 0)*  
- `finca_id` *(string UUID, opcional)* — Filtra apriscos de una finca específica.

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Listado de apriscos obtenido correctamente.",
  "data": [
    {
      "aprisco_id": "b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf",
      "finca_id": "06fcbfc8-ffc7-4956-b99d-77d879d772b7",
      "nombre_finca": "Finca Las Palmas",
      "nombre": "Aprisco Norte",
      "estado": "ACTIVO",
      "created_at": "2025-10-02 11:05:00",
      "created_by": "06fcbfc8-ffc7-4956-b99d-77d879d772b7",
      "updated_at": null,
      "updated_by": null
    }
  ]
}
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
# Lista general
curl -X GET 'https://tu-dominio/apriscos?limit=20&offset=0'

# Lista por finca
curl -X GET 'https://tu-dominio/apriscos?finca_id=06fcbfc8-ffc7-4956-b99d-77d879d772b7'
```

---

## 2) Obtener Aprisco por ID

**Función:** `mostrar($params)`  
**Endpoint:** `GET /apriscos/{aprisco_id}`  
**Descripción:** Devuelve los detalles del aprisco, incluyendo `nombre_finca` (JOIN).

### Parámetros (URL)
- `aprisco_id` *(string UUID, requerido)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Aprisco encontrado.",
  "data": {
    "aprisco_id": "b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf",
    "finca_id": "06fcbfc8-ffc7-4956-b99d-77d879d772b7",
    "nombre_finca": "Finca Las Palmas",
    "nombre": "Aprisco Norte",
    "estado": "ACTIVO",
    "created_at": "2025-10-02 11:05:00",
    "created_by": "06fcbfc8-ffc7-4956-b99d-77d879d772b7",
    "updated_at": null,
    "updated_by": null,
    "deleted_at": null,
    "deleted_by": null
  }
}
```

**No encontrado (404 Not Found)**
```json
{ "value": false, "message": "Aprisco no encontrado.", "data": null }
```

**Error de parámetro (400 Bad Request)**
```json
{ "value": false, "message": "Parámetro aprisco_id es obligatorio.", "data": null }
```

### Ejemplo (cURL)
```bash
curl -X GET 'https://tu-dominio/apriscos/b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf'
```

---

## 3) Crear Aprisco

**Función:** `crear()`  
**Endpoint:** `POST /apriscos`  
**Descripción:** Crea un aprisco en una **finca** existente. Aplica zona horaria y contexto de auditoría.

### Cuerpo (JSON)
- `finca_id` *(string UUID, **requerido**)* — Debe existir y no estar eliminada.  
- `nombre` *(string, **requerido**)*  
- `estado` *(string, opcional, por defecto `'ACTIVO'`)* — Valores permitidos: `'ACTIVO' | 'INACTIVO'`.

### Validaciones y reglas
- Verificación previa: `fincaExiste(finca_id)`; si falla → **400/409** según escenario.  
- Restricción recomendada: **unicidad por (finca_id, nombre)** para evitar duplicados en la misma finca.  
- Manejo de errores por **clave foránea** y **duplicados**: respuestas con mensajes claros.

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Aprisco creado correctamente.",
  "data": { "aprisco_id": "uuid-generado" }
}
```

**Error de validación (400 Bad Request)**
```json
{ "value": false, "message": "Faltan campos requeridos: finca_id, nombre.", "data": null }
```

**Conflicto / Foránea (409 Conflict)**
```json
{ "value": false, "message": "La finca especificada no existe o está eliminada.", "data": null }
```
o
```json
{ "value": false, "message": "Ya existe un aprisco con ese nombre en la misma finca.", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/apriscos'   -H 'Content-Type: application/json'   -d '{
    "finca_id": "06fcbfc8-ffc7-4956-b99d-77d879d772b7",
    "nombre": "Aprisco Norte",
    "estado": "ACTIVO"
  }'
```

---

## 4) Actualizar Aprisco (campos explícitos)

**Función:** `actualizar($params)`  
**Endpoint:** `POST /apriscos/{aprisco_id}`  
**Descripción:** Actualiza `finca_id`, `nombre` y/o `estado`. Solo modifica los campos enviados.

### Parámetros (URL)
- `aprisco_id` *(string UUID, requerido)*

### Cuerpo (JSON — todos opcionales)
- `finca_id` *(string UUID)* — Si se envía, debe existir y no estar eliminada.  
- `nombre` *(string)*  
- `estado` *(string: `'ACTIVO' | 'INACTIVO'`)*

### Validaciones y reglas
- Al menos **un** campo debe enviarse.  
- Si cambia `finca_id`, se valida existencia (FK).  
- Unicidad (finca_id, nombre) para evitar duplicados.  

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Aprisco actualizado correctamente.",
  "data": { "updated": true }
}
```

**Error de validación (400 Bad Request)**
```json
{ "value": false, "message": "No hay campos para actualizar.", "data": null }
```
o
```json
{ "value": false, "message": "finca_id no válido (no existe o está eliminado).", "data": null }
```

**Conflicto (409 Conflict)**
```json
{ "value": false, "message": "Conflicto de unicidad (nombre por finca).", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/apriscos/b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf'   -H 'Content-Type: application/json'   -d '{ "nombre": "Aprisco Central", "estado": "INACTIVO" }'
```

---

## 5) Actualizar **solo** el Estado

**Función:** `actualizarEstado($params)`  
**Endpoint:** `POST /apriscos/{aprisco_id}/estado`  
**Descripción:** Cambia únicamente el `estado` del aprisco.

### Parámetros (URL)
- `aprisco_id` *(string UUID, requerido)*

### Cuerpo (JSON)
- `estado` *(string, **requerido**: `'ACTIVO' | 'INACTIVO'`)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Estado del aprisco actualizado correctamente.",
  "data": { "updated": true }
}
```

**Error de validación (400 Bad Request)**
```json
{ "value": false, "message": "El campo estado es obligatorio.", "data": null }
```
o
```json
{ "value": false, "message": "Valor de estado inválido. Use 'ACTIVO' o 'INACTIVO'.", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/apriscos/b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf/estado'   -H 'Content-Type: application/json'   -d '{ "estado": "ACTIVO" }'
```

---

## 6) Eliminar Aprisco (borrado lógico)

**Función:** `eliminar($params)`  
**Endpoint:** `DELETE /apriscos/{aprisco_id}`  
**Descripción:** Marca `deleted_at` y `deleted_by`. No elimina físicamente.

### Parámetros (URL)
- `aprisco_id` *(string UUID, requerido)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Aprisco eliminado correctamente.",
  "data": { "deleted": true }
}
```

**No se pudo eliminar (400 Bad Request)**
```json
{ "value": false, "message": "No se pudo eliminar (o ya estaba eliminado).", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X DELETE 'https://tu-dominio/apriscos/b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf'
```

---

## Modelo de Datos (Tabla `apriscos`)

| Campo         | Tipo                        | Notas                                                   |
|---------------|-----------------------------|---------------------------------------------------------|
| `aprisco_id`  | `CHAR(36)` (UUID)           | **PK**                                                  |
| `finca_id`    | `CHAR(36)` (UUID)           | **FK** → `fincas.finca_id` (requerida, no eliminada)    |
| `nombre`      | `VARCHAR(120)`              | Requerido; se recomienda **UNIQUE (finca_id, nombre)**  |
| `estado`      | `ENUM('ACTIVO','INACTIVO')` | Por defecto `'ACTIVO'`                                  |
| `created_at`  | `DATETIME`                  | Seteado por `nowWithAudit()`                            |
| `created_by`  | `CHAR(36)` \| `NULL`        | UUID del actor                                          |
| `updated_at`  | `DATETIME` \| `NULL`        | Última actualización                                    |
| `updated_by`  | `CHAR(36)` \| `NULL`        | UUID del actor                                          |
| `deleted_at`  | `DATETIME` \| `NULL`        | Borrado lógico                                          |
| `deleted_by`  | `CHAR(36)` \| `NULL`        | UUID del actor                                          |

> **Índices sugeridos:**
> - `INDEX (finca_id)` para joins y filtros.  
> - `UNIQUE (finca_id, nombre)` para garantizar unicidad dentro de cada finca.  
> - `INDEX (estado)` si habrá filtros frecuentes por estado.

---

## Reglas de Auditoría y TZ

- `ClientEnvironmentInfo::applyAuditContext($db, $userId)` y `TimezoneManager::applyTimezone()` antes de insertar/actualizar/eliminar.  
- Fechas desde `getCurrentDatetime()` ajustadas a la TZ activa.  
- Fallback de `created_by/updated_by/deleted_by` cuando no hay sesión: se usa UUID local.

---

## Códigos de Estado HTTP

- `200 OK` — Operación exitosa.  
- `400 Bad Request` — Parámetros inválidos/faltantes.  
- `404 Not Found` — Recurso no encontrado.  
- `409 Conflict` — Violación de FK o unicidad (duplicados).  
- `500 Internal Server Error` — Error inesperado.

---

## Rutas Registradas

```php
$router->get('/apriscos',                   ['controlador' => ApriscoController::class, 'accion' => 'listar']);
$router->get('/apriscos/{aprisco_id}',      ['controlador' => ApriscoController::class, 'accion' => 'mostrar']);
$router->post('/apriscos',                  ['controlador' => ApriscoController::class, 'accion' => 'crear']);
$router->post('/apriscos/{aprisco_id}',     ['controlador' => ApriscoController::class, 'accion' => 'actualizar']);
$router->post('/apriscos/{aprisco_id}/estado',['controlador' => ApriscoController::class, 'accion' => 'actualizarEstado']);
$router->delete('/apriscos/{aprisco_id}',   ['controlador' => ApriscoController::class, 'accion' => 'eliminar']);
```

---

## Ejemplos Rápidos

```bash
# Crear
curl -X POST 'https://tu-dominio/apriscos' -H 'Content-Type: application/json'   -d '{ "finca_id": "{uuid-finca}", "nombre": "Aprisco A", "estado": "ACTIVO" }'

# Listar por finca
curl -X GET 'https://tu-dominio/apriscos?finca_id={uuid-finca}'

# Actualizar nombre/estado
curl -X POST 'https://tu-dominio/apriscos/{uuid-aprisco}' -H 'Content-Type: application/json'   -d '{ "nombre": "Aprisco B", "estado": "INACTIVO" }'

# Cambiar solo estado
curl -X POST 'https://tu-dominio/apriscos/{uuid-aprisco}/estado' -H 'Content-Type: application/json'   -d '{ "estado": "ACTIVO" }'

# Eliminar (soft)
curl -X DELETE 'https://tu-dominio/apriscos/{uuid-aprisco}'
```
