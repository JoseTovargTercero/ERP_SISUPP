<?php
// Headers para API
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// Dependencias
$schemas = require __DIR__ . "/../config/schemas.php";
$conexion = require __DIR__ . "/../config/db.php";

require __DIR__ . "/../core/Router.php";
require __DIR__ . "/../core/Validator.php";
require __DIR__ . "/../core/Response.php";

// Leer body JSON
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    Response::json(["error" => "JSON inválido o vacío"]);
}

// Validar formId
$formId = $input["formId"] ?? null;
if (!$formId || !isset($schemas[$formId])) {
    Response::json(["error" => "Formulario inválido"]);
}

$schema = $schemas[$formId];

// Validar origen (opcional en API, depende de tu estrategia)
if (!Validator::validarOrigen($schema)) {
    Response::json(["error" => "Origen no permitido"]);
}

// Filtrar datos según campos del schema
$data = Validator::filtrarDatos($schema, $input);
if (empty($data)) {
    Response::json(["error" => "Datos incompletos"]);
}



// Verificar si hay un hook específico para este formId
$hookPath = __DIR__ . "/forms/{$formId}.php";
if (file_exists($hookPath)) {
    $hook = require $hookPath;
    $resultadoHook = $hook($data, $conexion);

    // Si el hook devolvió un error, cortar ahí
    if (isset($resultadoHook["error"])) {
        Response::json($resultadoHook);
    }

    // Reemplazar $data con lo modificado
    $data = $resultadoHook;
}


// Ejecutar acción
$result = Router::ejecutar($schema, $data, $conexion);
Response::json($result);