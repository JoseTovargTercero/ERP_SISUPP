<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';
require_once __DIR__ . '/../helpers/session_timezone_helper.php';

class SessionManagementModel
{
    private $db;
    private $table = 'session_management';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY login_time DESC";
        $result = $this->db->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getById($sessionId)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE session_id = ?");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Cuenta intentos fallidos por IP en los últimos N minutos.
     * IMPORTANTE: aquí seguimos filtrando por el campo almacenado "user_type",
     * que ahora se deriva de system_users.nivel (0=administrator, 1=user).
     */
    public function countFailedAttemptsByIp(string $ipAddress, string $userType, int $withinMinutes = 1): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS failed_attempts
            FROM {$this->table}
            WHERE ip_address = ?
              AND user_type = ?
              AND login_success = 0
              AND created_at > (NOW() - INTERVAL ? MINUTE)
        ");
        if (!$stmt) {
            throw new \Exception("Failed to prepare IP attempt query: " . $this->db->error);
        }

        $stmt->bind_param("ssi", $ipAddress, $userType, $withinMinutes);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int) ($result['failed_attempts'] ?? 0);
    }

    /**
     * Crea un registro de sesión.
     * AHORA: Sólo se usa la tabla system_users para identificar rol y datos del usuario.
     * - system_users.nivel: 0 = administrador → user_type = 'administrator'
     *                       1 = usuario      → user_type = 'user'
     *
     * @param string|null $userId
     * @param string $userType (IGNORADO para almacenamiento: se sobrescribe con system_users.nivel)
     * @param string|null $deviceId
     * @param string|null $deviceType
     * @param bool $loginSuccess
     * @param string|null $failureReason
     * @return string session_id
     * @throws \mysqli_sql_exception
     */
