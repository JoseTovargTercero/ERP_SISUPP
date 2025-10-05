<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class AnimalPesoModel
{
    private $db;
    private $table = 'animal_pesos';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /* ============ Utilidades ============ */

    private function generateUUIDv4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function nowWithAudit(): array
    {
        $env = new ClientEnvironmentInfo(APP_ROOT . '/app/config/geolite.mmdb');
        $env->applyAuditContext($this->db, 0);
        $tzManager = new TimezoneManager($this->db);
        $tzManager->applyTimezone();
        return [$env->getCurrentDatetime(), $env];
    }

    private function animalExiste(string $animalId): bool
    {
        $sql = "SELECT 1 FROM animales WHERE animal_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar verificación de animal: " . $this->db->error);
        $stmt->bind_param('s', $animalId);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }

    private function validarFecha(string $fechaYmd): void
    {
        // YYYY-MM-DD
        $ok = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaYmd) === 1;
        if (!$ok) {
            throw new InvalidArgumentException("fecha_peso inválida. Formato esperado YYYY-MM-DD.");
        }
        // Validar calendario
        [$y,$m,$d] = array_map('intval', explode('-', $fechaYmd));
        if (!checkdate($m,$d,$y)) {
            throw new InvalidArgumentException("fecha_peso no es una fecha válida.");
        }
    }

    private function normalizarPeso(float $valor, string $unidad): float
    {
        $unidad = strtoupper(trim($unidad));
        if (!in_array($unidad, ['KG','LB'], true)) {
            throw new InvalidArgumentException("unidad inválida. Use 'KG' o 'LB'.");
        }
        if ($valor <= 0 || $valor > 9999) {
            throw new InvalidArgumentException("peso fuera de rango razonable.");
        }
        // Convertir a KG si viene en LB
        return $unidad === 'LB' ? $valor * 0.45359237 : $valor;
    }

    /* ============ Lecturas ============ */

    /**
     * Lista registros de peso.
     * Filtros: animal_id, desde (YYYY-MM-DD), hasta (YYYY-MM-DD), incluirEliminados.
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $animalId = null,
        ?string $desde = null,
        ?string $hasta = null
    ): array {
        $where  = [];
        $params = [];
        $types  = '';

        $where[] = $incluirEliminados ? 'p.deleted_at IS NOT NULL OR p.deleted_at IS NULL' : 'p.deleted_at IS NULL';

        if ($animalId) {
            $where[]  = 'p.animal_id = ?';
            $params[] = $animalId;
            $types   .= 's';
        }
        if ($desde) {
            $this->validarFecha($desde);
            $where[]  = 'p.fecha_peso >= ?';
            $params[] = $desde;
            $types   .= 's';
        }
        if ($hasta) {
            $this->validarFecha($hasta);
            $where[]  = 'p.fecha_peso <= ?';
            $params[] = $hasta;
            $types   .= 's';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT
                    p.animal_peso_id,
                    p.animal_id,
                    a.identificador AS animal_identificador,
                    p.fecha_peso,
                    p.peso_kg,
                    p.metodo,
                    p.observaciones,
                    p.created_at,
                    p.created_by,
                    p.updated_at,
                    p.updated_by
                FROM {$this->table} p
                LEFT JOIN animales a ON a.animal_id = p.animal_id
                WHERE {$whereSql}
                ORDER BY p.fecha_peso DESC, p.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $types   .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res  = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function obtenerPorId(string $id): ?array
    {
        $sql = "SELECT
                    p.animal_peso_id,
                    p.animal_id,
                    a.identificador AS animal_identificador,
                    p.fecha_peso,
                    p.peso_kg,
                    p.metodo,
                    p.observaciones,
                    p.created_at,
                    p.created_by,
                    p.updated_at,
                    p.updated_by,
                    p.deleted_at,
                    p.deleted_by
                FROM {$this->table} p
                LEFT JOIN animales a ON a.animal_id = p.animal_id
                WHERE p.animal_peso_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crear registro de peso.
     * Requeridos: animal_id, fecha_peso (YYYY-MM-DD), peso (numérico), unidad ('KG'|'LB')
     * Opcionales: metodo (string corto), observaciones (texto)
     * Nota: se guarda en peso_kg (conversión automática si unidad=LB)
     */
    public function crear(array $data): string
{
    foreach (['animal_id','fecha_peso','peso_kg','unidad'] as $k) {
        if (!isset($data[$k]) || $data[$k] === '') {
            throw new InvalidArgumentException("Falta campo requerido: {$k}.");
        }
    }

    $animalId   = (string) trim((string)$data['animal_id']);
    $fechaPeso  = (string) trim((string)$data['fecha_peso']);
    $pesoInput  = (float) $data['peso_kg'];
    $unidad     = (string) $data['unidad'];

    if (!$this->animalExiste($animalId)) {
        throw new RuntimeException('El animal especificado no existe o está eliminado.');
    }
    $this->validarFecha($fechaPeso);

    // IMPORTANTE: calcular en variable (no pasar expresiones a bind_param)
    $pesoKg = $this->normalizarPeso($pesoInput, $unidad); // devuelve float (o numeric)

    // Variables simples (ok si son NULL)
    $metodo        = isset($data['metodo']) ? (string) trim((string)$data['metodo']) : null;
    $observaciones = isset($data['observaciones']) ? (string) trim((string)$data['observaciones']) : null;

    $this->db->begin_transaction();
    try {
        [$now, $env] = $this->nowWithAudit();
        $uuid    = $this->generateUUIDv4();
        $actorId = $_SESSION['user_id'] ?? $uuid;

        $sql = "INSERT INTO {$this->table}
                (animal_peso_id, animal_id, fecha_peso, peso_kg, metodo, observaciones,
                 created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);
        }

        // Opción A: bind con 'd' para el float (8 placeholders => 8 tipos)
        // 1: s uuid
        // 2: s animal_id
        // 3: s fecha_peso
        // 4: d peso_kg
        // 5: s metodo
        // 6: s observaciones
        // 7: s created_at
        // 8: s created_by
        $types = 'sssdssss';

        // IMPORTANTE: pasar **variables**; nada de (float)$data['x'], trim(...), etc.
        $stmt->bind_param(
            $types,
            $uuid,
            $animalId,
            $fechaPeso,
            $pesoKg,        // variable float
            $metodo,        // puede ser NULL (mysqli lo acepta con 's')
            $observaciones, // puede ser NULL
            $now,
            $actorId
        );

        if (!$stmt->execute()) {
            $err = strtolower($stmt->error);
            $stmt->close();
            $this->db->rollback();

            if (str_contains($err, 'foreign key')) {
                throw new RuntimeException('El animal no existe (violación de clave foránea).');
            }
            if (str_contains($err, 'duplicate')) {
                throw new RuntimeException('Ya existe un registro de peso para este animal en esa fecha.');
            }
            throw new mysqli_sql_exception("Error al ejecutar inserción: " . $err);
        }

        $stmt->close();
        $this->db->commit();
        return $uuid;
    } catch (\Throwable $e) {
        $this->db->rollback();
        throw $e;
    }
}


    /**
     * Actualiza campos: fecha_peso?, peso/unidad?, metodo?, observaciones?
     * Si se envían 'peso' y 'unidad', se recalcula y guarda en peso_kg.
     */
    public function actualizar(string $id, array $data): bool
    {
        $campos = [];
        $params = [];
        $types  = '';

        if (isset($data['fecha_peso'])) {
            $this->validarFecha((string)$data['fecha_peso']);
            $campos[] = 'fecha_peso = ?';
            $params[] = (string)$data['fecha_peso'];
            $types   .= 's';
        }

        if (isset($data['peso']) && isset($data['unidad'])) {
            $pesoKg = $this->normalizarPeso((float)$data['peso'], (string)$data['unidad']);
            $campos[] = 'peso_kg = ?';
            $params[] = $pesoKg;
            $types   .= 'd';
        } elseif (isset($data['peso']) xor isset($data['unidad'])) {
            throw new InvalidArgumentException("Si actualizas el peso debes enviar ambos campos: 'peso' y 'unidad'.");
        }

        if (array_key_exists('metodo', $data)) {
            $campos[] = 'metodo = ?';
            $params[] = $data['metodo'] !== null ? trim((string)$data['metodo']) : null;
            $types   .= 's';
        }
        if (array_key_exists('observaciones', $data)) {
            $campos[] = 'observaciones = ?';
            $params[] = $data['observaciones'] !== null ? trim((string)$data['observaciones']) : null;
            $types   .= 's';
        }

        if (empty($campos)) {
            throw new InvalidArgumentException('No hay campos para actualizar.');
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $id;

        $campos[] = 'updated_at = ?';
        $params[] = $now;    $types .= 's';
        $campos[] = 'updated_by = ?';
        $params[] = $actorId; $types .= 's';

        $sql = "UPDATE {$this->table}
                SET " . implode(', ', $campos) . "
                WHERE animal_peso_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $types   .= 's';
        $params[] = $id;

        $stmt->bind_param($types, ...$params);
        $ok  = $stmt->execute();
        $err = strtolower($stmt->error);
        $stmt->close();

        if (!$ok) {
            if (str_contains($err, 'duplicate')) {
                throw new RuntimeException('Conflicto de unicidad (ej: mismo animal y fecha).');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    /**
     * Eliminación lógica (soft delete)
     */
    public function eliminar(string $id): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $id;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE animal_peso_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
