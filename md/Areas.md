# 🧭 Documentación del Módulo: Áreas

Este documento define los endpoints y reglas del módulo **Áreas** de ERP Ganado. Cubre lectura con filtros, creación, actualización, cambio de estado y borrado lógico. Se basa en `AreaModel`, `AreaController` y las rutas provistas (solo **GET/POST/DELETE**).

---

## 1) Listar Áreas

**Función (Controller):** `listar()`  
**Endpoint:** `GET /areas`  
**Descripción:** Devuelve una lista paginada de áreas. Por defecto excluye eliminadas lógicamente (`a.deleted_at IS NULL`). Permite filtrar por `aprisco_id` y por `tipo_area`.

### Parámetros (Query)
- `limit` *(int, opcional, por defecto 100)*  
- `offset` *(int, opcional, por defecto 0)*  
- `incluirEliminados` *(int, opcional: 0|1, por defecto 0)*  
- `aprisco_id` *(string UUID, opcional)* — Filtro por aprisco.  
- `tipo_area` *(string, opcional)* — Uno de: `LEVANTE_CEBA`, `GESTACION`, `MATERNIDAD`, `REPRODUCCION`, `CHIQUERO`.

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Listado de áreas obtenido correctamente.",
  "data": [
    {
      "area_id": "5a1f9c5d-5141-47e1-87e2-3c1304a7932a",
      "aprisco_id": "b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf",
      "nombre_aprisco": "Aprisco Norte",
      "nombre_personalizado": "Corral 1",
      "tipo_area": "LEVANTE_CEBA",
      "numeracion": "LC-01",
      "estado": "ACTIVA",
      "created_at": "2025-10-02 11:25:00",
      "created_by": "b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf",
      "updated_at": null,
      "updated_by": null
    }
  ]
}
```

**Error (400 Bad Request)** cuando `tipo_area` no es válido.  
**Error (500 Internal Server Error)** para fallos inesperados.

### Ejemplo (cURL)
```bash
# Lista general
curl -X GET 'https://tu-dominio/areas?limit=20&offset=0'

# Filtrar por aprisco
curl -X GET 'https://tu-dominio/areas?aprisco_id=b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf'

# Filtrar por tipo de área
curl -X GET 'https://tu-dominio/areas?tipo_area=GESTACION'
```

---

## 2) Obtener Área por ID

**Función:** `mostrar($params)`  
**Endpoint:** `GET /areas/{area_id}`  
**Descripción:** Devuelve los detalles del área, incluyendo `nombre_aprisco` (JOIN).

### Parámetros (URL)
- `area_id` *(string UUID, requerido)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Área encontrada.",
  "data": {
    "area_id": "5a1f9c5d-5141-47e1-87e2-3c1304a7932a",
    "aprisco_id": "b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf",
    "nombre_aprisco": "Aprisco Norte",
    "nombre_personalizado": "Corral 1",
    "tipo_area": "LEVANTE_CEBA",
    "numeracion": "LC-01",
    "estado": "ACTIVA",
    "created_at": "2025-10-02 11:25:00",
    "created_by": "b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf",
    "updated_at": null,
    "updated_by": null,
    "deleted_at": null,
    "deleted_by": null
  }
}
```

**No encontrado (404 Not Found)**  
```json
{ "value": false, "message": "Área no encontrada.", "data": null }
```

**Error de parámetro (400 Bad Request)**  
```json
{ "value": false, "message": "Parámetro area_id es obligatorio.", "data": null }
```

### Ejemplo (cURL)
```bash
curl -X GET 'https://tu-dominio/areas/5a1f9c5d-5141-47e1-87e2-3c1304a7932a'
```

---

## 3) Crear Área

**Función:** `crear()`  
**Endpoint:** `POST /areas`  
**Descripción:** Crea un área en un **aprisco** existente. Aplica zona horaria y contexto de auditoría.

### Cuerpo (JSON)
- `aprisco_id` *(string UUID, **requerido**)* — Debe existir y no estar eliminado.  
- `tipo_area` *(string, **requerido**)* — Uno de: `LEVANTE_CEBA`, `GESTACION`, `MATERNIDAD`, `REPRODUCCION`, `CHIQUERO`.  
- `nombre_personalizado` *(string, opcional)*  
- `numeracion` *(string, opcional)*  
- `estado` *(string, opcional, por defecto `'ACTIVA'`)* — Valores permitidos: `'ACTIVA' | 'INACTIVA'`.