public function create($userId, string $userType, $deviceId, $deviceType, $loginSuccess = true, $failureReason = null): string
{
    $this->db->begin_transaction();
    try {
        $sessionId = $this->generateUUIDv4();

        // Auditoría + TZ
        $env = new ClientEnvironmentInfo(APP_ROOT . '/config/geolite.mmdb');
        $env->applyAuditContext($this->db, $userId);
        $tzManager = new TimezoneManager($this->db);
        $tzManager->applyTimezone();

        // Geo + tiempos
        $geo = $env->getGeoInfo() ?? [];
        $createdAt = getNowInUserLocalTime(
            $geo['client_country'] ?? '',
            $geo['client_region'] ?? '',
            $geo['client_city'] ?? ''
        );
        $loginTime          = $createdAt;
        $logoutTime         = null;
        $inactivityDuration = null;

        // Info cliente
        $ip        = $_SERVER['REMOTE_ADDR']     ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        $hostname  = $env->getClientHostname()   ?? 'UNKNOWN';
        $os        = $env->getClientOs()         ?? 'UNKNOWN';
        $browser   = $env->getClientBrowser()    ?? 'UNKNOWN';

        // Geo a variables (¡no expresiones en bind_param!)
        $city        = $geo['client_city']        ?? 'Unknown';
        $region      = $geo['client_region']      ?? 'Unknown';
        $country     = $geo['client_country']     ?? 'Unknown';
        $zipcode     = $geo['client_zipcode']     ?? 'Unknown';
        $coordinates = $geo['client_coordinates'] ?? '0.0,0.0';

        // Datos desde system_users
        $fullName = 'UNKNOWN';
        $username = 'UNKNOWN';
        $nivel    = null; // 0 admin, 1 user

        if (!empty($userId)) {
            $stmt = $this->db->prepare("
                SELECT nombre AS full_name, email, nivel
                FROM system_users
                WHERE user_id = ? AND deleted_at IS NULL
            ");
            if (!$stmt) {
                throw new mysqli_sql_exception("Error preparando consulta system_users: " . $this->db->error);
            }
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $fullName = $row['full_name'] ?? $fullName;
                $username = $row['email']     ?? $username;
                $nivel    = isset($row['nivel']) ? (int)$row['nivel'] : null;
            }
            $stmt->close();
        }

        // Normalizar user_type
        $derivedUserType = ($nivel === 0) ? 'administrator' : 'user';

        // Estado sesión
        $loginSuccessInt = (int)$loginSuccess;               // (i)
        $sessionStatus   = $loginSuccess ? 'active' : 'failed';

        // Device
        $deviceIdVar   = $deviceId ?? null;                  // string|null
        $deviceTypeStr = (string)($deviceType ?? '');        // tratar como string
        // Si device_type es INT en la DB, usa (int)$deviceType y ajusta la cadena de tipos (ver nota).

        // INSERT
        $sql = "INSERT INTO {$this->table} (
            session_id, user_id, user_name, user_type, full_name,
            login_time, logout_time, inactivity_duration,
            login_success, failure_reason, session_status,
            ip_address, city, region, country, zipcode, coordinates,
            hostname, os, browser, user_agent,
            device_id, device_type, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmtInsert = $this->db->prepare($sql);
        if (!$stmtInsert) {
            throw new mysqli_sql_exception("Error preparando la inserción: " . $this->db->error);
        }

        // 8s + 1i + 15s = 24 tipos
        $types = str_repeat('s', 8) . 'i' . str_repeat('s', 15); // 'ssssssssissssssssssssssss'

        $stmtInsert->bind_param(
            $types,
            $sessionId,        //  1 s
            $userId,           //  2 s
            $username,         //  3 s
            $derivedUserType,  //  4 s
            $fullName,         //  5 s
            $loginTime,        //  6 s
            $logoutTime,       //  7 s
            $inactivityDuration, // 8 s
            $loginSuccessInt,  //  9 i
            $failureReason,    // 10 s
            $sessionStatus,    // 11 s
            $ip,               // 12 s
            $city,             // 13 s
            $region,           // 14 s
            $country,          // 15 s
            $zipcode,          // 16 s
            $coordinates,      // 17 s
            $hostname,         // 18 s
            $os,               // 19 s
            $browser,          // 20 s
            $userAgent,        // 21 s
            $deviceIdVar,      // 22 s
            $deviceTypeStr,    // 23 s  (si INT, ver nota)
            $createdAt         // 24 s
        );

        $stmtInsert->execute();
        $stmtInsert->close();

        $this->db->commit();
        return $sessionId;

    } catch (mysqli_sql_exception $e) {
        $this->db->rollback();
        throw $e;
    }
}




    /**
     * Cierra una sesión y actualiza su estado.
     * $status permitido: 'expired' | 'closed' | 'kicked'
     */
    public function logoutSession(string $sessionId, ?string $inactivityDuration = null, string $status = ''): bool
    {
        try {
            // 1) Obtener user_id
            $stmt = $this->db->prepare("SELECT user_id FROM {$this->table} WHERE session_id = ?");
            $stmt->bind_param("s", $sessionId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if (!$row || empty($row['user_id'])) {
                throw new Exception("No se pudo encontrar el user_id de la sesión.");
            }

            $userId = $row['user_id'];

            // 2) Inicializar entorno
            $env = new ClientEnvironmentInfo(APP_ROOT . '/config/geolite.mmdb');
            $env->applyAuditContext($this->db, $userId);
            $tzManager = new TimezoneManager($this->db);
            $tzManager->applyTimezone();

            $geo = $env->getGeoInfo();
            $logoutTime = getNowInUserLocalTime(
                $geo['client_country'] ?? '',
                $geo['client_region'] ?? '',
                $geo['client_city'] ?? ''
            );

            // 3) Validar estado
            if (empty($status)) {
                $status = $inactivityDuration !== null && $inactivityDuration !== '' ? 'expired' : 'closed';
            } elseif (!in_array($status, ['expired', 'closed', 'kicked'], true)) {
                throw new Exception("Estado de sesión no válido: $status");
            }

            // 4) Si status=expired y no hay duración, obtener de config
            if ($status === 'expired' && ($inactivityDuration === null || $inactivityDuration === '')) {
                require_once APP_ROOT . '/models/SessionConfigModel.php';
                $configModel = new SessionConfigModel();
                $config = $configModel->getConfig();
                $timeoutMinutes = (int) ($config['timeout_minutes'] ?? 5);
                $inactivityDuration = (string) ($timeoutMinutes * 60);
            }

            // 5) UPDATE
            $query = "UPDATE {$this->table}
                      SET logout_time = ?, session_status = ?, inactivity_duration = ?
                      WHERE session_id = ?";
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                throw new mysqli_sql_exception("Error preparando logoutSession: " . $this->db->error);
            }

            $stmt->bind_param('ssss', $logoutTime, $status, $inactivityDuration, $sessionId);
            $stmt->execute();
            $stmt->close();

            return true;
        } catch (Exception | mysqli_sql_exception $e) {
            return false;
        }
    }

    public function getStatusBySessionId(string $sessionId): ?string
    {
        $stmt = $this->db->prepare("SELECT session_status FROM {$this->table} WHERE session_id = ?");
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['session_status'] ?? null;
    }

    public function exportToCSV(): void
    {
        $filename = 'session_audit_export_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Session ID',
            'User ID',
            'User Type',
            'Full Name',
            'Login Time',
            'IP Address',
            'User Agent',
            'Created At'
        ]);

        $sql = "SELECT session_id, user_id, user_type, full_name, login_time, ip_address, user_agent, created_at
                FROM {$this->table}
                ORDER BY login_time DESC";
        $result = $this->db->query($sql);

        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['session_id'],
                $row['user_id'],
                ucfirst($row['user_type']),
                $row['full_name'],
                $row['login_time'],
                $row['ip_address'],
                $row['user_agent'],
                $row['created_at']
            ]);
        }

        fclose($output);
        exit;
    }

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
}
