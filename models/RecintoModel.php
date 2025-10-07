<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class RecintoModel
{
    private $db;
    private $table = 'recintos';

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

    private function areaExiste(string $areaId): bool
    {
        $sql  = "SELECT 1 FROM areas WHERE area_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $areaId);
        $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0; $stmt->close();
        return $ok;
    }

    private function validarEstado(?string $v): void
    {
        if ($v === null) return;
        $validos = ['ACTIVO','INACTIVO'];
        if (!in_array($v, $validos, true)) {
            throw new InvalidArgumentException("estado inválido. Use: " . implode(', ', $validos));
        }
    }

    /**
     * Obtiene el siguiente código correlativo para un área dada.
     * Formato: 'rec_01', 'rec_02', ... según el MAYOR valor existente en el área.
     * Si no hay registros, devuelve 'rec_01'.
     */
    private function siguienteCodigoPorArea(string $areaId): string
    {
        // Tomamos la parte numérica posterior al último '_' y la convertimos a UNSIGNED
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(codigo_recinto, '_', -1) AS UNSIGNED)) AS maxnum
                FROM {$this->table}
                WHERE area_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $areaId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $next = (int)($res['maxnum'] ?? 0) + 1;
        // Mínimo 2 dígitos; si pasa de 99 seguirá creciendo (100, 101, ...)
        $sufijo = str_pad((string)$next, 2, '0', STR_PAD_LEFT);
        return "rec_" . $sufijo;
    }

    /* ============ Lecturas ============ */

    /**
     * Lista recintos.
     * Filtros: area_id, estado, codigo (coincidencia exacta u opcional LIKE conservador).
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $areaId = null,
        ?string $estado = null,
        ?string $codigo = null
    ): array {
        $w=[]; $p=[]; $t='';

        $w[] = $incluirEliminados ? 'r.deleted_at IS NOT NULL OR r.deleted_at IS NULL' : 'r.deleted_at IS NULL';
        if ($areaId) { $w[]='r.area_id = ?';           $p[]=$areaId;  $t.='s'; }
        if ($estado) { $this->validarEstado($estado);  $w[]='r.estado = ?';    $p[]=$estado; $t.='s'; }
        if ($codigo) {
            // búsqueda exacta o patrón 'rec_%'; aquí exacta; si quieres LIKE, descomenta la línea de abajo
            $w[]='r.codigo_recinto = ?'; $p[]=$codigo; $t.='s';
            // $w[]='r.codigo_recinto LIKE ?'; $p[]='%'.$codigo.'%'; $t.='s';
        }

        $where = implode(' AND ', $w);

        $sql = "SELECT
                    r.recinto_id,
                    r.area_id,
                    r.codigo_recinto,
                    r.capacidad,
                    r.estado,
                    r.observaciones,
                    r.created_at, r.created_by, r.updated_at, r.updated_by
                FROM {$this->table} r
                WHERE {$where}
                ORDER BY r.area_id, r.codigo_recinto
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $t .= 'ii'; $p[] = $limit; $p[] = $offset;
        $stmt->bind_param($t, ...$p);
        $stmt->execute();
        $res  = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function obtenerPorId(string $recintoId): ?array
    {
        $sql = "SELECT
                    r.recinto_id,
                    r.area_id,
                    r.codigo_recinto,
                    r.capacidad,
                    r.estado,
                    r.observaciones,
                    r.created_at, r.created_by, r.updated_at, r.updated_by,
                    r.deleted_at, r.deleted_by
                FROM {$this->table} r
                WHERE r.recinto_id = ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $recintoId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crea un recinto.
     * Requeridos: area_id
     * Opcionales: capacidad, estado (default ACTIVO), observaciones
     * codigo_recinto se genera automáticamente por área (rec_01, rec_02, ...)
     */
    public function crear(array $data): string
    {
        $areaId = trim((string)($data['area_id'] ?? ''));
        if ($areaId === '') {
            throw new InvalidArgumentException('Falta el campo requerido: area_id.');
        }
        if (!$this->areaExiste($areaId)) {
            throw new RuntimeException('El área no existe o está eliminada.');
        }

        $capacidad = isset($data['capacidad']) && $data['capacidad'] !== '' ? (int)$data['capacidad'] : null;
        if ($capacidad !== null && $capacidad < 0) {
            throw new InvalidArgumentException('La capacidad no puede ser negativa.');
        }

        $estado = isset($data['estado']) ? (string)$data['estado'] : 'ACTIVO';
        $this->validarEstado($estado);

        $observ = isset($data['observaciones']) ? trim((string)$data['observaciones']) : null;

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $uuid    = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            // Generar correlativo por área usando el máximo actual
            $codigo = $this->siguienteCodigoPorArea($areaId);

            $sql = "INSERT INTO {$this->table}
                    (recinto_id, area_id, codigo_recinto, capacidad, estado, observaciones,
                     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            // tipos: s s s i s s s s
            $types = 'sssissss';
            $stmt->bind_param(
                $types,
                $uuid,
                $areaId,
                $codigo,
                $capacidad,
                $estado,
                $observ,
                $now,
                $actorId
            );

            if (!$stmt->execute()) {
                $err = strtolower($stmt->error);
                $stmt->close();
                // Mitigación básica de condición de carrera en UNIQUE (reintento una vez)
                if (str_contains($err, 'duplicate') || str_contains($err, 'unique')) {
                    $codigo = $this->siguienteCodigoPorArea($areaId);
                    $stmt2 = $this->db->prepare($sql);
                    if (!$stmt2) { $this->db->rollback(); throw new mysqli_sql_exception($this->db->error); }
                    $stmt2->bind_param($types, $uuid, $areaId, $codigo, $capacidad, $estado, $observ, $now, $actorId);
                    if (!$stmt2->execute()) {
                        $e2 = $stmt2->error; $stmt2->close(); $this->db->rollback();
                        throw new mysqli_sql_exception("Error al insertar (reintento): " . $e2);
                    }
                    $stmt2->close();
                } else {
                    $this->db->rollback();
                    if (str_contains($err, 'foreign key')) {
                        throw new RuntimeException('Referencia inválida a área.');
                    }
                    throw new mysqli_sql_exception("Error al ejecutar inserción: " . $err);
                }
            } else {
                $stmt->close();
            }

            $this->db->commit();
            return $uuid;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Actualiza campos: capacidad?, estado?, observaciones?
     * (area_id y codigo_recinto NO se actualizan para preservar la trazabilidad)
     */
    public function actualizar(string $recintoId, array $data): bool
    {
        $set=[]; $p=[]; $t='';

        if (array_key_exists('capacidad', $data)) {
            if ($data['capacidad'] === '' || $data['capacidad'] === null) {
                $set[]='capacidad = ?'; $p[]=null; $t.='s';
            } else {
                $c = (int)$data['capacidad']; if ($c < 0) throw new InvalidArgumentException('capacidad no puede ser negativa.');
                $set[]='capacidad = ?'; $p[]=$c; $t.='i';
            }
        }
        if (isset($data['estado'])) {
            $this->validarEstado((string)$data['estado']);
            $set[]='estado = ?'; $p[]=(string)$data['estado']; $t.='s';
        }
        if (array_key_exists('observaciones', $data)) {
            $set[]='observaciones = ?'; $p[]=($data['observaciones']!=='' ? (string)$data['observaciones'] : null); $t.='s';
        }

        if (empty($set)) throw new InvalidArgumentException('No hay campos para actualizar.');

        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $recintoId;

        $set[]='updated_at = ?'; $p[]=$now;     $t.='s';
        $set[]='updated_by = ?'; $p[]=$actorId; $t.='s';

        $sql = "UPDATE {$this->table} SET ".implode(', ', $set)." WHERE recinto_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $t.='s'; $p[]=$recintoId;
        $stmt->bind_param($t, ...$p);
        $ok  = $stmt->execute(); $err = $stmt->error; $stmt->close();
        if (!$ok) throw new mysqli_sql_exception("Error al actualizar: " . $err);
        return true;
    }

    /**
     * Cambiar solo estado.
     * JSON: { estado: 'ACTIVO'|'INACTIVO' }
     */
    public function actualizarEstado(string $recintoId, string $estado): bool
    {
        $this->validarEstado($estado);

        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $recintoId;

        $sql = "UPDATE {$this->table}
                SET estado = ?, updated_at = ?, updated_by = ?
                WHERE recinto_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización de estado: " . $this->db->error);

        $stmt->bind_param('ssss', $estado, $now, $actorId, $recintoId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Soft delete.
     */
    public function eliminar(string $recintoId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $recintoId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE recinto_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql); if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $recintoId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
