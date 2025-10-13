<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';



class SessionConfigModel
{
    private $db;
    private $table = 'session_config';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getConfig()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY updated_at DESC LIMIT 1";
        $result = $this->db->query($sql);
        return $result->fetch_assoc();
    }

    public function updateTimeout($timeout)
    {
        $this->db->begin_transaction();
        try {
            $userId = $_SESSION['user_id'] ?? null;

            $env = new ClientEnvironmentInfo(APP_ROOT . '/config/geolite.mmdb');
            $env->applyAuditContext($this->db, $userId);

            $tzManager = new TimezoneManager($this->db);
            $tzManager->applyTimezone();

            $now = $env->getCurrentDatetime();

            $stmt = $this->db->prepare("UPDATE {$this->table} SET timeout_minutes = ?, updated_at = ?, updated_by = ? ORDER BY config_id DESC LIMIT 1");
            if (!$stmt) {
                throw new mysqli_sql_exception("Error preparando el update: " . $this->db->error);
            }

            $stmt->bind_param("isi", $timeout, $now, $userId);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                // Insertar si no existe configuración previa
                $stmt = $this->db->prepare("INSERT INTO {$this->table} (timeout_minutes, updated_at, updated_by) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $timeout, $now, $userId);
                $stmt->execute();
            }

            $this->db->commit();
            return true;
        } catch (mysqli_sql_exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
