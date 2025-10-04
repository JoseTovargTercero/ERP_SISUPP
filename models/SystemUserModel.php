<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class SystemUserModel
{
    private $db;
    private $table = 'system_users';

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

    private function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    private function nowWithAudit(): array
    {
        // userId=0 porque aún no sabemos; lo importante es setear contexto/tiempo
        $env = new ClientEnvironmentInfo(APP_ROOT . '/app/config/geolite.mmdb');
        $env->applyAuditContext($this->db, 0);
        $tzManager = new TimezoneManager($this->db);
        $tzManager->applyTimezone();
        return [$env->getCurrentDatetime(), $env];
    }

    /* ============ Lecturas ============ */

    // Lista excluyendo eliminados lógicamente por defecto
    public function listar(int $limit = 100, int $offset = 0, bool $incluirEliminados = false): array
    {
        $where = $incluirEliminados ? '1=1' : 'deleted_at IS NULL';
        $sql = "SELECT user_id, nombre, email, nivel, estado, created_at, created_by, updated_at, updated_by
                FROM {$this->table}
                WHERE {$where}
                ORDER BY created_at DESC, nombre ASC
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }
        /* ============ Logout ============ */
    public function logout(): bool
    {
        try {
            // Si no hay sesión activa, la iniciamos para poder destruirla
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Limpiar todas las variables de sesión
            $_SESSION = [];

            // Destruir completamente la sesión
            session_destroy();

            return true;
        } catch (Throwable $e) {
            error_log("Error al cerrar sesión: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerPorId(string $userId): ?array
    {
        $sql = "SELECT user_id, nombre, email, nivel, estado,
                       created_at, created_by, updated_at, updated_by, deleted_at, deleted_by
                FROM {$this->table}
                WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function obtenerPorEmail(string $email): ?array
    {
        $sql = "SELECT user_id, nombre, email, contrasena, nivel, estado, deleted_at
                FROM {$this->table}
                WHERE email = ?
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ============ Escrituras ============ */

    public function crear(array $data): string
    {
        if (empty($data['nombre']) || empty($data['email']) || empty($data['contrasena']) || !isset($data['nivel'])) {
            throw new InvalidArgumentException('Faltan campos requeridos: nombre, email, contrasena, nivel.');
        }

        // Unicidad de email (excluye eliminados si así lo deseas)
        $existente = $this->obtenerPorEmail($data['email']);
        if ($existente && $existente['deleted_at'] === null) {
            throw new RuntimeException('El correo ya está registrado.');
        }

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();

            $uuid       = $this->generateUUIDv4();
            $actorId    = $_SESSION['user_id'] ?? $uuid; // si no hay actor en sesión, deja el propio
            $hash       = $this->hashPassword($data['contrasena']);
            $nivel      = (int)$data['nivel'];
            $estado     = isset($data['estado']) ? (int)$data['estado'] : 1;

            $sql = "INSERT INTO {$this->table}
                    (user_id, nombre, email, contrasena, nivel, estado,
                     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            $stmt->bind_param(
                'ssssisss',
                $uuid,
                $data['nombre'],
                $data['email'],
                $hash,
                $nivel,
                $estado,
                $now,
                $actorId
            );

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                $this->db->rollback();
                if (str_contains(strtolower($err), 'duplicate')) {
                    throw new RuntimeException('El correo ya existe (índice único).');
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

    public function actualizar(string $userId, array $data): bool
    {
        // Sólo permitirá actualizar campos explícitos
        $campos = [];
        $params = [];
        $types  = '';

        if (isset($data['nombre']))     { $campos[] = 'nombre = ?';     $params[] = $data['nombre'];          $types .= 's'; }
        if (isset($data['email']))      { $campos[] = 'email = ?';      $params[] = $data['email'];           $types .= 's'; }
        if (isset($data['nivel']))      { $campos[] = 'nivel = ?';      $params[] = (int)$data['nivel'];      $types .= 'i'; }
        if (isset($data['estado']))     { $campos[] = 'estado = ?';     $params[] = (int)$data['estado'];     $types .= 'i'; }
        if (isset($data['contrasena']) && $data['contrasena'] !== '') {
            $campos[] = 'contrasena = ?';
            $params[] = $this->hashPassword($data['contrasena']);
            $types   .= 's';
        }

        if (empty($campos)) {
            throw new InvalidArgumentException('No hay campos para actualizar.');
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $userId;

        $campos[] = 'updated_at = ?';
        $params[] = $now;    $types .= 's';
        $campos[] = 'updated_by = ?';
        $params[] = $actorId; $types .= 's';

        $sql = "UPDATE {$this->table} SET " . implode(', ', $campos) . " WHERE user_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $types .= 's';
        $params[] = $userId;

        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            if (str_contains(strtolower($err), 'duplicate')) {
                throw new RuntimeException('El correo ya existe para otro usuario.');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    public function actualizarEstado(string $userId, int $estado): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $userId;

        $sql = "UPDATE {$this->table}
                SET estado = ?, updated_at = ?, updated_by = ?
                WHERE user_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización de estado: " . $this->db->error);

        $stmt->bind_param('isss', $estado, $now, $actorId, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Soft delete
    public function eliminar(string $userId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $userId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE user_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
/* ============ Login básico (sin sesiones) ============ */
public function loginBasico(string $email, string $password): ?array
{
    $row = $this->obtenerPorEmail($email);
    if (!$row) return null;                                // email no existe
    if ($row['deleted_at'] !== null) return null;          // usuario eliminado/borrado lógico

    // Si está desactivado, avisamos explícitamente
    if ((int)$row['estado'] === 0) {
        throw new DomainException('USER_DISABLED', 1001);
    }

    if (!password_verify($password, $row['contrasena'])) return null; // contraseña inválida

    return [
        'user_id' => $row['user_id'],
        'nombre'  => $row['nombre'],
        'email'   => $row['email'],
        'nivel'   => (int)$row['nivel'],
        'estado'  => (int)$row['estado'],
    ];
}

}
