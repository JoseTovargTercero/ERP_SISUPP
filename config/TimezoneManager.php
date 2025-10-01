<?php
final class TimezoneManager
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function applyTimezone(): void
    {
        try {
            $region = $_SESSION['timezone'] ?? 'America/Caracas';
            $tz = new DateTimeZone($region);
            $now = new DateTime('now', $tz);
            $offset = $now->format('P'); // Ejemplo: -04:00
            $this->mysqli->query("SET time_zone = '{$offset}'");
        } catch (Exception $e) {
            error_log("Error applying timezone to MariaDB: " . $e->getMessage());
        }
    }
}
