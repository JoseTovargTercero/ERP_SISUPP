
# Módulo **Animal Ubicaciones** — Documentación Técnica (.md)

> Proyecto: **ERP_GANADO**  
> Archivo: `AnimalUbicacionModule_Documentation.md`  
> Fecha: 2025-10-05

---

## 1) Descripción general

Este módulo administra el **historial de ubicaciones** de cada animal (finca → aprisco → área), incluyendo aperturas, cierres,
validaciones jerárquicas y la **única ubicación activa** por animal. Consta de:

- **Modelo** `AnimalUbicacionModel` con validaciones de fechas, FKs, consistencia jerárquica y reglas de negocio (una activa).
- **Controlador** `AnimalUbicacionController` que expone endpoints REST (JSON) para **listar**, **mostrar**, **consultar la ubicación actual**, **crear**, **actualizar**, **cerrar** y **eliminar lógicamente**.

---

## 2) Dependencias y arquitectura

### Archivos
- Modelo: `models/AnimalUbicacionModel.php`
- Controlador: `controllers/AnimalUbicacionController.php`
- Rutas: definidas en el router (ver §9).

### Requisitos (PHP)
- PHP 8.x con **mysqli**.
- Sesión activa (`$_SESSION['user_id']`) para auditoría.

### Clases externas usadas por el modelo
```php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';
```
- `Database::getInstance()` → conexión **mysqli** (singleton).
- `ClientEnvironmentInfo` → aplica contexto de auditoría y `getCurrentDatetime()`.
- `TimezoneManager` → establece zona horaria activa.

> `nowWithAudit()` usa `APP_ROOT . '/app/config/geolite.mmdb'` para enriquecer auditoría.

### Tablas relacionadas
- `animal_ubicaciones` (principal).
- Apoyo: `animales`, `fincas`, `apriscos`, `areas` (FKs y nombres).

---

## 3) Controlador: `AnimalUbicacionController`

### Respuesta JSON estándar
```json
{ "value": true|false, "message": "texto", "data": { ... } }
```

### Endpoints

1. **GET** `/animal_ubicaciones`  
   **Query params:**  
   `animal_id, finca_id, aprisco_id, area_id, desde (YYYY-MM-DD), hasta (YYYY-MM-DD), soloActivas (0|1), incluirEliminados (0|1), limit, offset`  
   **200** → `data: array<ubicacion>`

2. **GET** `/animal_ubicaciones/{animal_ubicacion_id}`  
   **200** → `data: ubicacion` (incluye `animal_identificador` y nombres de finca/aprisco/área)  
   **404** → no encontrada

3. **GET** `/animal_ubicaciones/actual/{animal_id}`  
   **200** → `data: ubicacion` **activa** (si existe)  
   **404** → no hay ubicación activa

4. **POST** `/animal_ubicaciones`  
   **Body (JSON) requerido:** `animal_id`, `fecha_desde (YYYY-MM-DD)`  
   **Opcionales:** `finca_id`, `aprisco_id`, `area_id`, `fecha_hasta (YYYY-MM-DD)`, `motivo` ∈ {`TRASLADO`,`INGRESO`,`EGRESO`,`AISLAMIENTO`,`VENTA`,`OTRO`} (default `OTRO`), `observaciones`  
   **Reglas:** ver §5.1 (estado forzado y única activa)  
   **201/200** → `data: { "animal_ubicacion_id": "uuid" }`

5. **POST** `/animal_ubicaciones/{animal_ubicacion_id}` (update parcial)  
   **Body (JSON):** puede incluir cambios en fechas, FKs y observaciones.  
   **Reglas:** estado se **normaliza** según `fecha_hasta` y se revalida **única activa**.  
   **200** → `data: { "updated": true }`

6. **POST** `/animal_ubicaciones/{animal_ubicacion_id}/cerrar`  
   **Body (JSON):** `fecha_hasta?` (si se omite, el controlador usa `date('Y-m-d')`)  
   **Efecto:** pone `fecha_hasta`, `estado='INACTIVA'`, actualiza auditoría, sólo si estaba activa.  
   **200** → datos de cierre (id, `fecha_hasta`, `estado='CERRADA'` en respuesta del controlador)

7. **DELETE** `/animal_ubicaciones/{animal_ubicacion_id}`  
   **200** → `data: { "deleted": true }` (soft delete)

---

## 4) Modelo: `AnimalUbicacionModel`

### 4.1 Utilidades
- `generateUUIDv4()` → UUID v4.
- `nowWithAudit()` → auditoría + TZ; retorna `[now, env]`.
- `validarFecha('YYYY-MM-DD', campo)` → regex + `checkdate`.
- Existencias: `animalExiste`, `fincaExiste`, `apriscoExiste`, `areaExiste`.
- `validarJerarquia(fincaId?, apriscoId?, areaId?)` → **área ∈ aprisco** y **aprisco ∈ finca**.
- `existeActiva(animalId)` → hay ubicación activa no eliminada.

