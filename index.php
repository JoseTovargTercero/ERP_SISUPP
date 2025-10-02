<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/controllers/SystemUserController.php';
require_once __DIR__ . '/controllers/AreaController.php';
require_once __DIR__ . '/controllers/FincaController.php';
require_once __DIR__ . '/controllers/ApriscoController.php';
require_once __DIR__ . '/controllers/ReporteDanoController.php';
use App\Core\ViewRenderer;
use App\Router;





$host = $_SERVER['HTTP_HOST'];

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';


// Uso para archivos en php
define('APP_ROOT', __DIR__ . '/'); // Define la ruta raíz de la aplicación
// Uso para rutas en el js/html
define('BASE_URL', "$protocol://$host$path");


$viewRenderer = new ViewRenderer('views/');

$router = new Router($viewRenderer);


$router->get('/login', ['vista' => 'auth/login', 'vistaData' => ['titulo' => 'Iniciar Sesión', 'layout' => false]]);
$router->get('/', ['vista' => 'auth/login', 'vistaData' => ['titulo' => 'Iniciar Sesión', 'layout' => false]]);
$router->get('', ['vista' => 'auth/login', 'vistaData' => ['titulo' => 'Iniciar Sesión', 'layout' => false]]);


// Login
$router->post('system_users/login', ['controlador' => SystemUserController::class, 'accion' => 'login']);

// usuarios

// vista
$router->get('/users', ['vista' => 'modules/usuarios_view', 'vistaData' => ['titulo' => 'Usuarios del Sistema']]);
// acciones
$router->get('/system_users', ['controlador' => SystemUserController::class, 'accion' => 'listar']);
$router->get('/system_users/{user_id}', ['controlador' => SystemUserController::class, 'accion' => 'mostrar']);
$router->post('/system_users', ['controlador' => SystemUserController::class, 'accion' => 'crear']);
$router->put('/system_users/{user_id}', ['controlador' => SystemUserController::class, 'accion' => 'actualizar']);
$router->delete('/system_users/{user_id}', ['controlador' => SystemUserController::class, 'accion' => 'eliminar']);



// ============================
// Rutas para Fincas
// ============================

$router->get('/fincas', [
    'controlador' => FincaController::class,
    'accion' => 'listar'
]);

$router->get('/fincas/{finca_id}', [
    'controlador' => FincaController::class,
    'accion' => 'mostrar'
]);

$router->post('/fincas', [
    'controlador' => FincaController::class,
    'accion' => 'crear'
]);

$router->put('/fincas/{finca_id}', [
    'controlador' => FincaController::class,
    'accion' => 'actualizar'
]);

$router->put('/fincas/{finca_id}/estado', [
    'controlador' => FincaController::class,
    'accion' => 'actualizarEstado'
]);

$router->delete('/fincas/{finca_id}', [
    'controlador' => FincaController::class,
    'accion' => 'eliminar'
]);



// ============================
// Rutas para Apriscos
// ============================

$router->get('/apriscos', [
    'controlador' => ApriscoController::class,
    'accion' => 'listar'
]);

$router->get('/apriscos/{aprisco_id}', [
    'controlador' => ApriscoController::class,
    'accion' => 'mostrar'
]);

$router->post('/apriscos', [
    'controlador' => ApriscoController::class,
    'accion' => 'crear'
]);

$router->put('/apriscos/{aprisco_id}', [
    'controlador' => ApriscoController::class,
    'accion' => 'actualizar'
]);

$router->put('/apriscos/{aprisco_id}/estado', [
    'controlador' => ApriscoController::class,
    'accion' => 'actualizarEstado'
]);

$router->delete('/apriscos/{aprisco_id}', [
    'controlador' => ApriscoController::class,
    'accion' => 'eliminar'
]);


// ============================
// Rutas para Áreas
// ============================

$router->get('/areas', [
    'controlador' => AreaController::class,
    'accion' => 'listar'
]);

$router->get('/areas/{area_id}', [
    'controlador' => AreaController::class,
    'accion' => 'mostrar'
]);

$router->post('/areas', [
    'controlador' => AreaController::class,
    'accion' => 'crear'
]);

$router->put('/areas/{area_id}', [
    'controlador' => AreaController::class,
    'accion' => 'actualizar'
]);

$router->put('/areas/{area_id}/estado', [
    'controlador' => AreaController::class,
    'accion' => 'actualizarEstado'
]);

$router->delete('/areas/{area_id}', [
    'controlador' => AreaController::class,
    'accion' => 'eliminar'
]);


// ============================
// Rutas para Reportes de Daño
// ============================

$router->get('/reportes_dano', [
    'controlador' => ReporteDanoController::class,
    'accion' => 'listar'
]);

$router->get('/reportes_dano/{reporte_id}', [
    'controlador' => ReporteDanoController::class,
    'accion' => 'mostrar'
]);

$router->post('/reportes_dano', [
    'controlador' => ReporteDanoController::class,
    'accion' => 'crear'
]);

$router->put('/reportes_dano/{reporte_id}', [
    'controlador' => ReporteDanoController::class,
    'accion' => 'actualizar'
]);

$router->put('/reportes_dano/{reporte_id}/estado', [
    'controlador' => ReporteDanoController::class,
    'accion' => 'actualizarEstado'
]);

$router->delete('/reportes_dano/{reporte_id}', [
    'controlador' => ReporteDanoController::class,
    'accion' => 'eliminar'
]);



// --- Ejecutar el Router ---
$router->route();


