<?php
namespace App\Controllers;

require_once __DIR__ . '/../models/SessionManagementModel.php';

class SessionManagementController
{
    /** @var \SessionManagementModel */
    private $model;

    public function __construct()
    {
        // El modelo no tiene namespace, por eso usamos el global "\"
        $this->model = new \SessionManagementModel();
    }

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $json = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($json)) ? $json : [];
    }

    private function jsonResponse(bool $value, string $message = '', $data = null, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['value' => $value, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ===== Acciones ===== */

    // GET /session-management
    public function showAll()
    {
        try {
            $rows = $this->model->getAll();
            return $this->jsonResponse(true, '', $rows, 200);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, 'Error al obtener sesiones: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /session-management/kick
    // Body JSON: { "session_id": "...", "inactivity_duration": "300", "status": "kicked" }
    public function kick()
    {
        $input = $this->getJsonInput();
        $sessionId = $input['session_id'] ?? ($_SESSION['session_id'] ?? null);
        $inactivity = isset($input['inactivity_duration']) ? (string)$input['inactivity_duration'] : null;
        $status = $input['status'] ?? 'kicked';

        if (!$sessionId) {
            return $this->jsonResponse(false, 'session_id es requerido', null, 400);
        }

        try {
            $ok = $this->model->logoutSession($sessionId, $inactivity, $status);
            if ($ok && isset($_SESSION['session_id']) && $_SESSION['session_id'] === $sessionId) {
                unset($_SESSION['session_id']);
            }
            return $this->jsonResponse($ok, $ok ? 'Sesión finalizada' : 'No se pudo finalizar la sesión', ['session_id' => $sessionId, 'new_status' => $status], $ok ? 200 : 400);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, 'Error al finalizar sesión: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /session-management/store-status
    // Body JSON: { "session_status": "expired|closed|kicked|active", "inactivity_duration": "300", "session_id": "opcional" }
    public function storeStatus()
    {
        $input = $this->getJsonInput();
        $status = $input['session_status'] ?? null;
        $inactivity = isset($input['inactivity_duration']) ? (string)$input['inactivity_duration'] : null;
        $sessionId = $input['session_id'] ?? ($_SESSION['session_id'] ?? null);

        if (!$status) {
            return $this->jsonResponse(false, 'session_status es requerido', null, 400);
        }
        if (!$sessionId) {
            return $this->jsonResponse(false, 'session_id es requerido', null, 400);
        }

        try {
            // Sólo persistimos cuando el estado NO es "active". Para "active" respondemos OK sin tocar DB.
            if (in_array($status, ['expired', 'closed', 'kicked'], true)) {
                $ok = $this->model->logoutSession($sessionId, $inactivity, $status);
                if ($ok && isset($_SESSION['session_id']) && $_SESSION['session_id'] === $sessionId) {
                    unset($_SESSION['session_id']);
                }
                return $this->jsonResponse($ok, $ok ? 'Estado de sesión actualizado' : 'No se pudo actualizar el estado', ['session_id' => $sessionId, 'new_status' => $status], $ok ? 200 : 400);
            }

            // active: no-op
            return $this->jsonResponse(true, 'Estado activo registrado (sin cambios en BD)', ['session_id' => $sessionId, 'status' => 'active'], 200);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, 'Error al registrar estado: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /session-management/check-status
    public function checkStatus()
    {
        $sessionId = $_SESSION['session_id'] ?? null;
        if (!$sessionId) {
            return $this->jsonResponse(false, 'No hay sesión activa en contexto', null, 400);
        }

        try {
            $status = $this->model->getStatusBySessionId($sessionId);
            return $this->jsonResponse(true, '', ['session_id' => $sessionId, 'session_status' => $status], 200);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, 'Error al consultar estado: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /session-management/{id}
    public function showById($params)
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            return $this->jsonResponse(false, 'ID requerido', null, 400);
        }

        try {
            $row = $this->model->getById($id);
            if (!$row) {
                return $this->jsonResponse(false, 'Sesión no encontrada', null, 404);
            }
            return $this->jsonResponse(true, '', $row, 200);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, 'Error al obtener sesión: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /session-management (registro desde login)
    // Body JSON:
    // {
    //   "user_id": "uuid|null",
    //   "user_type": "administrator|user|...",   // será ignorado por el modelo (se deriva de system_users.nivel)
    //   "device_id": "opcional",
    //   "device_type": "opcional",
    //   "login_success": true|false,
    //   "failure_reason": "opcional"
    // }
    public function create()
    {
        $data = $this->getJsonInput();
        $userId        = $data['user_id'] ?? null;
        $userType      = (string)($data['user_type'] ?? 'user'); // el modelo lo ignora para almacenamiento
        $deviceId      = $data['device_id'] ?? null;
        $deviceType    = $data['device_type'] ?? null;
        $loginSuccess  = array_key_exists('login_success', $data) ? (bool)$data['login_success'] : true;
        $failureReason = $data['failure_reason'] ?? null;

        try {
            $sessionId = $this->model->create($userId, $userType, $deviceId, $deviceType, $loginSuccess, $failureReason);

            // Si fue login exitoso, asociamos la sesión al contexto actual
            if ($loginSuccess) {
                $_SESSION['session_id'] = $sessionId;
            }

            return $this->jsonResponse(true, 'Sesión registrada', [
                'session_id'    => $sessionId,
                'user_id'       => $userId,
                'login_success' => $loginSuccess
            ], 201);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, 'Error al crear sesión: ' . $e->getMessage(), null, 400);
        }
    }

    // GET /session-management/export
    public function export()
    {
        try {
            // Este método envía cabeceras y hace exit;
            $this->model->exportToCSV();

            // Si por alguna razón no salió, devolvemos JSON.
            return $this->jsonResponse(true, 'Exportación generada', null, 200);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, 'Error al exportar: ' . $e->getMessage(), null, 500);
        }
    }
}
