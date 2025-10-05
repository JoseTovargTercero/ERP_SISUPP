
# Módulo **Animal Movimientos** — Documentación Técnica (.md)

> Proyecto: **ERP_GANADO**  
> Archivo generado: `AnimalMovimientoModule_Documentation.md`  
> Autor: ChatGPT (asistente)  
> Fecha: 2025-10-05

---

## 1) Descripción general

Este módulo gestiona **movimientos de animales** (ingresos, egresos, traslados, compras, ventas, nacimientos, muertes, etc.).
Incluye un **modelo** (`AnimalMovimientoModel`) con validaciones robustas (fechas, enums, jerarquía finca→aprisco→área), auditoría
contextual y manejo de zona horaria; y un **controlador** (`AnimalMovimientoController`) que expone un CRUD REST en JSON.

### Funcionalidades clave

- **Listado** filtrable por animal, tipo/motivo/estado, rangos de fecha y **origen/destino** (finca/aprisco/área).
- **Consulta por ID** con datos enriquecidos (identificador del animal y nombres de origen/destino).
- **Crear**, **Actualizar** y **Eliminar (soft delete)** con:
  - Validación de **enums** y **formato de fecha (YYYY-MM-DD)**.
  - Verificación de existencia de **animal**, **fincas**, **apriscos** y **áreas**.
  - **Consistencia jerárquica**: un área pertenece a un aprisco; un aprisco pertenece a una finca.
  - **Reglas por tipo de movimiento** (ver §5.1).

---

## 2) Arquitectura y dependencias

### Archivos

- **Modelo:** `models/AnimalMovimientoModel.php`
- **Controlador:** `controllers/AnimalMovimientoController.php` (o ruta equivalente en tu proyecto)
- **Rutas:** registradas en tu `router` (ver §7).

### Requisitos (PHP)

- PHP 8.x con **mysqli** habilitado.
- Sesión activa (usa `$_SESSION['user_id']` para `created_by/updated_by/deleted_by`).

### Clases externas requeridas (modelo)

```php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';
```

- `Database::getInstance()` → conexión **mysqli** (singleton).
- `ClientEnvironmentInfo` → aplica **contexto de auditoría** y `getCurrentDatetime()`.
- `TimezoneManager` → aplica zona horaria a la conexión.

> **Nota:** `nowWithAudit()` usa `APP_ROOT . '/app/config/geolite.mmdb'` para GeoLite (auditoría).

### Tablas relacionadas esperadas

- `animal_movimientos` (principal).
- Apoyo: `animales`, `fincas`, `apriscos`, `areas` (para FK y nombres).

---

## 3) Controlador: `AnimalMovimientoController`

### Métodos de soporte

- `getJsonInput()` → lee y decodifica JSON de `php://input`.
- `jsonResponse($value, $message, $data, $status)` → respuesta estándar JSON:

```json
{ "value": true|false, "message": "texto", "data": { ... } }
```

### Endpoints

1. **GET** `/animal_movimientos`  
   **Query params:**  
   `limit, offset, incluirEliminados(0|1), animal_id, tipo_movimiento, motivo, estado, desde, hasta,`  
   `finca_origen_id, aprisco_origen_id, area_origen_id, finca_destino_id, aprisco_destino_id, area_destino_id`  
   **200** → `data: array<movimiento>`

2. **GET** `/animal_movimientos/{animal_movimiento_id}`  
   **200** → `data: movimiento` (incluye `animal_identificador`)  
   **404** → no encontrado

3. **POST** `/animal_movimientos`  
   **Body (JSON) mínimo:** `animal_id`, `fecha_mov (YYYY-MM-DD)`, `tipo_movimiento`.  
   **Campos opcionales:** `motivo`, `estado`, FKs de **origen/destino**, `costo`, `documento_ref`, `observaciones`.  
   **201/200** → `data: { "animal_movimiento_id": "uuid" }`  
   **400** → validación (faltantes/enums/fecha/reglas por tipo)  
   **409** → conflictos (FKs, etc., encapsulados como `RuntimeException`)

4. **POST** `/animal_movimientos/{animal_movimiento_id}` (update parcial)  
   **Body (JSON):** cualquier subset de campos soportados.  
   **200** → `data: { "updated": true }`  
   **400/409/500** según error.

5. **DELETE** `/animal_movimientos/{animal_movimiento_id}`  
   **200** → `data: { "deleted": true }`  
   **400** → ya estaba eliminado o no afectó filas.

---

## 4) Modelo: `AnimalMovimientoModel`

### 4.1 Utilidades

