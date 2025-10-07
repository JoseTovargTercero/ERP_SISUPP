# 🏠 Documentación del Módulo: Recintos

Este documento define **endpoints**, **entradas/salidas**, **filtros**, **validaciones** y **ejemplos** para el módulo **Recintos** del sistema ERP_GANADO.

> **Convenciones**
> - Respuesta estándar: `{"value": boolean, "message": string, "data": any}`.
> - Fechas/hora: `YYYY-MM-DD` y `YYYY-MM-DD HH:MM:SS`.
> - IDs: **UUIDv4**.
> - Eliminación: **lógica** (soft delete).
> - Auditoría: `ClientEnvironmentInfo` y `TimezoneManager` aplican zona horaria y contexto.

---

## 📌 Rutas
```php
// endpoints de recintos
$router->get('/recintos',                      ['controlador' => RecintoController::class, 'accion' => 'listar']);
$router->get('/recintos/{recinto_id}',         ['controlador' => RecintoController::class, 'accion' => 'mostrar']);
$router->post('/recintos',                     ['controlador' => RecintoController::class, 'accion' => 'crear']);
$router->post('/recintos/{recinto_id}',        ['controlador' => RecintoController::class, 'accion' => 'actualizar']);
$router->post('/recintos/{recinto_id}/estado', ['controlador' => RecintoController::class, 'accion' => 'actualizarEstado']);
$router->delete('/recintos/{recinto_id}',      ['controlador' => RecintoController::class, 'accion' => 'eliminar']);
```

---

## 🧩 Entidad `recintos` (campos principales)

| Campo              | Tipo          | Reglas / Comentarios |
|--------------------|---------------|-----------------------|
| `recinto_id`       | UUID          | PK (servidor) |
| `area_id`          | UUID          | **Requerido**. Debe existir en `areas` (FK) |
| `codigo_recinto`   | STRING        | **Autogenerado por área**: `rec_01`, `rec_02`, … (máximo actual + 1) |
| `capacidad`        | INT/NULL      | ≥ 0 o `null` |
| `estado`           | ENUM          | **ACTIVO** \| **INACTIVO** (default **ACTIVO**) |
| `observaciones`    | TEXT/NULL     | Opcional |
| `created_at/by`    | DATETIME/UUID | Auditoría |
| `updated_at/by`    | DATETIME/UUID | Auditoría |
| `deleted_at/by`    | DATETIME/UUID | Soft delete |

> `codigo_recinto` se deriva con `MAX(CAST(SUBSTRING_INDEX(codigo_recinto, '_', -1) AS UNSIGNED))` por `area_id`. Incluye un **reintento** ante colisión UNIQUE.

---

## 🔎 Listar recintos
### GET `/recintos`
**Query params** (opcionales):
- `limit` (int, default 100)
- `offset` (int, default 0)
- `incluirEliminados` (0|1, default 0)
- `area_id` (UUID)
- `estado` (**ACTIVO|INACTIVO**)
- `codigo` (string, ej. `rec_01` — coincidencia exacta en el controlador actual)

**Response 200**
```json
{
  "value": true,
  "message": "Listado de recintos obtenido correctamente.",
  "data": [
    {
      "recinto_id": "…",
      "area_id": "…",
      "codigo_recinto": "rec_03",
      "capacidad": 25,
      "estado": "ACTIVO",
      "observaciones": "OK",
      "created_at": "2025-09-21 10:33:00",
      "created_by": "…",
      "updated_at": null,
      "updated_by": null
    }
  ]
}
```

**Errores**
- 400 `{"value":false,"message":"estado inválido. Use: ACTIVO, INACTIVO","data":null}`
- 500 `{"value":false,"message":"Error al listar recintos: …","data":null}`

---

## 📄 Mostrar un recinto
### GET `/recintos/{recinto_id}`

