
# Módulo **Animales** — Documentación Técnica (.md)

> Proyecto: **ERP_GANADO**
>
> Archivo generado: `AnimalModule_Documentation.md`  
> Autor: ChatGPT (asistente)  
> Fecha: 2025-10-05

---

## 1) Descripción general

Este módulo ofrece CRUD y consultas enriquecidas para **animales**. Expone endpoints REST (estilo JSON) y un modelo `AnimalModel` con validaciones, auditoría contextual (IP/Geo/UA) y zona horaria aplicada antes de escribir.

### Funcionalidades clave

- **Listado** con filtros + datos enriquecidos:
  - Último peso (`fecha_peso`, `peso_kg`).
  - **Ubicación activa** (finca/aprisco/área) cuando el animal tiene una ubicación vigente.
- **Consulta por ID** con el mismo enriquecimiento.
- **Options** para **Select2** o combos (`animal_id`, `label`).
- **Crear**, **Actualizar** y **Eliminar (soft delete)** con validaciones de:
  - Enums (`sexo`, `especie`, `estado`, `etapa_productiva`, `categoria`, `origen`).
  - Formato de fecha `YYYY-MM-DD`.
  - Unicidad de `identificador`.
  - Referencias tolerantes a `madre_id` y `padre_id` (si no existen, se ignoran).
- **Soporte de fotografía**: permite subir una imagen (`fotografia`) mediante `multipart/form-data`. Se guarda en `APP_ROOT/uploads` con nombre `{animal_id}.{ext}` (`jpg|png|webp`) y se almacena la ruta relativa en el campo `fotografia_url`.


---

## 2) Arquitectura y dependencias

### Archivos

- **Modelo:** `models/AnimalModel.php`
- **Controlador:** `controllers/AnimalController.php` (o ruta equivalente en tu estructura)
- **Rutas:** registradas en tu `router` (ver §6).

### Requisitos (PHP)

- PHP 8.x con **mysqli** habilitado.
- Sesión iniciada (usa `$_SESSION['user_id']` para `created_by/updated_by/deleted_by`).

### Clases externas requeridas

El modelo requiere:

```php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';
```

- `Database::getInstance()` → retorna conexión **mysqli** (singleton).
- `ClientEnvironmentInfo` → rellena **contexto de auditoría** en la sesión/conexión y provee `getCurrentDatetime()`.
- `TimezoneManager` → aplica zona horaria a la conexión (p. ej., `SET time_zone = ...`).

> **Nota:** Se usa `APP_ROOT` y la base de GeoLite: `APP_ROOT . '/app/config/geolite.mmdb'` en `nowWithAudit()`.

### Tablas relacionadas (nombres esperados)

- `animales` (principal).
- `animal_pesos` (para último peso).
- `animal_ubicaciones` + `fincas` + `apriscos` + `areas` (para ubicación activa).

> El modelo asume nombres de columnas como en el código (`identificador`, `sexo`, `especie`, `estado`, etc.). Adecuar a tu schema si difiere.

---

## 3) Modelo: `AnimalModel`

### Métodos utilitarios

- `generateUUIDv4(): string` → genera UUID v4.
- `nowWithAudit(): array` → aplica contexto de auditoría y TZ; retorna `[now, env]`.
- `validarFecha(?string $ymd, string $campo='fecha')` → formato `YYYY-MM-DD` y `checkdate`.
- `validarEnum(string $valor, array $permitidos, string $campo): string` → normaliza a **UPPERCASE** y valida.
- `animalExistePorId(string $animalId): bool` → verifica existencia **no eliminada**.
- `identificadorDisponible(string $identificador, ?string $exceptId=null): bool` → unicidad.


> **Nuevo:** Los métodos `crear()` y `actualizar()` admiten el campo `fotografia_url`. El modelo no gestiona archivos directamente; solo almacena la ruta. La subida física se maneja en el controlador (ver §4).

### Lecturas

#### `listar(...) : array`

