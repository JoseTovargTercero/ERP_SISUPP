# Módulo **Alertas** — Documentación Técnica (.md)

> Proyecto: **ERP_GANADO**
>
> Archivo generado: `AlertaModule_Documentation.md`  
> Fecha: 2025-10-15

---

## 1) Descripción general

El módulo **Alertas** gestiona los recordatorios automáticos y manuales generados por eventos dentro del sistema (como revisiones de servicio o partos próximos).  
Permite registrar, consultar, actualizar y eliminar alertas relacionadas con los **animales** y los **períodos de servicio**.

### Funcionalidades clave

- Registro de alertas automáticas (p. ej., **REVISION_20_21**, **PROX_PARTO_117**) o manuales (**OTRA**).
- Seguimiento del estado: `PENDIENTE`, `CUMPLIDA`, `VENCIDA`, `CANCELADA`.
- Filtros por tipo, estado, fechas, período o animal.
- Auditoría completa (`created_at/by`, `updated_at/by`, `deleted_at/by`).
- Operaciones **soft delete** para preservar el historial.

---

## 2) Arquitectura y dependencias

### Archivos

- **Modelo:** `models/AlertaModel.php`
- **Controlador:** `controllers/AlertaController.php`
- **Rutas:** declaradas en tu `router` (ver §6).

### Requisitos (PHP)

- PHP 8.x con **mysqli** habilitado.
- Sesión activa (`$_SESSION['user_id']` disponible).

### Clases externas requeridas

```php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';
```

- `Database::getInstance()` → conexión MySQLi (singleton).
- `ClientEnvironmentInfo` → contexto de auditoría.
- `TimezoneManager` → aplica `SET time_zone` en la sesión MySQL.

---

## 3) Modelo: `AlertaModel`

### Métodos utilitarios

- `generateUUIDv4(): string` → genera UUID v4.
- `nowWithAudit(): array` → aplica contexto de auditoría + zona horaria; retorna `[now, env]`.
- `validarTipoAlerta(string)` → valores válidos: `REVISION_20_21`, `PROX_PARTO_117`, `OTRA`.
- `validarEstadoAlerta(string)` → valores válidos: `PENDIENTE`, `CUMPLIDA`, `VENCIDA`, `CANCELADA`.
- `validarFechaYMD(?string)` → asegura formato `YYYY-MM-DD` y fecha válida.
- `periodoExiste(?string)` → verifica existencia del `periodo_id` (si aplica).
- `animalExiste(?string)` → verifica existencia del `animal_id` (si aplica).

---

### Lecturas

#### `listar(...) : array`

Devuelve alertas con filtros opcionales:

| Filtro | Tipo | Descripción |
|--------|------|-------------|
| `periodo_id` | UUID | Filtra por período de servicio |
| `animal_id` | UUID | Filtra por animal asociado |
| `tipo_alerta` | ENUM | Tipo de alerta |
| `estado_alerta` | ENUM | Estado actual |
| `desde`, `hasta` | DATE | Rango de `fecha_objetivo` |
| `incluirEliminados` | bool | Incluye eliminadas si `true` |

**Retorna:** listado de alertas ordenadas por `fecha_objetivo ASC` y `created_at DESC`.

#### `obtenerPorId(string $alertaId)`

Devuelve una alerta específica o `null` si no existe.

---

### Escrituras

#### `crear(array $in): string`

**Requeridos:** `tipo_alerta`, `fecha_objetivo`.  
**Opcionales:** `periodo_id`, `animal_id`, `estado_alerta`, `detalle`.

**Validaciones:**
- `tipo_alerta` y `estado_alerta` válidos según enums.
- Formato de `fecha_objetivo` (`YYYY-MM-DD`).
- FK de `periodo_id` y `animal_id` válidas (si se proporcionan).

**Auditoría:** agrega `created_at/by` y `updated_*` nulos.  
**Retorna:** `alerta_id` (UUID).

---

#### `actualizar(string $alertaId, array $in): bool`

Permite modificar campos existentes:

`tipo_alerta`, `periodo_id`, `animal_id`, `fecha_objetivo`, `estado_alerta`, `detalle`.

Agrega `updated_at/by` automáticamente.

**Errores posibles:**
- `400`: Validación de enum o fecha.
- `404`: Alerta inexistente o eliminada.
- `500`: Error de ejecución SQL.

---

#### `cambiarEstado(string $alertaId, string $nuevoEstado): bool`

Atajo para actualizar únicamente `estado_alerta`.  
Usado en endpoint `/alertas/{id}/estado`.

Estados válidos: `PENDIENTE`, `CUMPLIDA`, `VENCIDA`, `CANCELADA`.

---

#### `eliminar(string $alertaId): bool`