### 4.2 Lecturas

#### `listar(limit, offset, incluirEliminados, animalId?, fincaId?, apriscoId?, areaId?, desde?, hasta?, soloActivas=false) : array`
- Filtra por FKs y rango **superpuesto** (`COALESCE(fecha_hasta,'9999-12-31') >= desde` y `fecha_desde <= hasta`).  
- Permite `soloActivas` para `fecha_hasta IS NULL`.  
- Incluye `animal_identificador` y nombres (`finca/aprisco/área`).  
- **Orden:** `COALESCE(fecha_hasta,'9999-12-31') DESC`, `fecha_desde DESC`, `created_at DESC`.

#### `obtenerPorId(id) : ?array`
- Regresa la ubicación con campos de auditoría/eliminación.

#### `getActual(animalId) : ?array`
- Trae **la ubicación activa** (`fecha_hasta IS NULL`) más reciente.

### 4.3 Escrituras

#### 4.3.1 `crear(array $data) : string`
**Requeridos:** `animal_id`, `fecha_desde (YYYY-MM-DD)`  
**Opcionales:** `finca_id`, `aprisco_id`, `area_id`, `fecha_hasta`, `motivo`, `observaciones`  
**Reglas de negocio:**  
- Estado **forzado** por actividad:  
  - `fecha_hasta = NULL` ⇒ `estado = 'ACTIVA'`  
  - `fecha_hasta ≠ NULL` ⇒ `estado = 'INACTIVA'`
- **Única activa por animal:** si se crea como activa y ya existe una activa, **rechaza**.
- Valida FKs y **jerarquía** coherente.

**Retorna:** `animal_ubicacion_id` (UUID).

#### 4.3.2 `actualizar(string $id, array $data) : bool`
- Permite cambiar fechas, FKs y observaciones.  
- Revalida FKs/jerarquía y **única activa** si el resultado queda con `fecha_hasta = NULL`.  
- **Normaliza** `estado` según `fecha_hasta` siempre.  
- Actualiza `updated_at/by`.

**Retorna:** `true` si ejecuta sin errores.

#### 4.3.3 `cerrarUbicacion(string $id, string $fechaHasta) : bool`
- Requiere que el registro esté **activo** (`fecha_hasta IS NULL`).  
- Valida que `fecha_hasta ≥ fecha_desde`.  
- Actualiza `fecha_hasta`, `estado='INACTIVA'`, `updated_at/by`.  
- **Retorna:** `true` si afectó filas.

> `cerrar(string $id, string $fechaHasta)` es alias para retrocompatibilidad.

#### 4.3.4 `eliminar(string $id) : bool` (soft delete)
- Marca `deleted_at/by` si no estaba eliminado.  
- **Retorna:** `true` si afectó filas.

---

## 5) Contratos de E/S (resumen)

### 5.1 Reglas clave (negocio)
- **Una sola ubicación activa** por animal.
- **Estado forzado** por `fecha_hasta`: `NULL`→`ACTIVA`, no-`NULL`→`INACTIVA`.
- **Jerarquía**: `area_id` debe pertenecer a `aprisco_id`; `aprisco_id` debe pertenecer a `finca_id`.

### 5.2 Crear
**Recibe (JSON):**
```json
{
  "animal_id": "UUID-ANIMAL",
  "finca_id": "UUID-FINCA",
  "aprisco_id": "UUID-APRISCO",
  "area_id": "UUID-AREA",
  "fecha_desde": "2025-09-01",
  "fecha_hasta": null,
  "motivo": "TRASLADO",
  "observaciones": "Ingreso por reubicación"
}
```
**Da:** `{ "animal_ubicacion_id": "uuid" }`.

### 5.3 Actualizar
**Recibe (JSON, ejemplos):**
```json
{ "fecha_hasta": "2025-10-05" }
```
o bien
```json
{ "aprisco_id": "UUID-APR-NUEVO", "area_id": "UUID-AREA-NUEVA" }
```
**Da:** `{ "updated": true }`.

### 5.4 Cerrar
**Recibe (JSON):**
```json
{ "fecha_hasta": "2025-10-05" }
```
*(Si se omite, el controlador usa la fecha actual Y-m-d)*  
**Da (controlador):**
```json
{ "animal_ubicacion_id": "UUID", "fecha_hasta": "2025-10-05", "estado": "CERRADA" }
```

### 5.5 Eliminar
- **Recibe:** `animal_ubicacion_id` por ruta.  
- **Da:** `{ "deleted": true }`.

### 5.6 Listar/Mostrar/Actual
- **Recibe:** query/path params.  
- **Da:** registros con `animal_identificador` y nombres de finca/aprisco/área.

