# 🐖 Documentación del Módulo: Partos

Este documento define los **endpoints**, **formatos de entrada/salida**, **filtros**, **validaciones** y **ejemplos** para el módulo **Partos** del sistema ERP_GANADO.

> **Convenciones**
> - Todas las respuestas JSON siguen `{
  "value": boolean,
  "message": string,
  "data": any
}`.
> - Fechas en formato **YYYY-MM-DD**.
> - IDs son **UUIDv4**.
> - Eliminación es **lógica** (soft delete).
> - Los endpoints están registrados en el router y atendidos por `PartoController`/`PartoModel`.

---

## 📌 Rutas
```php
// endpoints de partos
$router->get('/partos',                     ['controlador' => PartoController::class, 'accion' => 'listar']);
$router->get('/partos/{parto_id}',          ['controlador' => PartoController::class, 'accion' => 'mostrar']);
$router->post('/partos',                    ['controlador' => PartoController::class, 'accion' => 'crear']);
$router->post('/partos/{parto_id}',         ['controlador' => PartoController::class, 'accion' => 'actualizar']);
$router->post('/partos/{parto_id}/estado',  ['controlador' => PartoController::class, 'accion' => 'actualizarEstado']);
$router->delete('/partos/{parto_id}',       ['controlador' => PartoController::class, 'accion' => 'eliminar']);
```
---

## 🧩 Entidad `partos` (campos principales)

| Campo               | Tipo          | Reglas / Comentarios |
|---------------------|---------------|-----------------------|
| `parto_id`          | UUID          | PK (servidor) |
| `periodo_id`        | UUID          | **Requerido**. Debe existir en `periodos_servicio` y no estar eliminado. FK |
| `fecha_parto`       | DATE          | **Requerido** (YYYY-MM-DD) |
| `crias_machos`      | INT           | ≥ 0 (default **0**) |
| `crias_hembras`     | INT           | ≥ 0 (default **0**) |
| `peso_promedio_kg`  | DECIMAL/NULL  | ≥ 0 o **NULL** |
| `estado_parto`      | ENUM          | Uno de: **NORMAL**, **DISTOCIA**, **MUERTE_PERINATAL**, **OTRO** (default **NORMAL**) |
| `observaciones`     | TEXT/NULL     | Opcional |
| `created_at/by`     | DATETIME/UUID | Auditoría |
| `updated_at/by`     | DATETIME/UUID | Auditoría |
| `deleted_at/by`     | DATETIME/UUID | Soft delete |

> En lecturas, se incluyen además `ps.hembra_id` y `ps.verraco_id` (via `LEFT JOIN periodos_servicio ps`).

---

## 🔎 Listar partos
### GET `/partos`
**Query params** (opcionales):
- `limit` (int, default 100)
- `offset` (int, default 0)
- `incluirEliminados` (0|1, default 0)
- `periodo_id` (UUID)
- `estado_parto` (**NORMAL|DISTOCIA|MUERTE_PERINATAL|OTRO**)
- `desde` (YYYY-MM-DD) → filtra `fecha_parto >=`
- `hasta` (YYYY-MM-DD) → filtra `fecha_parto <=`

**Response 200**
```json
{
  "value": true,
  "message": "Listado de partos obtenido correctamente.",
  "data": [
    {
      "parto_id": "…",
      "periodo_id": "…",
      "hembra_id": "…",
      "verraco_id": "…",
      "fecha_parto": "2025-09-21",
      "crias_machos": 3,
      "crias_hembras": 4,
      "peso_promedio_kg": 1.85,
      "estado_parto": "NORMAL",
      "observaciones": "sin novedades",
      "created_at": "2025-09-21 10:33:00",
      "created_by": "…",
      "updated_at": null,
      "updated_by": null
    }
  ]
}
```

**Errores**
- 400 `{"value":false,"message":"estado_parto inválido. Use: NORMAL, DISTOCIA, MUERTE_PERINATAL, OTRO","data":null}`
- 500 `{"value":false,"message":"Error al listar partos: …","data":null}`

---

## 📄 Mostrar un parto
### GET `/partos/{parto_id}`

**Response 200**
```json
{
  "value": true,
  "message": "Parto encontrado.",
  "data": {
    "parto_id": "…",
    "periodo_id": "…",
    "hembra_id": "…",
    "verraco_id": "…",
    "fecha_parto": "2025-09-21",
    "crias_machos": 3,
    "crias_hembras": 4,
    "peso_promedio_kg": 1.85,
    "estado_parto": "NORMAL",
    "observaciones": "sin novedades",
    "created_at": "2025-09-21 10:33:00",
    "created_by": "…",
    "updated_at": null,
    "updated_by": null,
    "deleted_at": null,
    "deleted_by": null
  }
}
```

**Errores**
- 404 `{"value":false,"message":"Parto no encontrado.","data":null}`
- 500 `{"value":false,"message":"Error al obtener parto: …","data":null}`

---

## ✳️ Crear parto
### POST `/partos`
**Body (JSON)**
```json
{
  "periodo_id": "UUID-EXISTENTE",
  "fecha_parto": "YYYY-MM-DD",
  "crias_machos": 0,
  "crias_hembras": 0,
  "peso_promedio_kg": 1.80,
  "estado_parto": "NORMAL",
  "observaciones": "opcional"
}
```
**Reglas**
- `periodo_id` y `fecha_parto` **obligatorios**.
- `crias_machos` / `crias_hembras` **≥ 0**.
- `peso_promedio_kg` **≥ 0** o **NULL**.
- `estado_parto` validado contra el ENUM real.