- `generateUUIDv4()` → genera UUID v4.
- `nowWithAudit()` → aplica auditoría y TZ; retorna `[now, env]`.
- `validarFecha(ymd)` → exige `YYYY-MM-DD` y `checkdate`.
- `validarEnum(valor, permitidos, campo)` → normaliza UPPER y valida.
- **Existencias:** `animalExiste`, `fincaExiste`, `apriscoExiste`, `areaExiste`.
- **Jerarquía:** `validarJerarquia(fincaId, apriscoId, areaId)` asegura consistencia **área → aprisco → finca** (y que los IDs existan).

### 4.2 Lecturas

#### `listar(...) : array`

**Filtros:**  
`animal_id, tipo_movimiento, motivo, estado, desde, hasta` + FKs de **origen y destino**, con `incluirEliminados`.

**Retorna:** columnas del movimiento + nombres de finca/aprisco/área (origen/destino) y `animal_identificador`.
Ordenado por `m.fecha_mov DESC, m.created_at DESC`.

#### `obtenerPorId(string $id): ?array`

Retorna el movimiento (o `null`) con `animal_identificador`.

### 4.3 Escrituras

#### 4.3.1 Reglas por tipo (validación)

- **INGRESO / COMPRA / NACIMIENTO** → **requiere DESTINO** (finca/aprisco/área).  
- **EGRESO / VENTA / MUERTE** → **requiere ORIGEN** (finca/aprisco/área).  
- **TRASLADO** → **requiere ORIGEN y DESTINO**.

Además, se verifica que los IDs existan y que respeten la **jerarquía** (área ∈ aprisco, aprisco ∈ finca).

#### 4.3.2 `crear(array $data): string`

**Requeridos:** `animal_id`, `fecha_mov (YYYY-MM-DD)`, `tipo_movimiento`.  
**Opcionales:** `motivo ('OTRO' por defecto)`, `estado ('REGISTRADO' por defecto)`, FKs de origen/destino, `costo` (nullable), `documento_ref`, `observaciones`.

- Valida **animal** existente y no eliminado.
- Valida **fecha**, **enums**, **existencias** y **jerarquía** de FKs.
- Aplica **reglas por tipo**.
- Inserta con auditoría (`created_at/by`, `updated_at/by NULL` en inserción).

**Retorna:** `animal_movimiento_id` (UUID).

> **Nota técnica:** Para evitar problemas con `NULL` y tipos, el `bind_param` usa **todos 's'** (strings) y convierte `costo` a string cuando no es `NULL`.

#### 4.3.3 `actualizar(string $id, array $data): bool`

- Verifica existencia y que **no esté eliminado**.
- Revalida `fecha`, `enums` y, si cambian FKs, revalida **existencia**, **jerarquía** y **reglas por tipo** según el `tipo_movimiento` **nuevo o actual**.
- Permite `costo` `NULL` o en rango `0..999999.99` (si no `NULL`).  
- Actualiza `updated_at`/`updated_by`.

**Retorna:** `true` si ejecuta sin errores.

#### 4.3.4 `eliminar(string $id): bool` (soft delete)

Marca `deleted_at` y `deleted_by` si el registro no estaba eliminado.  
**Retorna:** `true` si afectó filas.

---

## 5) Contratos de E/S (resumen)

### **Crear**

- **Recibe (JSON):**
  ```json
  {
    "animal_id": "UUID-ANIMAL",
    "fecha_mov": "2025-09-01",
    "tipo_movimiento": "TRASLADO",
    "motivo": "TRASLADO",
    "estado": "REGISTRADO",
    "finca_origen_id": "UUID-FINCA-ORI",
    "aprisco_origen_id": "UUID-APR-ORI",
    "area_origen_id": "UUID-AREA-ORI",
    "finca_destino_id": "UUID-FINCA-DES",
    "aprisco_destino_id": "UUID-APR-DES",
    "area_destino_id": "UUID-AREA-DES",
    "costo": 45.50,
    "documento_ref": "REM-000123",
    "observaciones": "Traslado por redistribución de lotes."
  }
  ```
- **Da:** `{ "animal_movimiento_id": "uuid" }` y `message`.

### **Actualizar**

- **Recibe (JSON):** subset de campos (incl. cambios de origen/destino).  
- **Da:** `{ "updated": true }` y `message`.

### **Eliminar**

- **Recibe:** `animal_movimiento_id` por ruta.  
- **Da:** `{ "deleted": true }` y `message`.

### **Listar/Mostrar**

- **Recibe:** query params / path param.  
- **Da:** registros con **nombres** de origen/destino y `animal_identificador`.

---

## 6) SQL/Esquema mínimo esperado

