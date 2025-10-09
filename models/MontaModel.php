<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class MontaModel
{
    private $db;
    private $table = 'montas';

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

    private function periodoExiste(string $id): bool
    {
        $sql = "SELECT 1 FROM periodos_servicio WHERE periodo_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    /* ============ Lecturas ============ */

    /**
     * Lista montas (excluye eliminados por defecto).
     * Filtros: periodo_id, numero_monta, fecha_monta (desde/hasta).
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $periodoId = null,
        ?int $numeroMonta = null,
        ?string $desde = null,
        ?string $hasta = null
    ): array {
        $w = [];
        $p = [];
        $t = '';

        $w[] = $incluirEliminados ? 'm.deleted_at IS NOT NULL OR m.deleted_at IS NULL' : 'm.deleted_at IS NULL';

        if ($periodoId) {
            $w[] = 'm.periodo_id = ?';
            $p[] = $periodoId;
            $t .= 's';
        }
        if ($numeroMonta !== null) {
            $w[] = 'm.numero_monta = ?';
            $p[] = $numeroMonta;
            $t .= 'i';
        }
        if ($desde) {
            $w[] = 'm.fecha_monta >= ?';
            $p[] = $desde;
            $t .= 's';
        }
        if ($hasta) {
            $w[] = 'm.fecha_monta <= ?';
            $p[] = $hasta;
            $t .= 's';
        }

        $whereSql = implode(' AND ', $w);

        $sql = "SELECT
                    m.monta_id,
                    m.periodo_id,
                    m.numero_monta,
                    m.fecha_monta,
                    m.created_at, m.created_by, m.updated_at, m.updated_by
                FROM {$this->table} m
                WHERE {$whereSql}
                ORDER BY m.fecha_monta DESC, m.numero_monta ASC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $t .= 'ii';
        $p[] = $limit;
        $p[] = $offset;
        $stmt->bind_param($t, ...$p);
        $stmt->execute();
        $res  = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function obtenerPorId(string $montaId): ?array
    {
        $sql = "SELECT
                    m.monta_id,
                    m.periodo_id,
                    m.numero_monta,
                    m.fecha_monta,
                    m.created_at, m.created_by, m.updated_at, m.updated_by,
                    m.deleted_at, m.deleted_by
                FROM {$this->table} m
                WHERE m.monta_id = ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $montaId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    /**
     * Crea una monta.
     * Requeridos: periodo_id, numero_monta (>=1), fecha_monta (Y-m-d)
     */
    public function crear(array $data): string
    {
        $fecha_servicio   = trim((string)($data['fecha_servicio'] ?? ''));
        $observacion_servicio  = trim((string)($data['observacion_servicio'] ?? ''));
        $periodo_id  = trim((string)($data['periodo_id'] ?? ''));


        if ($fecha_servicio === '' || $observacion_servicio === '' || $periodo_id === 0) {
            throw new InvalidArgumentException('Faltan campos requeridos: periodo_id, numero_monta, fecha_monta.');
        }

        if (!$this->periodoExiste($periodo_id)) {
            throw new RuntimeException('El periodo de servicio no existe o está eliminado.');
        }
        $montas = $this->listar(100, 0, false, $periodo_id, null, null, null);
        $numeroMonta = count($montas) + 1;


        $this->db->begin_transaction();
        try {
            $uuid    = $this->generateUUIDv4();
            $now = (new DateTime())->format('Y-m-d H:i:s');


            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                    (monta_id, periodo_id, numero_monta, fecha_monta,
                     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            $stmt->bind_param(
                'ssisss',
                $uuid,
                $periodo_id,
                $numeroMonta,
                $fecha_servicio,
                $now,
                $actorId
            );

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                $this->db->rollback();

                $errLow = strtolower($err);
                if (str_contains($errLow, 'foreign key')) {
                    throw new RuntimeException('Referencia inválida a periodo de servicio.');
                }
                if (str_contains($errLow, 'duplicate') || str_contains($errLow, 'unique')) {
                    // Por si tienes una restricción única por (periodo_id, numero_monta)
                    throw new RuntimeException('Ya existe una monta con ese número en este periodo.');
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
     * Actualiza campos explícitos de la monta.
     * Campos: periodo_id?, numero_monta?, fecha_monta?
     */
    public function actualizar(string $montaId, array $data): bool
    {
        $set = [];
        $p = [];
        $t = '';

        if (array_key_exists('periodo_id', $data)) {
            $v = $data['periodo_id'];
            if ($v !== null && $v !== '' && !$this->periodoExiste($v)) {
                throw new InvalidArgumentException('periodo_id inválido.');
            }
            $set[] = 'periodo_id = ?';
            $p[] = ($v !== '' ? $v : null);
            $t .= 's';
        }
        if (array_key_exists('numero_monta', $data)) {
            $nm = $data['numero_monta'];
            if ($nm === null || $nm === '') {
                $set[] = 'numero_monta = ?';
                $p[] = null;
                $t .= 's';
            } else {
                $nm = (int)$nm;
                if ($nm < 1) throw new InvalidArgumentException('numero_monta debe ser >= 1.');
                $set[] = 'numero_monta = ?';
                $p[] = $nm;
                $t .= 'i';
            }
        }
        if (isset($data['fecha_monta'])) {
            $set[] = 'fecha_monta = ?';
            $p[] = (string)$data['fecha_monta'];
            $t .= 's';
        }

        if (empty($set)) throw new InvalidArgumentException('No hay campos para actualizar.');

        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $montaId;

        $set[] = 'updated_at = ?';
        $p[] = $now;
        $t .= 's';
        $set[] = 'updated_by = ?';
        $p[] = $actorId;
        $t .= 's';

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE monta_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $t .= 's';
        $p[] = $montaId;
        $ok = $stmt->bind_param($t, ...$p);
        if (!$ok) throw new mysqli_sql_exception("Error al bind_param en actualización.");

        $ok  = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            $errLow = strtolower($err);
            if (str_contains($errLow, 'foreign key')) {
                throw new RuntimeException('Referencia inválida a periodo de servicio.');
            }
            if (str_contains($errLow, 'duplicate') || str_contains($errLow, 'unique')) {
                throw new RuntimeException('Ya existe una monta con ese número en este periodo.');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    /**
     * Eliminación lógica (soft delete).
     */
    public function eliminar(string $montaId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $montaId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE monta_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $montaId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