**Enriquecido** con:

- Subconsulta de **último peso** (por `MAX(fecha_peso)`).
- **Ubicación activa** (donde `fecha_hasta IS NULL` y máxima `fecha_desde`).

**Parámetros:**

- `limit` (int, por defecto **100**), `offset` (int, **0**).
- `incluirEliminados` (bool): si `false`, solo `a.deleted_at IS NULL`.
- Filtros opcionales:
  - `q` (like por `identificador`).
  - `sexo` ∈ {`MACHO`,`HEMBRA`}.
  - `especie` ∈ {`BOVINO`,`OVINO`,`CAPRINO`,`PORCINO`,`OTRO`}.
  - `estado` ∈ {`ACTIVO`,`INACTIVO`,`MUERTO`,`VENDIDO`}.
  - `etapa` ∈ {`TERNERO`,`LEVANTE`,`CEBA`,`REPRODUCTOR`,`LACTANTE`,`SECA`,`GESTANTE`,`OTRO`}.
  - `categoria` ∈ {`CRIA`,`MADRE`,`PADRE`,`ENGORDE`,`REEMPLAZO`,`OTRO`}.
  - `nacDesde`/`nacHasta` (YYYY-MM-DD).
  - Filtros por **ubicación actual**: `finca_id`, `aprisco_id`, `area_id`.

**Retorna:** `array<assoc>` con columnas del animal + `ultima_fecha_peso`, `ultimo_peso_kg`, y nombres de finca/aprisco/área si aplican.

#### `obtenerPorId(string $animalId): ?array`

Mismo enriquecimiento que `listar`, filtrado por `animal_id`. Retorna `null` si no existe.

#### `getOptions(?string $q=null): array`

Devuelve hasta 200 registros: `[{ animal_id, label }]` (label = `identificador`), pensado para combos/autocomplete.

### Escrituras

#### `crear(array $in): string`

**Requeridos**: `identificador`, `sexo`, `especie`.  
**Opcionales**: `raza`, `color`, `fecha_nacimiento`, `estado`, `etapa_productiva`, `categoria`, `origen`, `madre_id`, `padre_id`.

**Validaciones:**

- Fechas `YYYY-MM-DD`.
- Enums en mayúsculas.
- Unicidad de `identificador` (conflictos → 409).
- FK tolerantes de padres: si no existen, se guardan como `NULL`.

**Auditoría:** usa `$_SESSION['user_id']` o el propio UUID como `created_by`; `created_at` desde `nowWithAudit()`.

**Retorna:** `animal_id` (UUID).

#### `actualizar(string $animalId, array $in): bool`

Actualiza solo **campos presentes** en `$in`. Revalida enums/fecha/unicidad. Añade `updated_at`, `updated_by`.

- 409 por **duplicado** de `identificador`.
- 400 por **entrada inválida** (sin campos, formato fecha incorrecto, enum no permitido).
- 404 implícito si el ID no existe (el método lanza excepción).

**Retorna:** `true` si ejecuta sin errores.

#### `eliminar(string $animalId): bool` (soft delete)

Marca `deleted_at`, `deleted_by` si el registro **no** estaba eliminado.  
**Retorna:** `true` si afectó filas.

---

## 4) Controlador: `AnimalController`

### Métodos de soporte
### Manejo de fotografías (nuevo)

El controlador ahora acepta peticiones `multipart/form-data` con un campo de archivo llamado `fotografia`.

**Métodos agregados:**

- `isMultipart()` → Detecta si la petición usa `multipart/form-data`.
- `saveFotoIfAny($uuid, $file)` → Guarda el archivo en `APP_ROOT/uploads/{uuid}.{ext}` (extensión validada `jpg|png|webp`, tamaño máximo 5 MB) y retorna la ruta relativa `/uploads/{uuid}.{ext}`.

**Flujos soportados:**

- **Crear (POST /animales)**: si viene archivo `fotografia`, se guarda y se actualiza `fotografia_url`.
- **Actualizar (POST /animales/{id})**: si se adjunta nueva foto, reemplaza la anterior.


