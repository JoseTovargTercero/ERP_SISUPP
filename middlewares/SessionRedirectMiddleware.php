<?php

namespace App\Middlewares;
use App\Middlewares\Middleware;

class SessionRedirectMiddleware implements Middleware
{
    public function handle()
    {

        if (isset($_SESSION['user_id']) && isset($_SESSION['roles_user'])) {


            header('Location: ./home');
            exit();
        }

        // Si no hay sesión, permitir continuar normalmente.
    }
}
