# 📋 Documentación del Módulo: Fincas

Este documento detalla los endpoints para la gestión de **fincas** dentro del ERP Ganado. Incluye lectura, creación, actualización de datos y borrado lógico.

---

## 1) Listar Fincas

**Función (Controller):** `listar()`  
**Endpoint:** `GET /fincas`  
**Descripción:** Devuelve una lista paginada de fincas. Por defecto, excluye las eliminadas lógicamente (`deleted_at IS NULL`).

### Parámetros (Query)
- `limit` *(int, opcional, por defecto 100)*: Máximo de registros a devolver.  
- `offset` *(int, opcional, por defecto 0)*: Número de registros a omitir.  
- `incluirEliminados` *(int, opcional, 0|1)*: Si es `1`, incluye registros con `deleted_at` no nulo.

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Listado de fincas obtenido correctamente.",
  "data": [
    {
      "finca_id": "06fcbfc8-ffc7-4956-b99d-77d879d772b7",
      "nombre": "Finca Demo",
      "ubicacion": "Coordenadas XYZ, Municipio ABC",
      "estado": "ACTIVA",
      "created_at": "2025-10-02 10:52:16",
      "created_by": "06fcbfc8-ffc7-4956-b99d-77d879d772b7",
      "updated_at": null,
      "updated_by": null
    }
  ]
}
```

**Error (500 Internal Server Error)**  
Si ocurre un error en base de datos u otro error inesperado.

### Ejemplo (cURL)
```bash
curl -X GET 'https://tu-dominio/fincas?limit=20&offset=0&incluirEliminados=0'
```

---

## 2) Obtener Finca por ID

**Función:** `mostrar($parametros)`  
**Endpoint:** `GET /fincas/{finca_id}`  
**Descripción:** Devuelve los detalles de una finca por su UUID.

### Parámetros (URL)
- `finca_id` *(string, requerido)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Finca encontrada.",
  "data": {
    "finca_id": "06fcbfc8-ffc7-4956-b99d-77d879d772b7",
    "nombre": "Finca Demo",
    "ubicacion": "Coordenadas XYZ, Municipio ABC",
    "estado": "ACTIVA",
    "created_at": "2025-10-02 10:52:16",
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
{ "value": false, "message": "Finca no encontrada.", "data": null }
```

**Error de parámetro (400 Bad Request)**
```json
{ "value": false, "message": "Parámetro finca_id es obligatorio.", "data": null }
```

### Ejemplo (cURL)
```bash
curl -X GET 'https://tu-dominio/fincas/06fcbfc8-ffc7-4956-b99d-77d879d772b7'
```

---

## 3) Crear Finca

**Función:** `crear()`  
**Endpoint:** `POST /fincas`  
**Descripción:** Crea una nueva finca. Se aplican zona horaria y contexto de auditoría antes de insertar.

### Cuerpo (JSON)
- `nombre` *(string, **requerido**)*
- `ubicacion` *(string, opcional, por defecto `null`)*
- `estado` *(string, opcional, por defecto `'ACTIVA'`)* — Valores permitidos: `'ACTIVA' | 'INACTIVA'`

### Validaciones y reglas
- `nombre` no vacío.  
- `estado` ∈ {`ACTIVA`, `INACTIVA`}.  
- Si existe un **índice único** por nombre en BD, se retornará **409** al intentar duplicar (el modelo ya prepara este manejo).

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Finca creada correctamente.",
  "data": { "finca_id": "uuid-generado" }
}
```

**Error de validación (400 Bad Request)**
```json
{ "value": false, "message": "Falta el campo requerido: nombre.", "data": null }
```

**Conflicto (409 Conflict)**  
Cuando `nombre` viola una restricción única.
```json
{ "value": false, "message": "Una finca con ese nombre ya existe (índice único).", "data": null }
```

**Error (500 Internal Server Error)**  
Errores inesperados en el servidor o BD.

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/fincas'   -H 'Content-Type: application/json'   -d '{
    "nombre": "Finca Las Palmas",
    "ubicacion": "Sector El Roble, Km 12",
    "estado": "ACTIVA"
  }'
```

---

## 4) Actualizar Finca (campos explícitos)

**Función:** `actualizar($parametros)`  
**Endpoint:** `POST /fincas/{finca_id}`  
**Descripción:** Actualiza **cualquier** combinación de campos permitidos: `nombre`, `ubicacion`, `estado`. Solo se modifican los enviados.

### Parámetros (URL)
- `finca_id` *(string, requerido)*

### Cuerpo (JSON — todos opcionales)
- `nombre` *(string)*
- `ubicacion` *(string | null)*
- `estado` *(string: `'ACTIVA' | 'INACTIVA'`)*

