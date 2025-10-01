<?php

function cargarDotEnv($ruta)
{
    // Construir rutas posibles
    $archivoEnv = rtrim($ruta, '/') . '/.env_erpg';
    $archivoAlt = rtrim($ruta, '/') . '/env_erpg';

    // Verificar cuál existe
    if (file_exists($archivoEnv)) {
        $archivo = $archivoEnv;
    } elseif (file_exists($archivoAlt)) {
        $archivo = $archivoAlt;
    } else {
        echo "Archivo .env o env no encontrado en $ruta";
        return;
    }

    $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        if (strpos(trim($linea), '#') === 0) continue; // Ignorar comentarios

        list($nombre, $valor) = explode('=', $linea, 2);
        $nombre = trim($nombre);
        $valor = trim($valor);

        // No sobrescribe variables ya definidas
        if (!isset($_ENV[$nombre])) {
            $_ENV[$nombre] = $valor;
        }
    }
}

cargarDotEnv(dirname(__DIR__) . '/../../');
$usuario = $_ENV['DB_USER'];
$contrasena = $_ENV['DB_PASS'];
$baseDeDatos = $_ENV['DB_NAME'];


$conexion = new mysqli('localhost', $usuario, $contrasena, $baseDeDatos);
$conexion->set_charset('utf8');
if ($conexion->connect_error) {
    die("Error conexión BD: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");
return $conexion;
