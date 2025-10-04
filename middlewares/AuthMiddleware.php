<?php
namespace App\Middlewares;

class AuthMiddleware implements Middleware
{
    /**
     * @var array Lista de roles permitidos para acceder a la ruta.
     */
    protected $allowedRoles;

    /**
     * El constructor acepta un array de roles permitidos.
     * Si el array está vacío, significa que cualquier usuario autenticado puede pasar.
     * @param array $allowedRoles
     */
    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle()
    {
        // 1. Primero, verificar que el usuario haya iniciado sesión.
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['roles_user'])) {
            session_unset();
            session_destroy();
            header('Location: ./login');
            exit();

            // 2. Si se especificaron roles, verificar que el usuario tenga uno de ellos.
        }
        // Si el array de roles permitidos está vacío, permitimos el paso a cualquier usuario autenticado.
        $userRole = $_SESSION['roles_user'];
        if (!empty($this->allowedRoles)) {
            if (!in_array(strtolower($userRole), $this->allowedRoles)) {
                // Si el rol del usuario no está en la lista, lo enviamos al login.
                // Podrías tener una lógica más avanzada aquí, como redirigir a una página de "acceso denegado".
                session_unset();
                session_destroy();
                header('Location: ./login');
                exit();
            }


        }



        // Si todas las comprobaciones pasan, la petición puede continuar.
    }
}
