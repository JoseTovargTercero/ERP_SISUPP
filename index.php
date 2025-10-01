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
$router->agregarRuta('POST', 'system_users/login', [
    'controlador' => SystemUserController::class,
    'accion'      => 'login'
]);

// Listar usuarios
$router->agregarRuta('GET', 'system_users', [
    'controlador' => SystemUserController::class,
    'accion'      => 'listar'
]);

// Mostrar usuario por UUID
$router->agregarRuta('GET', 'system_users/user/{user_id}', [
    'controlador' => SystemUserController::class,
    'accion'      => 'mostrar'
]);

// Crear usuario
$router->agregarRuta('POST', 'system_users', [
    'controlador' => SystemUserController::class,
    'accion'      => 'crear'
]);

// Actualizar usuario (usaremos POST en vez de PUT)
$router->agregarRuta('POST', 'system_users/update/{user_id}', [
    'controlador' => SystemUserController::class,
    'accion'      => 'actualizar'
]);

// Eliminar usuario (soft delete)
$router->agregarRuta('DELETE', 'system_users/user/{user_id}', [
    'controlador' => SystemUserController::class,
    'accion'      => 'eliminar'
]);


// --- Ejecutar el Router ---
$router->route();