**Response 200**
```json
{
  "value": true,
  "message": "Recinto encontrado.",
  "data": {
    "recinto_id": "…",
    "area_id": "…",
    "codigo_recinto": "rec_01",
    "capacidad": 20,
    "estado": "ACTIVO",
    "observaciones": null,
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
- 404 `{"value":false,"message":"Recinto no encontrado.","data":null}`
- 500 `{"value":false,"message":"Error al obtener recinto: …","data":null}`

---

## ✳️ Crear recinto
### POST `/recintos`
**Body (JSON)**
```json
{
  "area_id": "UUID-EXISTENTE",
  "capacidad": 25,
  "estado": "ACTIVO",
  "observaciones": "texto opcional"
}
```
**Reglas**
- `area_id` **obligatorio** y debe existir (no eliminado).
- `capacidad` ≥ 0 o `null`.
- `estado` ∈ { ACTIVO, INACTIVO } (default **ACTIVO**).
- `codigo_recinto` lo genera el servidor por área (`rec_01`, `rec_02`, …).

**Response 200**
```json
{ "value": true, "message": "Recinto creado correctamente.", "data": { "recinto_id": "…" } }
```
**Errores**
- 400 `{"value":false,"message":"Falta el campo requerido: area_id.","data":null}`
- 409 `{"value":false,"message":"El área no existe o está eliminada.","data":null}`
- 409 `{"value":false,"message":"Referencia inválida a área.","data":null}`
- 500 `{"value":false,"message":"Error al crear recinto: …","data":null}`

---

## ♻️ Actualizar recinto
### POST `/recintos/{recinto_id}`
**Body (JSON) — cualquier subconjunto:**
```json
{
  "capacidad": 30,
  "estado": "INACTIVO",
  "observaciones": "en mantenimiento"
}
```
**Reglas**
- `capacidad` ≥ 0 o `null`.
- `estado` ∈ { ACTIVO, INACTIVO }.
- **No** se actualizan `area_id` ni `codigo_recinto` (trazabilidad).

**Response 200**
```json
{ "value": true, "message": "Recinto actualizado correctamente.", "data": { "updated": true } }
```
**Errores**
- 400 `{"value":false,"message":"No hay campos para actualizar.","data":null}`
- 500 `{"value":false,"message":"Error al actualizar recinto: …","data":null}`

---

## 🏷️ Actualizar **solo** el estado
### POST `/recintos/{recinto_id}/estado`
**Body (JSON)**
```json
{ "estado": "ACTIVO" }
```
Valores válidos: **ACTIVO | INACTIVO**

**Response 200**
```json
{ "value": true, "message": "Estado del recinto actualizado correctamente.", "data": { "updated": true } }
```
**Errores**
- 400 `{"value":false,"message":"El campo estado es obligatorio.","data":null}`
- 400 `{"value":false,"message":"estado inválido. Use: ACTIVO, INACTIVO","data":null}`
- 500 `{"value":false,"message":"Error al actualizar estado: …","data":null}`

---

## 🗑️ Eliminar (soft delete)
### DELETE `/recintos/{recinto_id}`

**Response 200**
```json
{ "value": true, "message": "Recinto eliminado correctamente.", "data": { "deleted": true } }
```
**Errores**
- 400 `{"value":false,"message":"No se pudo eliminar (o ya estaba eliminado).","data":null}`
- 500 `{"value":false,"message":"Error al eliminar recinto: …","data":null}`

---

## 🧪 Ejemplos rápidos

### cURL
```bash
# Listar (solo activos)
curl -s -X GET "https://tu-dominio/api/recintos?limit=50&offset=0"

# Filtrar por área y estado
curl -s -X GET "https://tu-dominio/api/recintos?area_id={AREA_UUID}&estado=ACTIVO"

# Crear
curl -s -H "Content-Type: application/json" -d '{
  "area_id":"UUID-EXISTENTE",
  "capacidad":25,
  "estado":"ACTIVO",
  "observaciones":"ninguna"
}' https://tu-dominio/api/recintos

# Actualizar
curl -s -H "Content-Type: application/json" -X POST -d '{
  "capacidad":30,
  "estado":"INACTIVO",
  "observaciones":"en mantenimiento"
}' https://tu-dominio/api/recintos/{recinto_id}

# Actualizar estado
curl -s -H "Content-Type: application/json" -X POST -d '{
  "estado":"ACTIVO"
}' https://tu-dominio/api/recintos/{recinto_id}/estado

# Eliminar
curl -s -X DELETE https://tu-dominio/api/recintos/{recinto_id}
```

### fetch (JS)
```js
// Crear
await fetch('/api/recintos', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Accept':'application/json' },
  body: JSON.stringify({
    area_id: 'UUID-EXISTENTE',
    capacidad: 25,
    estado: 'ACTIVO',
    observaciones: 'ok'
  })
}).then(r=>r.json())

// Actualizar solo estado
await fetch(`/api/recintos/${'{recinto_id}'}/estado`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Accept':'application/json' },
  body: JSON.stringify({ estado: 'INACTIVO' })
}).then(r=>r.json())
```

---

## 🧯 Validaciones & Mensajes frecuentes
- `Falta el campo requerido: area_id.`
- `La capacidad no puede ser negativa.`
- `estado inválido. Use: ACTIVO, INACTIVO`
- `El área no existe o está eliminada.`
- `Referencia inválida a área.`

> **Nota:** El modelo aplica **auditoría de entorno** y asigna `created_by/updated_by/deleted_by` con `$_SESSION['user_id']` cuando esté disponible.

---

## 🧷 Notas de implementación
- `listar()` excluye eliminados salvo `incluirEliminados=1`.
- `obtenerPorId()` retorna también campos `deleted_*`.
- `crear()` genera `codigo_recinto` por área y reintenta si hay colisión UNIQUE.
- `actualizar()` no permite cambiar `area_id` ni `codigo_recinto`.
- `actualizarEstado()` toca solo `estado` + auditoría.
- `eliminar()` marca `deleted_at/by` (soft delete).
