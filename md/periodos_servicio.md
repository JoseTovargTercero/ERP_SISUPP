# API y Modelo: **Periodos de Servicio** (`periodos_servicio`)
_Versión_: 2025-10-07 15:19
_Sistema_: ERP GANADO — Módulo Reproducción

---


## Resumen
Este documento describe el **modelo PHP**, **controlador** y **endpoints REST** para gestionar los **periodos de servicio** (emparejamientos hembra–verraco) en el sistema. Incluye validaciones de negocio (sexo de los animales), filtros de listado, estados del periodo y ejemplos de consumo.

---

## Esquema de base de datos

Tabla principal: `periodos_servicio`

```sql
CREATE TABLE `periodos_servicio` (
  `periodo_id`      char(36) NOT NULL,
  `hembra_id`       char(36) NOT NULL,
  `verraco_id`      char(36) NOT NULL,
  `fecha_inicio`    date NOT NULL,
  `observaciones`   varchar(255) DEFAULT NULL,
  `estado_periodo`  enum('ABIERTO','CERRADO') NOT NULL DEFAULT 'ABIERTO',
  `created_at`      datetime DEFAULT NULL,
  `created_by`      char(36) DEFAULT NULL,
  `updated_at`      datetime DEFAULT NULL,
  `updated_by`      char(36) DEFAULT NULL,
  `deleted_at`      datetime DEFAULT NULL,
  `deleted_by`      char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Índices y FKs relevantes:
```sql
ALTER TABLE `periodos_servicio`
  ADD PRIMARY KEY (`periodo_id`),
  ADD KEY `fk_ps_hembra` (`hembra_id`),
  ADD KEY `fk_ps_verraco` (`verraco_id`);

