# 🧯 Documentación del Módulo: Reportes de Daño

Este documento detalla los endpoints y reglas de negocio del módulo **Reportes de Daño** en ERP Ganado. Cubre lectura con filtros, creación, actualización (incluye cierre), cambio de estado y borrado lógico. Basado en `ReporteDanoModel`, `ReporteDanoController` y las rutas provistas (GET/POST/DELETE).

---

## 1) Listar Reportes

**Función (Controller):** `listar()`  
**Endpoint:** `GET /reportes_dano`  
**Descripción:** Devuelve una lista paginada de reportes. Por defecto excluye eliminados (`r.deleted_at IS NULL`). Permite múltiples filtros y devuelve nombres relacionados (finca, aprisco, área).

### Parámetros (Query)
- `limit` *(int, opcional, por defecto 100)*  
- `offset` *(int, opcional, por defecto 0)*  
- `incluirEliminados` *(int, opcional: 0|1, por defecto 0)*  
- `finca_id` *(string UUID, opcional)*  
- `aprisco_id` *(string UUID, opcional)*  
- `area_id` *(string UUID, opcional)*  
- `criticidad` *(string, opcional)* — Uno de: `BAJA`, `MEDIA`, `ALTA`.  
- `estado_reporte` *(string, opcional)* — Uno de: `ABIERTO`, `EN_PROCESO`, `CERRADO`.

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Listado de reportes obtenido correctamente.",
  "data": [
    {
      "reporte_id": "c3a8b1b2-7b8a-4e5f-9f9c-8b1a2d3c4e5f",
      "finca_id": "06fcbfc8-ffc7-4956-b99d-77d879d772b7",
      "nombre_finca": "Finca Las Palmas",
      "aprisco_id": "b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf",
      "nombre_aprisco": "Aprisco Norte",
      "area_id": "5a1f9c5d-5141-47e1-87e2-3c1304a7932a",
      "nombre_area": "Corral 1",
      "tipo_area": "LEVANTE_CEBA",
      "titulo": "Valla caída",
      "descripcion": "Se desplomó la valla perimetral por vientos fuertes",
      "criticidad": "ALTA",
      "estado_reporte": "EN_PROCESO",
      "fecha_reporte": "2025-10-02 12:05:00",
      "fecha_cierre": null,
      "created_at": "2025-10-02 12:05:00",
      "created_by": "user-uuid",
      "updated_at": null,
      "updated_by": null
    }
  ]
}
```

**Errores**  
- `400 Bad Request` si `criticidad` o `estado_reporte` no son válidos.  
- `500 Internal Server Error` ante fallos inesperados.

### Ejemplo (cURL)
```bash
# Lista general
curl -X GET 'https://tu-dominio/reportes_dano?limit=20&offset=0'

# Filtro por finca y criticidad
curl -X GET 'https://tu-dominio/reportes_dano?finca_id=06fcbfc8-ffc7-4956-b99d-77d879d772b7&criticidad=ALTA'

# Filtro por estado
curl -X GET 'https://tu-dominio/reportes_dano?estado_reporte=EN_PROCESO'
```

---

## 2) Obtener Reporte por ID

**Función:** `mostrar($params)`  
**Endpoint:** `GET /reportes_dano/{reporte_id}`  
**Descripción:** Devuelve los detalles del reporte incluyendo nombres de finca/aprisco/área y campos de auditoría.

### Parámetros (URL)
- `reporte_id` *(string UUID, requerido)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Reporte encontrado.",
  "data": {
    "reporte_id": "c3a8b1b2-7b8a-4e5f-9f9c-8b1a2d3c4e5f",
    "finca_id": "06fcbfc8-ffc7-4956-b99d-77d879d772b7",
    "nombre_finca": "Finca Las Palmas",
    "aprisco_id": "b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf",
    "nombre_aprisco": "Aprisco Norte",
    "area_id": "5a1f9c5d-5141-47e1-87e2-3c1304a7932a",
    "nombre_area": "Corral 1",
    "tipo_area": "LEVANTE_CEBA",
    "titulo": "Valla caída",
    "descripcion": "Se desplomó la valla perimetral por vientos fuertes",
    "criticidad": "ALTA",
    "estado_reporte": "EN_PROCESO",
    "fecha_reporte": "2025-10-02 12:05:00",
    "reportado_por": "user-uuid",
    "solucionado_por": null,
    "fecha_cierre": null,
    "created_at": "2025-10-02 12:05:00",
    "created_by": "user-uuid",
    "updated_at": null,
    "updated_by": null,
    "deleted_at": null,
    "deleted_by": null
  }
}
```

