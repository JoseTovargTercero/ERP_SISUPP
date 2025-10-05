
# Módulo **Animal Salud** — Documentación Técnica (.md)

> Proyecto: **ERP_GANADO**  
> Archivo: `AnimalSaludModule_Documentation.md`  
> Fecha: 2025-10-05

---

## 1) Descripción general

Este módulo registra y gestiona **eventos de salud** por animal: enfermedades, vacunaciones, desparasitaciones, revisiones y tratamientos.
Incluye un **modelo** (`AnimalSaludModel`) con validaciones exhaustivas (fechas, enums y rangos) y auditoría contextual; y un
**controlador** (`AnimalSaludController`) que expone endpoints REST (JSON) para **listar**, **mostrar**, **crear**, **actualizar** y **eliminar lógicamente**.

### Funcionalidades clave
- **Listado** con filtros por `animal_id`, `tipo_evento`, `severidad`, `estado`, rango de fechas y **búsqueda textual** (`q`) sobre diagnóstico/tratamiento/medicamento/observaciones/responsable.
- **Consulta por ID** con `animal_identificador`.
- **Creación/Actualización** con validación de **enums** y fechas (incluida `proxima_revision`).
- **Soft delete** con auditoría (`deleted_at`, `deleted_by`).

---

## 2) Dependencias y arquitectura

### Archivos
- Modelo: `models/AnimalSaludModel.php`
- Controlador: `controllers/AnimalSaludController.php`
- Rutas: definidas en el router (ver §8).

### Requisitos (PHP)
- PHP 8.x con **mysqli**.
- Sesión activa (`$_SESSION['user_id']` usado en auditoría).

### Clases externas usadas en el modelo
```php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';
```
- `Database::getInstance()` → conexión **mysqli** (singleton).
- `ClientEnvironmentInfo` → aplica **contexto de auditoría** y `getCurrentDatetime()`.
- `TimezoneManager` → establece la zona horaria SQL.

> `nowWithAudit()` usa `APP_ROOT . '/app/config/geolite.mmdb'` para la información GeoLite.

### Tablas relacionadas
- `animal_salud` (principal).
- `animales` (FK de `animal_id` para validación y joins).

---

## 3) Controlador: `AnimalSaludController`

### Respuesta JSON estándar
```json
{ "value": true|false, "message": "texto", "data": { ... } }
```

### Endpoints

1. **GET** `/animal_salud`  
   **Query params:**  
   `animal_id, tipo_evento, severidad, estado, desde (YYYY-MM-DD), hasta (YYYY-MM-DD), q, incluirEliminados (0|1), limit, offset`  
   **200** → `data: array<evento_salud>`

2. **GET** `/animal_salud/{animal_salud_id}`  
   **200** → `data: evento_salud` (incluye `animal_identificador`)  
   **404** → no encontrado

3. **POST** `/animal_salud`  
   **Body (JSON) requerido:** `animal_id`, `fecha_evento (YYYY-MM-DD)`, `tipo_evento`  
   **Opcionales:** `diagnostico`, `severidad`, `tratamiento`, `medicamento`, `dosis`, `via_administracion`, `costo`, `estado`, `proxima_revision (YYYY-MM-DD)`, `responsable`, `observaciones`  
   **201/200** → `data: { "animal_salud_id": "uuid" }`  
   **400** → validación (faltantes, enums/fecha/rangos)  
   **409** → conflictos (FKs, etc., propagados como `RuntimeException`)

4. **POST** `/animal_salud/{animal_salud_id}` (update parcial)  
   **Body (JSON):** subset de campos soportados (ver §5.3).  
   **200** → `data: { "updated": true }`

5. **DELETE** `/animal_salud/{animal_salud_id}`  
   **200** → `data: { "deleted": true }`  
   **400** → ya eliminado o no afectó filas.

---

## 4) Modelo: `AnimalSaludModel`

