<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class ReporteDanoModel
{
    private $db;
    private $table = 'reportes_dano';

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

    private function validarCriticidad(?string $v): void
    {
        if ($v === null) return;
        $validos = ['BAJA','MEDIA','ALTA'];
        if (!in_array($v, $validos, true)) {
            throw new InvalidArgumentException("criticidad inválida. Use: " . implode(', ', $validos));
        }
    }

    private function validarEstadoReporte(?string $v): void
    {
        if ($v === null) return;
        $validos = ['ABIERTO','EN_PROCESO','CERRADO'];
        if (!in_array($v, $validos, true)) {
            throw new InvalidArgumentException("estado_reporte inválido. Use: " . implode(', ', $validos));
        }
    }

    private function fincaExiste(string $id): bool
    {
        $sql = "SELECT 1 FROM fincas WHERE finca_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id); $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0; $stmt->close(); return $ok;
    }
    private function apriscoExiste(string $id): bool
    {
        $sql = "SELECT 1 FROM apriscos WHERE aprisco_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id); $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0; $stmt->close(); return $ok;
    }
    private function areaExiste(string $id): bool
    {
        $sql = "SELECT 1 FROM areas WHERE area_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id); $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0; $stmt->close(); return $ok;
    }

    /* ============ Lecturas ============ */

    /**
     * Lista reportes (excluye eliminados por defecto).
     * Filtros: finca_id, aprisco_id, area_id, criticidad, estado_reporte.
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $fincaId = null,
        ?string $apriscoId = null,
        ?string $areaId = null,
        ?string $criticidad = null,
        ?string $estado = null
    ): array {
        $where  = [];
        $params = [];
        $types  = '';

        $where[] = $incluirEliminados ? 'r.deleted_at IS NOT NULL OR r.deleted_at IS NULL' : 'r.deleted_at IS NULL';

        if ($fincaId)   { $where[] = 'r.finca_id = ?';   $params[] = $fincaId;   $types .= 's'; }
        if ($apriscoId) { $where[] = 'r.aprisco_id = ?'; $params[] = $apriscoId; $types .= 's'; }
        if ($areaId)    { $where[] = 'r.area_id = ?';    $params[] = $areaId;    $types .= 's'; }

        if ($criticidad) { $this->validarCriticidad($criticidad); $where[] = 'r.criticidad = ?'; $params[] = $criticidad; $types .= 's'; }
        if ($estado)     { $this->validarEstadoReporte($estado);  $where[] = 'r.estado_reporte = ?'; $params[] = $estado; $types .= 's'; }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT r.reporte_id, r.finca_id, f.nombre AS nombre_finca,
                       r.aprisco_id, a2.nombre AS nombre_aprisco,
                       r.area_id, ar.nombre_personalizado AS nombre_area, ar.tipo_area,
                       r.titulo, r.descripcion, r.criticidad, r.estado_reporte,
                       r.fecha_reporte, r.fecha_cierre,
                       r.created_at, r.created_by, r.updated_at, r.updated_by
                FROM {$this->table} r
                LEFT JOIN fincas   f  ON f.finca_id   = r.finca_id
                LEFT JOIN apriscos a2 ON a2.aprisco_id = r.aprisco_id
                LEFT JOIN areas    ar ON ar.area_id    = r.area_id
                WHERE {$whereSql}
                ORDER BY r.fecha_reporte DESC, r.criticidad DESC
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

    public function obtenerPorId(string $reporteId): ?array
    {
        $sql = "SELECT r.reporte_id, r.finca_id, f.nombre AS nombre_finca,
                       r.aprisco_id, a2.nombre AS nombre_aprisco,
                       r.area_id, ar.nombre_personalizado AS nombre_area, ar.tipo_area,
                       r.titulo, r.descripcion, r.criticidad, r.estado_reporte,
                       r.fecha_reporte, r.reportado_por, r.solucionado_por, r.fecha_cierre,
                       r.created_at, r.created_by, r.updated_at, r.updated_by,
                       r.deleted_at, r.deleted_by
                FROM {$this->table} r
                LEFT JOIN fincas   f  ON f.finca_id   = r.finca_id
                LEFT JOIN apriscos a2 ON a2.aprisco_id = r.aprisco_id
                LEFT JOIN areas    ar ON ar.area_id    = r.area_id
                WHERE r.reporte_id = ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $reporteId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crea un reporte de daño.
     * Requeridos: titulo, descripcion
     * Opcionales: finca_id, aprisco_id, area_id (cualquiera o combinación), criticidad, estado_reporte
     * Notas:
     *  - Si envías finca/aprisco/area, se valida que existan (si no, error).
     */
    public function crear(array $data): string
    {
        if (empty($data['titulo']) || empty($data['descripcion'])) {
            throw new InvalidArgumentException('Faltan campos requeridos: titulo, descripcion.');
        }

        $fincaId   = isset($data['finca_id'])   && $data['finca_id']   !== '' ? (string)$data['finca_id']   : null;
        $apriscoId = isset($data['aprisco_id']) && $data['aprisco_id'] !== '' ? (string)$data['aprisco_id'] : null;
        $areaId    = isset($data['area_id'])    && $data['area_id']    !== '' ? (string)$data['area_id']    : null;

        if ($fincaId && !$this->fincaExiste($fincaId))     throw new RuntimeException('La finca no existe o está eliminada.');
        if ($apriscoId && !$this->apriscoExiste($apriscoId)) throw new RuntimeException('El aprisco no existe o está eliminado.');
        if ($areaId && !$this->areaExiste($areaId))         throw new RuntimeException('El área no existe o está eliminada.');

        $titulo      = trim((string)$data['titulo']);
        $descripcion = trim((string)$data['descripcion']);

        $criticidad = isset($data['criticidad']) ? (string)$data['criticidad'] : 'BAJA';
        $this->validarCriticidad($criticidad);

        $estado = isset($data['estado_reporte']) ? (string)$data['estado_reporte'] : 'ABIERTO';
        $this->validarEstadoReporte($estado);

        $reportadoPor = isset($data['reportado_por']) ? (string)$data['reportado_por'] : null; // UUID de usuario

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();

            $uuid    = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? ($reportadoPor ?? $uuid);

            $sql = "INSERT INTO {$this->table}
                    (reporte_id, finca_id, aprisco_id, area_id, titulo, descripcion,
                     criticidad, estado_reporte, fecha_reporte, reportado_por, solucionado_por,
                     fecha_cierre, created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, NULL, NULL, NULL, NULL)";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            $stmt->bind_param(
                'ssssssssssss',
                $uuid, $fincaId, $apriscoId, $areaId, $titulo, $descripcion,
                $criticidad, $estado, $now, $reportadoPor, $now, $actorId
            );

            if (!$stmt->execute()) {
                $err = $stmt->error; $stmt->close(); $this->db->rollback();
                if (str_contains(strtolower($err), 'foreign key')) {
                    throw new RuntimeException('Referencia inválida a finca/aprisco/área.');
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
     * Actualiza campos explícitos: finca_id, aprisco_id, area_id, titulo, descripcion, criticidad, estado_reporte.
     * Si se cambia estado a 'CERRADO' y envías 'solucionado_por', se fija fecha_cierre=now (si no viene).
     */
    public function actualizar(string $reporteId, array $data): bool
    {
        $campos = [];
        $params = [];
        $types  = '';

        if (array_key_exists('finca_id', $data)) {
            $v = $data['finca_id']; 
            if ($v !== null && $v !== '' && !$this->fincaExiste($v)) throw new InvalidArgumentException('finca_id inválido.');
            $campos[]='finca_id = ?'; $params[] = ($v!==''?$v:null); $types.='s';
        }
        if (array_key_exists('aprisco_id', $data)) {
            $v = $data['aprisco_id']; 
            if ($v !== null && $v !== '' && !$this->apriscoExiste($v)) throw new InvalidArgumentException('aprisco_id inválido.');
            $campos[]='aprisco_id = ?'; $params[] = ($v!==''?$v:null); $types.='s';
        }
        if (array_key_exists('area_id', $data)) {
            $v = $data['area_id']; 
            if ($v !== null && $v !== '' && !$this->areaExiste($v)) throw new InvalidArgumentException('area_id inválido.');
            $campos[]='area_id = ?'; $params[] = ($v!==''?$v:null); $types.='s';
        }
        if (isset($data['titulo']))       { $campos[]='titulo = ?';       $params[] = trim((string)$data['titulo']);       $types.='s'; }
        if (isset($data['descripcion']))  { $campos[]='descripcion = ?';  $params[] = trim((string)$data['descripcion']);  $types.='s'; }
        if (isset($data['criticidad']))   { $this->validarCriticidad((string)$data['criticidad']); $campos[]='criticidad = ?'; $params[]=(string)$data['criticidad']; $types.='s'; }
        if (isset($data['estado_reporte'])) {
            $this->validarEstadoReporte((string)$data['estado_reporte']);
            $campos[]='estado_reporte = ?'; $params[]=(string)$data['estado_reporte']; $types.='s';
        }
        if (array_key_exists('solucionado_por', $data)) { $campos[]='solucionado_por = ?'; $params[] = $data['solucionado_por'] ?: null; $types.='s'; }
        if (array_key_exists('fecha_cierre', $data))    { $campos[]='fecha_cierre = ?';    $params[] = $data['fecha_cierre'] ?: null;    $types.='s'; }

        if (empty($campos)) throw new InvalidArgumentException('No hay campos para actualizar.');

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $reporteId;

        $campos[]='updated_at = ?'; $params[]=$now;    $types.='s';
        $campos[]='updated_by = ?'; $params[]=$actorId; $types.='s';

        $sql = "UPDATE {$this->table} SET ".implode(', ', $campos)." WHERE reporte_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $types.='s'; $params[]=$reporteId;
        $stmt->bind_param($types, ...$params);
        $ok  = $stmt->execute(); $err = $stmt->error; $stmt->close();

        if (!$ok) {
            if (str_contains(strtolower($err), 'foreign key')) {
                throw new RuntimeException('Referencia inválida a finca/aprisco/área.');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    /**
     * Cambia solo estado_reporte ('ABIERTO'|'EN_PROCESO'|'CERRADO').
     * Si pasa a 'CERRADO' y no se envía fecha_cierre, la fija a now.
     * Puede opcionalmente fijar solucionado_por.
     */
    public function actualizarEstado(string $reporteId, string $estado, ?string $solucionadoPor = null, ?string $fechaCierre = null): bool
    {
        $this->validarEstadoReporte($estado);

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $reporteId;

        if ($estado === 'CERRADO' && $fechaCierre === null) {
            $fechaCierre = $now;
        }

        $sql = "UPDATE {$this->table}
                SET estado_reporte = ?, solucionado_por = ?, fecha_cierre = ?, updated_at = ?, updated_by = ?
                WHERE reporte_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización de estado: " . $this->db->error);

        $stmt->bind_param('ssssss', $estado, $solucionadoPor, $fechaCierre, $now, $actorId, $reporteId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Eliminación lógica (soft delete).
     */
    public function eliminar(string $reporteId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $reporteId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE reporte_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $reporteId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