- `animal_movimientos` con columnas:  
  `animal_movimiento_id (PK)`, `animal_id (FK)`, `fecha_mov`, `tipo_movimiento`, `motivo`, `estado`,  
  `finca_origen_id?`, `aprisco_origen_id?`, `area_origen_id?`,  
  `finca_destino_id?`, `aprisco_destino_id?`, `area_destino_id?`,  
  `costo?`, `documento_ref?`, `observaciones?`,  
  `created_at`, `created_by`, `updated_at?`, `updated_by?`, `deleted_at?`, `deleted_by?`.

- Tablas de apoyo: `animales`, `fincas`, `apriscos`, `areas`, con PK y columnas de nombre (`nombre`, `nombre_personalizado`, `numeracion`).

> **Indices sugeridos:** por `animal_id`, `fecha_mov`, y FKs de origen/destino para mejorar filtros.

---

## 7) Registro de rutas

```php
$router->get('/animal_movimientos', ['controlador' => AnimalMovimientoController::class, 'accion' => 'listar']);
$router->get('/animal_movimientos/{animal_movimiento_id}', ['controlador' => AnimalMovimientoController::class, 'accion' => 'mostrar']);
$router->post('/animal_movimientos', ['controlador' => AnimalMovimientoController::class, 'accion' => 'crear']);
$router->post('/animal_movimientos/{animal_movimiento_id}', ['controlador' => AnimalMovimientoController::class, 'accion' => 'actualizar']);
$router->delete('/animal_movimientos/{animal_movimiento_id}', ['controlador' => AnimalMovimientoController::class, 'accion' => 'eliminar']);
```

---

## 8) Códigos de estado y manejo de errores

- `200` OK (lecturas/updates/deletes correctos).
- `201` (si tu capa HTTP lo usa para creación).
- `400` Entrada inválida (faltantes, enums, jerarquía, fecha, `costo` fuera de rango, update vacío).
- `404` No encontrado (en `mostrar`).
- `409` Conflicto o violación de FK en inserción/actualización (propagado como `RuntimeException`).
- `500` Error interno (mysqli/procesamiento).

**Mensajes comunes:**

- `Falta campo requerido: animal_id/fecha_mov/tipo_movimiento.`
- `fecha_mov inválida. Formato esperado YYYY-MM-DD.`
- `tipo_movimiento inválido. Use uno de: ...`
- `Para TRASLADO requiere origen y destino.`
- `El aprisco no pertenece a la finca indicada.`
- `Finca/aprisco/área (origen/destino) no existen o están eliminados.`

---

## 9) Ejemplos `curl`

```bash
# Listar por intervalo y animal
curl -s "https://tu.host/animal_movimientos?animal_id=UUID-ANIMAL&desde=2025-09-01&hasta=2025-09-30&limit=50"

# Crear ingreso (requiere DESTINO)
curl -s -X POST "https://tu.host/animal_movimientos"   -H "Content-Type: application/json"   -d '{"animal_id":"UUID-A","fecha_mov":"2025-09-10","tipo_movimiento":"INGRESO","finca_destino_id":"UUID-F"}'

# Crear traslado (requiere ORIGEN y DESTINO)
curl -s -X POST "https://tu.host/animal_movimientos"   -H "Content-Type: application/json"   -d '{"animal_id":"UUID-A","fecha_mov":"2025-09-11","tipo_movimiento":"TRASLADO","finca_origen_id":"UUID-FO","finca_destino_id":"UUID-FD"}'

# Actualizar costo y documento
curl -s -X POST "https://tu.host/animal_movimientos/UUID-M"   -H "Content-Type: application/json"   -d '{"costo":35.9,"documento_ref":"REM-0009"}'

# Eliminar (soft)
curl -s -X DELETE "https://tu.host/animal_movimientos/UUID-M"
```

---

## 10) Checklist de integración

- [ ] Rutas registradas en el router.
- [ ] Sesión activa con `$_SESSION['user_id']`.
- [ ] `Database`, `ClientEnvironmentInfo`, `TimezoneManager` configurados.
- [ ] Índices en FKs y `fecha_mov`.
- [ ] Frontend consume respuestas estándar `{ value, message, data }`.

---

## 11) Mejoras futuras sugeridas

- **Anulación lógica** con motivo y usuario (historial de cambios).
- **Auditoría** detallada en `audit_log` para `INSERT/UPDATE/DELETE` de movimientos.
- **Paginación con total** y orden adicional por `animal_identificador`.
- **Batch operations** (importación desde CSV/Excel).
- **Integración con stock** (si aplica para `VENTA/COMPRA`).

---

© 2025 ERP_GANADO — Módulo Animal Movimientos