- `getJsonInput(): array` → lee `php://input` y decodifica JSON.
- `jsonResponse($value, string $message='', $data=null, int $status=200)` → respuesta estándar:

```json
{ "value": true|false, "message": "texto", "data": { ... } }
```

### Endpoints

1. **GET** `/animales`  
   **Query params:**  
   `limit, offset, incluirEliminados(0|1), q, sexo, especie, estado, etapa, categoria, nacDesde, nacHasta, finca_id, aprisco_id, area_id`  
   **200** → `data: array<animal_enriquecido>`

2. **GET** `/animales/{animal_id}`  
   **200** → `data: animal_enriquecido`  
   **404** → no encontrado

3. **GET** `/animales/options?q=`  
   **200** → `data: { "data": [{ "animal_id": "...", "label": "..." }, ...] }`

4. **POST** `/animales`  
   **Body (JSON):**
   ```json
   {
     "identificador": "A-001",
     "sexo": "MACHO",
     "especie": "PORCINO",
     "raza": "Landrace",
     "color": "Blanco",
     "fecha_nacimiento": "2025-03-10",
     "estado": "ACTIVO",
     "etapa_productiva": "CEBA",
     "categoria": "ENGORDE",
     "origen": "COMPRA",
     "madre_id": null,
     "padre_id": null
   }
   ```
   **201/200** → `data: { "animal_id": "uuid" }`  
   **400** → validación (faltantes/enums/fecha)  
   **409** → `Identificador duplicado.`

5. **POST** `/animales/{animal_id}` (update parcial)  
   **Body (JSON):** cualquiera de los campos soportados.  
   **200** → `data: { "updated": true }`  
   **400/409/500** según error.

6. **DELETE** `/animales/{animal_id}`  
   **200** → `data: { "deleted": true }`  
   **400** → ya estaba eliminado o no afectó filas.

### Ejemplos `curl`

```bash
# Listar con filtros
curl -s "https://tu.host/animales?limit=20&sexo=HEMBRA&q=001"

# Crear
curl -s -X POST "https://tu.host/animales"   -H "Content-Type: application/json"   -d '{"identificador":"A-001","sexo":"MACHO","especie":"PORCINO"}'

# Actualizar (solo estado y etapa)
curl -s -X POST "https://tu.host/animales/UUID-ANIMAL"   -H "Content-Type: application/json"   -d '{"estado":"INACTIVO","etapa_productiva":"REPRODUCTOR"}'

# Eliminar (soft)
curl -s -X DELETE "https://tu.host/animales/UUID-ANIMAL"

**Soporte de fotografía:**

- `multipart/form-data`: campo de archivo `fotografia`.
- Guarda en `/uploads/{uuid}.jpg` y actualiza `fotografia_url`.
- Tamaño máximo 20 MB.

**Ejemplo:**
```bash
curl -X POST http://localhost/api/animales \
  -F "identificador=VAC-001" \
  -F "sexo=HEMBRA" \
  -F "especie=BOVINO" \
  -F "fotografia=@/ruta/foto.jpg"
```

```

---

## 5) SQL esperado (mínimo)