---

## 6) SQL/Esquema mínimo esperado

- `animal_ubicaciones` columnas:  
  `animal_ubicacion_id (PK)`, `animal_id (FK)`, `finca_id? (FK)`, `aprisco_id? (FK)`, `area_id? (FK)`,  
  `fecha_desde DATE`, `fecha_hasta? DATE`, `motivo`, `estado`, `observaciones?`,  
  `created_at`, `created_by`, `updated_at?`, `updated_by?`, `deleted_at?`, `deleted_by?`.

- Tablas apoyo: `animales`, `fincas`, `apriscos`, `areas` con campos de nombres (`nombre`, `nombre_personalizado`, `numeracion`).

**Índices sugeridos:**
- `(animal_id, fecha_desde)` y `fecha_hasta` para rangos.
- FKs `finca_id`, `aprisco_id`, `area_id` para filtros.
- Índice parcial para `fecha_hasta IS NULL` (si el motor/versión lo permite) o bien compuesto `(animal_id, fecha_hasta)`.

---

## 7) Ejemplos `curl`

```bash
# Listar ubicaciones activas por animal en un rango
curl -s "https://tu.host/animal_ubicaciones?animal_id=UUID-A&desde=2025-09-01&hasta=2025-10-01&soloActivas=1&limit=50"

# Obtener ubicación actual
curl -s "https://tu.host/animal_ubicaciones/actual/UUID-A"

# Crear ubicación activa (verifica única activa)
curl -s -X POST "https://tu.host/animal_ubicaciones"   -H "Content-Type: application/json"   -d '{"animal_id":"UUID-A","finca_id":"UUID-F","aprisco_id":"UUID-APR","area_id":"UUID-AR","fecha_desde":"2025-09-10","motivo":"INGRESO"}'

# Cerrar ubicación
curl -s -X POST "https://tu.host/animal_ubicaciones/UUID-U/cerrar"   -H "Content-Type: application/json"   -d '{"fecha_hasta":"2025-10-05"}'

# Actualizar (cambio de fechas y observaciones)
curl -s -X POST "https://tu.host/animal_ubicaciones/UUID-U"   -H "Content-Type: application/json"   -d '{"fecha_desde":"2025-09-12","observaciones":"Ajuste de fecha"}'

# Eliminar (soft)
curl -s -X DELETE "https://tu.host/animal_ubicaciones/UUID-U"
```

---

## 8) Manejo de errores y códigos de estado

- **200** OK (lecturas/updates/deletes correctos).  
- **201** (si tu capa HTTP lo usa para creación).  
- **400** Entrada inválida (faltantes, formato de fecha, jerarquía, `fecha_hasta < fecha_desde`, update vacío).  
- **404** No encontrada (en `mostrar` o `actual` cuando no hay activa).  
- **409** Conflictos (única activa, FKs inconsistentes).  
- **500** Error interno (mysqli/procesamiento).

**Mensajes comunes:**
- `Faltan campos requeridos: animal_id, fecha_desde.`
- `El área no pertenece al aprisco/finca especificado.`
- `Ya existe una ubicación activa para este animal.`
- `Ubicación no encontrada o ya estaba cerrada.`

---

## 9) Registro de rutas

```php
$router->get('/animal_ubicaciones', ['controlador' => AnimalUbicacionController::class, 'accion' => 'listar']);
$router->get('/animal_ubicaciones/{animal_ubicacion_id}', ['controlador' => AnimalUbicacionController::class, 'accion' => 'mostrar']);
$router->get('/animal_ubicaciones/actual/{animal_id}', ['controlador' => AnimalUbicacionController::class, 'accion' => 'actual']);
$router->post('/animal_ubicaciones', ['controlador' => AnimalUbicacionController::class, 'accion' => 'crear']);
$router->post('/animal_ubicaciones/{animal_ubicacion_id}', ['controlador' => AnimalUbicacionController::class, 'accion' => 'actualizar']);
$router->post('/animal_ubicaciones/{animal_ubicacion_id}/cerrar', ['controlador' => AnimalUbicacionController::class, 'accion' => 'cerrar']);
$router->delete('/animal_ubicaciones/{animal_ubicacion_id}', ['controlador' => AnimalUbicacionController::class, 'accion' => 'eliminar']);
```

---

## 10) Checklist de integración

- [ ] Rutas registradas.  
- [ ] Sesión activa (`$_SESSION['user_id']`).  
- [ ] `Database`, `ClientEnvironmentInfo`, `TimezoneManager` configurados.  
- [ ] Índices por FKs y fechas (`fecha_desde`, `fecha_hasta`).  
- [ ] Frontend consume `{ value, message, data }` y muestra nombres de finca/aprisco/área.

---

© 2025 ERP_GANADO — Módulo Animal Ubicaciones
