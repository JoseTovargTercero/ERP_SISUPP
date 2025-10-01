<?php

class Database
{
    private static $instance = null;
    private $mysqli;

    private function __construct()
    {
        $this->loadEnv(APP_ROOT . '/.env');

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'erp_g';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost/ERP_SISUPP');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->mysqli = new mysqli($host, $username, $password, $dbname);
            $this->mysqli->set_charset("utf8mb4");
        } catch (mysqli_sql_exception $e) {
            $this->errorResponse(500, "Database connection error: " . $e->getMessage());
        }
    }

    private function loadEnv($filePath)
    {
        if (!file_exists($filePath)) {
            $this->errorResponse(500, ".env file not found at $filePath");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }


    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->mysqli;
    }

    public function getConnection()
    {
        return $this->mysqli;
    }

    public function startTransaction()
    {
        $this->mysqli->begin_transaction();
    }

    public function commit()
    {
        $this->mysqli->commit();
    }

    public function rollback()
    {
        $this->mysqli->rollback();
    }

    private function errorResponse($http_code, $message)
    {
        http_response_code($http_code);
        echo json_encode([
            'value' => false,
            'message' => $message
        ]);
        exit;
    }
}
?>