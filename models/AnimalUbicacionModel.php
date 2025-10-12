<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class AnimalUbicacionModel
{
    private $db;
    private $table = 'animal_ubicaciones';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /* ============ Utilidades ============ */

    private function generateUUIDv4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
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

    private function validarFecha(string $ymd, string $campo = 'fecha'): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
            throw new InvalidArgumentException("$campo inválida. Formato esperado YYYY-MM-DD.");
        }
        [$y, $m, $d] = array_map('intval', explode('-', $ymd));
        if (!checkdate($m, $d, $y)) {
            throw new InvalidArgumentException("$campo no es una fecha válida.");
        }
    }

    private function animalExiste(string $animalId): bool
    {
        $sql = "SELECT 1 FROM animales WHERE animal_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error al preparar verificación de animal: " . $this->db->error);
        $stmt->bind_param('s', $animalId);
        $stmt->execute();
        $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    private function fincaExiste(?string $fincaId): bool
    {
        if ($fincaId === null)
            return true;
        $sql = "SELECT 1 FROM fincas WHERE finca_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error verificación finca: " . $this->db->error);
        $stmt->bind_param('s', $fincaId);
        $stmt->execute();
        $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    private function apriscoExiste(?string $apriscoId): bool
    {
        if ($apriscoId === null)
            return true;
        $sql = "SELECT 1 FROM apriscos WHERE aprisco_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error verificación aprisco: " . $this->db->error);
        $stmt->bind_param('s', $apriscoId);
        $stmt->execute();
        $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    private function areaExiste(?string $areaId): bool
    {
        if ($areaId === null)
            return true;
        $sql = "SELECT aprisco_id FROM areas WHERE area_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error verificación área: " . $this->db->error);
        $stmt->bind_param('s', $areaId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return isset($row['aprisco_id']);
    }

    private function recintoExiste(?string $recintoId): bool
    {
        if ($recintoId === null)
            return true;
        $sql = "SELECT area_id FROM recintos WHERE recinto_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error verificación recinto: " . $this->db->error);
        $stmt->bind_param('s', $recintoId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return isset($row['area_id']);
    }

    /**
     * Verifica la consistencia jerárquica:
     * - si hay recinto_id ⇒ pertenece a area_id/aprisco_id/finca_id indicados (si fueron provistos)
     * - si hay area_id ⇒ pertenece a aprisco_id/finca_id indicados
     * - si hay aprisco_id ⇒ pertenece a finca_id indicado
     */
    private function validarJerarquia(?string $fincaId, ?string $apriscoId, ?string $areaId, ?string $recintoId): void
    {
        if ($recintoId !== null) {
            $sql = "SELECT r.area_id, a.aprisco_id, ap.finca_id
                    FROM recintos r
                    JOIN areas a    ON a.area_id = r.area_id
                    JOIN apriscos ap ON ap.aprisco_id = a.aprisco_id
                    WHERE r.recinto_id = ? AND r.deleted_at IS NULL AND a.deleted_at IS NULL AND ap.deleted_at IS NULL";
            $stmt = $this->db->prepare($sql);
            if (!$stmt)
                throw new mysqli_sql_exception("Error jerarquía recinto: " . $this->db->error);
            $stmt->bind_param('s', $recintoId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if (!$row)
                throw new InvalidArgumentException("El recinto no existe o está eliminado.");
            if ($areaId !== null && $row['area_id'] !== $areaId) {
                throw new InvalidArgumentException("El recinto no pertenece al área especificada.");
            }
            if ($apriscoId !== null && $row['aprisco_id'] !== $apriscoId) {
                throw new InvalidArgumentException("El recinto no pertenece al aprisco especificado.");
            }
            if ($fincaId !== null && $row['finca_id'] !== $fincaId) {
                throw new InvalidArgumentException("El recinto no pertenece a la finca especificada.");
            }
            // Si no vino area/aprisco pero sí recinto, propagamos los IDs derivados
            if ($areaId === null)
                $areaId = $row['area_id'];
            if ($apriscoId === null)
                $apriscoId = $row['aprisco_id'];
            if ($fincaId === null)
                $fincaId = $row['finca_id'];
        }

        if ($areaId !== null) {
            $sql = "SELECT ap.aprisco_id, ap.finca_id
                    FROM areas a
                    JOIN apriscos ap ON ap.aprisco_id = a.aprisco_id
                    WHERE a.area_id = ? AND a.deleted_at IS NULL";
            $stmt = $this->db->prepare($sql);
            if (!$stmt)
                throw new mysqli_sql_exception("Error jerarquía área: " . $this->db->error);
            $stmt->bind_param('s', $areaId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            if (!$row)
                throw new InvalidArgumentException("El área no existe o está eliminada.");
            if ($apriscoId !== null && $row['aprisco_id'] !== $apriscoId) {
                throw new InvalidArgumentException("El área no pertenece al aprisco especificado.");
            }
            if ($fincaId !== null && $row['finca_id'] !== $fincaId) {
                throw new InvalidArgumentException("El área no pertenece a la finca especificada.");
            }
        }

        if ($apriscoId !== null) {
            $sql = "SELECT finca_id FROM apriscos WHERE aprisco_id = ? AND deleted_at IS NULL";
            $stmt = $this->db->prepare($sql);
            if (!$stmt)
                throw new mysqli_sql_exception("Error jerarquía aprisco: " . $this->db->error);
            $stmt->bind_param('s', $apriscoId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            if (!$row)
                throw new InvalidArgumentException("El aprisco no existe o está eliminado.");
            if ($fincaId !== null && $row['finca_id'] !== $fincaId) {
                throw new InvalidArgumentException("El aprisco no pertenece a la finca especificada.");
            }
        }
    }

    private function existeActiva(string $animalId): bool
    {
        $sql = "SELECT 1
                FROM {$this->table}
                WHERE animal_id = ? AND fecha_hasta IS NULL AND deleted_at IS NULL
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error verificación activa: " . $this->db->error);
        $stmt->bind_param('s', $animalId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /* ============ Lecturas ============ */

    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $animalId = null,
        ?string $fincaId = null,
        ?string $apriscoId = null,
        ?string $areaId = null,
        ?string $recintoId = null,
        ?string $desde = null,
        ?string $hasta = null,
        bool $soloActivas = false
    ): array {
        $where = [];
        $params = [];
        $types = '';

        if (!$incluirEliminados) {
            $where[] = 'u.deleted_at IS NULL';
        }

        if ($animalId) {
            $where[] = 'u.animal_id = ?';
            $params[] = $animalId;
            $types .= 's';
        }
        if ($fincaId) {
            $where[] = 'u.finca_id = ?';
            $params[] = $fincaId;
            $types .= 's';
        }
        if ($apriscoId) {
            $where[] = 'u.aprisco_id = ?';
            $params[] = $apriscoId;
            $types .= 's';
        }
        if ($areaId) {
            $where[] = 'u.area_id = ?';
            $params[] = $areaId;
            $types .= 's';
        }
        if ($recintoId) {
            $where[] = 'u.recinto_id = ?';
            $params[] = $recintoId;
            $types .= 's';
        }
        if ($soloActivas) {
            $where[] = 'u.fecha_hasta IS NULL';
        }
        if ($desde) {
            $this->validarFecha($desde, 'desde');
            $where[] = 'COALESCE(u.fecha_hasta, "9999-12-31") >= ?';
            $params[] = $desde;
            $types .= 's';
        }
        if ($hasta) {
            $this->validarFecha($hasta, 'hasta');
            $where[] = 'u.fecha_desde <= ?';
            $params[] = $hasta;
            $types .= 's';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT
                    u.animal_ubicacion_id,
                    u.animal_id,
                    a.identificador AS animal_identificador,
                    u.finca_id,   f.nombre AS nombre_finca,
                    u.aprisco_id, ap.nombre AS nombre_aprisco,
                    u.area_id,    ar.nombre_personalizado AS nombre_area, ar.numeracion AS area_numeracion,
                    u.recinto_id
                    -- Si tu tabla recintos tiene estos campos, descomenta:
                    , r.codigo_recinto AS codigo_recinto
                    , u.fecha_desde, u.fecha_hasta,
                    u.motivo, u.estado, u.observaciones,
                    u.created_at, u.created_by, u.updated_at, u.updated_by,
                    u.deleted_at, u.deleted_by
                FROM {$this->table} u
                LEFT JOIN animales a  ON a.animal_id   = u.animal_id
                LEFT JOIN fincas   f  ON f.finca_id    = u.finca_id
                LEFT JOIN apriscos ap  ON ap.aprisco_id = u.aprisco_id
                LEFT JOIN areas   ar  ON ar.area_id    = u.area_id
                LEFT JOIN recintos r  ON r.recinto_id  = u.recinto_id
                $whereSql
                ORDER BY COALESCE(u.fecha_hasta, '9999-12-31') DESC, u.fecha_desde DESC, u.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function obtenerPorId(string $id): ?array
    {
        $sql = "SELECT
                    u.animal_ubicacion_id,
                    u.animal_id,
                    a.identificador AS animal_identificador,
                    u.finca_id,   f.nombre AS nombre_finca,
                    u.aprisco_id, ap.nombre AS nombre_aprisco,
                    u.area_id,    ar.nombre_personalizado AS nombre_area, ar.numeracion AS area_numeracion,
                    u.recinto_id, r.codigo_recinto AS codigo_recinto, u.fecha_desde, u.fecha_hasta,
                    u.motivo, u.estado, u.observaciones,
                    u.created_at, u.created_by, u.updated_at, u.updated_by,
                    u.deleted_at, u.deleted_by
                FROM {$this->table} u
                LEFT JOIN animales a  ON a.animal_id   = u.animal_id
                LEFT JOIN fincas   f  ON f.finca_id    = u.finca_id
                LEFT JOIN apriscos ap  ON ap.aprisco_id = u.aprisco_id
                LEFT JOIN areas   ar  ON ar.area_id    = u.area_id
                LEFT JOIN recintos r  ON r.recinto_id  = u.recinto_id
                WHERE u.animal_ubicacion_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getActual(string $animalId): ?array
    {
        $sql = "SELECT
                    u.animal_ubicacion_id,
                    u.animal_id,
                    a.identificador AS animal_identificador,
                    u.finca_id,   f.nombre AS nombre_finca,
                    u.aprisco_id, ap.nombre AS nombre_aprisco,
                    u.area_id,    ar.nombre_personalizado AS nombre_area, ar.numeracion AS area_numeracion,
                    u.recinto_id
                    -- , r.nombre AS nombre_recinto, r.numeracion AS recinto_numeracion
                    , u.fecha_desde, u.fecha_hasta,
                    u.motivo, u.estado, u.observaciones
                FROM {$this->table} u
                LEFT JOIN animales a  ON a.animal_id   = u.animal_id
                LEFT JOIN fincas   f  ON f.finca_id    = u.finca_id
                LEFT JOIN apriscos ap  ON ap.aprisco_id = u.aprisco_id
                LEFT JOIN areas   ar  ON ar.area_id    = u.area_id
                LEFT JOIN recintos r  ON r.recinto_id  = u.recinto_id
                WHERE u.animal_id = ?
                  AND u.fecha_hasta IS NULL
                  AND u.deleted_at IS NULL
                ORDER BY u.fecha_desde DESC, u.created_at DESC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error al preparar getActual: " . $this->db->error);
        $stmt->bind_param('s', $animalId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crear ubicación.
     * Reglas clave:
     * - Si fecha_hasta es NULL ⇒ estado = 'ACTIVA' (forzado)
     * - Si fecha_hasta no es NULL ⇒ estado = 'INACTIVA' (forzado)
     * - No permite doble activa por animal.
     */
    public function crear(array $data): string
    {
        if (empty($data['animal_id']) || empty($data['fecha_desde'])) {
            throw new InvalidArgumentException("Faltan campos requeridos: animal_id, fecha_desde.");
        }

        $animalId = (string) trim($data['animal_id']);
        $fincaId = isset($data['finca_id']) ? (string) trim((string) $data['finca_id']) : null;
        $apriscoId = isset($data['aprisco_id']) ? (string) trim((string) $data['aprisco_id']) : null;
        $areaId = isset($data['area_id']) ? (string) trim((string) $data['area_id']) : null;
        $recintoId = isset($data['recinto_id']) ? (string) trim((string) $data['recinto_id']) : null;

        $fechaDesde = (string) trim((string) $data['fecha_desde']);
        $this->validarFecha($fechaDesde, 'fecha_desde');

        $fechaHasta = null;
        if (isset($data['fecha_hasta']) && $data['fecha_hasta'] !== null && $data['fecha_hasta'] !== '') {
            $fechaHasta = (string) trim((string) $data['fecha_hasta']);
            $this->validarFecha($fechaHasta, 'fecha_hasta');
            if ($fechaHasta < $fechaDesde) {
                throw new InvalidArgumentException("fecha_hasta no puede ser menor que fecha_desde.");
            }
        }

        // Motivo (opcional con validación)
        $motivo = isset($data['motivo']) ? strtoupper(trim((string) $data['motivo'])) : 'OTRO';
        if (!in_array($motivo, ['TRASLADO', 'INGRESO', 'EGRESO', 'AISLAMIENTO', 'VENTA', 'OTRO'], true)) {
            throw new InvalidArgumentException("motivo inválido. Use: TRASLADO, INGRESO, EGRESO, AISLAMIENTO, VENTA, OTRO.");
        }

        // Estado: SIEMPRE forzado según actividad
        $estado = ($fechaHasta === null) ? 'ACTIVA' : 'INACTIVA';

        if (!$this->animalExiste($animalId)) {
            throw new RuntimeException('El animal especificado no existe o está eliminado.');
        }
        if (
            !$this->fincaExiste($fincaId) ||
            !$this->apriscoExiste($apriscoId) ||
            !$this->areaExiste($areaId) ||
            !$this->recintoExiste($recintoId)
        ) {
            throw new RuntimeException('Finca, aprisco, área o recinto no existen o están eliminados.');
        }

        $this->validarJerarquia($fincaId, $apriscoId, $areaId, $recintoId);

        // Evitar doble activa
        if ($fechaHasta === null && $this->existeActiva($animalId)) {
            throw new RuntimeException('Ya existe una ubicación activa para este animal. Debe cerrarse antes de crear otra.');
        }

        $observaciones = isset($data['observaciones']) ? trim((string) $data['observaciones']) : null;

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $uuid = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                    (animal_ubicacion_id, animal_id, finca_id, aprisco_id, area_id, recinto_id,
                     fecha_desde, fecha_hasta, motivo, estado, observaciones,
                     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt)
                throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            $stmt->bind_param(
                'sssssssssssss',
                $uuid,
                $animalId,
                $fincaId,
                $apriscoId,
                $areaId,
                $recintoId,
                $fechaDesde,
                $fechaHasta,
                $motivo,
                $estado,
                $observaciones,
                $now,
                $actorId
            );

            if (!$stmt->execute()) {
                $err = strtolower($stmt->error);
                $stmt->close();
                $this->db->rollback();

                if (str_contains($err, 'foreign key')) {
                    throw new RuntimeException('FK inválida (animal/finca/aprisco/area/recinto).');
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
     * Actualizar ubicación.
     * Normaliza estado en función de fecha_hasta:
     *  - NULL  => ACTIVA (y valida única activa)
     *  - !NULL => INACTIVA
     */
    public function actualizar(string $id, array $data): bool
    {
        $campos = [];
        $params = [];
        $types = '';

        $current = $this->obtenerPorId($id);
        if (!$current || $current['deleted_at'] !== null) {
            throw new RuntimeException("La ubicación no existe o está eliminada.");
        }

        $fincaId = $current['finca_id'];
        $apriscoId = $current['aprisco_id'];
        $areaId = $current['area_id'];
        $recintoId = $current['recinto_id'];
        $fechaDesde = $current['fecha_desde'];
        $fechaHasta = $current['fecha_hasta']; // puede ser null

        if (array_key_exists('finca_id', $data)) {
            $fincaId = $data['finca_id'] !== null ? (string) $data['finca_id'] : null;
        }
        if (array_key_exists('aprisco_id', $data)) {
            $apriscoId = $data['aprisco_id'] !== null ? (string) $data['aprisco_id'] : null;
        }
        if (array_key_exists('area_id', $data)) {
            $areaId = $data['area_id'] !== null ? (string) $data['area_id'] : null;
        }
        if (array_key_exists('recinto_id', $data)) {
            $recintoId = $data['recinto_id'] !== null ? (string) $data['recinto_id'] : null;
        }

        if (isset($data['fecha_desde'])) {
            $this->validarFecha((string) $data['fecha_desde'], 'fecha_desde');
            $fechaDesde = (string) $data['fecha_desde'];
            $campos[] = 'fecha_desde = ?';
            $params[] = $fechaDesde;
            $types .= 's';
        }
        if (array_key_exists('fecha_hasta', $data)) {
            if ($data['fecha_hasta'] !== null && $data['fecha_hasta'] !== '') {
                $this->validarFecha((string) $data['fecha_hasta'], 'fecha_hasta');
                $fechaHasta = (string) $data['fecha_hasta'];
                if ($fechaHasta < $fechaDesde) {
                    throw new InvalidArgumentException("fecha_hasta no puede ser menor que fecha_desde.");
                }
            } else {
                $fechaHasta = null;
            }
            $campos[] = 'fecha_hasta = ?';
            $params[] = $fechaHasta;
            $types .= 's';
        }

        if (array_key_exists('observaciones', $data)) {
            $campos[] = 'observaciones = ?';
            $params[] = $data['observaciones'] !== null ? trim((string) $data['observaciones']) : null;
            $types .= 's';
        }

        // Validar FKs y jerarquía si cambiaron
        if (
            !$this->fincaExiste($fincaId) ||
            !$this->apriscoExiste($apriscoId) ||
            !$this->areaExiste($areaId) ||
            !$this->recintoExiste($recintoId)
        ) {
            throw new RuntimeException('Finca, aprisco, área o recinto no existen o están eliminados.');
        }
        $this->validarJerarquia($fincaId, $apriscoId, $areaId, $recintoId);

        // Regla de única activa
        if ($fechaHasta === null) {
            $sql = "SELECT 1 FROM {$this->table}
                    WHERE animal_id = ? AND fecha_hasta IS NULL AND deleted_at IS NULL
                      AND animal_ubicacion_id <> ?
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            if (!$stmt)
                throw new mysqli_sql_exception("Error única activa: " . $this->db->error);
            $stmt->bind_param('ss', $current['animal_id'], $id);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();
            if ($exists) {
                throw new RuntimeException('Ya existe otra ubicación activa para este animal.');
            }
        }

        // Normalizar SIEMPRE estado según fecha_hasta
        $estado = ($fechaHasta === null) ? 'ACTIVA' : 'INACTIVA';
        $campos[] = 'estado = ?';
        $params[] = $estado;
        $types .= 's';

        // Aplicar cambios en FKs si vinieron
        if (array_key_exists('finca_id', $data)) {
            $campos[] = 'finca_id = ?';
            $params[] = $fincaId;
            $types .= 's';
        }
        if (array_key_exists('aprisco_id', $data)) {
            $campos[] = 'aprisco_id = ?';
            $params[] = $apriscoId;
            $types .= 's';
        }
        if (array_key_exists('area_id', $data)) {
            $campos[] = 'area_id = ?';
            $params[] = $areaId;
            $types .= 's';
        }
        if (array_key_exists('recinto_id', $data)) {
            $campos[] = 'recinto_id = ?';
            $params[] = $recintoId;
            $types .= 's';
        }

        if (empty($campos)) {
            throw new InvalidArgumentException('No hay campos para actualizar.');
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $id;

        $campos[] = 'updated_at = ?';
        $params[] = $now;
        $types .= 's';
        $campos[] = 'updated_by = ?';
        $params[] = $actorId;
        $types .= 's';

        $sql = "UPDATE {$this->table}
                SET " . implode(', ', $campos) . "
                WHERE animal_ubicacion_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $types .= 's';
        $params[] = $id;

        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $err = strtolower($stmt->error);
        $stmt->close();

        if (!$ok) {
            if (str_contains($err, 'foreign key')) {
                throw new RuntimeException('FK inválida (animal/finca/aprisco/area/recinto).');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    /**
     * Cerrar ubicación (retrocompatibilidad con controladores antiguos).
     * Usa cerrarUbicacion().
     */
    public function cerrar(string $id, string $fechaHasta): bool
    {
        return $this->cerrarUbicacion($id, $fechaHasta);
    }

    /**
     * Cerrar ubicación activa: pone fecha_hasta y estado='INACTIVA'.
     * Sólo afecta si está activa (fecha_hasta IS NULL).
     */
    public function cerrarUbicacion(string $id, string $fechaHasta): bool
    {
        $this->validarFecha($fechaHasta, 'fecha_hasta');

        $row = $this->obtenerPorId($id);
        if (!$row)
            throw new RuntimeException("Ubicación no encontrada.");
        if ($row['deleted_at'] !== null)
            throw new RuntimeException("Ubicación eliminada.");
        if ($row['fecha_hasta'] !== null)
            throw new RuntimeException("La ubicación ya está cerrada.");
        if ($fechaHasta < $row['fecha_desde']) {
            throw new InvalidArgumentException("fecha_hasta no puede ser menor que fecha_desde.");
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $id;

        $sql = "UPDATE {$this->table}
                SET fecha_hasta = ?, estado = 'INACTIVA', updated_at = ?, updated_by = ?
                WHERE animal_ubicacion_id = ? AND deleted_at IS NULL AND fecha_hasta IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error al preparar cierre: " . $this->db->error);

        $stmt->bind_param('ssss', $fechaHasta, $now, $actorId, $id);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $ok && $affected > 0;
    }

    /**
     * Soft delete
     */
    public function eliminar(string $id): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $id;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE animal_ubicacion_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt)
            throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $id);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $ok && $affected > 0;
    }
}
