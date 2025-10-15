<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class AlertaModel
{
    private $db;
    private $table = 'alertas';

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

    private function getActorIdFallback(string $fallback): string
    {
        return $_SESSION['user_id'] ?? $fallback;
    }

    private function validarTipoAlerta(string $tipo): void
    {
        $permitidos = ['REVISION_20_21','PROX_PARTO_117','OTRA'];
        if (!in_array($tipo, $permitidos, true)) {
            throw new InvalidArgumentException("tipo_alerta inválido. Use uno de: " . implode(', ', $permitidos));
        }
    }

    private function validarEstadoAlerta(string $estado): void
    {
        $permitidos = ['PENDIENTE','CUMPLIDA','VENCIDA','CANCELADA'];
        if (!in_array($estado, $permitidos, true)) {
            throw new InvalidArgumentException("estado_alerta inválido. Use uno de: " . implode(', ', $permitidos));
        }
    }

    private function validarFechaYMD(?string $ymd, string $campo = 'fecha'): void
    {
        if ($ymd === null) return;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            throw new InvalidArgumentException("$campo inválida. Formato esperado YYYY-MM-DD.");
        }
        [$y,$m,$d] = array_map('intval', explode('-', $ymd));
        if (!checkdate($m, $d, $y)) {
            throw new InvalidArgumentException("$campo inválida. Fecha no existente.");
        }
    }

    private function periodoExiste(?string $periodoId): bool
    {
        if (!$periodoId) return true; // opcional
        $sql = "SELECT 1 FROM periodos_servicio WHERE periodo_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar verificación de período: " . $this->db->error);
        $stmt->bind_param('s', $periodoId);
        $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    private function animalExiste(?string $animalId): bool
    {
        if (!$animalId) return true; // opcional
        $sql = "SELECT 1 FROM animales WHERE animal_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar verificación de animal: " . $this->db->error);
        $stmt->bind_param('s', $animalId);
        $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    /* ============ Lecturas ============ */

    /**
     * Listado con filtros.
     * Filtros: periodo_id?, animal_id?, tipo_alerta?, estado_alerta?, desde?(YYYY-MM-DD), hasta?(YYYY-MM-DD)
     * Control: incluirEliminados (por defecto false)
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        ?string $periodoId = null,
        ?string $animalId = null,
        ?string $tipoAlerta = null,
        ?string $estadoAlerta = null,
        ?string $desde = null,
        ?string $hasta = null,
        bool $incluirEliminados = false
    ): array {
        $w = []; $p = []; $t = '';

        $w[] = $incluirEliminados ? '(a.deleted_at IS NOT NULL OR a.deleted_at IS NULL)' : 'a.deleted_at IS NULL';

        if ($periodoId)   { $w[]='a.periodo_id = ?';     $p[]=$periodoId;   $t.='s'; }
        if ($animalId)    { $w[]='a.animal_id = ?';      $p[]=$animalId;    $t.='s'; }
        if ($tipoAlerta)  { $this->validarTipoAlerta($tipoAlerta);     $w[]='a.tipo_alerta = ?';   $p[]=$tipoAlerta;  $t.='s'; }
        if ($estadoAlerta){ $this->validarEstadoAlerta($estadoAlerta); $w[]='a.estado_alerta = ?'; $p[]=$estadoAlerta;$t.='s'; }
        if ($desde)       { $this->validarFechaYMD($desde, 'desde');   $w[]='a.fecha_objetivo >= ?'; $p[]=$desde;      $t.='s'; }
        if ($hasta)       { $this->validarFechaYMD($hasta, 'hasta');   $w[]='a.fecha_objetivo <= ?'; $p[]=$hasta;      $t.='s'; }

        $where = implode(' AND ', $w);

        $sql = "SELECT 
                    a.alerta_id, a.tipo_alerta, a.periodo_id, a.animal_id,
                    a.fecha_objetivo, a.estado_alerta, a.detalle,
                    a.created_at, a.created_by, a.updated_at, a.updated_by,
                    a.deleted_at, a.deleted_by
                FROM {$this->table} a
                WHERE {$where}
                ORDER BY a.fecha_objetivo ASC, a.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando listado: " . $this->db->error);

        $t .= 'ii';
        $p[] = $limit;
        $p[] = $offset;
        $stmt->bind_param($t, ...$p);

        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function obtenerPorId(string $alertaId): ?array
    {
        $sql = "SELECT 
                    alerta_id, tipo_alerta, periodo_id, animal_id,
                    fecha_objetivo, estado_alerta, detalle,
                    created_at, created_by, updated_at, updated_by,
                    deleted_at, deleted_by
                FROM {$this->table}
                WHERE alerta_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando consulta: " . $this->db->error);
        $stmt->bind_param('s', $alertaId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crea una alerta.
     * Requeridos: tipo_alerta, fecha_objetivo (YYYY-MM-DD)
     * Opcionales: periodo_id?, animal_id?, detalle?
     * Estado inicial: PENDIENTE (si no se indica otro)
     */
    public function crear(array $in): string
    {
        $tipo      = isset($in['tipo_alerta']) ? (string)$in['tipo_alerta'] : '';
        $periodoId = isset($in['periodo_id']) ? trim((string)$in['periodo_id']) : null;
        $animalId  = isset($in['animal_id'])  ? trim((string)$in['animal_id'])  : null;
        $fechaObj  = isset($in['fecha_objetivo']) ? trim((string)$in['fecha_objetivo']) : '';
        $estado    = isset($in['estado_alerta']) ? (string)$in['estado_alerta'] : 'PENDIENTE';
        $detalle   = isset($in['detalle']) ? trim((string)$in['detalle']) : null;

        if ($tipo === '' || $fechaObj === '') {
            throw new InvalidArgumentException('Faltan campos requeridos: tipo_alerta, fecha_objetivo.');
        }
        $this->validarTipoAlerta($tipo);
        $this->validarEstadoAlerta($estado);
        $this->validarFechaYMD($fechaObj, 'fecha_objetivo');

        if (!$this->periodoExiste($periodoId)) {
            throw new RuntimeException('El período indicado no existe o está eliminado.');
        }
        if (!$this->animalExiste($animalId)) {
            throw new RuntimeException('El animal indicado no existe o está eliminado.');
        }

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $uuid    = $this->generateUUIDv4();
            $actorId = $this->getActorIdFallback($uuid);

    $sql = "INSERT INTO {$this->table}
        (alerta_id, tipo_alerta, periodo_id, animal_id, fecha_objetivo, estado_alerta, detalle,
         created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";

$stmt = $this->db->prepare($sql);
if (!$stmt) {
    throw new mysqli_sql_exception("Error preparando inserción: " . $this->db->error);
}

$stmt->bind_param(
    'sssssssss',
    $uuid, $tipo, $periodoId, $animalId, $fechaObj, $estado, $detalle, $now, $actorId
);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    $this->db->rollback();
    throw new mysqli_sql_exception("Error al crear alerta: " . $err);
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
     * Actualiza campos de una alerta (parcial).
     * Permitidos: tipo_alerta?, periodo_id?, animal_id?, fecha_objetivo?, estado_alerta?, detalle?
     */
    public function actualizar(string $alertaId, array $in): bool
    {
        $row = $this->obtenerPorId($alertaId);
        if (!$row || $row['deleted_at'] !== null) {
            throw new mysqli_sql_exception('Alerta no encontrada o eliminada.');
        }

        $campos = []; $params = []; $types = '';

        if (array_key_exists('tipo_alerta', $in)) {
            $tipo = (string)$in['tipo_alerta'];
            $this->validarTipoAlerta($tipo);
            $campos[]='tipo_alerta = ?'; $params[]=$tipo; $types.='s';
        }
        if (array_key_exists('periodo_id', $in)) {
            $pid = $in['periodo_id'] !== '' ? (string)$in['periodo_id'] : null;
            if (!$this->periodoExiste($pid)) throw new InvalidArgumentException('periodo_id inválido.');
            $campos[]='periodo_id = ?'; $params[]=$pid; $types.='s';
        }
        if (array_key_exists('animal_id', $in)) {
            $aid = $in['animal_id'] !== '' ? (string)$in['animal_id'] : null;
            if (!$this->animalExiste($aid)) throw new InvalidArgumentException('animal_id inválido.');
            $campos[]='animal_id = ?'; $params[]=$aid; $types.='s';
        }
        if (array_key_exists('fecha_objetivo', $in)) {
            $fo = $in['fecha_objetivo'] !== '' ? (string)$in['fecha_objetivo'] : null;
            $this->validarFechaYMD($fo, 'fecha_objetivo');
            $campos[]='fecha_objetivo = ?'; $params[]=$fo; $types.='s';
        }
        if (array_key_exists('estado_alerta', $in)) {
            $est = (string)$in['estado_alerta'];
            $this->validarEstadoAlerta($est);
            $campos[]='estado_alerta = ?'; $params[]=$est; $types.='s';
        }
        if (array_key_exists('detalle', $in)) {
            $campos[]='detalle = ?'; $params[] = $in['detalle'] !== null ? (string)$in['detalle'] : null; $types.='s';
        }

        if (!$campos) {
            throw new InvalidArgumentException('No hay campos para actualizar.');
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId = $this->getActorIdFallback($alertaId);

        $campos[] = 'updated_at = ?'; $params[] = $now;     $types .= 's';
        $campos[] = 'updated_by = ?'; $params[] = $actorId; $types .= 's';

        $sql = "UPDATE {$this->table}
                SET ".implode(', ', $campos)."
                WHERE alerta_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando actualización: " . $this->db->error);

        $types .= 's';
        $params[] = $alertaId;

        $stmt->bind_param($types, ...$params);
        $ok  = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            throw new mysqli_sql_exception("Error al actualizar alerta: " . $err);
        }
        return true;
    }

    /**
     * Cambia el estado de la alerta (helper).
     * Estados válidos: PENDIENTE, CUMPLIDA, VENCIDA, CANCELADA
     */
    public function cambiarEstado(string $alertaId, string $nuevoEstado): bool
    {
        $this->validarEstadoAlerta($nuevoEstado);

        $row = $this->obtenerPorId($alertaId);
        if (!$row || $row['deleted_at'] !== null) {
            throw new mysqli_sql_exception('Alerta no encontrada o eliminada.');
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId = $this->getActorIdFallback($alertaId);

        $sql = "UPDATE {$this->table}
                SET estado_alerta = ?, updated_at = ?, updated_by = ?
                WHERE alerta_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando cambio de estado: " . $this->db->error);

        $stmt->bind_param('ssss', $nuevoEstado, $now, $actorId, $alertaId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** Soft delete: marca deleted_at/deleted_by si no estaba eliminada. */
    public function eliminar(string $alertaId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $this->getActorIdFallback($alertaId);

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE alerta_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $alertaId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
