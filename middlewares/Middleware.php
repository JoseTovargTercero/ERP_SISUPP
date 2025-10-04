<?php
namespace App\Middlewares;
/**
 * Interfaz que deben implementar todos los middlewares.
 * Define un único método 'handle' que se ejecutará antes del controlador.
 */
interface Middleware
{
    /**
     * Maneja la lógica del middleware.
     * Puede redirigir, modificar la petición o simplemente pasar al siguiente paso.
     */
    public function handle();
}