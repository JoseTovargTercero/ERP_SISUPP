<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class RevisionesServicioModel
{
    private $db;
    private $table = 'revisiones_servicio';

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

    private function validarResultado(?string $resultado): void
    {
        if ($resultado === null || $resultado === '') return;
        $validos = ['ENTRO_EN_CELO','SOSPECHA_PREÑEZ','CONFIRMADA_PREÑEZ'];
        if (!in_array($resultado, $validos, true)) {
            throw new InvalidArgumentException(
                "resultado inválido. Use uno de: " . implode(', ', $validos)
            );
        }
    }

    private function validarCiclo(int $ciclo): void
    {
        if ($ciclo < 1 || $ciclo > 3) {
            throw new InvalidArgumentException('ciclo_control debe estar entre 1 y 3.');
        }
    }

    private function periodoExiste(string $periodoId, bool $requerirAbierto = false): array
    {
        $sql = "SELECT periodo_id, hembra_id, fecha_inicio, estado_periodo
                FROM periodos_servicio
                WHERE periodo_id = ? AND deleted_at IS NULL
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando verificación de período: " . $this->db->error);
        $stmt->bind_param('s', $periodoId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new RuntimeException('El período de servicio no existe o está eliminado.');
        }
        if ($requerirAbierto && $row['estado_periodo'] !== 'ABIERTO') {
            throw new RuntimeException('El período de servicio no está ABIERTO.');
        }
        return $row;
    }

    private function getMaxCiclo(string $periodoId): int
    {
        $sql = "SELECT COALESCE(MAX(ciclo_control), 0) AS max_ciclo
                FROM {$this->table}
                WHERE periodo_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando MAX ciclo: " . $this->db->error);
        $stmt->bind_param('s', $periodoId);
        $stmt->execute();
        $max = (int)$stmt->get_result()->fetch_assoc()['max_ciclo'];
        $stmt->close();
        return $max;
    }

    /** Devuelve la fecha de la primera monta del período (o null si no hay) */
    private function getFechaPrimeraMonta(string $periodoId): ?string
    {
        $sql = "SELECT MIN(fecha_monta) AS primera
                FROM montas
                WHERE periodo_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando MIN(fecha_monta): " . $this->db->error);
        $stmt->bind_param('s', $periodoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !$row['primera']) return null;
        return substr($row['primera'], 0, 10); // yyyy-mm-dd de un datetime
    }

    /** Inserta una alerta (REVISION_20_21 o PROX_PARTO_117) */
    private function crearAlerta(string $tipo, string $periodoId, ?string $animalId, string $fechaObjetivo, ?string $detalle = null): void
    {
        $sql = "INSERT INTO alertas (alerta_id, tipo_alerta, periodo_id, animal_id, fecha_objetivo, estado_alerta, detalle)
                VALUES (?, ?, ?, ?, ?, 'PENDIENTE', ?)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando inserción de alerta: " . $this->db->error);

        $alertaId = $this->generateUUIDv4();
        $stmt->bind_param('ssssss', $alertaId, $tipo, $periodoId, $animalId, $fechaObjetivo, $detalle);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new mysqli_sql_exception("No se pudo crear la alerta: " . $err);
        }
        $stmt->close();
    }

    private function dateAddDays(string $yyyy_mm_dd, int $days): string
    {
        $d = new DateTime($yyyy_mm_dd);
        $d->modify(($days >= 0 ? '+' : '') . $days . ' days');
        return $d->format('Y-m-d');
    }

    /* ============ Lecturas ============ */

    public function listar(
        int $limit = 100,
        int $offset = 0,
        ?string $periodoId = null,
        ?string $resultado = null,
        bool $incluirEliminados = false
    ): array {
        $w = []; $p = []; $t = '';

        // soft delete
        $w[] = $incluirEliminados ? '(r.deleted_at IS NOT NULL OR r.deleted_at IS NULL)' : 'r.deleted_at IS NULL';

        if ($periodoId) { $w[] = 'r.periodo_id = ?'; $p[] = $periodoId; $t .= 's'; }
        if ($resultado) { $this->validarResultado($resultado); $w[] = 'r.resultado = ?'; $p[] = $resultado; $t .= 's'; }

        $where = implode(' AND ', $w);

        $sql = "SELECT 
                    r.revision_id, r.periodo_id, r.ciclo_control, r.fecha_programada,
                    r.fecha_realizada, r.resultado, r.observaciones,
                    r.created_at, r.created_by, r.updated_at, r.updated_by,
                    r.deleted_at, r.deleted_by
                FROM {$this->table} r
                WHERE {$where}
                ORDER BY r.fecha_programada ASC, r.ciclo_control ASC
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

    public function obtenerPorId(string $revisionId): ?array
    {
        $sql = "SELECT 
                    revision_id, periodo_id, ciclo_control, fecha_programada,
                    fecha_realizada, resultado, observaciones,
                    created_at, created_by, updated_at, updated_by,
                    deleted_at, deleted_by
                FROM {$this->table}
                WHERE revision_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando consulta: " . $this->db->error);
        $stmt->bind_param('s', $revisionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crear revisión.
     * Requeridos: periodo_id, fecha_programada (YYYY-MM-DD)
     * Opcionales: ciclo_control (si no viene, se autocalcula max+1), fecha_realizada, resultado, observaciones.
     * - Si resultado = CONFIRMADA_PREÑEZ -> cierra período y crea alerta PROX_PARTO_117 (si hay fecha de 1era monta).
     * - Si resultado = SOSPECHA_PREÑEZ y ciclo_control<3 -> crea alerta REVISION_20_21 para siguiente ciclo (+21 días).
     * - Si resultado = ENTRO_EN_CELO -> cierra período (se reinicia externamente un nuevo período).
     */
    public function crear(array $data): string
    {
        $periodoId       = trim((string)($data['periodo_id'] ?? ''));
        $fechaProgramada = trim((string)($data['fecha_programada'] ?? ''));
        $fechaRealizada  = isset($data['fecha_realizada']) && $data['fecha_realizada'] !== '' ? trim((string)$data['fecha_realizada']) : null;
        $resultado       = isset($data['resultado']) && $data['resultado'] !== '' ? trim((string)$data['resultado']) : null;
        $obs             = isset($data['observaciones']) ? trim((string)$data['observaciones']) : null;

        if ($periodoId === '' || $fechaProgramada === '') {
            throw new InvalidArgumentException('Faltan campos requeridos: periodo_id, fecha_programada.');
        }
        $this->validarResultado($resultado);
        $periodo = $this->periodoExiste($periodoId, true); // exigir ABIERTO

        $ciclo = isset($data['ciclo_control']) && $data['ciclo_control'] !== ''
            ? (int)$data['ciclo_control']
            : ($this->getMaxCiclo($periodoId) + 1);

        $this->validarCiclo($ciclo);
        if ($fechaRealizada && $fechaRealizada < $fechaProgramada) {
            throw new InvalidArgumentException('fecha_realizada no puede ser anterior a fecha_programada.');
        }

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $uuid    = $this->generateUUIDv4();
            $actorId = $this->getActorIdFallback($uuid);

            // Insert principal con auditoría
            $sql = "INSERT INTO {$this->table}
                    (revision_id, periodo_id, ciclo_control, fecha_programada, fecha_realizada, resultado, observaciones,
                     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error preparando inserción: " . $this->db->error);

            $stmt->bind_param(
                'ssissssss',
                $uuid, $periodoId, $ciclo, $fechaProgramada, $fechaRealizada, $resultado, $obs,
                $now, $actorId
            );
            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                $this->db->rollback();
                if (str_contains(strtolower($err), 'duplicate')) {
                    throw new RuntimeException('Ya existe una revisión para ese ciclo en este período.');
                }
                throw new mysqli_sql_exception("Error al ejecutar inserción: " . $err);
            }
            $stmt->close();

            // Efectos colaterales según resultado
            if ($resultado === 'CONFIRMADA_PREÑEZ') {
                // 1) Cerrar período
                $stmt = $this->db->prepare("UPDATE periodos_servicio SET estado_periodo='CERRADO', updated_at=?, updated_by=? WHERE periodo_id=? AND deleted_at IS NULL");
                if (!$stmt) throw new mysqli_sql_exception("Error preparando cierre de período: " . $this->db->error);
                $stmt->bind_param('sss', $now, $actorId, $periodoId);
                $stmt->execute();
                $stmt->close();

                // 2) Crear alerta de parto a +117 días desde la primera monta (si existe)
                $primeraMonta = $this->getFechaPrimeraMonta($periodoId);
                if ($primeraMonta) {
                    $fechaParto = $this->dateAddDays($primeraMonta, 117);
                    $this->crearAlerta('PROX_PARTO_117', $periodoId, $periodo['hembra_id'], $fechaParto, 'Parto estimado a +117 días de la primera monta');
                }
            } elseif ($resultado === 'ENTRO_EN_CELO') {
                // Cerrar período (se creará uno nuevo fuera de este flujo)
                $stmt = $this->db->prepare("UPDATE periodos_servicio SET estado_periodo='CERRADO', updated_at=?, updated_by=? WHERE periodo_id=? AND deleted_at IS NULL");
                if (!$stmt) throw new mysqli_sql_exception("Error preparando cierre de período (celo): " . $this->db->error);
                $stmt->bind_param('sss', $now, $actorId, $periodoId);
                $stmt->execute();
                $stmt->close();
            } elseif ($resultado === 'SOSPECHA_PREÑEZ' && $ciclo < 3) {
                // Programar próxima revisión a +21 días
                $proxima = $this->dateAddDays($fechaProgramada, 21);
                $this->crearAlerta('REVISION_20_21', $periodoId, $periodo['hembra_id'], $proxima, "Ciclo ".($ciclo+1));
            }

            $this->db->commit();
            return $uuid;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Actualiza una revisión.
     * Campos permitidos: fecha_programada?, fecha_realizada?, resultado?, observaciones?
     * Aplica mismos efectos que crear() si resultado cambia a CONFIRMADA_PREÑEZ / ENTRO_EN_CELO / SOSPECHA_PREÑEZ.
     */
    public function actualizar(string $revisionId, array $data): bool
    {
        $row = $this->obtenerPorId($revisionId);
        if (!$row || $row['deleted_at'] !== null) {
            throw new mysqli_sql_exception('Revisión no encontrada o eliminada.');
        }

        $campos = [];
        $params = [];
        $types  = '';

        if (array_key_exists('fecha_programada', $data)) {
            $campos[] = 'fecha_programada = ?';
            $params[] = $data['fecha_programada'] !== '' ? (string)$data['fecha_programada'] : $row['fecha_programada'];
            $types   .= 's';
        }
        if (array_key_exists('fecha_realizada', $data)) {
            $campos[] = 'fecha_realizada = ?';
            $params[] = $data['fecha_realizada'] !== '' ? (string)$data['fecha_realizada'] : null;
            $types   .= 's';
        }
        if (array_key_exists('resultado', $data)) {
            $this->validarResultado($data['resultado'] !== '' ? (string)$data['resultado'] : null);
            $campos[] = 'resultado = ?';
            $params[] = $data['resultado'] !== '' ? (string)$data['resultado'] : null;
            $types   .= 's';
        }
        if (array_key_exists('observaciones', $data)) {
            $campos[] = 'observaciones = ?';
            $params[] = $data['observaciones'] !== null ? (string)$data['observaciones'] : null;
            $types   .= 's';
        }

        if (empty($campos)) {
            throw new InvalidArgumentException('No hay campos para actualizar.');
        }

        // Validación de fechas coherentes
        $nuevaProg = array_key_exists('fecha_programada', $data)
            ? ($params[array_search('fecha_programada = ?', $campos)] ?? $row['fecha_programada'])
            : $row['fecha_programada'];
        $nuevaReal = array_key_exists('fecha_realizada', $data)
            ? ($params[array_search('fecha_realizada = ?', $campos)] ?? $row['fecha_realizada'])
            : $row['fecha_realizada'];
        if ($nuevaReal && $nuevaProg && $nuevaReal < $nuevaProg) {
            throw new InvalidArgumentException('fecha_realizada no puede ser anterior a fecha_programada.');
        }

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $actorId = $this->getActorIdFallback($revisionId);

            // auditoría
            $campos[] = 'updated_at = ?';
            $params[] = $now;      $types .= 's';
            $campos[] = 'updated_by = ?';
            $params[] = $actorId;  $types .= 's';

            $sql = "UPDATE {$this->table} SET " . implode(', ', $campos) . " WHERE revision_id = ? AND deleted_at IS NULL";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error preparando actualización: " . $this->db->error);

            $types .= 's';
            $params[] = $revisionId;

            $stmt->bind_param($types, ...$params);
            $ok  = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();

            if (!$ok) {
                throw new mysqli_sql_exception("Error al actualizar: " . $err);
            }

            // Efectos colaterales si resultado cambió
            $revActual = $this->obtenerPorId($revisionId);
            $periodo   = $this->periodoExiste($revActual['periodo_id'], false);

            if ($revActual['resultado'] === 'CONFIRMADA_PREÑEZ') {
                // Cerrar período
                $stmt = $this->db->prepare("UPDATE periodos_servicio SET estado_periodo='CERRADO', updated_at=?, updated_by=? WHERE periodo_id=? AND deleted_at IS NULL");
                if (!$stmt) throw new mysqli_sql_exception("Error preparando cierre de período: " . $this->db->error);
                $stmt->bind_param('sss', $now, $actorId, $revActual['periodo_id']);
                $stmt->execute();
                $stmt->close();

                // Alerta parto +117
                $primeraMonta = $this->getFechaPrimeraMonta($revActual['periodo_id']);
                if ($primeraMonta) {
                    $fechaParto = $this->dateAddDays($primeraMonta, 117);
                    $this->crearAlerta('PROX_PARTO_117', $revActual['periodo_id'], $periodo['hembra_id'], $fechaParto, 'Parto estimado a +117 días de la primera monta');
                }
            } elseif ($revActual['resultado'] === 'ENTRO_EN_CELO') {
                $stmt = $this->db->prepare("UPDATE periodos_servicio SET estado_periodo='CERRADO', updated_at=?, updated_by=? WHERE periodo_id=? AND deleted_at IS NULL");
                if (!$stmt) throw new mysqli_sql_exception("Error preparando cierre de período (celo): " . $this->db->error);
                $stmt->bind_param('sss', $now, $actorId, $revActual['periodo_id']);
                $stmt->execute();
                $stmt->close();
            } elseif ($revActual['resultado'] === 'SOSPECHA_PREÑEZ' && (int)$revActual['ciclo_control'] < 3) {
                $proxima = $this->dateAddDays($revActual['fecha_programada'], 21);
                $this->crearAlerta('REVISION_20_21', $revActual['periodo_id'], $periodo['hembra_id'], $proxima, "Ciclo ".((int)$revActual['ciclo_control']+1));
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /** Soft delete (usa deleted_at/deleted_by en la nueva estructura). */
    public function eliminar(string $revisionId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $this->getActorIdFallback($revisionId);

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE revision_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparando eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $revisionId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
