<?php
require_once __DIR__ . '/../models/SystemUserModel.php';
require_once __DIR__ . '/../models/UsersPermisosModel.php';

class SystemUserController
{
    private $model;

    public function __construct()
    {
        $this->model = new SystemUserModel();
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    private function jsonResponse($value, string $message = '', $data = null, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'value' => $value,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    /* ============ Endpoints ============ */

    // POST /system-users/login
// POST /system-users/login
// POST /system-users/login
    public function login(): void
    {
        $in = $this->getJsonInput();
        $email = trim($in['email'] ?? '');
        $password = (string) ($in['contrasena'] ?? '');

        if ($email === '' || $password === '') {
            $this->jsonResponse(false, 'Correo y contraseña son obligatorios.', null, 400);
            return;
        }

        try {
            $user = $this->model->loginBasico($email, $password);

            // Credenciales inválidas o usuario inexistente/borrado
            if (!$user) {
                $this->jsonResponse(false, 'Credenciales inválidas o usuario inactivo.', null, 401);
                return;
            }

            // Si el nivel es 0 (Administrador), se omite la verificación de permisos
            if ((int) $user['nivel'] !== 0) {
                $permisosModel = new UsersPermisosModel();
                $permisos = $permisosModel->listarPermisosConMenu($user['user_id']);

                if (empty($permisos)) {
                    $this->jsonResponse(false, 'El usuario no puede ingresar porque no tiene permisos asignados.', null, 403);
                    return;
                }

                $user['permisos'] = $permisos;
            } else {
                // Acceso completo para nivel 0
                $user['permisos'] = ['*']; // Indica acceso total
            }

            // Crear sesión
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['nivel'] = $user['nivel'];


            $urlsPermitidas = [];
            $permisosData = $user['permisos'];
            foreach ($permisosData as $permiso) {
                $urlsPermitidas[] = $permiso['menu']['url'];
            }
            $_SESSION['user_modules'] = $urlsPermitidas;


            $this->jsonResponse(true, 'Inicio de sesión exitoso.', $user);

        } catch (DomainException $e) {
            if ($e->getCode() === 1001 || $e->getMessage() === 'USER_DISABLED') {
                $this->jsonResponse(false, 'Este usuario ha sido desactivado y no puede ingresar.', null, 403);
                return;
            }
            $this->jsonResponse(false, 'No se pudo iniciar sesión: ' . $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al iniciar sesión: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /system-users/logout
    // POST /system-users/logout
    public function logout(): void
    {
        try {
            $ok = $this->model->logout();

            // Redirigir al inicio de sesión o a la página principal
            $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
            header("Location: " . $baseUrl);
            exit;

        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al cerrar sesión: ' . $e->getMessage(), null, 500);
        }
    }



    // GET /system-users?limit=&offset=&incluirEliminados=0|1
    public function listar(): void
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int) $_GET['incluirEliminados'] === 1) : false;

        try {
            $data = $this->model->listar($limit, $offset, $incluir);
            $this->jsonResponse(true, 'Listado obtenido correctamente.', $data);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /system-users/show?user_id=UUID
    public function mostrar($parametros): void
    {
        $userId = $parametros['user_id'];
        if ($userId === '') {
            $this->jsonResponse(false, 'Parámetro user_id es obligatorio.', null, 400);
        }

        try {
            $row = $this->model->obtenerPorId($userId);
            if (!$row)
                $this->jsonResponse(false, 'Usuario no encontrado.', null, 404);
            $this->jsonResponse(true, 'Usuario encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener usuario: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /system-users/create
    // JSON: { nombre, email, contrasena, nivel, estado? }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Usuario creado correctamente.', ['user_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear usuario: ' . $e->getMessage(), null, 500);
        }
    }

    // PUT/PATCH /system-users/update?user_id=UUID
    // JSON: { nombre?, email?, contrasena?, nivel?, estado? }
    public function actualizar($parametros): void
    {
        $userId = $parametros['user_id'] ?? '';
        if ($userId === '') {
            $this->jsonResponse(false, 'Parámetro user_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($userId, $in);
            $this->jsonResponse(true, 'Usuario actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar usuario: ' . $e->getMessage(), null, 500);
        }
    }

    // PATCH /system-users/status?user_id=UUID
    // JSON: { estado: 0|1 }
    public function actualizarEstado($parametros): void
    {
        $userId = $parametros['user_id'] ?? '';
        if ($userId === '') {
            $this->jsonResponse(false, 'Parámetro user_id es obligatorio.', null, 400);
        }
        $in = $this->getJsonInput();
        if (!isset($in['estado'])) {
            $this->jsonResponse(false, 'El campo estado es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->actualizarEstado($userId, (int) $in['estado']);
            $this->jsonResponse(true, 'Estado actualizado correctamente.', ['updated' => $ok]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar estado: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE (soft) /system-users/delete?user_id=UUID
    public function eliminar($parametros): void
    {
        $userId = $parametros['user_id'] ?? '';
        if ($userId === '') {
            $this->jsonResponse(false, 'Parámetro user_id es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->eliminar($userId);
            if (!$ok)
                $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminado).', null, 400);
            $this->jsonResponse(true, 'Usuario eliminado correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar usuario: ' . $e->getMessage(), null, 500);
        }
    }
}
