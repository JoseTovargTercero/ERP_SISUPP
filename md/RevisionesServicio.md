# Módulo **Revisiones de Servicio** — Documentación Técnica (.md)

> Proyecto: **ERP_GANADO**  
> Archivo generado: `RevisionesServicioModule_Documentation.md`    
> Fecha: 2025-10-15

---

## 1) Descripción general

El módulo **Revisiones de Servicio** gestiona los **controles periódicos** tras un período de servicio reproductivo (monta o inseminación).  
Permite registrar y consultar revisiones en los días clave (día **20–21** y subsiguientes ciclos de 21 días), determinando si la hembra **entró en celo**, existe **sospecha de preñez**, o se **confirmó la preñez**.

Incluye auditoría contextual (IP/Geo/UA) y aplicación de zona horaria **antes de escribir** en la base de datos.

### Funcionalidades clave

- **Listado** de revisiones con filtros por período y resultado; soporta incluir eliminadas (soft delete).
- **Consulta por ID**.
- **Crear** revisiones con:
  - **Autocálculo de ciclo** (`MAX(ciclo_control)+1`, limitado a 1..3).
  - **Reglas de negocio**:
    - `CONFIRMADA_PREÑEZ` → **cierra el período** y genera **alerta de parto** a `+117 días` desde la **primera monta**.
    - `ENTRO_EN_CELO` → **cierra el período** (el ciclo se reinicia externamente).
    - `SOSPECHA_PREÑEZ` con `ciclo_control < 3` → genera **alerta de revisión** a `+21 días`.
- **Actualizar** revisiones con re-evaluación de reglas anteriores si cambia el `resultado`.
- **Eliminar (soft delete)** con `deleted_at/deleted_by`.

---

## 2) Arquitectura y dependencias

### Archivos

- **Modelo:** `models/RevisionesServicioModel.php`
- **Controlador:** `controllers/RevisionesServicioController.php`
- **Rutas:** registradas en el `router` (ver §6).

### Requisitos (PHP)

- PHP 8.x con **mysqli**.
- Sesión activa (usa `$_SESSION['user_id']` como actor).

### Clases externas requeridas

```php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';
```

- `Database::getInstance()` → conexión mysqli (singleton).
- `ClientEnvironmentInfo` → aplica **contexto de auditoría** y expone `getCurrentDatetime()`.
- `TimezoneManager` → setea `time_zone` en MySQL en función de la región/offset.

> Se utiliza `APP_ROOT . '/app/config/geolite.mmdb'` dentro de `nowWithAudit()`.

### Tablas relacionadas

- `revisiones_servicio` (principal del módulo).  
- `periodos_servicio` (FK lógica por `periodo_id`, debe estar **ABIERTO** para crear revisiones).  
- `montas` (para obtener la **primera monta** y calcular el **parto +117**).  
- `alertas` (inserta eventos: `REVISION_20_21`, `PROX_PARTO_117`).

---

## 3) Modelo: `RevisionesServicioModel`

### Métodos utilitarios

- `generateUUIDv4(): string` → UUID v4.
- `nowWithAudit(): array` → aplica contexto y TZ; retorna `[now, env]`.
- `getActorIdFallback(string $fallback): string` → usa `$_SESSION['user_id']` o `fallback`.
- `validarResultado(?string $resultado)` → ∈ {`ENTRO_EN_CELO`,`SOSPECHA_PREÑEZ`,`CONFIRMADA_PREÑEZ`}.
- `validarCiclo(int $ciclo)` → 1..3.
- `periodoExiste(string $periodoId, bool $requerirAbierto=false): array` → valida existencia/estado.
- `getMaxCiclo(string $periodoId): int` → último ciclo no eliminado.
- `getFechaPrimeraMonta(string $periodoId): ?string` → `YYYY-MM-DD` o `null`.
- `crearAlerta(tipo, periodo_id, animal_id?, fecha_objetivo, detalle?)` → inserta en `alertas`.
- `dateAddDays(yyyy_mm_dd, days): string` → suma/días.

### Lecturas

#### `listar(limit=100, offset=0, periodoId?, resultado?, incluirEliminados=false): array`

- Filtros: `periodo_id`, `resultado` (valida enum).  
- Soft delete: por defecto **excluye** `deleted_at IS NOT NULL`.  
- Orden: `fecha_programada ASC, ciclo_control ASC`.  
- Retorna columnas de la revisión **+** auditoría.

#### `obtenerPorId(revisionId): ?array`

- Retorna registro con auditoría o `null`.

### Escrituras

#### `crear(array $data): string`