### 4.1 Utilidades
- `generateUUIDv4()` → genera UUID v4.
- `nowWithAudit()` → aplica auditoría y TZ; retorna `[now, env]`.
- `animalExiste(animalId)` → valida FK en `animales` (no eliminado).
- `validarFecha(ymd, campo)` → `YYYY-MM-DD` + `checkdate`.
- `validarEnum(valor, permitidos, campo)` → normaliza a **UPPER** y valida.
- `validarCosto(?float)` → acepta `NULL`, rango `0..999999.99`.

### 4.2 Lecturas
#### `listar(limit, offset, incluirEliminados, animalId?, tipoEvento?, severidad?, estado?, desde?, hasta?, q?) : array`
- Aplica filtros y búsqueda textual (`q`) sobre `diagnostico`, `tratamiento`, `medicamento`, `observaciones`, `responsable`.
- Incluye `animal_identificador`.  
- **Orden:** `fecha_evento DESC, created_at DESC`.

#### `obtenerPorId(id) : ?array`
- Devuelve un evento con campos de auditoría (incl. `deleted_*`) y `animal_identificador`.

### 4.3 Escrituras
#### `crear(array $data) : string`
**Requeridos:** `animal_id`, `fecha_evento` (YYYY-MM-DD), `tipo_evento` ∈ {`ENFERMEDAD`,`VACUNACION`,`DESPARASITACION`,`REVISION`,`TRATAMIENTO`,`OTRO`}  
**Opcionales:** `diagnostico`, `severidad` ∈ {`LEVE`,`MODERADA`,`GRAVE`}, `tratamiento`, `medicamento`, `dosis`, `via_administracion`, `costo (0..999999.99)`, `estado` ∈ {`ABIERTO`,`SEGUIMIENTO`,`CERRADO`} (*default* `ABIERTO`), `proxima_revision (YYYY-MM-DD)`, `responsable`, `observaciones`.

**Flujo:** valida FK de animal y fechas/enums; inserta con `created_at/by`; `updated_*` nulos en inserción.  
**Retorna:** `animal_salud_id` (UUID).

#### `actualizar(string $id, array $data) : bool`
- Permite parche parcial de todos los campos anteriores.
- Revalida enums/fechas (`proxima_revision` inclusive) y `costo` en rango o `NULL`.
- Actualiza `updated_at/by` y exige que el registro **no esté eliminado**.  
**Retorna:** `true` si se ejecuta sin errores.

#### `eliminar(string $id) : bool` (soft delete)
- Marca `deleted_at/by` si no estaba eliminado.  
- **Retorna:** `true` si afectó filas.

---

## 5) Contratos de E/S (resumen)

### 5.1 Crear
**Recibe (JSON):**
```json
{
  "animal_id": "UUID-ANIMAL",
  "fecha_evento": "2025-09-20",
  "tipo_evento": "VACUNACION",
  "diagnostico": "Profilaxis anual",
  "severidad": "LEVE",
  "tratamiento": "Vacuna A",
  "medicamento": "Vacuna A",
  "dosis": "5 ml",
  "via_administracion": "IM",
  "costo": 12.5,
  "estado": "ABIERTO",
  "proxima_revision": "2025-10-20",
  "responsable": "MVZ Pérez",
  "observaciones": "Sin reacciones"
}
```
**Da:** `{ "animal_salud_id": "uuid" }`.

### 5.2 Mostrar / Listar
- **Recibe:** path param / query params (incl. `q`).  
- **Da:** registros con `animal_identificador`.

### 5.3 Actualizar
**Recibe (JSON, ejemplo):**
```json
{
  "estado": "SEGUIMIENTO",
  "proxima_revision": "2025-10-05",
  "observaciones": "Control de evolución"
}
```
**Da:** `{ "updated": true }`.

### 5.4 Eliminar
- **Recibe:** `animal_salud_id` por ruta.  
- **Da:** `{ "deleted": true }`.

---

## 6) SQL/Esquema mínimo esperado

