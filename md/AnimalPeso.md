
# Módulo **Animal Pesos** — Documentación Técnica (.md)

> Proyecto: **ERP_GANADO**  
> Archivo: `AnimalPesoModule_Documentation.md`  
> Fecha: 2025-10-05

---

## 1) Descripción general

Este módulo gestiona los **registros de peso** de cada animal. Incluye:
- **Modelo** `AnimalPesoModel` con validaciones (fecha, unidad, rango), conversión automática de libras a kilogramos y auditoría contextual.
- **Controlador** `AnimalPesoController` que expone endpoints REST en JSON para **listar**, **mostrar**, **crear**, **actualizar** y **eliminar lógicamente** registros de peso.

**Dato persistido:** todo peso se almacena en **kilogramos** en la columna `peso_kg`, sin perder la fecha ni el método/observaciones.

---

## 2) Dependencias y arquitectura

### Archivos
- Modelo: `models/AnimalPesoModel.php`
- Controlador: `controllers/AnimalPesoController.php`
- Rutas: definidas en el router (ver §7).

### Requisitos (PHP)
- PHP 8.x con **mysqli**.
- Sesión activa (`$_SESSION['user_id']` utilizado para `created_by`, `updated_by`, `deleted_by`).

### Clases externas usadas por el modelo
```php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';
```
- `Database::getInstance()` → conexión **mysqli** (singleton).
- `ClientEnvironmentInfo` → aplica **contexto de auditoría** y entrega `getCurrentDatetime()`.
- `TimezoneManager` → aplica la zona horaria a la conexión.

> `nowWithAudit()` usa `APP_ROOT . '/app/config/geolite.mmdb'` para enrutar GeoLite y sellar auditoría/fechas.

### Tablas relacionadas
- `animal_pesos` (principal).
- `animales` (FK de `animal_id`).

---

## 3) Controlador: `AnimalPesoController`

### Respuesta estándar
```json
{ "value": true|false, "message": "texto", "data": { ... } }
```

### Endpoints

1. **GET** `/animal_pesos`  
   **Query params:** `animal_id`, `desde (YYYY-MM-DD)`, `hasta (YYYY-MM-DD)`, `incluirEliminados (0|1)`, `limit`, `offset`  
   **200** → `data: array<registro_peso>`

2. **GET** `/animal_pesos/{animal_peso_id}`  
   **200** → `data: registro_peso` (incluye `animal_identificador`)  
   **404** → no encontrado

3. **POST** `/animal_pesos`  
   **Body (JSON) requerido por el **modelo**:**  
   `animal_id`, `fecha_peso (YYYY-MM-DD)`, `peso_kg` *(valor de entrada)*, `unidad ('KG'|'LB')`  
   **Opcionales:** `metodo`, `observaciones`  
   **201/200** → `data: { "animal_peso_id": "uuid" }`  
   **400** → validación (faltantes, formato de fecha, unidad inválida, rango)  
   **409** → conflictos (FK/duplicado por combinación animal-fecha, según constraint)

   > **Nota importante:** aunque el comentario del endpoint menciona `peso`, el **método `crear()` del modelo** espera la clave **`peso_kg`** como entrada y la **convierte** si la `unidad` es `'LB'`. Ver §5.2.

4. **POST** `/animal_pesos/{animal_peso_id}` (update parcial)  
   **Body (JSON):**  
   - `fecha_peso?`  
   - `peso?` **y** `unidad?`  *(si envías `peso`, debes enviar también `unidad`)*  
   - `metodo?`, `observaciones?`  
   **200** → `data: { "updated": true }`  
   **400/409/500** según error.

5. **DELETE** `/animal_pesos/{animal_peso_id}`  
   **200** → `data: { "deleted": true }`  
   **400** → ya estaba eliminado o no afectó filas.

---

## 4) Modelo: `AnimalPesoModel`

