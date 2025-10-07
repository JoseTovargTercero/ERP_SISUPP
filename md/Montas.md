# 📘 Especificación API — Montas

Guía de integración para los endpoints de **montas**. Aquí se detalla **qué recibe**, **qué devuelve**, **validaciones**, **códigos de estado**, y **ejemplos**.

> **Tabla fuente**: `montas`  
> **Clave**: `monta_id` (UUIDv4)  
> **Único**: (`periodo_id`, `numero_monta`) — no puede repetirse dentro del mismo periodo.  
> **Eliminación**: *soft delete* usando `deleted_at`/`deleted_by`.

---

## 🧱 Esquema de datos (Montas)

```json
{
  "monta_id": "string-uuid",
  "periodo_id": "string-uuid",
  "numero_monta": 1,
  "fecha_monta": "YYYY-MM-DDTHH:mm:ss", 
  "created_at": "YYYY-MM-DDTHH:mm:ss",
  "created_by": "string-uuid",
  "updated_at": "YYYY-MM-DDTHH:mm:ss|null",
  "updated_by": "string-uuid|null",
  "deleted_at": "YYYY-MM-DDTHH:mm:ss|null",
  "deleted_by": "string-uuid|null"
}
```

> **Formato de fecha**: Acepta `YYYY-MM-DD` o `YYYY-MM-DD HH:mm:ss`. El sistema persiste en `datetime` (zona horaria ajustada por backend).

---

## ✅ Reglas de validación (servidor)

- `periodo_id` (**requerido**): debe existir en `periodos_servicio` y no estar eliminado.
- `numero_monta` (**requerido** en creación): entero **>= 1**.
- `fecha_monta` (**requerido**): fecha válida. Se recomienda ISO 8601.
- **Unicidad**: no puede existir otra monta con el mismo `periodo_id` + `numero_monta`.
- **Actualización parcial**: puedes enviar solo los campos a modificar.
- **Soft delete**: no permite eliminar si ya está `deleted_at` (no falla, simplemente no afecta filas).

---

## 🔗 Endpoints

### 1) Listar montas
**GET** `/montas`

**Query params** (todos opcionales):
- `limit` (int, por defecto `100`)  
- `offset` (int, por defecto `0`)  
- `incluirEliminados` (`0|1`, por defecto `0`) — si `1`, incluye las montas con `deleted_at` no nulo
- `periodo_id` (uuid) — filtra por periodo
- `numero_monta` (int) — filtra por número de monta
- `desde` (date/datetime) — `fecha_monta >= desde`
- `hasta` (date/datetime) — `fecha_monta <= hasta`

**Response 200**
```json
{
  "value": true,
  "message": "Listado de montas obtenido correctamente.",
  "data": [
    {
      "monta_id": "uuid",
      "periodo_id": "uuid",
      "numero_monta": 1,
      "fecha_monta": "2025-10-05T00:00:00",
      "created_at": "2025-10-05T12:00:00",
      "created_by": "uuid",
      "updated_at": null,
      "updated_by": null
    }
  ]
}
```

**Errores**
- `400` parámetro inválido
- `500` error interno

---

### 2) Mostrar una monta
**GET** `/montas/{monta_id}`

**Response 200**
```json
{
  "value": true,
  "message": "Monta encontrada.",
  "data": {
    "monta_id": "uuid",
    "periodo_id": "uuid",
    "numero_monta": 2,
    "fecha_monta": "2025-10-06T09:30:00",
    "created_at": "2025-10-06T10:00:00",
    "created_by": "uuid",
    "updated_at": "2025-10-06T12:00:00",
    "updated_by": "uuid",
    "deleted_at": null,
    "deleted_by": null
  }
}
```

**Errores**
- `400` si falta `monta_id`
- `404` si no existe
- `500` error interno

---

### 3) Crear monta
**POST** `/montas`  
**Body (JSON)**
```json
{
  "periodo_id": "uuid",
  "numero_monta": 1,
  "fecha_monta": "2025-10-05 14:30:00"
}
```

