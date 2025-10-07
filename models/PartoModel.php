<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class PartoModel
{
    private $db;
    private $table = 'partos';

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

    private function validarEstadoParto(?string $v): void
    {
        if ($v === null) return;
        // ENUM real en tu BD
        $validos = ['NORMAL','DISTOCIA','MUERTE_PERINATAL','OTRO'];
        if (!in_array($v, $validos, true)) {
            throw new InvalidArgumentException("estado_parto inválido. Use: " . implode(', ', $validos));
        }
    }

    private function periodoExiste(string $id): bool
    {
        $sql = "SELECT 1 FROM periodos_servicio WHERE periodo_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id); $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0; $stmt->close(); return $ok;
    }

    /* ============ Lecturas ============ */

    /**
     * Lista partos (excluye eliminados por defecto).
     * Filtros: periodo_id, estado_parto, fecha_parto (desde/hasta).
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $periodoId = null,
        ?string $estado = null,
        ?string $desde = null,
        ?string $hasta = null
    ): array {
        $w=[]; $p=[]; $t='';

        $w[] = $incluirEliminados ? 'p.deleted_at IS NOT NULL OR p.deleted_at IS NULL' : 'p.deleted_at IS NULL';

        if ($periodoId) { $w[]='p.periodo_id = ?';     $p[]=$periodoId; $t.='s'; }
        if ($estado)    { $this->validarEstadoParto($estado); $w[]='p.estado_parto = ?'; $p[]=$estado; $t.='s'; }
        if ($desde)     { $w[]='p.fecha_parto >= ?';   $p[]=$desde;     $t.='s'; }
        if ($hasta)     { $w[]='p.fecha_parto <= ?';   $p[]=$hasta;     $t.='s'; }

        $whereSql = implode(' AND ', $w);

        $sql = "SELECT
                    p.parto_id,
                    p.periodo_id,
                    ps.hembra_id,
                    ps.verraco_id,
                    p.fecha_parto,
                    p.crias_machos,
                    p.crias_hembras,
                    p.peso_promedio_kg,
                    p.estado_parto,
                    p.observaciones,
                    p.created_at, p.created_by, p.updated_at, p.updated_by
                FROM {$this->table} p
                LEFT JOIN periodos_servicio ps ON ps.periodo_id = p.periodo_id
                WHERE {$whereSql}
                ORDER BY p.fecha_parto DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $t.='ii'; $p[]=$limit; $p[]=$offset;
        $stmt->bind_param($t, ...$p);
        $stmt->execute();
        $res  = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function obtenerPorId(string $partoId): ?array
    {
        $sql = "SELECT
                    p.parto_id,
                    p.periodo_id,
                    ps.hembra_id,
                    ps.verraco_id,
                    p.fecha_parto,
                    p.crias_machos,
                    p.crias_hembras,
                    p.peso_promedio_kg,
                    p.estado_parto,
                    p.observaciones,
                    p.created_at, p.created_by, p.updated_at, p.updated_by,
                    p.deleted_at, p.deleted_by
                FROM {$this->table} p
                LEFT JOIN periodos_servicio ps ON ps.periodo_id = p.periodo_id
                WHERE p.parto_id = ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $partoId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crea un registro de parto.
     * Requeridos: periodo_id, fecha_parto
     * Opcionales: crias_machos, crias_hembras, peso_promedio_kg, estado_parto (default NORMAL), observaciones
     */
    public function crear(array $data): string
    {
        $periodoId   = trim((string)($data['periodo_id'] ?? ''));
        $fechaParto  = trim((string)($data['fecha_parto'] ?? ''));

        if ($periodoId === '' || $fechaParto === '') {
            throw new InvalidArgumentException('Faltan campos requeridos: periodo_id, fecha_parto.');
        }

        if (!$this->periodoExiste($periodoId)) {
            throw new RuntimeException('El periodo de servicio no existe o está eliminado.');
        }

        $criasM   = isset($data['crias_machos']) ? (int)$data['crias_machos'] : 0;
        $criasH   = isset($data['crias_hembras']) ? (int)$data['crias_hembras'] : 0;
        $pesoProm = isset($data['peso_promedio_kg']) && $data['peso_promedio_kg'] !== '' ? (float)$data['peso_promedio_kg'] : null;

        if ($criasM < 0 || $criasH < 0) {
            throw new InvalidArgumentException('Las crías no pueden ser negativas.');
        }
        if ($pesoProm !== null && $pesoProm < 0) {
            throw new InvalidArgumentException('El peso promedio no puede ser negativo.');
        }

        // Default alineado al ENUM real
        $estado = isset($data['estado_parto']) ? (string)$data['estado_parto'] : 'NORMAL';
        $this->validarEstadoParto($estado);

        $observ = isset($data['observaciones']) ? trim((string)$data['observaciones']) : null;

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $uuid    = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                    (parto_id, periodo_id, fecha_parto, crias_machos, crias_hembras, peso_promedio_kg,
                     estado_parto, observaciones, created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            // 10 placeholders → tipos dinámicos para peso_promedio_kg (NULL o double)
            $types = 'sssii' . ($pesoProm === null ? 's' : 'd') . 'ssss';
            $args  = [
                $uuid,
                $periodoId,
                $fechaParto,
                $criasM,
                $criasH,
                $pesoProm === null ? null : $pesoProm,
                $estado,
                $observ,
                $now,
                $actorId
            ];

            $stmt->bind_param($types, ...$args);

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                $this->db->rollback();
                if (str_contains(strtolower($err), 'foreign key')) {
                    throw new RuntimeException('Referencia inválida a periodo de servicio.');
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
     * Actualiza campos explícitos del parto.
     * Campos: periodo_id?, fecha_parto?, crias_machos?, crias_hembras?, peso_promedio_kg?, estado_parto?, observaciones?
     */
    public function actualizar(string $partoId, array $data): bool
    {
        $set = []; $p=[]; $t='';

        if (array_key_exists('periodo_id', $data)) {
            $v = $data['periodo_id'];
            if ($v !== null && $v !== '' && !$this->periodoExiste($v)) {
                throw new InvalidArgumentException('periodo_id inválido.');
            }
            $set[]='periodo_id = ?'; $p[] = ($v!==''?$v:null); $t.='s';
        }
        if (isset($data['fecha_parto']))      { $set[]='fecha_parto = ?';     $p[]=(string)$data['fecha_parto']; $t.='s'; }
        if (isset($data['crias_machos']))     {
            $cm = (int)$data['crias_machos']; if ($cm < 0) throw new InvalidArgumentException('crias_machos no puede ser negativo.');
            $set[]='crias_machos = ?'; $p[]=$cm; $t.='i';
        }
        if (isset($data['crias_hembras']))    {
            $ch = (int)$data['crias_hembras']; if ($ch < 0) throw new InvalidArgumentException('crias_hembras no puede ser negativo.');
            $set[]='crias_hembras = ?'; $p[]=$ch; $t.='i';
        }
        if (array_key_exists('peso_promedio_kg', $data)) {
            $pp = $data['peso_promedio_kg'];
            if ($pp === '' || $pp === null) {
                $set[]='peso_promedio_kg = ?'; $p[]=null; $t.='s';
            } else {
                $pp = (float)$pp; if ($pp < 0) throw new InvalidArgumentException('peso_promedio_kg no puede ser negativo.');
                $set[]='peso_promedio_kg = ?'; $p[]=$pp; $t.='d';
            }
        }
        if (isset($data['estado_parto']))     { $this->validarEstadoParto((string)$data['estado_parto']); $set[]='estado_parto = ?'; $p[]=(string)$data['estado_parto']; $t.='s'; }
        if (array_key_exists('observaciones', $data)) { $set[]='observaciones = ?'; $p[]=($data['observaciones']!=='' ? (string)$data['observaciones'] : null); $t.='s'; }

        if (empty($set)) throw new InvalidArgumentException('No hay campos para actualizar.');

        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $partoId;

        $set[]='updated_at = ?'; $p[]=$now; $t.='s';
        $set[]='updated_by = ?'; $p[]=$actorId; $t.='s';

        $sql = "UPDATE {$this->table} SET ".implode(', ', $set)." WHERE parto_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $t.='s'; $p[]=$partoId;
        $stmt->bind_param($t, ...$p);
        $ok  = $stmt->execute(); $err = $stmt->error; $stmt->close();

        if (!$ok) {
            if (str_contains(strtolower($err), 'foreign key')) {
                throw new RuntimeException('Referencia inválida a periodo de servicio.');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    /**
     * Cambia solo estado_parto.
     * JSON: { estado_parto: 'NORMAL'|'DISTOCIA'|'MUERTE_PERINATAL'|'OTRO' }
     */
    public function actualizarEstado(string $partoId, string $estado): bool
    {
        $this->validarEstadoParto($estado);

        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $partoId;

        $sql = "UPDATE {$this->table}
                SET estado_parto = ?, updated_at = ?, updated_by = ?
                WHERE parto_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización de estado: " . $this->db->error);

        $stmt->bind_param('ssss', $estado, $now, $actorId, $partoId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Eliminación lógica (soft delete).
     */
    public function eliminar(string $partoId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $partoId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE parto_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $partoId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