**Response 200**
```json
{ "value": true, "message": "Parto creado correctamente.", "data": { "parto_id": "…" } }
```
**Errores**
- 400 `{"value":false,"message":"Faltan campos requeridos: periodo_id, fecha_parto.","data":null}`
- 409 `{"value":false,"message":"El periodo de servicio no existe o está eliminado.","data":null}`
- 500 `{"value":false,"message":"Error al crear parto: …","data":null}`

---

## ♻️ Actualizar parto
### POST `/partos/{parto_id}`
**Body (JSON) — cualquier subconjunto de:**
```json
{
  "periodo_id": "UUID",
  "fecha_parto": "YYYY-MM-DD",
  "crias_machos": 2,
  "crias_hembras": 3,
  "peso_promedio_kg": 1.9,
  "estado_parto": "DISTOCIA",
  "observaciones": "texto opcional"
}
```
**Reglas**
- Si se envía `periodo_id`, debe existir (o `null` para limpiar – no recomendado).
- `crias_*` ≥ 0, `peso_promedio_kg` ≥ 0 o `null`.
- `estado_parto` conforme al ENUM.

**Response 200**
```json
{ "value": true, "message": "Parto actualizado correctamente.", "data": { "updated": true } }
```
**Errores**
- 400 `{"value":false,"message":"No hay campos para actualizar.","data":null}`
- 409 `{"value":false,"message":"Referencia inválida a periodo de servicio.","data":null}`
- 500 `{"value":false,"message":"Error al actualizar parto: …","data":null}`

---

## 🏷️ Actualizar solo el estado
### POST `/partos/{parto_id}/estado`
**Body (JSON)**
```json
{ "estado_parto": "NORMAL" }
```
Valores válidos: **NORMAL | DISTOCIA | MUERTE_PERINATAL | OTRO**

**Response 200**
```json
{ "value": true, "message": "Estado del parto actualizado correctamente.", "data": { "updated": true } }
```
**Errores**
- 400 `{"value":false,"message":"El campo estado_parto es obligatorio.","data":null}`
- 400 `{"value":false,"message":"estado_parto inválido. Use: NORMAL, DISTOCIA, MUERTE_PERINATAL, OTRO","data":null}`
- 500 `{"value":false,"message":"Error al actualizar estado: …","data":null}`

---

## 🗑️ Eliminar (soft delete)
### DELETE `/partos/{parto_id}`

**Response 200**
```json
{ "value": true, "message": "Parto eliminado correctamente.", "data": { "deleted": true } }
```
**Errores**
- 400 `{"value":false,"message":"No se pudo eliminar (o ya estaba eliminado).","data":null}`
- 500 `{"value":false,"message":"Error al eliminar parto: …","data":null}`

---

## 🧪 Ejemplos rápidos

### cURL
```bash
# Listar (últimos 50, solo activos)
curl -s -X GET "https://tu-dominio/api/partos?limit=50&offset=0"

# Filtrar por estado y rango de fechas
curl -s -X GET "https://tu-dominio/api/partos?estado_parto=NORMAL&desde=2025-01-01&hasta=2025-12-31"

# Crear
curl -s -H "Content-Type: application/json" -d '{
  "periodo_id":"UUID-EXISTENTE",
  "fecha_parto":"2025-09-21",
  "crias_machos":3,
  "crias_hembras":4,
  "peso_promedio_kg":1.85,
  "estado_parto":"NORMAL",
  "observaciones":"sin novedades"
}' https://tu-dominio/api/partos

# Actualizar
curl -s -H "Content-Type: application/json" -X POST -d '{
  "estado_parto":"DISTOCIA",
  "observaciones":"requiere control"
}' https://tu-dominio/api/partos/{parto_id}

# Actualizar estado
curl -s -H "Content-Type: application/json" -X POST -d '{
  "estado_parto":"OTRO"
}' https://tu-dominio/api/partos/{parto_id}/estado

# Eliminar
curl -s -X DELETE https://tu-dominio/api/partos/{parto_id}
```

### fetch (JS)
```js
// Crear
await fetch('/api/partos', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Accept':'application/json' },
  body: JSON.stringify({
    periodo_id: 'UUID-EXISTENTE',
    fecha_parto: '2025-09-21',
    crias_machos: 2,
    crias_hembras: 3,
    peso_promedio_kg: 1.9,
    estado_parto: 'NORMAL',
    observaciones: 'ok'
  })
}).then(r=>r.json())

// Actualizar solo estado
await fetch(`/api/partos/${'{parto_id}'}/estado`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Accept':'application/json' },
  body: JSON.stringify({ estado_parto: 'DISTOCIA' })
}).then(r=>r.json())
```

---

## 🧯 Validaciones & Mensajes frecuentes
- `Faltan campos requeridos: periodo_id, fecha_parto.`
- `Las crías no pueden ser negativas.`
- `El peso promedio no puede ser negativo.`
- `estado_parto inválido. Use: NORMAL, DISTOCIA, MUERTE_PERINATAL, OTRO`
- `El periodo de servicio no existe o está eliminado.`
- `Referencia inválida a periodo de servicio.`

> **Nota:** El modelo aplica **auditoría de entorno** (`ClientEnvironmentInfo`, `TimezoneManager`) y setea `created_by/updated_by/deleted_by` con el `$_SESSION['user_id']` disponible o un fallback.

---

## 🧷 Notas de implementación
- `listar()` excluye eliminados salvo `incluirEliminados=1`.
- `obtenerPorId()` retorna campos de auditoría y `deleted_*`.
- `crear()` usa transacción y valida FK `periodo_id`.
- `actualizar()` aplica `updated_at/by` y valida cambios parciales.
- `actualizarEstado()` modifica solo `estado_parto` + auditoría.
- `eliminar()` marca `deleted_at/by` (soft delete).
