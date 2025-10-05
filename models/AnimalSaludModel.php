<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class AnimalSaludModel
{
    private $db;
    private $table = 'animal_salud';

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

    private function validarFecha(string $ymd, string $campo = 'fecha'): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
            throw new InvalidArgumentException("$campo inválida. Formato esperado YYYY-MM-DD.");
        }
        [$y,$m,$d] = array_map('intval', explode('-', $ymd));
        if (!checkdate($m,$d,$y)) {
            throw new InvalidArgumentException("$campo no es una fecha válida.");
        }
    }

    private function validarEnum(string $valor, array $permitidos, string $campo): string
    {
        $v = strtoupper(trim($valor));
        if (!in_array($v, $permitidos, true)) {
            throw new InvalidArgumentException("$campo inválido. Use uno de: " . implode(', ', $permitidos));
        }
        return $v;
    }

    private function validarCosto(?float $costo): ?float
    {
        if ($costo === null) return null;
        if ($costo < 0 || $costo > 999999.99) {
            throw new InvalidArgumentException("costo fuera de rango.");
        }
        return $costo;
    }

    /* ============ Lecturas ============ */

    /**
     * Lista eventos de salud.
     * Filtros: animal_id, tipo_evento, severidad, estado, desde, hasta, q (texto),
     * incluirEliminados (bool). Paginación: limit/offset
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $animalId = null,
        ?string $tipoEvento = null,
        ?string $severidad = null,
        ?string $estado = null,
        ?string $desde = null,
        ?string $hasta = null,
        ?string $q = null
    ): array {
        $where  = [];
        $params = [];
        $types  = '';

        $where[] = $incluirEliminados ? 's.deleted_at IS NOT NULL OR s.deleted_at IS NULL' : 's.deleted_at IS NULL';

        if ($animalId) {
            $where[]  = 's.animal_id = ?';
            $params[] = $animalId;
            $types   .= 's';
        }
        if ($tipoEvento) {
            $tipoEvento = $this->validarEnum($tipoEvento, ['ENFERMEDAD','VACUNACION','DESPARASITACION','REVISION','TRATAMIENTO','OTRO'], 'tipo_evento');
            $where[]  = 's.tipo_evento = ?';
            $params[] = $tipoEvento;
            $types   .= 's';
        }
        if ($severidad) {
            // ✅ Ahora incluye NO_APLICA
            $severidad = $this->validarEnum($severidad, ['LEVE','MODERADA','GRAVE','NO_APLICA'], 'severidad');
            $where[]  = 's.severidad = ?';
            $params[] = $severidad;
            $types   .= 's';
        }
        if ($estado) {
            $estado = $this->validarEnum($estado, ['ABIERTO','SEGUIMIENTO','CERRADO'], 'estado');
            $where[]  = 's.estado = ?';
            $params[] = $estado;
            $types   .= 's';
        }
        if ($desde) {
            $this->validarFecha($desde, 'desde');
            $where[]  = 's.fecha_evento >= ?';
            $params[] = $desde;
            $types   .= 's';
        }
        if ($hasta) {
            $this->validarFecha($hasta, 'hasta');
            $where[]  = 's.fecha_evento <= ?';
            $params[] = $hasta;
            $types   .= 's';
        }
        if ($q) {
            $like = '%' . $q . '%';
            $where[]  = '(s.diagnostico LIKE ? OR s.tratamiento LIKE ? OR s.medicamento LIKE ? OR s.observaciones LIKE ? OR s.responsable LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
            $types .= 'sssss';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT
                    s.animal_salud_id,
                    s.animal_id,
                    a.identificador AS animal_identificador,
                    s.fecha_evento,
                    s.tipo_evento,
                    s.diagnostico,
                    s.severidad,
                    s.tratamiento,
                    s.medicamento,
                    s.dosis,
                    s.via_administracion,
                    s.costo,
                    s.estado,
                    s.proxima_revision,
                    s.responsable,
                    s.observaciones,
                    s.created_at,
                    s.created_by,
                    s.updated_at,
                    s.updated_by
                FROM {$this->table} s
                LEFT JOIN animales a ON a.animal_id = s.animal_id
                WHERE {$whereSql}
                ORDER BY s.fecha_evento DESC, s.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $types   .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function obtenerPorId(string $id): ?array
    {
        $sql = "SELECT
                    s.animal_salud_id,
                    s.animal_id,
                    a.identificador AS animal_identificador,
                    s.fecha_evento,
                    s.tipo_evento,
                    s.diagnostico,
                    s.severidad,
                    s.tratamiento,
                    s.medicamento,
                    s.dosis,
                    s.via_administracion,
                    s.costo,
                    s.estado,
                    s.proxima_revision,
                    s.responsable,
                    s.observaciones,
                    s.created_at,
                    s.created_by,
                    s.updated_at,
                    s.updated_by,
                    s.deleted_at,
                    s.deleted_by
                FROM {$this->table} s
                LEFT JOIN animales a ON a.animal_id = s.animal_id
                WHERE s.animal_salud_id = ?";
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
     * Crear evento de salud.
     * Requeridos: animal_id, fecha_evento(YYYY-MM-DD), tipo_evento
     * Opcionales: diagnostico, severidad(LEVE|MODERADA|GRAVE|NO_APLICA), tratamiento, medicamento, dosis,
     *             via_administracion, costo, estado, proxima_revision, responsable, observaciones
     */
    public function crear(array $data): string
    {
        foreach (['animal_id','fecha_evento','tipo_evento'] as $k) {
            if (!isset($data[$k]) || $data[$k] === '') {
                throw new InvalidArgumentException("Falta campo requerido: {$k}.");
            }
        }

        $animalId    = trim((string)$data['animal_id']);
        $fechaEvento = trim((string)$data['fecha_evento']);
        $tipoEvento  = $this->validarEnum((string)$data['tipo_evento'], ['ENFERMEDAD','VACUNACION','DESPARASITACION','REVISION','TRATAMIENTO','OTRO'], 'tipo_evento');

        if (!$this->animalExiste($animalId)) {
            throw new RuntimeException('El animal especificado no existe o está eliminado.');
        }
        $this->validarFecha($fechaEvento, 'fecha_evento');

        $diagnostico      = isset($data['diagnostico']) ? trim((string)$data['diagnostico']) : null;
        $severidad        = isset($data['severidad']) && $data['severidad'] !== null
                            ? $this->validarEnum((string)$data['severidad'], ['LEVE','MODERADA','GRAVE','NO_APLICA'], 'severidad')
                            : null;
        $tratamiento      = isset($data['tratamiento']) ? trim((string)$data['tratamiento']) : null;
        $medicamento      = isset($data['medicamento']) ? trim((string)$data['medicamento']) : null;
        $dosis            = isset($data['dosis']) ? trim((string)$data['dosis']) : null;
        $via              = isset($data['via_administracion']) ? trim((string)$data['via_administracion']) : null;
        $costo            = array_key_exists('costo',$data) && $data['costo'] !== null ? $this->validarCosto((float)$data['costo']) : null;
        $estado           = isset($data['estado']) && $data['estado'] !== null
                            ? $this->validarEnum((string)$data['estado'], ['ABIERTO','SEGUIMIENTO','CERRADO'], 'estado')
                            : 'ABIERTO';
        $proximaRevision  = isset($data['proxima_revision']) && $data['proxima_revision'] !== null
                            ? (function($d,$self){ $self->validarFecha($d,'proxima_revision'); return $d; })((string)$data['proxima_revision'], $this)
                            : null;
        $responsable      = isset($data['responsable']) ? trim((string)$data['responsable']) : null;
        $observaciones    = isset($data['observaciones']) ? trim((string)$data['observaciones']) : null;

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $uuid    = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                (animal_salud_id, animal_id, fecha_evento, tipo_evento, diagnostico, severidad,
                 tratamiento, medicamento, dosis, via_administracion, costo, estado, proxima_revision,
                 responsable, observaciones, created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            // tipos: s s s s s s s s s s d s s s s s s
            $types = 'ssssssssss' . 'd' . 'ssssss';

            $stmt->bind_param(
                $types,
                $uuid,
                $animalId,
                $fechaEvento,
                $tipoEvento,
                $diagnostico,
                $severidad,
                $tratamiento,
                $medicamento,
                $dosis,
                $via,
                $costo,
                $estado,
                $proximaRevision,
                $responsable,
                $observaciones,
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
     * Actualizar evento de salud.
     * Campos: fecha_evento?, tipo_evento?, diagnostico?, severidad(LEVE|MODERADA|GRAVE|NO_APLICA)?,
     * tratamiento?, medicamento?, dosis?, via_administracion?, costo?, estado?, proxima_revision?, responsable?, observaciones?
     */
    public function actualizar(string $id, array $data): bool
    {
        $campos = [];
        $params = [];
        $types  = '';

        if (isset($data['fecha_evento'])) {
            $this->validarFecha((string)$data['fecha_evento'], 'fecha_evento');
            $campos[] = 'fecha_evento = ?';
            $params[] = (string)$data['fecha_evento'];
            $types   .= 's';
        }
        if (isset($data['tipo_evento'])) {
            $campos[] = 'tipo_evento = ?';
            $params[] = $this->validarEnum((string)$data['tipo_evento'], ['ENFERMEDAD','VACUNACION','DESPARASITACION','REVISION','TRATAMIENTO','OTRO'], 'tipo_evento');
            $types   .= 's';
        }
        if (array_key_exists('diagnostico', $data)) {
            $campos[] = 'diagnostico = ?';
            $params[] = $data['diagnostico'] !== null ? trim((string)$data['diagnostico']) : null;
            $types   .= 's';
        }
        if (isset($data['severidad'])) {
            $campos[] = 'severidad = ?';
            $params[] = $this->validarEnum((string)$data['severidad'], ['LEVE','MODERADA','GRAVE','NO_APLICA'], 'severidad');
            $types   .= 's';
        }
        if (array_key_exists('tratamiento', $data)) {
            $campos[] = 'tratamiento = ?';
            $params[] = $data['tratamiento'] !== null ? trim((string)$data['tratamiento']) : null;
            $types   .= 's';
        }
        if (array_key_exists('medicamento', $data)) {
            $campos[] = 'medicamento = ?';
            $params[] = $data['medicamento'] !== null ? trim((string)$data['medicamento']) : null;
            $types   .= 's';
        }
        if (array_key_exists('dosis', $data)) {
            $campos[] = 'dosis = ?';
            $params[] = $data['dosis'] !== null ? trim((string)$data['dosis']) : null;
            $types   .= 's';
        }
        if (array_key_exists('via_administracion', $data)) {
            $campos[] = 'via_administracion = ?';
            $params[] = $data['via_administracion'] !== null ? trim((string)$data['via_administracion']) : null;
            $types   .= 's';
        }
        if (array_key_exists('costo', $data)) {
            $costo = $data['costo'] !== null ? $this->validarCosto((float)$data['costo']) : null;
            $campos[] = 'costo = ?';
            $params[] = $costo; // puede ser null
            $types   .= 'd';
        }
        if (isset($data['estado'])) {
            $campos[] = 'estado = ?';
            $params[] = $this->validarEnum((string)$data['estado'], ['ABIERTO','SEGUIMIENTO','CERRADO'], 'estado');
            $types   .= 's';
        }
        if (array_key_exists('proxima_revision', $data)) {
            $val = $data['proxima_revision'];
            if ($val !== null) $this->validarFecha((string)$val, 'proxima_revision');
            $campos[] = 'proxima_revision = ?';
            $params[] = $val !== null ? (string)$val : null;
            $types   .= 's';
        }
        if (array_key_exists('responsable', $data)) {
            $campos[] = 'responsable = ?';
            $params[] = $data['responsable'] !== null ? trim((string)$data['responsable']) : null;
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
                WHERE animal_salud_id = ? AND deleted_at IS NULL";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $types   .= 's';
        $params[] = $id;

        $stmt->bind_param($types, ...$params);
        $ok  = $stmt->execute();
        $err = strtolower($stmt->error);
        $stmt->close();

        if (!$ok) {
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
                WHERE animal_salud_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
