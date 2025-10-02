<?php
use GeoIp2\Database\Reader;

final class ClientEnvironmentInfo
{
    private string $userAgent;
    private string $geoDbPath;

    public function __construct(string $geoDbPath = '')
    {
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        if (empty($geoDbPath)) {
            $geoDbPath = APP_ROOT . '/config/geolite.mmdb';
        }

        $this->geoDbPath = $geoDbPath;
    }

    public function getCurrentDatetime(): string
    {
        $tz = new DateTimeZone($this->getTimezoneRegion());
        return (new DateTime('now', $tz))->format('Y-m-d H:i:s');
    }

    public function getTimezoneRegion(): string
    {
        return $_SESSION['timezone'] ?? 'America/Caracas';
    }

    public function getClientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function getClientHostname(): string
    {
        return gethostbyaddr($this->getClientIp());
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function getClientOs(): string
    {
        $ua = strtolower($this->userAgent);
        $osArray = [
            'windows nt 10.0' => 'Windows 10',
            'mac os x' => 'Mac OS X',
            'linux' => 'Linux',
            'android' => 'Android',
            'iphone' => 'iPhone',
            'ipad' => 'iPad',
            'ubuntu' => 'Ubuntu',
            'cros' => 'Chrome OS'
        ];
        foreach ($osArray as $key => $label) {
            if (str_contains($ua, $key)) {
                return $label;
            }
        }
        return 'Unknown OS';
    }

    public function getClientBrowser(): string
    {
        $ua = strtolower($this->userAgent);
        $browserArray = [
            'edg' => 'Microsoft Edge',
            'opr' => 'Opera',
            'opera' => 'Opera',
            'chrome' => 'Google Chrome',
            'safari' => 'Safari',
            'firefox' => 'Mozilla Firefox',
            'msie' => 'Internet Explorer',
            'trident' => 'Internet Explorer'
        ];
        foreach ($browserArray as $key => $label) {
            if (str_contains($ua, $key)) {
                return $label;
            }
        }
        return 'Unknown Browser';
    }

    public function getDomainName(): string
    {
        return $_SERVER['HTTP_HOST'] ?? 'localhost';
    }

    public function getRequestUri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? 'unknown';
    }

    public function getServerHostname(): string
    {
        return gethostname();
    }

    public function getGeoInfo(): array
    {
        $ip = $this->getClientIp();
        try {
            $reader = new Reader($this->geoDbPath);
            $record = $reader->city($ip);

            return [
                'client_country' => $record->country->name ?? 'Unknown',
                'client_region' => $record->subdivisions[0]->name ?? 'Unknown',
                'client_city' => $record->city->name ?? 'Unknown',
                'client_zipcode' => $record->postal->code ?? 'Unknown',
                'client_coordinates' => $record->location->latitude . ',' . $record->location->longitude
            ];
        } catch (\Exception $e) {
            return [
                'client_country' => 'Unknown',
                'client_region' => 'Unknown',
                'client_city' => 'Unknown',
                'client_zipcode' => 'Unknown',
                'client_coordinates' => '0.0,0.0'
            ];
        }
    }
    public function applyAuditContext(mysqli $mysqli, $userId): void
    {
        require_once APP_ROOT . '/helpers/session_timezone_helper.php';

        $geo = $this->getGeoInfo();

        // Obtener zona horaria y timestamp local basado en geo-ip
        $geoTimezone = getTimezoneFromLocation(
            $geo['client_country'] ?? '',
            $geo['client_region'] ?? '',
            $geo['client_city'] ?? ''
        );
        $geoTimestamp = getNowInUserLocalTime(
            $geo['client_country'] ?? '',
            $geo['client_region'] ?? '',
            $geo['client_city'] ?? ''
        );

        $vars = [
            'user_id' => $userId,
            'user_type' => $_SESSION['roles_user'] ?? 'Unknown',
            'full_name' => $_SESSION['user_name'] ?? 'Unknown',
            'client_ip' => $this->getClientIp(),
            'client_hostname' => $this->getClientHostname(),
            'user_agent' => $this->getUserAgent(),
            'client_os' => $this->getClientOs(),
            'client_browser' => $this->getClientBrowser(),
            'domain_name' => $this->getDomainName(),
            'request_uri' => $this->getRequestUri(),
            'server_hostname' => $this->getServerHostname(),
            'action_timezone' => $this->getTimezoneRegion(),
            'geo_ip_timezone' => $geoTimezone,
            'geo_ip_timestamp' => $geoTimestamp
        ] + $geo;

        foreach ($vars as $key => $value) {
            $safeValue = addslashes($value);
            $mysqli->query("SET @{$key} = '{$safeValue}'");
        }
    }

}
