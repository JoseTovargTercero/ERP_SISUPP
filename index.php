<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/controllers/SystemUserController.php';
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
$router->get('/users', ['vista' => 'modules/usuarios_view', 'vistaData' => ['titulo' => 'Usuarios del Sistema', 'layout' => true]]);
// acciones
$router->get('/system_users', ['controlador' => SystemUserController::class, 'accion' => 'listar']);
$router->get('/system_users/{user_id}', ['controlador' => SystemUserController::class, 'accion' => 'mostrar']);
$router->post('/system_users', ['controlador' => SystemUserController::class, 'accion' => 'crear']);
$router->put('/system_users/{user_id}', ['controlador' => SystemUserController::class, 'accion' => 'actualizar']);
$router->delete('/system_users/{user_id}', ['controlador' => SystemUserController::class, 'accion' => 'eliminar']);



// --- Ejecutar el Router ---
$router->route();


