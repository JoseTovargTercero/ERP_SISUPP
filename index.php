<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/controllers/SystemUserController.php';
require_once __DIR__ . '/controllers/AreaController.php';
require_once __DIR__ . '/controllers/FincaController.php';
require_once __DIR__ . '/controllers/ApriscoController.php';
require_once __DIR__ . '/controllers/ReporteDanoController.php';
require_once __DIR__ . '/controllers/MenuController.php';
require_once __DIR__ . '/controllers/UsersPermisosController.php';
require_once __DIR__ . '/middlewares/AuthMiddleware.php';
require_once __DIR__ . '/controllers/AnimalController.php';
require_once __DIR__ . '/controllers/AnimalMovimientoController.php';
require_once __DIR__ . '/controllers/AnimalPesoController.php';
require_once __DIR__ . '/controllers/AnimalSaludController.php';
require_once __DIR__ . '/controllers/AnimalUbicacionController.php';
require_once __DIR__ . '/controllers/MontaController.php';
require_once __DIR__ . '/controllers/PartoController.php';
require_once __DIR__ . '/controllers/PeriodoServicioController.php';


use App\Core\ViewRenderer;

use App\Router;

if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Iniciar sesión si no está iniciada
}




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

// vistas

$router->group(['middleware' => AuthMiddleware::class], function ($router) {
    $router->get('/users', ['vista' => 'modules/usuarios_view', 'vistaData' => ['titulo' => 'Usuarios del Sistema']]);
    $router->get('/modulos', ['vista' => 'modules/menus_view', 'vistaData' => ['titulo' => 'Modulos del Sistema']]);
    $router->get('/fincas', ['vista' => 'modules/fincas_view', 'vistaData' => ['titulo' => 'Fincas del Sistema']]);
    $router->get('/animales', ['vista' => 'modules/animales_view', 'vistaData' => ['titulo' => 'Fincas del Sistema']]);

});