Eliminación lógica (`soft delete`): marca `deleted_at`, `deleted_by`.  
**Retorna:** `true` si afectó filas.

---

## 4) Controlador: `AlertaController`

### Métodos principales

| Método | Ruta | Descripción |
|--------|------|--------------|
| `listar()` | `GET /alertas` | Listado con filtros |
| `mostrar()` | `GET /alertas/{alerta_id}` | Detalle de una alerta |
| `crear()` | `POST /alertas` | Crea una nueva alerta |
| `actualizar()` | `POST /alertas/{alerta_id}` | Modifica campos parciales |
| `cambiarEstado()` | `POST /alertas/{alerta_id}/estado` | Cambia solo el estado |
| `eliminar()` | `DELETE /alertas/{alerta_id}` | Soft delete |

### Estructura de respuesta estándar

```json
{
  "value": true|false,
  "message": "texto descriptivo",
  "data": { ... }
}
```

---

## 5) SQL esperado

```sql
CREATE TABLE alertas (
  alerta_id        CHAR(36)     NOT NULL PRIMARY KEY,
  tipo_alerta      ENUM('REVISION_20_21','PROX_PARTO_117','OTRA') NOT NULL,
  periodo_id       CHAR(36)     NULL,
  animal_id        CHAR(36)     NULL,
  fecha_objetivo   DATE         NOT NULL,
  estado_alerta    ENUM('PENDIENTE','CUMPLIDA','VENCIDA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
  detalle          VARCHAR(255) NULL,
  created_at       DATETIME     NOT NULL,
  created_by       CHAR(36)     NOT NULL,
  updated_at       DATETIME     NULL,
  updated_by       CHAR(36)     NULL,
  deleted_at       DATETIME     NULL,
  deleted_by       CHAR(36)     NULL,
  CONSTRAINT fk_alerta_periodo FOREIGN KEY (periodo_id) REFERENCES periodos_servicio(periodo_id),
  CONSTRAINT fk_alerta_animal  FOREIGN KEY (animal_id)  REFERENCES animales(animal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_alertas_fecha_objetivo ON alertas (fecha_objetivo);
CREATE INDEX idx_alertas_estado_alerta  ON alertas (estado_alerta);
CREATE INDEX idx_alertas_tipo_alerta    ON alertas (tipo_alerta);
```

---

## 6) Registro de rutas

```php
$router->get('/alertas', ['controlador' => AlertaController::class, 'accion' => 'listar']);
$router->get('/alertas/{alerta_id}', ['controlador' => AlertaController::class, 'accion' => 'mostrar']);
$router->post('/alertas', ['controlador' => AlertaController::class, 'accion' => 'crear']);
$router->post('/alertas/{alerta_id}', ['controlador' => AlertaController::class, 'accion' => 'actualizar']);
$router->post('/alertas/{alerta_id}/estado', ['controlador' => AlertaController::class, 'accion' => 'cambiarEstado']);
$router->delete('/alertas/{alerta_id}', ['controlador' => AlertaController::class, 'accion' => 'eliminar']);
```

---

## 7) Contratos de E/S (resumen)

### Crear

```json
POST /alertas
{
  "tipo_alerta": "REVISION_20_21",
  "fecha_objetivo": "2025-10-30",
  "periodo_id": "uuid",
  "animal_id": "uuid",
  "detalle": "Revisión programada 20 días después"
}
→ 201 OK
{ "alerta_id": "uuid" }
```

### Actualizar

```json
POST /alertas/{id}
{ "estado_alerta": "CUMPLIDA", "detalle": "Revisión realizada" }
→ 200 OK
{ "updated": true }
```

### Cambiar estado

```json
POST /alertas/{id}/estado
{ "estado_alerta": "CANCELADA" }
→ 200 OK
{ "updated": true }
```

### Eliminar

```bash
DELETE /alertas/{id}
→ 200 OK
{ "deleted": true }
```

---

## 8) Códigos de estado y errores

| Código | Situación |
|---------|------------|
| 200 | Lectura o actualización exitosa |
| 201 | Creación exitosa |
| 400 | Entrada inválida (falta campo, enum o formato) |
| 404 | Registro no encontrado |
| 409 | Conflicto (duplicado o FK) |
| 500 | Error interno o SQL |

---

## 9) Checklist de integración

- [x] Modelo y controlador creados.
- [x] Rutas registradas en el router.
- [x] Base de datos con tabla `alertas` y FKs activas.
- [x] Sesión iniciada (`$_SESSION['user_id']`).
- [x] `ClientEnvironmentInfo` y `TimezoneManager` configurados.
- [x] Endpoints probados vía Postman o `curl`.

---

© 2025 ERP_GANADO — Módulo Alertas