**No encontrado (404 Not Found)**  
```json
{ "value": false, "message": "Reporte no encontrado.", "data": null }
```

**Error de parámetro (400 Bad Request)**  
```json
{ "value": false, "message": "Parámetro reporte_id es obligatorio.", "data": null }
```

### Ejemplo (cURL)
```bash
curl -X GET 'https://tu-dominio/reportes_dano/c3a8b1b2-7b8a-4e5f-9f9c-8b1a2d3c4e5f'
```

---

## 3) Crear Reporte

**Función:** `crear()`  
**Endpoint:** `POST /reportes_dano`  
**Descripción:** Crea un reporte con título y descripción obligatorios. Ubicación (finca/aprisco/área) es opcional pero válida si se envía. Se aplica zona horaria y contexto de auditoría.

### Cuerpo (JSON)
- `titulo` *(string, **requerido**)*  
- `descripcion` *(string, **requerido**)*  
- `criticidad` *(string, opcional, por defecto `'BAJA'`)* — Uno de: `BAJA` | `MEDIA` | `ALTA`  
- `estado_reporte` *(string, opcional, por defecto `'ABIERTO'`)* — Uno de: `ABIERTO` | `EN_PROCESO` | `CERRADO`  
- `finca_id` *(string UUID, opcional)*  
- `aprisco_id` *(string UUID, opcional)*  
- `area_id` *(string UUID, opcional)*  
- `reportado_por` *(string UUID, opcional)* — Usuario que reporta (si no, se usa el actor).

