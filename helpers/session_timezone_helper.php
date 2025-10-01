<?php

/**
 * Intenta obtener la mejor zona horaria con base en país, región y ciudad.
 * Utiliza la lista completa de zonas horarias disponibles en PHP.
 */
function getTimezoneFromLocation($country, $region = '', $city = ''): string {
    $country = strtolower(trim($country));
    $region = strtolower(trim($region));
    $city = strtolower(trim($city));

    $key = "{$country}|{$region}|{$city}";
    $keyNoCity = "{$country}|{$region}|";
    $keyCountryOnly = "{$country}||";

    // Mapeo manual de país, región y ciudad → zona horaria
    $timezoneMap = [
        'mexico|cdmx|' => 'America/Mexico_City',
        'mexico|chihuahua|' => 'America/Chihuahua',
        'mexico|jalisco|guadalajara' => 'America/Mexico_City',
        'mexico|nuevo leon|monterrey' => 'America/Monterrey',

        'united states|california|' => 'America/Los_Angeles',
        'united states|new york|' => 'America/New_York',
        'united states|texas|houston' => 'America/Chicago',
        'united states|illinois|chicago' => 'America/Chicago',
        'united states|florida|miami' => 'America/New_York',
        'united states|arizona|' => 'America/Phoenix',

        'argentina||' => 'America/Argentina/Buenos_Aires',
        'colombia||' => 'America/Bogota',
        'chile||' => 'America/Santiago',
        'peru||' => 'America/Lima',
        'venezuela||' => 'America/Caracas',
        'ecuador||' => 'America/Guayaquil',
        'bolivia||' => 'America/La_Paz',
        'paraguay||' => 'America/Asuncion',
        'uruguay||' => 'America/Montevideo',
        'panama||' => 'America/Panama',
        'dominican republic||' => 'America/Santo_Domingo',
        'puerto rico||' => 'America/Puerto_Rico',
        'cuba||' => 'America/Havana',
        'guatemala||' => 'America/Guatemala',
        'honduras||' => 'America/Tegucigalpa',
        'el salvador||' => 'America/El_Salvador',
        'nicaragua||' => 'America/Managua',
        'costa rica||' => 'America/Costa_Rica',

        'spain||' => 'Europe/Madrid',
        'brazil||' => 'America/Sao_Paulo',
        'canada||' => 'America/Toronto',
        'germany||' => 'Europe/Berlin',
        'italy||' => 'Europe/Rome',
        'france||' => 'Europe/Paris',
    ];

    // Normaliza todas las claves del mapa
    $timezoneMapLower = array_change_key_case($timezoneMap, CASE_LOWER);

    if (isset($timezoneMapLower[$key])) {
        return $timezoneMapLower[$key];
    }
    if (isset($timezoneMapLower[$keyNoCity])) {
        return $timezoneMapLower[$keyNoCity];
    }
    if (isset($timezoneMapLower[$keyCountryOnly])) {
        return $timezoneMapLower[$keyCountryOnly];
    }

    return 'America/Caracas';
}


/**
 * Convierte la hora UTC del login a la hora local del usuario.
 */
/**
 * Devuelve la hora actual directamente en la zona horaria local del usuario.
 */
function getNowInUserLocalTime(string $country, string $region = '', string $city = ''): string {
    try {
        $timezone_id = getTimezoneFromLocation($country, $region, $city);
        $user_timezone = new DateTimeZone($timezone_id);
        $now = new DateTime('now', $user_timezone);
        return $now->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return gmdate('Y-m-d H:i:s'); // fallback a UTC si hay error
    }
}