**Reglas**:
- `periodo_id`: requerido y válido (FK → `periodos_servicio.periodo_id`).
- `numero_monta`: requerido, entero >= 1, único por periodo.
- `fecha_monta`: requerido, fecha/datetime válido.

**Response 200**
```json
{
  "value": true,
  "message": "Monta creada correctamente.",
  "data": { "monta_id": "uuid-nuevo" }
}
```

**Errores**
- `400` validaciones (faltan campos / formatos)
- `409` conflicto por `FK` inválida o duplicado (`periodo_id`, `numero_monta`)
- `500` error interno

---

### 4) Actualizar monta
**POST** `/montas/{monta_id}`  
**Body (JSON) — parcial**
```json
{
  "periodo_id": "uuid|opcional",
  "numero_monta": 2,
  "fecha_monta": "2025-10-06 09:30:00"
}
```

**Response 200**
```json
{
  "value": true,
  "message": "Monta actualizada correctamente.",
  "data": { "updated": true }
}
```

**Errores**
- `400` sin campos para actualizar o inválidos
- `409` duplicado o `FK` inválida
- `500` error interno

---

### 5) Eliminar monta (soft delete)
**DELETE** `/montas/{monta_id}`

**Response 200**
```json
{
  "value": true,
  "message": "Monta eliminada correctamente.",
  "data": { "deleted": true }
}
```

**Errores**
- `400` no se pudo eliminar (ya estaba eliminada o no afectó filas)
- `500` error interno

---

## 🧪 Ejemplos `curl`

**Crear**
```bash
curl -X POST http://localhost/api/montas   -H "Content-Type: application/json"   -d '{
    "periodo_id":"c9b7b6a8-d29e-4f55-9e46-1d0c7a341234",
    "numero_monta":1,
    "fecha_monta":"2025-10-05 14:30:00"
  }'
```

**Listar (por periodo, rango de fechas)**
```bash
curl "http://localhost/api/montas?periodo_id=c9b7b6a8-d29e-4f55-9e46-1d0c7a341234&desde=2025-10-01&hasta=2025-10-31&limit=50&offset=0"
```

**Mostrar**
```bash
curl "http://localhost/api/montas/5e2c4f7d-0a27-4f81-98d0-8b6e3f121234"
```

**Actualizar**
```bash
curl -X POST "http://localhost/api/montas/5e2c4f7d-0a27-4f81-98d0-8b6e3f121234"   -H "Content-Type: application/json"   -d '{"numero_monta":2,"fecha_monta":"2025-10-06 09:30:00"}'
```

**Eliminar**
```bash
curl -X DELETE "http://localhost/api/montas/5e2c4f7d-0a27-4f81-98d0-8b6e3f121234"
```

---

## 🧭 Reglas de negocio relevantes

- Un **periodo de servicio** puede tener **hasta 4 montas**. *(Recomendado validar a nivel de servicio/controlador si aplica a tu especie/regla local)*.
- Alertas separadas (fuera de este módulo) se basan en la **primera monta del periodo** para programar revisiones a **20–21 días** y proximidad a parto a **117 días**.
- El backend aplica **contexto de auditoría** y **zona horaria** automáticamente.

---

## 🧩 Códigos de estado resumidos

| Código | Caso |
|---|---|
| 200 | Operación exitosa (listar, mostrar, crear, actualizar, eliminar) |
| 400 | Parámetros o cuerpo inválido / faltan campos / sin cambios |
| 404 | Recurso no encontrado (mostrar) |
| 409 | Conflicto: FK inválida o duplicado único |
| 500 | Error interno del servidor |

---

## 📎 Notas de implementación

- `fecha_monta` se persiste como `datetime` en la BD. Si envías solo fecha, el backend complementa hora según configuración de zona horaria aplicada.
- La unicidad (`periodo_id`, `numero_monta`) está reforzada por índice único en BD **y** validación en servidor: evita carreras de concurrencia.
- `deleted_at`/`deleted_by` permiten recuperación o auditoría posterior; listar por defecto **excluye** eliminados salvo `incluirEliminados=1`.

---