### 4.1 Utilidades
- `generateUUIDv4()` → genera UUID v4.
- `nowWithAudit()` → aplica auditoría y TZ; retorna `[now, env]`.
- `animalExiste(animalId)` → valida FK contra `animales`.
- `validarFecha('YYYY-MM-DD')` → regex + `checkdate`.
- `normalizarPeso(valor, unidad)` → valida `unidad ∈ {'KG','LB'}` y **convierte a KG** si viene en LB (factor 0.45359237). Rango permitido `(0, 9999]`.

### 4.2 Lecturas
#### `listar(limit, offset, incluirEliminados, animalId?, desde?, hasta?) : array`
- Filtra por `animal_id`, rango de fechas (`fecha_peso`), y eliminados.
- **Orden:** `fecha_peso DESC, created_at DESC`.
- **Devuelve:** columnas del peso + `animal_identificador`.

#### `obtenerPorId(id) : ?array`
- Retorna un único registro (o `null`), incluyendo campos de auditoría/eliminación y `animal_identificador`.

### 4.3 Escrituras
#### `crear(array $data) : string`
**Requeridos (según implementación actual):**
- `animal_id`
- `fecha_peso` (**YYYY-MM-DD**)
- `peso_kg` *(valor a convertir si `unidad` = 'LB')*
- `unidad` ∈ {'KG','LB'}

**Opcionales:** `metodo`, `observaciones`.

**Proceso:**
- Verifica existencia de `animal_id` y formato de `fecha_peso`.
- Valida y **normaliza** peso a **kilogramos** → se guarda en `peso_kg`.
- Auditoría: establece `created_at/by` (y `updated_*` = `NULL` en inserción).

**Retorna:** `animal_peso_id` (UUID).

> **Por qué `peso_kg` en la entrada?**  
> El método `crear()` está escrito para leer la clave de entrada **`peso_kg`** (y una clave separada `unidad`), ejecutando la conversión internamente. Puedes enviar `peso_kg` en KG directamente, o enviar un valor en **libras** + `unidad: 'LB'` y el método lo convertirá a KG antes de guardarlo.

#### `actualizar(string $id, array $data) : bool`
Campos soportados:
- `fecha_peso?` (valida fecha)
- `peso?` **y** `unidad?` en conjunto → recalcula `peso_kg`
- `metodo?`, `observaciones?`
- Siempre añade `updated_at/by`

**Errores típicos:**
- `400` si envías `peso` sin `unidad` (o viceversa).
- `409` si choca con unicidad (e.g., misma fecha/animal).

#### `eliminar(string $id) : bool` (soft delete)
- Marca `deleted_at/by`.  
- Devuelve `true` si afectó filas.

---

## 5) Contratos de E/S (resumen)

### 5.1 Crear
- **Recibe (JSON):**
  ```json
  {
    "animal_id": "UUID-ANIMAL",
    "fecha_peso": "2025-09-10",
    "peso_kg": 250.5,
    "unidad": "KG",
    "metodo": "BALANZA",
    "observaciones": "Peso inicial"
  }
  ```
  También válido (en libras, conversión interna a KG):
  ```json
  { "animal_id":"UUID-A","fecha_peso":"2025-09-10","peso_kg":552.0,"unidad":"LB" }
  ```
- **Da:** `{ "animal_peso_id": "uuid" }`.

### 5.2 Actualizar
- **Recibe (JSON, ejemplos):**
  - Cambiar fecha:
    ```json
    { "fecha_peso": "2025-09-15" }
    ```
  - Cambiar peso (debe incluir `unidad`):
    ```json
    { "peso": 260.2, "unidad": "KG" }
    ```
  - Cambiar método/observaciones:
    ```json
    { "metodo": "BALANZA", "observaciones": "Control mensual" }
    ```
- **Da:** `{ "updated": true }`.

### 5.3 Eliminar
- **Recibe:** `animal_peso_id` por ruta.  
- **Da:** `{ "deleted": true }`.

### 5.4 Listar/Mostrar
- **Recibe:** query params / path param.  
- **Da:** registros con `animal_identificador` y pesos en **KG**.

---