### Validaciones y reglas
- Al menos **un** campo debe enviarse.  
- `estado` debe ser válido.  
- Manejo de duplicado de `nombre` con **409** si aplica.

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Finca actualizada correctamente.",
  "data": { "updated": true }
}
```

**Error de validación (400 Bad Request)**
```json
{ "value": false, "message": "No hay campos para actualizar.", "data": null }
```

**Conflicto (409 Conflict)**
```json
{ "value": false, "message": "Ya existe otra finca con ese nombre.", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/fincas/06fcbfc8-ffc7-4956-b99d-77d879d772b7'   -H 'Content-Type: application/json'   -d '{ "ubicacion": "Nueva ubicación", "estado": "INACTIVA" }'
```

---

## 5) Actualizar **solo** el Estado

**Función:** `actualizarEstado($parametros)`  
**Endpoint:** `POST /fincas/{finca_id}/estado`  
**Descripción:** Actualiza exclusivamente el campo `estado`.

### Parámetros (URL)
- `finca_id` *(string, requerido)*

### Cuerpo (JSON)
- `estado` *(string, **requerido**: `'ACTIVA' | 'INACTIVA'`)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Estado de la finca actualizado correctamente.",
  "data": { "updated": true }
}
```

**Error de validación (400 Bad Request)**
```json
{ "value": false, "message": "El campo estado es obligatorio.", "data": null }
```

**Estado inválido (400 Bad Request)**
```json
{ "value": false, "message": "Valor de estado inválido. Use 'ACTIVA' o 'INACTIVA'.", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/fincas/06fcbfc8-ffc7-4956-b99d-77d879d772b7/estado'   -H 'Content-Type: application/json'   -d '{ "estado": "ACTIVA" }'
```

---

## 6) Eliminar Finca (borrado lógico)

**Función:** `eliminar($parametros)`  
**Endpoint:** `DELETE /fincas/{finca_id}`  
**Descripción:** Marca `deleted_at` y `deleted_by` (soft delete). No elimina físicamente el registro.

### Parámetros (URL)
- `finca_id` *(string, requerido)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Finca eliminada correctamente.",
  "data": { "deleted": true }
}
```

**No se pudo eliminar (400 Bad Request)**  
Cuando ya estaba eliminada o no cumple la condición de borrado.
```json
{ "value": false, "message": "No se pudo eliminar (o ya estaba eliminada).", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X DELETE 'https://tu-dominio/fincas/06fcbfc8-ffc7-4956-b99d-77d879d772b7'
```

---

## Modelo de Datos (Tabla `fincas`)

| Campo        | Tipo                        | Notas                                         |
|--------------|-----------------------------|-----------------------------------------------|
| `finca_id`   | `CHAR(36)` (UUID)           | **PK**                                        |
| `nombre`     | `VARCHAR(120)`              | Requerido; se recomienda índice único         |
| `ubicacion`  | `VARCHAR(255)` \| `NULL`    | Opcional                                      |
| `estado`     | `ENUM('ACTIVA','INACTIVA')` | Por defecto `'ACTIVA'`                        |
| `created_at` | `DATETIME`                  | Seteado automáticamente                       |
| `created_by` | `CHAR(36)` \| `NULL`        | UUID del actor que creó                       |
| `updated_at` | `DATETIME` \| `NULL`        | Última actualización                           |
| `updated_by` | `CHAR(36)` \| `NULL`        | UUID del actor que actualizó                   |
| `deleted_at` | `DATETIME` \| `NULL`        | Fecha de borrado lógico                       |
| `deleted_by` | `CHAR(36)` \| `NULL`        | UUID del actor que borró                      |

> **Sugerido (Índices):**
> - `UNIQUE(nombre)` si la lógica de negocio exige nombres únicos de finca.
> - Índices por `estado`, si habrá filtros frecuentes por estado.

---

## Reglas de Auditoría y Zona Horaria

- Antes de insertar/actualizar/eliminar se aplica:
  - `ClientEnvironmentInfo::applyAuditContext($db, $userId)`  
  - `TimezoneManager::applyTimezone()`  
- La fecha/hora usada proviene de `getCurrentDatetime()` ajustada a la TZ activa.  
- Para *crear*, si no hay sesión, `created_by` se setea con un UUID (fallback).  
- Para *update/delete*, se setean `updated_at/updated_by` y `deleted_at/deleted_by` respectivamente.

---

## Códigos de Estado HTTP Estándar

- `200 OK` — Operación exitosa.
- `400 Bad Request` — Parámetros inválidos o faltantes.
- `404 Not Found` — Recurso no encontrado.
- `409 Conflict` — Violación de restricción única (p. ej., `nombre` duplicado).
- `500 Internal Server Error` — Error inesperado en el servidor.

---

## Ejemplos Rápidos

### Crear → Listar → Actualizar Estado → Eliminar
```bash
# Crear
curl -X POST 'https://tu-dominio/fincas' -H 'Content-Type: application/json'   -d '{ "nombre":"Finca El Encanto", "ubicacion":"Parcela 12", "estado":"ACTIVA" }'

# Listar
curl -X GET 'https://tu-dominio/fincas?limit=10&offset=0'

# Estado
curl -X POST 'https://tu-dominio/fincas/{uuid}/estado' -H 'Content-Type: application/json'   -d '{ "estado":"INACTIVA" }'

# Eliminar (soft)
curl -X DELETE 'https://tu-dominio/fincas/{uuid}'
```

---

## Rutas Registradas

```php
$router->get('/fincas',                  ['controlador' => FincaController::class, 'accion' => 'listar']);
$router->get('/fincas/{finca_id}',       ['controlador' => FincaController::class, 'accion' => 'mostrar']);
$router->post('/fincas',                 ['controlador' => FincaController::class, 'accion' => 'crear']);
$router->post('/fincas/{finca_id}',      ['controlador' => FincaController::class, 'accion' => 'actualizar']);
$router->post('/fincas/{finca_id}/estado',['controlador' => FincaController::class, 'accion' => 'actualizarEstado']);
$router->delete('/fincas/{finca_id}',    ['controlador' => FincaController::class, 'accion' => 'eliminar']);
```

---

## Notas de Implementación

- El **UUID** se genera con `generateUUIDv4()` desde el modelo.  
- Todos los métodos usan **prepared statements** y manejo explícito de errores.  
- `listar()` ordena por `created_at DESC, nombre ASC` para dar prioridad a lo reciente.  
- `actualizar()` ignora campos no enviados y exige al menos uno válido.  
- `eliminar()` solo aplica si `deleted_at IS NULL` (idempotencia parcial en soft delete).