### Validaciones y reglas
- Verificación previa: `apriscoExiste(aprisco_id)`; si falla → **409**.  
- Validación de catálogo: `validarTipoArea(tipo_area)`; si falla → **400**.  
- Se recomienda **índice único** sobre combinación (`aprisco_id`, `tipo_area`, `numeracion`) o (`aprisco_id`, `tipo_area`, `nombre_personalizado`) según tu negocio, para evitar duplicados.

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Área creada correctamente.",
  "data": { "area_id": "uuid-generado" }
}
```

**Errores comunes**
```json
{ "value": false, "message": "Faltan campos requeridos: aprisco_id, tipo_area.", "data": null }
```
```json
{ "value": false, "message": "El aprisco especificado no existe o está eliminado.", "data": null }
```
```json
{ "value": false, "message": "tipo_area inválido. Use uno de: LEVANTE_CEBA, GESTACION, MATERNIDAD, REPRODUCCION, CHIQUERO", "data": null }
```
```json
{ "value": false, "message": "Ya existe un área con esa combinación (ver índice único).", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/areas'   -H 'Content-Type: application/json'   -d '{
    "aprisco_id": "b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf",
    "tipo_area": "GESTACION",
    "nombre_personalizado": "Gestación A",
    "numeracion": "G-01",
    "estado": "ACTIVA"
  }'
```

---

## 4) Actualizar Área (campos explícitos)

**Función:** `actualizar($params)`  
**Endpoint:** `POST /areas/{area_id}`  
**Descripción:** Actualiza `aprisco_id`, `tipo_area`, `nombre_personalizado`, `numeracion` y/o `estado`. Solo modifica los campos enviados.

### Parámetros (URL)
- `area_id` *(string UUID, requerido)*

### Cuerpo (JSON — todos opcionales)
- `aprisco_id` *(string UUID)* — Si se envía, debe existir y no estar eliminado.  
- `tipo_area` *(string)* — Validado contra el catálogo.  
- `nombre_personalizado` *(string | null)*  
- `numeracion` *(string | null)*  
- `estado` *(string: `'ACTIVA' | 'INACTIVA'`)*

### Validaciones y reglas
- Al menos **un** campo debe enviarse.  
- Si cambia `aprisco_id`, se valida existencia (FK).  
- Validación de `tipo_area`.  
- Unicidad recomendada por combinación (ver sección de modelo).

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Área actualizada correctamente.",
  "data": { "updated": true }
}
```

**Errores comunes (400/409)**
```json
{ "value": false, "message": "No hay campos para actualizar.", "data": null }
```
```json
{ "value": false, "message": "aprisco_id no válido (no existe o está eliminado).", "data": null }
```
```json
{ "value": false, "message": "tipo_area inválido. Use uno de: LEVANTE_CEBA, GESTACION, MATERNIDAD, REPRODUCCION, CHIQUERO", "data": null }
```
```json
{ "value": false, "message": "Conflicto de unicidad (ver índice único).", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/areas/5a1f9c5d-5141-47e1-87e2-3c1304a7932a'   -H 'Content-Type: application/json'   -d '{ "nombre_personalizado": "Corral 2", "estado": "INACTIVA" }'
```

---

## 5) Actualizar **solo** el Estado

**Función:** `actualizarEstado($params)`  
**Endpoint:** `POST /areas/{area_id}/estado`  
**Descripción:** Cambia únicamente el `estado` del área.

### Parámetros (URL)
- `area_id` *(string UUID, requerido)*

### Cuerpo (JSON)
- `estado` *(string, **requerido**: `'ACTIVA' | 'INACTIVA'`)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Estado del área actualizado correctamente.",
  "data": { "updated": true }
}
```

**Errores (400)**
```json
{ "value": false, "message": "El campo estado es obligatorio.", "data": null }
```
```json
{ "value": false, "message": "Valor de estado inválido. Use 'ACTIVA' o 'INACTIVA'.", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/areas/5a1f9c5d-5141-47e1-87e2-3c1304a7932a/estado'   -H 'Content-Type: application/json'   -d '{ "estado": "ACTIVA" }'
```

---

## 6) Eliminar Área (borrado lógico)

**Función:** `eliminar($params)`  
**Endpoint:** `DELETE /areas/{area_id}`  
**Descripción:** Marca `deleted_at` y `deleted_by`. No elimina físicamente.

### Parámetros (URL)
- `area_id` *(string UUID, requerido)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Área eliminada correctamente.",
  "data": { "deleted": true }
}
```

**No se pudo eliminar (400 Bad Request)**
```json
{ "value": false, "message": "No se pudo eliminar (o ya estaba eliminada).", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X DELETE 'https://tu-dominio/areas/5a1f9c5d-5141-47e1-87e2-3c1304a7932a'
```

---

## Modelo de Datos (Tabla `areas`)

| Campo                 | Tipo                        | Notas                                                                                 |
|-----------------------|-----------------------------|---------------------------------------------------------------------------------------|
| `area_id`             | `CHAR(36)` (UUID)           | **PK**                                                                                |
| `aprisco_id`          | `CHAR(36)` (UUID)           | **FK** → `apriscos.aprisco_id` (requerida, no eliminada)                              |
| `nombre_personalizado`| `VARCHAR(120)` \| `NULL`    | Opcional                                                                              |
| `tipo_area`           | `ENUM('LEVANTE_CEBA','GESTACION','MATERNIDAD','REPRODUCCION','CHIQUERO')` | Requerido |
| `numeracion`          | `VARCHAR(40)` \| `NULL`     | Opcional (útil para códigos: `LC-01`, `G-01`, etc.)                                  |
| `estado`              | `ENUM('ACTIVA','INACTIVA')` | Por defecto `'ACTIVA'`                                                                |
| `created_at`          | `DATETIME`                  | Seteado por `nowWithAudit()`                                                          |
| `created_by`          | `CHAR(36)` \| `NULL`        | UUID del actor                                                                        |
| `updated_at`          | `DATETIME` \| `NULL`        | Última actualización                                                                  |
| `updated_by`          | `CHAR(36)` \| `NULL`        | UUID del actor                                                                        |
| `deleted_at`          | `DATETIME` \| `NULL`        | Borrado lógico                                                                        |
| `deleted_by`          | `CHAR(36)` \| `NULL`        | UUID del actor                                                                        |

> **Índices sugeridos:**
> - `INDEX (aprisco_id)` para joins y filtros.  
> - `INDEX (tipo_area)` para filtros por catálogo.  
> - `UNIQUE (aprisco_id, tipo_area, numeracion)` **o** `UNIQUE (aprisco_id, tipo_area, nombre_personalizado)` según la regla de negocio de unicidad.

---

## Reglas de Auditoría y Zona Horaria

- `ClientEnvironmentInfo::applyAuditContext($db, $userId)` y `TimezoneManager::applyTimezone()` antes de insertar/actualizar/eliminar.  
- Fechas desde `getCurrentDatetime()` ajustadas a la TZ activa.  
- Fallback de `created_by/updated_by/deleted_by` cuando no hay sesión: se utiliza un UUID local.

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
$router->get('/areas',                    ['controlador' => AreaController::class, 'accion' => 'listar']);
$router->get('/areas/{area_id}',          ['controlador' => AreaController::class, 'accion' => 'mostrar']);
$router->post('/areas',                   ['controlador' => AreaController::class, 'accion' => 'crear']);
$router->post('/areas/{area_id}',         ['controlador' => AreaController::class, 'accion' => 'actualizar']);
$router->post('/areas/{area_id}/estado',  ['controlador' => AreaController::class, 'accion' => 'actualizarEstado']);
$router->delete('/areas/{area_id}',       ['controlador' => AreaController::class, 'accion' => 'eliminar']);
```

---

## Ejemplos Rápidos

```bash
# Crear
curl -X POST 'https://tu-dominio/areas' -H 'Content-Type: application/json'   -d '{ "aprisco_id": "{uuid-aprisco}", "tipo_area": "LEVANTE_CEBA", "nombre_personalizado": "Corral 1", "numeracion": "LC-01" }'

# Listar por aprisco
curl -X GET 'https://tu-dominio/areas?aprisco_id={uuid-aprisco}'

# Actualizar (nombre y estado)
curl -X POST 'https://tu-dominio/areas/{uuid-area}' -H 'Content-Type: application/json'   -d '{ "nombre_personalizado": "Corral 2", "estado": "INACTIVA" }'

# Cambiar solo estado
curl -X POST 'https://tu-dominio/areas/{uuid-area}/estado' -H 'Content-Type: application/json'   -d '{ "estado": "ACTIVA" }'

# Eliminar (soft)
curl -X DELETE 'https://tu-dominio/areas/{uuid-area}'
```