**Requeridos**: `periodo_id`, `fecha_programada (YYYY-MM-DD)`  
**Opcionales**: `ciclo_control`, `fecha_realizada`, `resultado`, `observaciones`

**Flujo**:

1. Verifica `periodo_id` **ABIERTO**.
2. Define `ciclo_control` (si viene vacío, `MAX+1`). Valida `1..3`.
3. Inserta con auditoría (`created_at/by`).  
4. **Efectos** por `resultado`:
   - `CONFIRMADA_PREÑEZ` → cierra `periodos_servicio.estado_periodo='CERRADO'` y crea **alerta** `PROX_PARTO_117` a `+117` desde la **primera monta** (si existe).
   - `ENTRO_EN_CELO` → cierra período.
   - `SOSPECHA_PREÑEZ` y `ciclo<3` → crea alerta `REVISION_20_21` a `+21 días` desde `fecha_programada`.

**Retorna**: `revision_id` (UUID).

#### `actualizar(string $revisionId, array $data): bool`

- Campos: `fecha_programada?`, `fecha_realizada?`, `resultado?`, `observaciones?`.  
- Valida coherencia de fechas (no `realizada < programada`).  
- Actualiza con `updated_at/by`.  
- **Reaplica efectos** si cambia el `resultado` (mismas reglas de `crear`).  
- Falla si el registro está **eliminado** (`deleted_at` no null).

#### `eliminar(string $revisionId): bool` (soft)

- Marca `deleted_at/by` si no estaba eliminado.  
- **Retorna** `true` si afectó filas.

---

## 4) Controlador: `RevisionesServicioController`

### Soporte I/O

- `getJsonInput()` → decodifica JSON.
- `jsonResponse($value, $message='', $data=null, $status=200)` → respuesta estándar:

```json
{ "value": true, "message": "texto", "data": {} }
```

### Endpoints

1. **GET** `/revisiones-servicio`  
   **Query:** `limit, offset, periodo_id, resultado, incluirEliminados(0|1)`  
   **200** → `data: array<revision>`

2. **GET** `/revisiones-servicio/{revision_id}`  
   **200** → `data: revision`  
   **404** → no encontrada

3. **GET** `/revisiones-servicio/periodo/{periodo_id}`  
   **200** → `data: revisiones_del_periodo` (usa `listar` con filtro `periodo_id`)

4. **POST** `/revisiones-servicio`  
   **Body (JSON):**
   ```json
   {"periodo_id":"UUID-PERIODO","fecha_programada":"2025-11-04","ciclo_control":1,"fecha_realizada":null,"resultado":"SOSPECHA_PREÑEZ","observaciones":"Comportamiento compatible."}
   ```
   **200/201** → `data: { "revision_id": "uuid" }`  
   **400/409/500** según validación o conflicto

5. **POST** `/revisiones-servicio/{revision_id}` (update parcial)  
   **Body (JSON):** subset de campos permitidos.  
   **200** → `data: { "updated": true }`

6. **DELETE** `/revisiones-servicio/{revision_id}`  
   **200** → `data: { "deleted": true }` (soft)

---

## 5) SQL esperado (mínimo)

### `revisiones_servicio`

```sql
CREATE TABLE revisiones_servicio (
  revision_id      CHAR(36)     PRIMARY KEY,
  periodo_id       CHAR(36)     NOT NULL,
  ciclo_control    TINYINT      NOT NULL,   -- 1..3
  fecha_programada DATE         NOT NULL,
  fecha_realizada  DATE         NULL,
  resultado        ENUM('ENTRO_EN_CELO','SOSPECHA_PREÑEZ','CONFIRMADA_PREÑEZ') NULL,
  observaciones    VARCHAR(255) NULL,
  created_at       DATETIME     NOT NULL,
  created_by       CHAR(36)     NOT NULL,
  updated_at       DATETIME     NULL,
  updated_by       CHAR(36)     NULL,
  deleted_at       DATETIME     NULL,
  deleted_by       CHAR(36)     NULL,
  CONSTRAINT uq_rev_periodo_ciclo UNIQUE (periodo_id, ciclo_control),
  CONSTRAINT fk_rev_periodo FOREIGN KEY (periodo_id) REFERENCES periodos_servicio(periodo_id)
);
```

### Dependencias mínimas

- `periodos_servicio(periodo_id, hembra_id, estado_periodo, created_at, ...)`  
- `montas(periodo_id, fecha_monta, ...)`  
- `alertas(alerta_id, tipo_alerta, periodo_id, animal_id, fecha_objetivo, estado_alerta, detalle, created_at, ...)`

> Asegura índices por `periodo_id` y `resultado` si filtrarás por ellos frecuentemente.