- `animales` con columnas usadas por el modelo:  
  `animal_id (PK)`, `identificador (UNIQUE)`, `sexo`, `especie`, `raza?`, `color?`, `fecha_nacimiento?`, `estado`, `etapa_productiva?`, `categoria?`, `origen`, `madre_id?`, `padre_id?`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`.
- `animal_pesos`: `animal_id`, `fecha_peso`, `peso_kg`, `deleted_at`.
- `animal_ubicaciones`: `animal_id`, `finca_id?`, `aprisco_id?`, `area_id?`, `fecha_desde`, `fecha_hasta NULL` para **activa**, `deleted_at`.
- `fincas`, `apriscos`, `areas` con sus PK y nombres (`nombre`, `nombre_personalizado`, `numeracion`).

> **Ajusta nombres** si tu esquema difiere.

> **Actualización de tabla (fotografía)**
>
> ```sql
> ALTER TABLE animales ADD COLUMN fotografia_url VARCHAR(255) NULL AFTER padre_id;
> ```
 El SQL de ejemplo puede usar nombres alternos (`codigo_identificacion` vs `identificador`).

---

## 6) Registro de rutas

```php
$router->get('/animales',                ['controlador' => AnimalController::class, 'accion' => 'listar']);
$router->get('/animales/{animal_id}',    ['controlador' => AnimalController::class, 'accion' => 'mostrar']);
$router->get('/animales/options',        ['controlador' => AnimalController::class, 'accion' => 'options']);
$router->post('/animales',               ['controlador' => AnimalController::class, 'accion' => 'crear']);
$router->post('/animales/{animal_id}',   ['controlador' => AnimalController::class, 'accion' => 'actualizar']);
$router->delete('/animales/{animal_id}', ['controlador' => AnimalController::class, 'accion' => 'eliminar']);
```

---

## 7) Contratos de E/S (resumen)

### **Crear**

- **Recibe (JSON):** `identificador`, `sexo`, `especie`, ... (opcionales).
- **Da:** `{ "animal_id": "uuid" }` y `message`.

### **Actualizar**

- **Recibe (JSON):** subset de campos (parche).
- **Da:** `{ "updated": true }` y `message`.

### **Eliminar**

- **Recibe:** `animal_id` por ruta.
- **Da:** `{ "deleted": true }` y `message`.

### **Listar/Mostrar**

- **Recibe:** query params / path param.
- **Da:** registros con **último peso** y **ubicación activa** resueltos.

---

## 8) Códigos de estado y errores

- `200` OK (lecturas/updates/deletes correctos).
- `201` (si lo manejas así en tu capa HTTP para `crear`).
- `400` Entrada inválida (falta campo, enum inválido, fecha inválida, update sin campos).
- `401` Sesión ausente (si tu middleware lo exige).
- `404` No encontrado (en `mostrar`, o si adaptas el modelo).
- `409` Conflicto (`Identificador duplicado.`).
- `500` Error interno (mysqli/procesamiento).

**Mensajes típicos:**

- `Falta campo requerido: identificador/sexo/especie.`
- `fecha_nacimiento inválida. Formato esperado YYYY-MM-DD.`
- `sexo inválido. Use uno de: MACHO, HEMBRA`

---

## 9) Notas de implementación

- **Enums en mayúsculas**: el modelo normaliza a `UPPER` antes de validar/guardar.
- **FK padres tolerantes**: si `madre_id`/`padre_id` no existen, se guardan como `NULL` (evita 500).
- **Ubicación activa**: se define como aquella con `fecha_hasta IS NULL` y **máxima** `fecha_desde` por `animal_id`.
- **Último peso**: se toma por `MAX(fecha_peso)`; ajusta si tu tabla usa otra columna (`fecha`).

---

## 10) Checklist de integración

- [ ] Rutas registradas en el router.
- [ ] Sesión activa con `$_SESSION['user_id']`.
- [ ] `Database`, `ClientEnvironmentInfo`, `TimezoneManager` correctamente configurados.
- [ ] Indices/UNIQUE: `animales.identificador`.
- [ ] Vistas/Frontend: consumir `GET /animales` y `GET /animales/options` para combos.
- [ ] Migraciones alineadas a nombres de columnas usadas en el código.

---

## 11) Cambios futuros sugeridos

- Paginación con `total` usando `SQL_CALC_FOUND_ROWS` o consulta aparte.
- Búsqueda por múltiples campos (`identificador`, `raza`, etc.).
- Endpoints para **pesos** y **ubicaciones** asociados (ya existen en otros módulos).
- Inclusión de **líneas genealógicas** (descendencia directa).

---

© 2025 ERP_GANADO — Módulo Animales