### Validaciones y reglas
- Si se envían `finca_id`, `aprisco_id` o `area_id`, **deben existir** y no estar eliminados (FK).  
- Validación de catálogos: `criticidad` y `estado_reporte`.  
- `fecha_reporte` se fija con `now` (ajustada a TZ).  
- `created_by` se setea con el actor (o `reportado_por` si se provee).

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Reporte creado correctamente.",
  "data": { "reporte_id": "uuid-generado" }
}
```

**Errores comunes**
```json
{ "value": false, "message": "Faltan campos requeridos: titulo, descripcion.", "data": null }
```
```json
{ "value": false, "message": "La finca no existe o está eliminada.", "data": null }
```
```json
{ "value": false, "message": "El aprisco no existe o está eliminado.", "data": null }
```
```json
{ "value": false, "message": "El área no existe o está eliminada.", "data": null }
```
```json
{ "value": false, "message": "criticidad inválida. Use: BAJA, MEDIA, ALTA", "data": null }
```
```json
{ "value": false, "message": "estado_reporte inválido. Use: ABIERTO, EN_PROCESO, CERRADO", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/reportes_dano'   -H 'Content-Type: application/json'   -d '{
    "titulo": "Fuga de agua",
    "descripcion": "Se detecta fuga en bebederos del corral 1",
    "criticidad": "MEDIA",
    "estado_reporte": "ABIERTO",
    "aprisco_id": "b7c7b9a8-1b83-4e1f-8a71-0c2f7a8620bf",
    "area_id": "5a1f9c5d-5141-47e1-87e2-3c1304a7932a",
    "reportado_por": "user-uuid"
  }'
```

---

## 4) Actualizar Reporte (campos explícitos)

**Función:** `actualizar($params)`  
**Endpoint:** `POST /reportes_dano/{reporte_id}`  
**Descripción:** Actualiza cualquier combinación de: `finca_id`, `aprisco_id`, `area_id`, `titulo`, `descripcion`, `criticidad`, `estado_reporte`, `solucionado_por`, `fecha_cierre`. Solo modifica los campos enviados.

### Parámetros (URL)
- `reporte_id` *(string UUID, requerido)*

### Cuerpo (JSON — todos opcionales)
- `finca_id` *(string UUID | null)*  
- `aprisco_id` *(string UUID | null)*  
- `area_id` *(string UUID | null)*  
- `titulo` *(string)*  
- `descripcion` *(string)*  
- `criticidad` *(string: `BAJA`|`MEDIA`|`ALTA`)*  
- `estado_reporte` *(string: `ABIERTO`|`EN_PROCESO`|`CERRADO`)*  
- `solucionado_por` *(string UUID | null)*  
- `fecha_cierre` *(string fecha| null)*

### Reglas
- Si se envían IDs de relación, se validan (FK).  
- Validación de catálogos para `criticidad` y `estado_reporte`.  
- Si se pasa a `CERRADO` y no envías `fecha_cierre`, se mantiene como venga (esta autodefinición ocurre en `actualizarEstado`, pero puedes setearla aquí también si envías el campo).

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Reporte actualizado correctamente.",
  "data": { "updated": true }
}
```

**Errores comunes (400/409)**
```json
{ "value": false, "message": "No hay campos para actualizar.", "data": null }
```
```json
{ "value": false, "message": "finca_id inválido.", "data": null }
```
```json
{ "value": false, "message": "aprisco_id inválido.", "data": null }
```
```json
{ "value": false, "message": "area_id inválido.", "data": null }
```
```json
{ "value": false, "message": "criticidad inválida. Use: BAJA, MEDIA, ALTA", "data": null }
```
```json
{ "value": false, "message": "estado_reporte inválido. Use: ABIERTO, EN_PROCESO, CERRADO", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/reportes_dano/c3a8b1b2-7b8a-4e5f-9f9c-8b1a2d3c4e5f'   -H 'Content-Type: application/json'   -d '{
    "estado_reporte": "EN_PROCESO",
    "solucionado_por": "user-uuid"
  }'
```

---

## 5) Actualizar **solo** el Estado

**Función:** `actualizarEstado($params)`  
**Endpoint:** `POST /reportes_dano/{reporte_id}/estado`  
**Descripción:** Cambia únicamente `estado_reporte`. Si pasa a `CERRADO` y no envías `fecha_cierre`, se fija automáticamente a `now`. Puede registrar `solucionado_por`.

### Parámetros (URL)
- `reporte_id` *(string UUID, requerido)*

### Cuerpo (JSON)
- `estado_reporte` *(string, **requerido**: `ABIERTO` | `EN_PROCESO` | `CERRADO`)*  
- `solucionado_por` *(string UUID, opcional)*  
- `fecha_cierre` *(string fecha, opcional)* — Si no se envía y el estado es `CERRADO`, se fijará a `now`.

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Estado del reporte actualizado correctamente.",
  "data": { "updated": true }
}
```

**Errores (400)**
```json
{ "value": false, "message": "El campo estado_reporte es obligatorio.", "data": null }
```
```json
{ "value": false, "message": "estado_reporte inválido. Use: ABIERTO, EN_PROCESO, CERRADO", "data": null }
```

**Error (500 Internal Server Error)**

### Ejemplo (cURL)
```bash
curl -X POST 'https://tu-dominio/reportes_dano/c3a8b1b2-7b8a-4e5f-9f9c-8b1a2d3c4e5f/estado'   -H 'Content-Type: application/json'   -d '{ "estado_reporte": "CERRADO", "solucionado_por": "user-uuid" }'
```

---

## 6) Eliminar Reporte (borrado lógico)

**Función:** `eliminar($params)`  
**Endpoint:** `DELETE /reportes_dano/{reporte_id}`  
**Descripción:** Marca `deleted_at` y `deleted_by`. No elimina físicamente.

### Parámetros (URL)
- `reporte_id` *(string UUID, requerido)*

### Respuestas
**Éxito (200 OK)**
```json
{
  "value": true,
  "message": "Reporte eliminado correctamente.",
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
curl -X DELETE 'https://tu-dominio/reportes_dano/c3a8b1b2-7b8a-4e5f-9f9c-8b1a2d3c4e5f'
```

---

## Modelo de Datos (Tabla `reportes_dano`)

| Campo            | Tipo                        | Notas                                                                 |
|------------------|-----------------------------|-----------------------------------------------------------------------|
| `reporte_id`     | `CHAR(36)` (UUID)           | **PK**                                                                |
| `finca_id`       | `CHAR(36)` (UUID) \| `NULL` | **FK** → `fincas.finca_id`                                            |
| `aprisco_id`     | `CHAR(36)` (UUID) \| `NULL` | **FK** → `apriscos.aprisco_id`                                        |
| `area_id`        | `CHAR(36)` (UUID) \| `NULL` | **FK** → `areas.area_id`                                              |
| `titulo`         | `VARCHAR(150)`              | **Requerido**                                                         |
| `descripcion`    | `TEXT`                      | **Requerido**                                                         |
| `criticidad`     | `ENUM('BAJA','MEDIA','ALTA')` | Por defecto `'BAJA'`                                                 |
| `estado_reporte` | `ENUM('ABIERTO','EN_PROCESO','CERRADO')` | Por defecto `'ABIERTO'`                                 |
| `fecha_reporte`  | `DATETIME`                  | Seteada por `nowWithAudit()`                                          |
| `reportado_por`  | `CHAR(36)` \| `NULL`        | UUID del usuario que reporta                                          |
| `solucionado_por`| `CHAR(36)` \| `NULL`        | UUID del usuario que cierra                                           |
| `fecha_cierre`   | `DATETIME` \| `NULL`        | Fijada al cerrar si no se envía                                       |
| `created_at`     | `DATETIME`                  | Auditoría                                                             |
| `created_by`     | `CHAR(36)` \| `NULL`        | Auditoría                                                             |
| `updated_at`     | `DATETIME` \| `NULL`        | Auditoría                                                             |
| `updated_by`     | `CHAR(36)` \| `NULL`        | Auditoría                                                             |
| `deleted_at`     | `DATETIME` \| `NULL`        | Borrado lógico                                                        |
| `deleted_by`     | `CHAR(36)` \| `NULL`        | Borrado lógico                                                        |

> **Índices sugeridos:**
> - `INDEX (finca_id)`, `INDEX (aprisco_id)`, `INDEX (area_id)` para joins y filtros.  
> - `INDEX (criticidad)`, `INDEX (estado_reporte)`, `INDEX (fecha_reporte)` para consultas por prioridad/estado/recientes.

---

## Reglas de Auditoría y TZ

- `ClientEnvironmentInfo::applyAuditContext($db, $userId)` y `TimezoneManager::applyTimezone()` antes de insertar/actualizar/eliminar.  
- `fecha_reporte`, `created_at` y otras marcas de tiempo usan `getCurrentDatetime()` ajustada a la TZ activa.  
- `created_by/updated_by/deleted_by` se fijan con el actor; si no hay sesión, se usa un UUID local o `reportado_por` cuando aplica.

---

## Códigos de Estado HTTP

- `200 OK` — Operación exitosa.  
- `400 Bad Request` — Parámetros inválidos/faltantes.  
- `404 Not Found` — Recurso no encontrado.  
- `409 Conflict` — Violaciones de integridad referencial (FK) u otras reglas.  
- `500 Internal Server Error` — Error inesperado.

---

## Rutas Registradas

```php
$router->get('/reportes_dano',                     ['controlador' => ReporteDanoController::class, 'accion' => 'listar']);
$router->get('/reportes_dano/{reporte_id}',        ['controlador' => ReporteDanoController::class, 'accion' => 'mostrar']);
$router->post('/reportes_dano',                    ['controlador' => ReporteDanoController::class, 'accion' => 'crear']);
$router->post('/reportes_dano/{reporte_id}',       ['controlador' => ReporteDanoController::class, 'accion' => 'actualizar']);
$router->post('/reportes_dano/{reporte_id}/estado',['controlador' => ReporteDanoController::class, 'accion' => 'actualizarEstado']);
$router->delete('/reportes_dano/{reporte_id}',     ['controlador' => ReporteDanoController::class, 'accion' => 'eliminar']);
```

---

## Ejemplos Rápidos

```bash
# Crear
curl -X POST 'https://tu-dominio/reportes_dano' -H 'Content-Type: application/json'   -d '{ "titulo":"Valla caída", "descripcion":"Vientos fuertes derribaron la valla", "criticidad":"ALTA" }'

# Listar por estado y criticidad
curl -X GET 'https://tu-dominio/reportes_dano?estado_reporte=EN_PROCESO&criticidad=ALTA'

# Actualizar solo estado (cierre automático de fecha)
curl -X POST 'https://tu-dominio/reportes_dano/{uuid}/estado' -H 'Content-Type: application/json'   -d '{ "estado_reporte":"CERRADO", "solucionado_por":"user-uuid" }'

# Eliminar (soft)
curl -X DELETE 'https://tu-dominio/reportes_dano/{uuid}'
```