$router->group(['prefix' => '/api'], function ($router) {
    // Aquí puedes definir rutas que compartan el prefijo /api

    // endpoints de usuarios
    $router->get('/system_users', ['controlador' => SystemUserController::class, 'accion' => 'listar']);
    $router->get('/system_users/{user_id}', ['controlador' => SystemUserController::class, 'accion' => 'mostrar']);
    $router->post('/system_users', ['controlador' => SystemUserController::class, 'accion' => 'crear']);
    $router->put('/system_users/{user_id}', ['controlador' => SystemUserController::class, 'accion' => 'actualizar']);
    $router->delete('/system_users/{user_id}', ['controlador' => SystemUserController::class, 'accion' => 'eliminar']);
    $router->get('/logout', [
        'controlador' => SystemUserController::class,
        'accion' => 'logout'
    ]);


    // endpoints de fincas
    $router->get('/fincas', ['controlador' => FincaController::class, 'accion' => 'listar']);
    $router->get('/fincas/{finca_id}', ['controlador' => FincaController::class, 'accion' => 'mostrar']);
    $router->post('/fincas', ['controlador' => FincaController::class, 'accion' => 'crear']);
    $router->post('/fincas/{finca_id}', ['controlador' => FincaController::class, 'accion' => 'actualizar']);
    $router->post('/fincas/{finca_id}/estado', ['controlador' => FincaController::class, 'accion' => 'actualizarEstado']);
    $router->delete('/fincas/{finca_id}', ['controlador' => FincaController::class, 'accion' => 'eliminar']);

    // endpoints de apriscos
    $router->get('/apriscos', ['controlador' => ApriscoController::class, 'accion' => 'listar']);
    $router->get('/apriscos/{aprisco_id}', ['controlador' => ApriscoController::class, 'accion' => 'mostrar']);
    $router->post('/apriscos', ['controlador' => ApriscoController::class, 'accion' => 'crear']);
    $router->post('/apriscos/{aprisco_id}', ['controlador' => ApriscoController::class, 'accion' => 'actualizar']);
    $router->post('/apriscos/{aprisco_id}/estado', ['controlador' => ApriscoController::class, 'accion' => 'actualizarEstado']);
    $router->delete('/apriscos/{aprisco_id}', ['controlador' => ApriscoController::class, 'accion' => 'eliminar']);

    // endpoints de áreas
    $router->get('/areas', ['controlador' => AreaController::class, 'accion' => 'listar']);
    $router->get('/areas/{area_id}', ['controlador' => AreaController::class, 'accion' => 'mostrar']);
    $router->post('/areas', ['controlador' => AreaController::class, 'accion' => 'crear']);
    $router->post('/areas/{area_id}', ['controlador' => AreaController::class, 'accion' => 'actualizar']);
    $router->post('/areas/{area_id}/estado', ['controlador' => AreaController::class, 'accion' => 'actualizarEstado']);
    $router->delete('/areas/{area_id}', ['controlador' => AreaController::class, 'accion' => 'eliminar']);

    // endpoints de reportes de daño
    $router->get('/reportes_dano', ['controlador' => ReporteDanoController::class, 'accion' => 'listar']);
    $router->get('/reportes_dano/{reporte_id}', ['controlador' => ReporteDanoController::class, 'accion' => 'mostrar']);
    $router->post('/reportes_dano', ['controlador' => ReporteDanoController::class, 'accion' => 'crear']);
    $router->post('/reportes_dano/{reporte_id}', ['controlador' => ReporteDanoController::class, 'accion' => 'actualizar']);
    $router->post('/reportes_dano/{reporte_id}/estado', ['controlador' => ReporteDanoController::class, 'accion' => 'actualizarEstado']);
    $router->delete('/reportes_dano/{reporte_id}', ['controlador' => ReporteDanoController::class, 'accion' => 'eliminar']);

    // endpoints de menús
    $router->get('/menus', ['controlador' => MenuController::class, 'accion' => 'listar']);
    $router->get('/menus/{menu_id}', ['controlador' => MenuController::class, 'accion' => 'mostrar']);
    $router->post('/menus', ['controlador' => MenuController::class, 'accion' => 'crear']);
    $router->post('/menus/{menu_id}', ['controlador' => MenuController::class, 'accion' => 'actualizar']);
    $router->delete('/menus/{menu_id}', ['controlador' => MenuController::class, 'accion' => 'eliminar']);

    // endpoints de permisos de usuarios
    $router->post('/users-permisos', ['controlador' => UsersPermisosController::class, 'accion' => 'asignar']);
    $router->get('/users-permisos/user/{user_id}', ['controlador' => UsersPermisosController::class, 'accion' => 'listarPorUsuario']);
    $router->delete('/users-permisos/{users_permisos_id}', ['controlador' => UsersPermisosController::class, 'accion' => 'eliminarUno']);
    $router->delete('/users-permisos/user/{user_id}', ['controlador' => UsersPermisosController::class, 'accion' => 'eliminarPorUsuario']);

    // endpoints auxiliares OPTIONS
    $router->get('/fincas/options', ['controlador' => FincaController::class, 'accion' => 'options']);
    $router->get('/apriscos/options', ['controlador' => ApriscoController::class, 'accion' => 'options']);
    $router->get('/areas/options', ['controlador' => AreaController::class, 'accion' => 'options']);

    // endpoint de login
    $router->post('/system_users/login', ['controlador' => SystemUserController::class, 'accion' => 'login']);

    //gestion de animales

    // endpoints de animal_pesos
    $router->get('/animal_pesos', ['controlador' => AnimalPesoController::class, 'accion' => 'listar']);
    $router->get('/animal_pesos/{animal_peso_id}', ['controlador' => AnimalPesoController::class, 'accion' => 'mostrar']);
    $router->post('/animal_pesos', ['controlador' => AnimalPesoController::class, 'accion' => 'crear']);
    $router->post('/animal_pesos/{animal_peso_id}', ['controlador' => AnimalPesoController::class, 'accion' => 'actualizar']);
    $router->delete('/animal_pesos/{animal_peso_id}', ['controlador' => AnimalPesoController::class, 'accion' => 'eliminar']);

    // endpoints de animal_salud
    $router->get('/animal_salud', ['controlador' => AnimalSaludController::class, 'accion' => 'listar']);
    $router->get('/animal_salud/{animal_salud_id}', ['controlador' => AnimalSaludController::class, 'accion' => 'mostrar']);
    $router->post('/animal_salud', ['controlador' => AnimalSaludController::class, 'accion' => 'crear']);
    $router->post('/animal_salud/{animal_salud_id}', ['controlador' => AnimalSaludController::class, 'accion' => 'actualizar']);
    $router->delete('/animal_salud/{animal_salud_id}', ['controlador' => AnimalSaludController::class, 'accion' => 'eliminar']);


    // endpoints de animal_ubicaciones
    $router->get('/animal_ubicaciones', ['controlador' => AnimalUbicacionController::class, 'accion' => 'listar']);
    $router->get('/animal_ubicaciones/{animal_ubicacion_id}', ['controlador' => AnimalUbicacionController::class, 'accion' => 'mostrar']);
    $router->get('/animal_ubicaciones/actual/{animal_id}', ['controlador' => AnimalUbicacionController::class, 'accion' => 'actual']);
    $router->post('/animal_ubicaciones', ['controlador' => AnimalUbicacionController::class, 'accion' => 'crear']);
    $router->post('/animal_ubicaciones/{animal_ubicacion_id}', ['controlador' => AnimalUbicacionController::class, 'accion' => 'actualizar']);
    $router->post('/animal_ubicaciones/{animal_ubicacion_id}/cerrar', ['controlador' => AnimalUbicacionController::class, 'accion' => 'cerrar']);
    $router->delete('/animal_ubicaciones/{animal_ubicacion_id}', ['controlador' => AnimalUbicacionController::class, 'accion' => 'eliminar']);


    // endpoints de animal_movimientos
    $router->get('/animal_movimientos', ['controlador' => AnimalMovimientoController::class, 'accion' => 'listar']);
    $router->get('/animal_movimientos/{animal_movimiento_id}', ['controlador' => AnimalMovimientoController::class, 'accion' => 'mostrar']);
    $router->post('/animal_movimientos', ['controlador' => AnimalMovimientoController::class, 'accion' => 'crear']);
    $router->post('/animal_movimientos/{animal_movimiento_id}', ['controlador' => AnimalMovimientoController::class, 'accion' => 'actualizar']);
    $router->delete('/animal_movimientos/{animal_movimiento_id}', ['controlador' => AnimalMovimientoController::class, 'accion' => 'eliminar']);


    // endpoints de animales
    $router->get('/animales', ['controlador' => AnimalController::class, 'accion' => 'listar']);
    $router->get('/animales/{animal_id}', ['controlador' => AnimalController::class, 'accion' => 'mostrar']);
    $router->get('/animales/options', ['controlador' => AnimalController::class, 'accion' => 'options']);
    $router->post('/animales', ['controlador' => AnimalController::class, 'accion' => 'crear']);
    $router->post('/animales/{animal_id}', ['controlador' => AnimalController::class, 'accion' => 'actualizar']);
    $router->delete('/animales/{animal_id}', ['controlador' => AnimalController::class, 'accion' => 'eliminar']);



    //juntas de animales
    // endpoints de periodos de servicio
$router->get('/periodos_servicio',                   ['controlador' => PeriodoServicioController::class, 'accion' => 'listar']);
$router->get('/periodos_servicio/{periodo_id}',      ['controlador' => PeriodoServicioController::class, 'accion' => 'mostrar']);
$router->post('/periodos_servicio',                  ['controlador' => PeriodoServicioController::class, 'accion' => 'crear']);
$router->post('/periodos_servicio/{periodo_id}',     ['controlador' => PeriodoServicioController::class, 'accion' => 'actualizar']);
$router->post('/periodos_servicio/{periodo_id}/estado', ['controlador' => PeriodoServicioController::class, 'accion' => 'actualizarEstado']);
$router->delete('/periodos_servicio/{periodo_id}',   ['controlador' => PeriodoServicioController::class, 'accion' => 'eliminar']);

// endpoints de partos
$router->get('/partos',                     ['controlador' => PartoController::class, 'accion' => 'listar']);
$router->get('/partos/{parto_id}',          ['controlador' => PartoController::class, 'accion' => 'mostrar']);
$router->post('/partos',                    ['controlador' => PartoController::class, 'accion' => 'crear']);
$router->post('/partos/{parto_id}',         ['controlador' => PartoController::class, 'accion' => 'actualizar']);
$router->post('/partos/{parto_id}/estado',  ['controlador' => PartoController::class, 'accion' => 'actualizarEstado']);
$router->delete('/partos/{parto_id}',       ['controlador' => PartoController::class, 'accion' => 'eliminar']);

// endpoints de montas
$router->get('/montas',                 ['controlador' => MontaController::class, 'accion' => 'listar']);
$router->get('/montas/{monta_id}',      ['controlador' => MontaController::class, 'accion' => 'mostrar']);
$router->post('/montas',                ['controlador' => MontaController::class, 'accion' => 'crear']);
$router->post('/montas/{monta_id}',     ['controlador' => MontaController::class, 'accion' => 'actualizar']);
$router->delete('/montas/{monta_id}',   ['controlador' => MontaController::class, 'accion' => 'eliminar']);

});


// --- Ejecutar el Router ---
$router->route();


