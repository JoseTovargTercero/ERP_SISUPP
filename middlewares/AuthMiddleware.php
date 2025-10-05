<?php

require_once 'Middleware.php';



class AuthMiddleware implements Middleware
{
    /**
     * Maneja la lógica de autenticación y autorización para una ruta protegida.
     * @param string $ruta La ruta a la que el usuario intenta acceder.
     */
    public function handle($ruta)
    {

        // 1. Verificar si existe una sesión activa.
        // Si el middleware fue llamado, la ruta es protegida y requiere sesión.
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['nivel'])) {
            $this->redirigirALogin();
        }

        // 2. Si el usuario es Administrador (nivel 0), permitir acceso total.
        if ((string) $_SESSION['nivel'] === '0') {
            return; // Acceso concedido, el administrador puede pasar.
        }

        // 3. Para otros usuarios, verificar permisos específicos en la sesión.
        $permisosUsuario = $_SESSION['permisos'] ?? [];



        $accesoPermitido = false;
        foreach ($permisosUsuario as $permiso) {
            // Comprobación de coincidencia exacta (ej. '/dashboard')
            // formatear ruta
            $ruta = ltrim($ruta, '/');
            if ($ruta === $permiso) {
                $accesoPermitido = true;
                break;
            }
            // Comprobación de sub-rutas (ej. '/users/123' comienza con '/users/')
            if (str_starts_with($ruta, rtrim($permiso, '/') . '/')) {
                $accesoPermitido = true;
                break;
            }
        }

        // 4. Si no se encontró un permiso válido, denegar el acceso.
        if (!$accesoPermitido) {

            $this->accesoDenegado();

        }

        // Si las comprobaciones pasan, la ejecución del script continúa.
    }

    /**
     * Redirige al usuario a la página de login.
     */
    private function redirigirALogin()
    {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . 'login');
        exit();
    }

    /**
     * Maneja los casos de acceso denegado redirigiendo a una ruta
     * que provocará una respuesta 404 del Router.
     */
    private function accesoDenegado()
    {
        header('Location: ' . BASE_URL . '404');
        exit();
    }
}