---

## 6) Registro de rutas

```php
$router->get('/revisiones-servicio',                     ['controlador' => RevisionesServicioController::class, 'accion' => 'listar']);
$router->get('/revisiones-servicio/{revision_id}',       ['controlador' => RevisionesServicioController::class, 'accion' => 'mostrar']);
$router->get('/revisiones-servicio/periodo/{periodo_id}',['controlador' => RevisionesServicioController::class, 'accion' => 'listarPorPeriodo']);
$router->post('/revisiones-servicio',                    ['controlador' => RevisionesServicioController::class, 'accion' => 'crear']);
$router->post('/revisiones-servicio/{revision_id}',      ['controlador' => RevisionesServicioController::class, 'accion' => 'actualizar']);
$router->delete('/revisiones-servicio/{revision_id}',    ['controlador' => RevisionesServicioController::class, 'accion' => 'eliminar']);
```

---

## 7) Contratos de E/S (resumen)

### Crear

- **Recibe (JSON):** `periodo_id`, `fecha_programada`, (`ciclo_control?`, `fecha_realizada?`, `resultado?`, `observaciones?`).
- **Da:** `{ "revision_id": "uuid" }`

### Actualizar

- **Recibe (JSON):** subset: `fecha_programada?`, `fecha_realizada?`, `resultado?`, `observaciones?`.
- **Da:** `{ "updated": true }`

### Eliminar (soft)

- **Recibe:** `revision_id` por ruta.  
- **Da:** `{ "deleted": true }`

### Listar/Mostrar

- **Recibe:** query o path params.  
- **Da:** registros con columnas y campos de auditoría.

---

## 8) Códigos de estado y errores

- `200` OK (lecturas/updates/deletes correctos).
- `201` (si tu capa HTTP lo usa para crear).
- `400` Entrada inválida (faltantes, enum inválido, `fecha_realizada < fecha_programada`, update sin campos).
- `404` No encontrada (en `mostrar`).
- `409` Conflicto (duplicado `periodo_id + ciclo_control`).
- `500` Error interno.

**Mensajes típicos:**

- `Faltan campos requeridos: periodo_id, fecha_programada.`  
- `resultado inválido. Use uno de: ENTRO_EN_CELO, SOSPECHA_PREÑEZ, CONFIRMADA_PREÑEZ.`  
- `ciclo_control debe estar entre 1 y 3.`

---

## 9) Notas de implementación

- **Cierre de período**: se actualiza `periodos_servicio` con `estado_periodo='CERRADO'` y `updated_at/by`.
- **Parto +117**: requiere **primera monta**; si no existe, no se crea la alerta (no es error).
- **SOSPECHA_PREÑEZ** en ciclo 1 o 2 → genera **alerta** para el siguiente ciclo (+21 días).
- **Soft delete**: todos los listados excluyen eliminados por defecto.
- Alinea los **nombres de columnas** si tu schema difiere.

---

## 10) Checklist de integración

- [ ] Rutas registradas (ver §6).
- [ ] Sesión activa (`$_SESSION['user_id']`).
- [ ] `Database`, `ClientEnvironmentInfo`, `TimezoneManager` funcionales.
- [ ] `periodos_servicio` usa estado `ABIERTO`/`CERRADO`.
- [ ] Índice único `(periodo_id, ciclo_control)` en `revisiones_servicio`.
- [ ] `montas` con `fecha_monta` para cálculo +117.
- [ ] Vista/Frontend: consumir `GET /revisiones-servicio/periodo/{periodo_id}` para timeline del período.

---

## 11) Ejemplos `curl`

```bash
# Listar por período (solo no eliminadas)
curl -s "https://tu.host/revisiones-servicio?periodo_id=UUID-PERIODO&limit=50"

# Crear revisión (ciclo 1, sospecha)
curl -s -X POST "https://tu.host/revisiones-servicio" \
  -H "Content-Type: application/json" \
  -d '{"periodo_id":"UUID-PERIODO","fecha_programada":"2025-11-04","ciclo_control":1,"resultado":"SOSPECHA_PREÑEZ"}'

# Actualizar resultado a confirmada (cerrará el período y generará alerta +117 si procede)
curl -s -X POST "https://tu.host/revisiones-servicio/UUID-REV" \
  -H "Content-Type: application/json" \
  -d '{"resultado":"CONFIRMADA_PREÑEZ","fecha_realizada":"2025-11-05"}'

# Eliminar (soft)
curl -s -X DELETE "https://tu.host/revisiones-servicio/UUID-REV"
```

---

© 2025 ERP_GANADO — Módulo Revisiones de Servicio
