<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class AreaModel
{
    private $db;
    private $table = 'areas';

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

    private function apriscoExiste(string $apriscoId): bool
    {
        $sql = "SELECT 1 FROM apriscos WHERE aprisco_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar verificación de aprisco: " . $this->db->error);
        $stmt->bind_param('s', $apriscoId);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }

    private function validarTipoArea(string $tipo): void
    {
        $validos = ['LEVANTE_CEBA','GESTACION','MATERNIDAD','REPRODUCCION','CHIQUERO'];
        if (!in_array($tipo, $validos, true)) {
            throw new InvalidArgumentException(
                "tipo_area inválido. Use uno de: " . implode(', ', $validos)
            );
        }
    }

    /* ============ Lecturas ============ */

    /**
     * Lista áreas (por defecto excluye eliminadas).
     * Filtros opcionales: aprisco_id, tipo_area
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $apriscoId = null,
        ?string $tipoArea = null
    ): array {
        $where  = [];
        $params = [];
        $types  = '';

        $where[] = $incluirEliminados ? 'a.deleted_at IS NOT NULL OR a.deleted_at IS NULL' : 'a.deleted_at IS NULL';

        if ($apriscoId) {
            $where[]  = 'a.aprisco_id = ?';
            $params[] = $apriscoId;
            $types   .= 's';
        }
        if ($tipoArea) {
            $this->validarTipoArea($tipoArea);
            $where[]  = 'a.tipo_area = ?';
            $params[] = $tipoArea;
            $types   .= 's';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT a.area_id, a.aprisco_id, ap.nombre AS nombre_aprisco,
                       a.nombre_personalizado, a.tipo_area, a.numeracion, a.estado,
                       a.created_at, a.created_by, a.updated_at, a.updated_by
                FROM {$this->table} a
                LEFT JOIN apriscos ap ON ap.aprisco_id = a.aprisco_id
                WHERE {$whereSql}
                ORDER BY a.created_at DESC, a.tipo_area ASC, a.numeracion ASC
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

    public function obtenerPorId(string $areaId): ?array
    {
        $sql = "SELECT a.area_id, a.aprisco_id, ap.nombre AS nombre_aprisco,
                       a.nombre_personalizado, a.tipo_area, a.numeracion, a.estado,
                       a.created_at, a.created_by, a.updated_at, a.updated_by,
                       a.deleted_at, a.deleted_by
                FROM {$this->table} a
                LEFT JOIN apriscos ap ON ap.aprisco_id = a.aprisco_id
                WHERE a.area_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $areaId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crea un área.
     * Requeridos: aprisco_id, tipo_area
     * Opcionales: nombre_personalizado, numeracion, estado('ACTIVA'|'INACTIVA')
     * Reglas: valida existencia de aprisco y tipo_area permitido.
     */
    public function crear(array $data): string
    {
        if (empty($data['aprisco_id']) || empty($data['tipo_area'])) {
            throw new InvalidArgumentException('Faltan campos requeridos: aprisco_id, tipo_area.');
        }

        $apriscoId = trim((string)$data['aprisco_id']);
        $tipoArea  = trim((string)$data['tipo_area']);
        $this->validarTipoArea($tipoArea);

        if (!$this->apriscoExiste($apriscoId)) {
            throw new RuntimeException('El aprisco especificado no existe o está eliminado.');
        }

        $nombrePers = isset($data['nombre_personalizado']) ? trim((string)$data['nombre_personalizado']) : null;
        $numeracion = isset($data['numeracion']) ? trim((string)$data['numeracion']) : null;
        $estado     = isset($data['estado']) && in_array($data['estado'], ['ACTIVA','INACTIVA'], true)
                    ? $data['estado'] : 'ACTIVA';

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();

            $uuid    = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                    (area_id, aprisco_id, nombre_personalizado, tipo_area, numeracion, estado,
                     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            $stmt->bind_param('ssssssss',
                $uuid, $apriscoId, $nombrePers, $tipoArea, $numeracion, $estado, $now, $actorId
            );

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                $this->db->rollback();

                if (str_contains(strtolower($err), 'foreign key')) {
                    throw new RuntimeException('El aprisco no existe (violación de clave foránea).');
                }
                if (str_contains(strtolower($err), 'duplicate')) {
                    throw new RuntimeException('Ya existe un área con esa combinación (ver índice único).');
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
     * Actualiza campos explícitos: aprisco_id, nombre_personalizado, tipo_area, numeracion, estado.
     */
    public function actualizar(string $areaId, array $data): bool
    {
        $campos = [];
        $params = [];
        $types  = '';

        if (isset($data['aprisco_id'])) {
            $nuevoAprisco = trim((string)$data['aprisco_id']);
            if (!$this->apriscoExiste($nuevoAprisco)) {
                throw new InvalidArgumentException('aprisco_id no válido (no existe o está eliminado).');
            }
            $campos[] = 'aprisco_id = ?';
            $params[] = $nuevoAprisco;
            $types   .= 's';
        }
        if (array_key_exists('nombre_personalizado', $data)) {
            $campos[] = 'nombre_personalizado = ?';
            $params[] = $data['nombre_personalizado'] !== null ? trim((string)$data['nombre_personalizado']) : null;
            $types   .= 's';
        }
        if (isset($data['tipo_area'])) {
            $this->validarTipoArea((string)$data['tipo_area']);
            $campos[] = 'tipo_area = ?';
            $params[] = (string)$data['tipo_area'];
            $types   .= 's';
        }
        if (array_key_exists('numeracion', $data)) {
            $campos[] = 'numeracion = ?';
            $params[] = $data['numeracion'] !== null ? trim((string)$data['numeracion']) : null;
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
        $actorId     = $_SESSION['user_id'] ?? $areaId;

        $campos[] = 'updated_at = ?';
        $params[] = $now;    $types .= 's';
        $campos[] = 'updated_by = ?';
        $params[] = $actorId; $types .= 's';

        $sql = "UPDATE {$this->table}
                SET " . implode(', ', $campos) . "
                WHERE area_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $types   .= 's';
        $params[] = $areaId;

        $stmt->bind_param($types, ...$params);
        $ok  = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            if (str_contains(strtolower($err), 'foreign key')) {
                throw new RuntimeException('El aprisco no existe (violación de clave foránea).');
            }
            if (str_contains(strtolower($err), 'duplicate')) {
                throw new RuntimeException('Conflicto de unicidad (ver índice único).');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    /**
     * Actualiza solo el estado ('ACTIVA'|'INACTIVA').
     */
    public function actualizarEstado(string $areaId, string $estado): bool
    {
        if (!in_array($estado, ['ACTIVA','INACTIVA'], true)) {
            throw new InvalidArgumentException("Valor de estado inválido. Use 'ACTIVA' o 'INACTIVA'.");
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $areaId;

        $sql = "UPDATE {$this->table}
                SET estado = ?, updated_at = ?, updated_by = ?
                WHERE area_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización de estado: " . $this->db->error);

        $stmt->bind_param('ssss', $estado, $now, $actorId, $areaId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Eliminación lógica (soft delete).
     */
    public function eliminar(string $areaId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $areaId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE area_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $areaId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