## 6) SQL/Esquema mínimo esperado

- `animal_pesos` columnas:  
  `animal_peso_id (PK)`, `animal_id (FK)`, `fecha_peso DATE`, `peso_kg DECIMAL/DOUBLE`, `metodo VARCHAR?`, `observaciones TEXT?`,  
  `created_at`, `created_by`, `updated_at?`, `updated_by?`, `deleted_at?`, `deleted_by?`.
- `animales` con `animal_id` existente y **no** eliminado.

**Índices sugeridos:**
- `(animal_id, fecha_peso)` (único si deseas evitar duplicados por fecha).
- `fecha_peso` para ordenamientos frecuentes.

---

## 7) Registro de rutas

```php
$router->get('/animal_pesos',                        ['controlador' => AnimalPesoController::class, 'accion' => 'listar']);
$router->get('/animal_pesos/{animal_peso_id}',       ['controlador' => AnimalPesoController::class, 'accion' => 'mostrar']);
$router->post('/animal_pesos',                       ['controlador' => AnimalPesoController::class, 'accion' => 'crear']);
$router->post('/animal_pesos/{animal_peso_id}',      ['controlador' => AnimalPesoController::class, 'accion' => 'actualizar']);
$router->delete('/animal_pesos/{animal_peso_id}',    ['controlador' => AnimalPesoController::class, 'accion' => 'eliminar']);
```

---

## 8) Ejemplos `curl`

```bash
# Listar pesos por rango para un animal
curl -s "https://tu.host/animal_pesos?animal_id=UUID-ANIMAL&desde=2025-09-01&hasta=2025-09-30&limit=50"

# Crear peso en KG
curl -s -X POST "https://tu.host/animal_pesos"   -H "Content-Type: application/json"   -d '{"animal_id":"UUID-A","fecha_peso":"2025-09-10","peso_kg":250.5,"unidad":"KG","metodo":"BALANZA"}'

# Crear peso en LB (se convierte a KG automáticamente)
curl -s -X POST "https://tu.host/animal_pesos"   -H "Content-Type: application/json"   -d '{"animal_id":"UUID-A","fecha_peso":"2025-09-10","peso_kg":552,"unidad":"LB"}'

# Actualizar solo el peso (incluye unidad)
curl -s -X POST "https://tu.host/animal_pesos/UUID-PESO"   -H "Content-Type: application/json"   -d '{"peso":260.2,"unidad":"KG"}'

# Eliminar (soft)
curl -s -X DELETE "https://tu.host/animal_pesos/UUID-PESO"
```

---

## 9) Manejo de errores y códigos de estado

- **200** OK (lecturas/updates/deletes correctos).
- **201** (si tu capa HTTP lo usa para creación).
- **400** Entrada inválida (faltantes, fecha inválida, unidad inválida, rango de peso, update vacío, falta `unidad` en cambio de peso).
- **404** No encontrado (en `mostrar`).  
- **409** Conflicto (duplicado por combinación animal-fecha, violación FK en inserción).  
- **500** Error interno (mysqli/procesamiento).

**Mensajes frecuentes:**
- `Falta campo requerido: animal_id/fecha_peso/peso_kg/unidad.`
- `fecha_peso inválida. Formato esperado YYYY-MM-DD.`
- `unidad inválida. Use 'KG' o 'LB'.`
- `peso fuera de rango razonable.`
- `Si actualizas el peso debes enviar ambos campos: 'peso' y 'unidad'.`

---

## 10) Checklist de integración

- [ ] Rutas registradas.
- [ ] Sesión activa (`$_SESSION['user_id']`).
- [ ] `Database`, `ClientEnvironmentInfo`, `TimezoneManager` configurados.
- [ ] Índice único opcional `(animal_id, fecha_peso)` para evitar duplicados.
- [ ] Frontend alinea claves: **crear → `peso_kg` + `unidad`**, **actualizar → `peso` + `unidad`**.

---

© 2025 ERP_GANADO — Módulo Animal Pesos
