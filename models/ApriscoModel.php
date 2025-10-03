<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class ApriscoModel
{
    private $db;
    private $table = 'apriscos';

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

    private function fincaExiste(string $fincaId): bool
    {
        $sql = "SELECT 1 FROM fincas WHERE finca_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar verificación de finca: " . $this->db->error);
        $stmt->bind_param('s', $fincaId);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }

    /* ============ Lecturas ============ */

    /**
     * Lista apriscos (por defecto excluye eliminados).
     * Filtros opcionales: finca_id
     */
    public function listar(int $limit = 100, int $offset = 0, bool $incluirEliminados = false, ?string $fincaId = null): array
    {
        $where = [];
        $params = [];
        $types  = '';

        if (!$incluirEliminados) {
            $where[] = 'a.deleted_at IS NULL';
        } else {
            $where[] = '1=1';
        }
        if ($fincaId) {
            $where[] = 'a.finca_id = ?';
            $params[] = $fincaId;
            $types   .= 's';
        }
        $whereSql = implode(' AND ', $where);

        $sql = "SELECT a.aprisco_id, a.finca_id, f.nombre AS nombre_finca,
                       a.nombre, a.estado,
                       a.created_at, a.created_by, a.updated_at, a.updated_by
                FROM {$this->table} a
                LEFT JOIN fincas f ON f.finca_id = a.finca_id
                WHERE {$whereSql}
                ORDER BY a.created_at DESC, a.nombre ASC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res  = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }
public function getOptions($fincaId = null)
{
    $sql = "SELECT aprisco_id, nombre 
            FROM {$this->table} 
            WHERE deleted_at IS NULL";
    $params = [];

    if ($fincaId) {
        $sql .= " AND finca_id = ?";
        $stmt = $this->db->prepare($sql . " ORDER BY nombre ASC");
        $stmt->bind_param("s", $fincaId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $sql .= " ORDER BY nombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetch_all(MYSQLI_ASSOC);
    }
}

    public function obtenerPorId(string $apriscoId): ?array
    {
        $sql = "SELECT a.aprisco_id, a.finca_id, f.nombre AS nombre_finca,
                       a.nombre, a.estado,
                       a.created_at, a.created_by, a.updated_at, a.updated_by,
                       a.deleted_at, a.deleted_by
                FROM {$this->table} a
                LEFT JOIN fincas f ON f.finca_id = a.finca_id
                WHERE a.aprisco_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $apriscoId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crea un aprisco.
     * Requeridos: finca_id, nombre
     * Opcional: estado ('ACTIVO'|'INACTIVO')
     */
    public function crear(array $data): string
    {
        if (empty($data['finca_id']) || empty($data['nombre'])) {
            throw new InvalidArgumentException('Faltan campos requeridos: finca_id, nombre.');
        }

        $fincaId = trim((string)$data['finca_id']);
        $nombre  = trim((string)$data['nombre']);
        $estado  = isset($data['estado']) && in_array($data['estado'], ['ACTIVO','INACTIVO'], true)
                 ? $data['estado'] : 'ACTIVO';

        if (!$this->fincaExiste($fincaId)) {
            throw new RuntimeException('La finca especificada no existe o está eliminada.');
        }

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();

            $uuid    = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                    (aprisco_id, finca_id, nombre, estado,
                     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            $stmt->bind_param('ssssss', $uuid, $fincaId, $nombre, $estado, $now, $actorId);

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                $this->db->rollback();
                if (str_contains(strtolower($err), 'foreign key')) {
                    throw new RuntimeException('La finca no existe (violación de clave foránea).');
                }
                if (str_contains(strtolower($err), 'duplicate')) {
                    throw new RuntimeException('Ya existe un aprisco con ese nombre en la misma finca.');
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
     * Actualiza campos explícitos: finca_id, nombre, estado.
     */
    public function actualizar(string $apriscoId, array $data): bool
    {
        $campos = [];
        $params = [];
        $types  = '';

        if (isset($data['finca_id'])) {
            $nuevoFinca = trim((string)$data['finca_id']);
            if (!$this->fincaExiste($nuevoFinca)) {
                throw new InvalidArgumentException('finca_id no válido (no existe o está eliminado).');
            }
            $campos[] = 'finca_id = ?';
            $params[] = $nuevoFinca;
            $types   .= 's';
        }
        if (isset($data['nombre'])) {
            $campos[] = 'nombre = ?';
            $params[] = trim((string)$data['nombre']);
            $types   .= 's';
        }
        if (isset($data['estado'])) {
            $estado = (string)$data['estado'];
            if (!in_array($estado, ['ACTIVO','INACTIVO'], true)) {
                throw new InvalidArgumentException("Valor de estado inválido. Use 'ACTIVO' o 'INACTIVO'.");
            }
            $campos[] = 'estado = ?';
            $params[] = $estado;
            $types   .= 's';
        }

        if (empty($campos)) {
            throw new InvalidArgumentException('No hay campos para actualizar.');
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $apriscoId;

        $campos[] = 'updated_at = ?';
        $params[] = $now;    $types .= 's';
        $campos[] = 'updated_by = ?';
        $params[] = $actorId; $types .= 's';

        $sql = "UPDATE {$this->table}
                SET " . implode(', ', $campos) . "
                WHERE aprisco_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $types   .= 's';
        $params[] = $apriscoId;

        $stmt->bind_param($types, ...$params);
        $ok  = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            if (str_contains(strtolower($err), 'foreign key')) {
                throw new RuntimeException('La finca no existe (violación de clave foránea).');
            }
            if (str_contains(strtolower($err), 'duplicate')) {
                throw new RuntimeException('Conflicto de unicidad (nombre por finca).');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    /**
     * Actualiza solo el estado ('ACTIVO'|'INACTIVO').
     */
    public function actualizarEstado(string $apriscoId, string $estado): bool
    {
        if (!in_array($estado, ['ACTIVO','INACTIVO'], true)) {
            throw new InvalidArgumentException("Valor de estado inválido. Use 'ACTIVO' o 'INACTIVO'.");
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $apriscoId;

        $sql = "UPDATE {$this->table}
                SET estado = ?, updated_at = ?, updated_by = ?
                WHERE aprisco_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización de estado: " . $this->db->error);

        $stmt->bind_param('ssss', $estado, $now, $actorId, $apriscoId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Eliminación lógica (soft delete).
     */
    public function eliminar(string $apriscoId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $apriscoId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE aprisco_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $apriscoId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
