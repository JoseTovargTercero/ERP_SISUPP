<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class PeriodoServicioModel
{
    private $db;
    private $table = 'periodos_servicio';

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

    private function validarEstadoPeriodo(?string $v): void
    {
        if ($v === null) return;
        $validos = ['ABIERTO','CERRADO'];
        if (!in_array($v, $validos, true)) {
            throw new InvalidArgumentException("estado_periodo inválido. Use: " . implode(', ', $validos));
        }
    }

    private function animalExiste(string $id): bool
    {
        $sql = "SELECT 1 FROM animales WHERE animal_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id); $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0; $stmt->close(); return $ok;
    }

    private function esHembra(string $id): bool
    {
        $sql = "SELECT sexo FROM animales WHERE animal_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id); $stmt->execute();
        $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close();
        return isset($row['sexo']) && $row['sexo'] === 'HEMBRA';
    }

    private function esMacho(string $id): bool
    {
        $sql = "SELECT sexo FROM animales WHERE animal_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id); $stmt->execute();
        $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close();
        return isset($row['sexo']) && $row['sexo'] === 'MACHO';
    }

    /* ============ Lecturas ============ */

    /**
     * Lista periodos de servicio.
     * Filtros: hembra_id, verraco_id, estado_periodo, fecha_inicio (desde/hasta).
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $hembraId = null,
        ?string $verracoId = null,
        ?string $estado = null,
        ?string $desde = null,
        ?string $hasta = null
    ): array {
        $w=[]; $p=[]; $t='';

        $w[] = $incluirEliminados ? 'p.deleted_at IS NOT NULL OR p.deleted_at IS NULL' : 'p.deleted_at IS NULL';

        if ($hembraId)  { $w[]='p.hembra_id = ?';  $p[]=$hembraId;  $t.='s'; }
        if ($verracoId) { $w[]='p.verraco_id = ?'; $p[]=$verracoId; $t.='s'; }
        if ($estado)    { $this->validarEstadoPeriodo($estado); $w[]='p.estado_periodo = ?'; $p[]=$estado; $t.='s'; }
        if ($desde)     { $w[]='p.fecha_inicio >= ?'; $p[]=$desde; $t.='s'; }
        if ($hasta)     { $w[]='p.fecha_inicio <= ?'; $p[]=$hasta; $t.='s'; }

        $whereSql = implode(' AND ', $w);

        $sql = "SELECT 
                    p.periodo_id,
                    p.hembra_id,
                    h.identificador AS hembra_identificador,
                    p.verraco_id,
                    v.identificador AS verraco_identificador,
                    p.fecha_inicio,
                    p.observaciones,
                    p.estado_periodo,
                    p.created_at, p.created_by, p.updated_at, p.updated_by
                FROM {$this->table} p
                LEFT JOIN animales h ON h.animal_id = p.hembra_id
                LEFT JOIN animales v ON v.animal_id = p.verraco_id
                WHERE {$whereSql}
                ORDER BY p.fecha_inicio DESC
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

    public function obtenerPorId(string $periodoId): ?array
    {
        $sql = "SELECT 
                    p.periodo_id,
                    p.hembra_id, h.identificador AS hembra_identificador, h.sexo AS hembra_sexo,
                    p.verraco_id, v.identificador AS verraco_identificador, v.sexo AS verraco_sexo,
                    p.fecha_inicio,
                    p.observaciones,
                    p.estado_periodo,
                    p.created_at, p.created_by, p.updated_at, p.updated_by,
                    p.deleted_at, p.deleted_by
                FROM {$this->table} p
                LEFT JOIN animales h ON h.animal_id = p.hembra_id
                LEFT JOIN animales v ON v.animal_id = p.verraco_id
                WHERE p.periodo_id = ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);
        $stmt->bind_param('s', $periodoId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crea un periodo de servicio.
     * Requeridos: hembra_id (sexo=HEMBRA), verraco_id (sexo=MACHO), fecha_inicio (Y-m-d)
     * Opcional: observaciones, estado_periodo (default ABIERTO)
     */
    public function crear(array $data): string
    {
        $hembraId  = trim((string)($data['hembra_id'] ?? ''));
        $verracoId = trim((string)($data['verraco_id'] ?? ''));
        $fechaIni  = trim((string)($data['fecha_inicio'] ?? ''));

        if ($hembraId === '' || $verracoId === '' || $fechaIni === '') {
            throw new InvalidArgumentException('Faltan campos requeridos: hembra_id, verraco_id, fecha_inicio.');
        }

        if (!$this->animalExiste($hembraId))  throw new RuntimeException('La hembra no existe o está eliminada.');
        if (!$this->animalExiste($verracoId)) throw new RuntimeException('El verraco no existe o está eliminado.');

        // Verificación de sexos (consistente con dominio)
        if (!$this->esHembra($hembraId))  throw new InvalidArgumentException('hembra_id no corresponde a una HEMBRA.');
        if (!$this->esMacho($verracoId))  throw new InvalidArgumentException('verraco_id no corresponde a un MACHO.');

        $estado = isset($data['estado_periodo']) ? (string)$data['estado_periodo'] : 'ABIERTO';
        $this->validarEstadoPeriodo($estado);

        $observ = isset($data['observaciones']) ? trim((string)$data['observaciones']) : null;

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $uuid    = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                    (periodo_id, hembra_id, verraco_id, fecha_inicio, observaciones, estado_periodo,
                     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            $stmt->bind_param(
                'ssssssss',
                $uuid, $hembraId, $verracoId, $fechaIni, $observ, $estado,
                $now, $actorId
            );

            if (!$stmt->execute()) {
                $err = $stmt->error; $stmt->close(); $this->db->rollback();
                if (str_contains(strtolower($err), 'foreign key')) {
                    throw new RuntimeException('Referencia inválida a animales.');
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
     * Actualiza campos explícitos: hembra_id, verraco_id, fecha_inicio, observaciones, estado_periodo.
     */
    public function actualizar(string $periodoId, array $data): bool
    {
        $set = []; $p=[]; $t='';

        if (array_key_exists('hembra_id', $data)) {
            $v = $data['hembra_id'];
            if ($v !== null && $v !== '') {
                if (!$this->animalExiste($v)) throw new InvalidArgumentException('hembra_id inválido.');
                if (!$this->esHembra($v))     throw new InvalidArgumentException('hembra_id no corresponde a una HEMBRA.');
            }
            $set[]='hembra_id = ?'; $p[] = ($v!==''?$v:null); $t.='s';
        }
        if (array_key_exists('verraco_id', $data)) {
            $v = $data['verraco_id'];
            if ($v !== null && $v !== '') {
                if (!$this->animalExiste($v)) throw new InvalidArgumentException('verraco_id inválido.');
                if (!$this->esMacho($v))      throw new InvalidArgumentException('verraco_id no corresponde a un MACHO.');
            }
            $set[]='verraco_id = ?'; $p[] = ($v!==''?$v:null); $t.='s';
        }
        if (isset($data['fecha_inicio'])) { $set[]='fecha_inicio = ?'; $p[]=(string)$data['fecha_inicio']; $t.='s'; }
        if (array_key_exists('observaciones', $data)) { $set[]='observaciones = ?'; $p[]=($data['observaciones']!=='' ? (string)$data['observaciones'] : null); $t.='s'; }
        if (isset($data['estado_periodo'])) { $this->validarEstadoPeriodo((string)$data['estado_periodo']); $set[]='estado_periodo = ?'; $p[]=(string)$data['estado_periodo']; $t.='s'; }

        if (empty($set)) throw new InvalidArgumentException('No hay campos para actualizar.');

        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $periodoId;

        $set[]='updated_at = ?'; $p[]=$now; $t.='s';
        $set[]='updated_by = ?'; $p[]=$actorId; $t.='s';

        $sql = "UPDATE {$this->table} SET ".implode(', ', $set)." WHERE periodo_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $t.='s'; $p[]=$periodoId;
        $stmt->bind_param($t, ...$p);
        $ok  = $stmt->execute(); $err = $stmt->error; $stmt->close();

        if (!$ok) {
            if (str_contains(strtolower($err), 'foreign key')) {
                throw new RuntimeException('Referencia inválida a animales.');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    /**
     * Cambia solo estado_periodo ('ABIERTO'|'CERRADO').
     */
    public function actualizarEstado(string $periodoId, string $estado): bool
    {
        $this->validarEstadoPeriodo($estado);

        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $periodoId;

        $sql = "UPDATE {$this->table}
                SET estado_periodo = ?, updated_at = ?, updated_by = ?
                WHERE periodo_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización de estado: " . $this->db->error);

        $stmt->bind_param('ssss', $estado, $now, $actorId, $periodoId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Eliminación lógica (soft delete).
     */
    public function eliminar(string $periodoId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $periodoId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE periodo_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $periodoId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