ALTER TABLE `periodos_servicio`
  ADD CONSTRAINT `fk_ps_hembra`  FOREIGN KEY (`hembra_id`)  REFERENCES `animales` (`animal_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ps_verraco` FOREIGN KEY (`verraco_id`) REFERENCES `animales` (`animal_id`) ON UPDATE CASCADE;
```

> **Relaciones auxiliares** (no estrictamente necesarias aquí pero comunes en el módulo):  
> `montas(periodo_id)`, `revisiones_servicio(periodo_id)`, `partos(periodo_id)`, `alertas(periodo_id)`.

---

## Modelo: `PeriodoServicioModel` (PHP, MySQLi)
Archivo sugerido: `app/models/PeriodoServicioModel.php`

### Utilidades clave
- `generateUUIDv4()`: genera UUID v4 para PK.
- `nowWithAudit()`: aplica **contexto de auditoría** con `ClientEnvironmentInfo` y **zona horaria** con `TimezoneManager`; retorna `(now, env)`.
- Validadores:
  - `validarEstadoPeriodo('ABIERTO'|'CERRADO')`
  - `animalExiste(animal_id)`
  - `esHembra(animal_id)` y `esMacho(animal_id)`

### Lecturas
#### `listar(limit=100, offset=0, incluirEliminados=false, hembraId?, verracoId?, estado?, desde?, hasta?) : array`
- Filtros soportados: `hembra_id`, `verraco_id`, `estado_periodo`, `fecha_inicio` (rango `desde`–`hasta`).
- `incluirEliminados`: incluye soft-deleted si `true`.
- JOIN con `animales` para nombres/sexos de hembra y verraco.
- Orden: `fecha_inicio DESC`.

#### `obtenerPorId(periodo_id) : ?array`
Devuelve datos completos (incluye `deleted_*`).

### Escrituras
#### `crear(data) : string`
Campos requeridos:
- `hembra_id` (debe existir y ser **HEMBRA**)
- `verraco_id` (debe existir y ser **MACHO**)
- `fecha_inicio` (`YYYY-mm-dd`)

Opcionales:
- `observaciones`
- `estado_periodo` (`ABIERTO` por defecto)

**Auditoría**: establece `created_at`, `created_by`. Maneja errores por FK con mensajes legibles.

#### `actualizar(periodo_id, data) : bool`
Permite actualizar explícitamente cualquiera de: `hembra_id`, `verraco_id`, `fecha_inicio`, `observaciones`, `estado_periodo`.  
Valida sexos y existencia. Aplica `updated_at`, `updated_by`.

#### `actualizarEstado(periodo_id, estado) : bool`
Cambia únicamente `estado_periodo` (valores válidos: `ABIERTO` | `CERRADO`) y audita.

#### `eliminar(periodo_id) : bool`
**Eliminación lógica** (soft delete): setea `deleted_at`, `deleted_by`.

---

## Controlador: `PeriodoServicioController`
Archivo sugerido: `app/controllers/PeriodoServicioController.php`

### Convenciones
- `getJsonInput()` para parsear JSON del cuerpo.
- `jsonResponse(value, message, data, status)` para respuestas uniformes.

### Endpoints
```php
// GET    /periodos_servicio                       -> listar()
// GET    /periodos_servicio/{periodo_id}          -> mostrar()
// POST   /periodos_servicio                       -> crear()
// POST   /periodos_servicio/{periodo_id}          -> actualizar()
// POST   /periodos_servicio/{periodo_id}/estado   -> actualizarEstado()
// DELETE /periodos_servicio/{periodo_id}          -> eliminar()
```

### Registro de rutas (router)
```php
$router->get   ('/periodos_servicio',                   ['controlador' => PeriodoServicioController::class, 'accion' => 'listar']);
$router->get   ('/periodos_servicio/{periodo_id}',      ['controlador' => PeriodoServicioController::class, 'accion' => 'mostrar']);
$router->post  ('/periodos_servicio',                   ['controlador' => PeriodoServicioController::class, 'accion' => 'crear']);
$router->post  ('/periodos_servicio/{periodo_id}',      ['controlador' => PeriodoServicioController::class, 'accion' => 'actualizar']);
$router->post  ('/periodos_servicio/{periodo_id}/estado',['controlador' => PeriodoServicioController::class, 'accion' => 'actualizarEstado']);
$router->delete('/periodos_servicio/{periodo_id}',      ['controlador' => PeriodoServicioController::class, 'accion' => 'eliminar']);
```

---

## Especificación de la API

### 1) Listar periodos
**GET** `/periodos_servicio`

**Query params** (opcionales):
- `limit` (int, por defecto 100), `offset` (int, por defecto 0)
- `incluirEliminados` (0|1; por defecto 0)
- `hembra_id`, `verraco_id`
- `estado_periodo` (`ABIERTO`|`CERRADO`)
- `desde`, `hasta` (formato `YYYY-mm-dd` — sobre `fecha_inicio`)

**Respuesta 200**
```json
{
  "value": true,
  "message": "Listado de periodos de servicio obtenido correctamente.",
  "data": [
    {
      "periodo_id": "uuid",
      "hembra_id": "uuid",
      "hembra_identificador": "H-001",
      "verraco_id": "uuid",
      "verraco_identificador": "V-010",
      "fecha_inicio": "2025-10-06",
      "observaciones": "string|null",
      "estado_periodo": "ABIERTO",
      "created_at": "2025-10-06 10:00:00",
      "created_by": "uuid",
      "updated_at": null,
      "updated_by": null
    }
  ]
}
```

**Errores**
- `400` Parámetros inválidos
- `500` Error interno

**cURL**
```bash
curl -G "https://tu-dominio/periodos_servicio"   --data-urlencode "limit=20"   --data-urlencode "estado_periodo=ABIERTO"
```

---

### 2) Obtener por ID
**GET** `/periodos_servicio/{periodo_id}`

**Respuesta 200**
```json
{ "value": true, "message": "Periodo encontrado.", "data": { "campos": "..." } }
```
**Errores**: `400` sin `periodo_id`, `404` no encontrado, `500` interno.

**cURL**
```bash
curl "https://tu-dominio/periodos_servicio/UUID-PER"
```

---

### 3) Crear periodo
**POST** `/periodos_servicio`  
**Body (JSON)**
```json
{
  "hembra_id": "UUID-HEMBRA",
  "verraco_id": "UUID-VERRACO",
  "fecha_inicio": "2025-10-06",
  "observaciones": "Opcional",
  "estado_periodo": "ABIERTO"
}
```

**Validaciones de negocio**
- `hembra_id` **debe** existir y su `sexo` ser `HEMBRA`.
- `verraco_id` **debe** existir y su `sexo` ser `MACHO`.
- `estado_periodo` ∈ {"ABIERTO","CERRADO"} (por defecto `ABIERTO`).

**Respuesta 200**
```json
{ "value": true, "message": "Periodo de servicio creado correctamente.", "data": { "periodo_id": "uuid" } }
```
**Errores**
- `400` faltan campos o inválidos
- `409` conflicto por FK (referencia a animales inválida)
- `500` interno

**cURL**
```bash
curl -X POST "https://tu-dominio/periodos_servicio"   -H "Content-Type: application/json"   -d '{"hembra_id":"UUID-H","verraco_id":"UUID-V","fecha_inicio":"2025-10-06","observaciones":"Primer servicio"}'
```

---

### 4) Actualizar (parcial) por ID
**POST** `/periodos_servicio/{periodo_id}`  
**Body (JSON, cualquiera de):**
```json
{
  "hembra_id": "UUID-HEMBRA",
  "verraco_id": "UUID-VERRACO",
  "fecha_inicio": "2025-10-10",
  "observaciones": "Texto",
  "estado_periodo": "CERRADO"
}
```

**Respuesta 200**
```json
{ "value": true, "message": "Periodo actualizado correctamente.", "data": { "updated": true } }
```
**Errores**: `400` invalid/empty, `409` FK inválida, `500` interno.

**cURL**
```bash
curl -X POST "https://tu-dominio/periodos_servicio/UUID-PER"   -H "Content-Type: application/json"   -d '{"estado_periodo":"CERRADO"}'
```

---

### 5) Actualizar **estado** por ID
**POST** `/periodos_servicio/{periodo_id}/estado`  
**Body (JSON)**
```json
{ "estado_periodo": "ABIERTO" }
```
**Respuesta 200**
```json
{ "value": true, "message": "Estado del periodo actualizado correctamente.", "data": { "updated": true } }
```
**Errores**: `400` estado inválido o faltante, `500` interno.

**cURL**
```bash
curl -X POST "https://tu-dominio/periodos_servicio/UUID-PER/estado"   -H "Content-Type: application/json"   -d '{"estado_periodo":"ABIERTO"}'
```

---

### 6) Eliminar (soft delete) por ID
**DELETE** `/periodos_servicio/{periodo_id}`

**Respuesta 200**
```json
{ "value": true, "message": "Periodo eliminado correctamente.", "data": { "deleted": true } }
```
**Errores**: `400` ya eliminado/no se pudo, `500` interno.

**cURL**
```bash
curl -X DELETE "https://tu-dominio/periodos_servicio/UUID-PER"
```

---

## Reglas y consideraciones de negocio
- **Estados del periodo**: `ABIERTO` (seguimiento activo) y `CERRADO` (finalizado).
- **Sexos obligatorios**: hembra ⇢ `HEMBRA`; verraco ⇢ `MACHO`.
- **Soft delete**: se conserva el rastro de auditoría (`deleted_*`).  
- **Auditoría**: la creación/actualización/eliminación setea `*_at` y `*_by` usando `ClientEnvironmentInfo` y `TimezoneManager`.

> **Integraciones típicas**:  
> - `montas`: hasta **4 montas** por periodo (validación en módulo de montas).  
> - `revisiones_servicio` (días **20–21** tras 1ª monta, ciclos hasta 3).  
> - `alertas`: `REVISION_20_21`, `PROX_PARTO_117`.  
> - `partos`: cierre del ciclo reproductivo (puede marcar `CERRADO`).

---

## Ejemplos de respuestas de error
```json
{ "value": false, "message": "hembra_id no corresponde a una HEMBRA.", "data": null }
```
```json
{ "value": false, "message": "Referencia inválida a animales.", "data": null }
```
```json
{ "value": false, "message": "El campo estado_periodo es obligatorio.", "data": null }
```

---

## Buenas prácticas
- Validar formato de fechas (`YYYY-mm-dd`) en cliente.
- Enviar solo campos a modificar en `actualizar`.
- Usar `limit/offset` y filtros para paginación/eficiencia.
- Gestionar **transacciones** a nivel de orquestación cuando se creen periodos + montas iniciales + alertas/revisiones (si aplica).

---

## Changelog
- **Inicial**: inclusión de modelo, controlador y endpoints; validaciones de sexo; soft delete; auditoría.
