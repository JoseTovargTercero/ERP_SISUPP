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
    $input    = $this->getJsonInput();
    $email    = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? ($input['contrasena'] ?? ''));

    // Auditoría / contexto
    $deviceId    = $input['device_id'] ?? null;
    $isMobile    = isset($input['is_mobile']) ? (bool)$input['is_mobile'] : null;
    $deviceType  = ($isMobile === null ? '' : ($isMobile ? 'mobile' : 'desktop'));
    $userTypeDef = 'user'; // por defecto antes de conocer nivel

    require_once __DIR__ . '/../helpers/login_helpers.php';
    require_once __DIR__ . '/../models/SessionManagementModel.php';
    $SessionManagementModel = new SessionManagementModel();

    $maxIntentos   = 3;
    $tiempoBloqueo = 60; // seg
    $keyIntentos   = 'login_attempts_' . md5($email);
    $ahora         = time();
    $intentos      = $_SESSION[$keyIntentos] ?? ['count' => 0, 'last_attempt' => 0, 'locked_until' => 0];

    /* ───── RATE LIMIT: bloqueado ───── */
    if ($intentos['locked_until'] > $ahora) {
        $espera = ceil(($intentos['locked_until'] - $ahora) / 60);

        // 🔎 NUEVO: si el correo es válido y existe el usuario, auditar con su user_id y user_type correcto
        $audUserId   = null;
        $audUserType = $userTypeDef;

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Usa el helper del modelo que te pasé antes
            if (method_exists($this->model, 'obtenerPorEmail')) {
                $u = $this->model->obtenerPorEmail($email);
                if (is_array($u) && !empty($u)) {
                    $audUserId   = $u['user_id'];
                    $nivel       = (int)($u['nivel'] ?? 1);
                    $audUserType = ($nivel === 0) ? 'administrator' : 'user';
                }
            }
        }

        $SessionManagementModel->create(
            $audUserId,          // ← si existe, va el real; si no, null
            $audUserType,        // ← administrator/user según nivel si lo conocemos
            $deviceId,
            $deviceType,
            false,
            'Demasiados intentos fallidos'
        );

        $this->jsonResponse(false, "Demasiados intentos. Intente nuevamente en {$espera} minuto(s).", ['espera_minutos' => $espera], 429);
        return;
    }

    /* ───── VALIDACIONES INICIALES ───── */
    if ($email === '' || $password === '') {
        $SessionManagementModel->create(null, $userTypeDef, $deviceId, $deviceType, false, 'Correo y/o contraseña vacíos');
        $intentos['count']++;
        $intentos['last_attempt'] = $ahora;
        if ($intentos['count'] >= $maxIntentos) {
            $intentos['locked_until'] = $ahora + $tiempoBloqueo;
        }
        $_SESSION[$keyIntentos] = $intentos;
        $this->jsonResponse(false, 'Correo y contraseña son obligatorios.', null, 400);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $SessionManagementModel->create(null, $userTypeDef, $deviceId, $deviceType, false, 'Formato de correo inválido');
        $intentos['count']++;
        $intentos['last_attempt'] = $ahora;
        if ($intentos['count'] >= $maxIntentos) {
            $intentos['locked_until'] = $ahora + $tiempoBloqueo;
        }
        $_SESSION[$keyIntentos] = $intentos;
        $this->jsonResponse(false, 'El formato del correo electrónico no es válido.', null, 400);
        return;
    }

    /* ───── AUTENTICACIÓN con contrato loginBasico (ok/code/user) ───── */
    try {
        $result = $this->model->loginBasico($email, $password);

        if (!$result['ok']) {
            $code = $result['code'] ?? 'unknown';
            $u    = $result['user'] ?? null;

            if ($code === 'user_not_found') {
                $SessionManagementModel->create(null, $userTypeDef, $deviceId, $deviceType, false, 'Usuario no encontrado');
                $this->jsonResponse(false, 'Credenciales inválidas.', null, 401);
                return;
            }

            if ($code === 'user_disabled') {
                $nivel = (int)($u['nivel'] ?? 1);
                $userType = ($nivel === 0) ? 'administrator' : 'user';
                $SessionManagementModel->create($u['user_id'], $userType, $deviceId, $deviceType, false, 'Usuario desactivado');
                $this->jsonResponse(false, 'Este usuario ha sido desactivado y no puede ingresar.', null, 403);
                return;
            }

            if ($code === 'invalid_password') {
                // ❗ Contraseña incorrecta: ya tenemos user_id y nivel → auditar con datos reales
                $nivel = (int)($u['nivel'] ?? 1);
                $userType = ($nivel === 0) ? 'administrator' : 'user';
                $SessionManagementModel->create($u['user_id'], $userType, $deviceId, $deviceType, false, 'Contraseña incorrecta');

                // rate-limit
                $intentos['count']++;
                $intentos['last_attempt'] = $ahora;
                if ($intentos['count'] >= $maxIntentos) {
                    $intentos['locked_until'] = $ahora + $tiempoBloqueo;
                }
                $_SESSION[$keyIntentos] = $intentos;

                $this->jsonResponse(false, 'Contraseña incorrecta.', null, 401);
                return;
            }

            // Fallback
            $SessionManagementModel->create($u['user_id'] ?? null, $userTypeDef, $deviceId, $deviceType, false, 'Fallo de autenticación');
            $this->jsonResponse(false, 'No se pudo iniciar sesión.', null, 401);
            return;
        }

        // ✅ Éxito
        unset($_SESSION[$keyIntentos]);
        $usuario  = $result['user'];
        $nivel    = (int)$usuario['nivel'];
        $userType = ($nivel === 0) ? 'administrator' : 'user';

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id']   = $usuario['user_id'];
        $_SESSION['nombre']    = $usuario['nombre'] ?? 'Usuario';
        $_SESSION['nivel']     = $nivel;
        $_SESSION['user_type'] = $userType;

        if ($nivel === 0) {
            $_SESSION['permisos'] = ['*'];
        } else {
            $permisosModel = new UsersPermisosModel();
            $permisos = $permisosModel->listarPermisosConMenu($usuario['user_id']);
            if (empty($permisos)) {
                $SessionManagementModel->create($usuario['user_id'], $userType, $deviceId, $deviceType, false, 'Sin permisos asignados');
                $this->jsonResponse(false, 'El usuario no tiene permisos asignados.', null, 403);
                return;
            }
            $_SESSION['permisos'] = array_map(fn($p) => $p['menu']['url'] ?? '', $permisos);
        }

        $sessionId = $SessionManagementModel->create(
            $usuario['user_id'],
            $userType,
            $deviceId,
            $deviceType,
            true,
            null
        );
        $_SESSION['session_id'] = $sessionId;

        $this->jsonResponse(true, 'Inicio de sesión exitoso.', $usuario);

    } catch (Throwable $e) {
        error_log("Error en login: " . $e->getMessage());
        $nivelAudit    = isset($usuario['nivel']) ? (int)$usuario['nivel'] : 1;
        $userTypeAudit = ($nivelAudit === 0) ? 'administrator' : 'user';
        $SessionManagementModel->create($usuario['user_id'] ?? null, $userTypeAudit, $deviceId, $deviceType, false, 'Error inesperado: ' . $e->getMessage());
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
