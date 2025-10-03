<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class FincaModel
{
    private $db;
    private $table = 'fincas';

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

    /**
     * Prepara zona horaria y contexto de auditoría y devuelve [fechaHoraActual, env].
     */
    private function nowWithAudit(): array
    {
        $env = new ClientEnvironmentInfo(APP_ROOT . '/app/config/geolite.mmdb');
        // userId=0 si aún no hay sesión; lo importante es setear contexto y tz
        $env->applyAuditContext($this->db, 0);
        $tzManager = new TimezoneManager($this->db);
        $tzManager->applyTimezone();
        return [$env->getCurrentDatetime(), $env];
    }

    /* ============ Lecturas ============ */

    /**
     * Lista fincas (por defecto excluye eliminadas lógicamente).
     */
    public function listar(int $limit = 100, int $offset = 0, bool $incluirEliminados = false): array
    {
        $where = $incluirEliminados ? '1=1' : 'deleted_at IS NULL';
        $sql = "SELECT finca_id, nombre, ubicacion, estado,
                       created_at, created_by, updated_at, updated_by
                FROM {$this->table}
                WHERE {$where}
                ORDER BY created_at DESC, nombre ASC
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $res  = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }
public function getOptions()
{
    $sql = "SELECT finca_id, nombre 
            FROM {$this->table} 
            WHERE deleted_at IS NULL 
            ORDER BY nombre ASC";
    $stmt = $this->db->query($sql);
    return $stmt->fetch_all(MYSQLI_ASSOC);
}

    /**
     * Obtiene una finca por ID.
     */
    public function obtenerPorId(string $fincaId): ?array
    {
        $sql = "SELECT finca_id, nombre, ubicacion, estado,
                       created_at, created_by, updated_at, updated_by, deleted_at, deleted_by
                FROM {$this->table}
                WHERE finca_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $fincaId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crea una finca.
     * Requeridos: nombre
     * Opcionales: ubicacion, estado('ACTIVA'|'INACTIVA')
     */
    public function crear(array $data): string
    {
        if (empty($data['nombre'])) {
            throw new InvalidArgumentException('Falta el campo requerido: nombre.');
        }
        $nombre    = trim($data['nombre']);
        $ubicacion = isset($data['ubicacion']) ? trim((string)$data['ubicacion']) : null;
        $estado    = isset($data['estado']) && in_array($data['estado'], ['ACTIVA','INACTIVA'], true)
                    ? $data['estado'] : 'ACTIVA';

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();

            $uuid    = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                    (finca_id, nombre, ubicacion, estado,
                     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            $stmt->bind_param('ssssss', $uuid, $nombre, $ubicacion, $estado, $now, $actorId);

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                $this->db->rollback();

                if (str_contains(strtolower($err), 'duplicate')) {
                    throw new RuntimeException('Una finca con ese nombre ya existe (índice único).');
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
     * Actualiza campos explícitos: nombre, ubicacion, estado.
     */
    public function actualizar(string $fincaId, array $data): bool
    {
        $campos = [];
        $params = [];
        $types  = '';

        if (isset($data['nombre'])) {
            $campos[] = 'nombre = ?';
            $params[] = trim((string)$data['nombre']);
            $types   .= 's';
        }
        if (array_key_exists('ubicacion', $data)) {
            $campos[] = 'ubicacion = ?';
            $params[] = $data['ubicacion'] !== null ? trim((string)$data['ubicacion']) : null;
            $types   .= 's';
        }
        if (isset($data['estado'])) {
            $estado = (string)$data['estado'];
            if (!in_array($estado, ['ACTIVA','INACTIVA'], true)) {
                throw new InvalidArgumentException("Valor de estado inválido. Use 'ACTIVA' o 'INACTIVA'.");
            }
            $campos[] = 'estado = ?';
            $params[] = $estado;
            $types   .= 's';
        }

        if (empty($campos)) {
            throw new InvalidArgumentException('No hay campos para actualizar.');
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $fincaId;

        $campos[] = 'updated_at = ?';
        $params[] = $now;    $types .= 's';
        $campos[] = 'updated_by = ?';
        $params[] = $actorId; $types .= 's';

        $sql = "UPDATE {$this->table}
                SET " . implode(', ', $campos) . "
                WHERE finca_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $types   .= 's';
        $params[] = $fincaId;

        $stmt->bind_param($types, ...$params);
        $ok  = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            if (str_contains(strtolower($err), 'duplicate')) {
                throw new RuntimeException('Ya existe otra finca con ese nombre.');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    /**
     * Actualiza solo el estado ('ACTIVA'|'INACTIVA').
     */
    public function actualizarEstado(string $fincaId, string $estado): bool
    {
        if (!in_array($estado, ['ACTIVA','INACTIVA'], true)) {
            throw new InvalidArgumentException("Valor de estado inválido. Use 'ACTIVA' o 'INACTIVA'.");
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $fincaId;

        $sql = "UPDATE {$this->table}
                SET estado = ?, updated_at = ?, updated_by = ?
                WHERE finca_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización de estado: " . $this->db->error);

        $stmt->bind_param('ssss', $estado, $now, $actorId, $fincaId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Eliminación lógica (soft delete).
     */
    public function eliminar(string $fincaId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $fincaId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE finca_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $fincaId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