- `animal_salud` columnas:  
  `animal_salud_id (PK)`, `animal_id (FK)`, `fecha_evento DATE`, `tipo_evento`, `diagnostico?`, `severidad?`, `tratamiento?`, `medicamento?`, `dosis?`, `via_administracion?`, `costo?`, `estado`, `proxima_revision? DATE`, `responsable?`, `observaciones?`,  
  `created_at`, `created_by`, `updated_at?`, `updated_by?`, `deleted_at?`, `deleted_by?`.

- `animales` (FK para `animal_id`, no eliminado).

**Índices sugeridos:**  
- `(animal_id, fecha_evento)` para consultas por animal y rango.  
- `tipo_evento`, `estado` para filtros frecuentes.  
- Índice FULLTEXT/BTREE (según motor) sobre `diagnostico`, `tratamiento`, `medicamento`, `observaciones`, `responsable` (opcional, si usas MySQL InnoDB ≥5.6 para FULLTEXT).

---

## 7) Ejemplos `curl`

```bash
# Listar eventos de salud por animal y rango con búsqueda textual
curl -s "https://tu.host/animal_salud?animal_id=UUID-A&desde=2025-09-01&hasta=2025-10-01&q=vacuna&limit=50"

# Crear evento de vacunación
curl -s -X POST "https://tu.host/animal_salud"   -H "Content-Type: application/json"   -d '{"animal_id":"UUID-A","fecha_evento":"2025-09-20","tipo_evento":"VACUNACION","medicamento":"Vacuna A","dosis":"5 ml","via_administracion":"IM","costo":12.5}'

# Actualizar estado y próxima revisión
curl -s -X POST "https://tu.host/animal_salud/UUID-SALUD"   -H "Content-Type: application/json"   -d '{"estado":"CERRADO","proxima_revision":"2025-10-20"}'

# Eliminar (soft)
curl -s -X DELETE "https://tu.host/animal_salud/UUID-SALUD"
```

---

## 8) Registro de rutas

```php
$router->get('/animal_salud', ['controlador' => AnimalSaludController::class, 'accion' => 'listar']);
$router->get('/animal_salud/{animal_salud_id}', ['controlador' => AnimalSaludController::class, 'accion' => 'mostrar']);
$router->post('/animal_salud', ['controlador' => AnimalSaludController::class, 'accion' => 'crear']);
$router->post('/animal_salud/{animal_salud_id}', ['controlador' => AnimalSaludController::class, 'accion' => 'actualizar']);
$router->delete('/animal_salud/{animal_salud_id}', ['controlador' => AnimalSaludController::class, 'accion' => 'eliminar']);
```

---

## 9) Manejo de errores y códigos de estado

- **200** OK (lecturas/updates/deletes correctos).  
- **201** (si tu capa HTTP lo usa para creación).  
- **400** Entrada inválida (faltantes, fecha inválida, enums fuera de catálogo, `costo` fuera de rango, update sin campos).  
- **404** No encontrado (en `mostrar`).  
- **409** Conflicto / FK (p. ej., animal inexistente en inserción).  
- **500** Error interno (mysqli/procesamiento).

**Mensajes frecuentes:**
- `Falta campo requerido: animal_id/fecha_evento/tipo_evento.`
- `fecha_evento inválida. Formato esperado YYYY-MM-DD.`
- `tipo_evento inválido. Use uno de: ...`
- `severidad inválida. Use uno de: LEVE, MODERADA, GRAVE.`
- `costo fuera de rango.`

---

## 10) Checklist de integración

- [ ] Rutas registradas en el router.  
- [ ] Sesión activa (`$_SESSION['user_id']`).  
- [ ] `Database`, `ClientEnvironmentInfo`, `TimezoneManager` configurados.  
- [ ] Índices en `(animal_id, fecha_evento)` y columnas de filtro.  
- [ ] Frontend consume el contrato `{ value, message, data }` y muestra `animal_identificador`.

---

© 2025 ERP_GANADO — Módulo Animal Salud
