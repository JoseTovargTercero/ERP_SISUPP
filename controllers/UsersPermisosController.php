<?php
require_once __DIR__ . '/../models/UsersPermisosModel.php';

class UsersPermisosController
{
    private $model;

    public function __construct()
    {
        $this->model = new UsersPermisosModel();
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

    // POST /users-permisos
    // JSON: { user_id: UUID, menu_ids: [UUID, UUID, ...] }
    public function asignar(): void
    {
        $in = $this->getJsonInput();
        try {
            $userId = (string) ($in['user_id'] ?? '');
            // Se permite que menu_ids sea un array vacío para poder quitar todos los permisos
            $menuIds = $in['menu_ids'] ?? null;

            if ($userId === '' || !is_array($menuIds)) {
                throw new InvalidArgumentException('Campos requeridos: user_id (string) y menu_ids (array).');
            }
            // Llamamos al nuevo método de sincronización
            $result = $this->model->sincronizarPermisos($userId, $menuIds);

            // Actualizamos el mensaje para reflejar la acción de sincronización
            $this->jsonResponse(true, 'Permisos del usuario sincronizados correctamente.', $result);

        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) { // Aunque no se usa en el nuevo método, es buena práctica mantenerlo
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al sincronizar permisos: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /users-permisos/user/{user_id}?via=menuModel|join (default join)
    public function listarPorUsuario(array $params): void
    {
        $userId = $params['user_id'] ?? '';
        if ($userId === '') {
            $this->jsonResponse(false, 'Parámetro user_id es obligatorio.', null, 400);
        }
        $via = isset($_GET['via']) ? (string) $_GET['via'] : 'join';

        try {
            if ($via === 'menuModel') {
                $data = $this->model->listarPermisosConMenu_UsandoMenuModel($userId);
            } else {
                $data = $this->model->listarPermisosConMenu($userId);
            }
            $this->jsonResponse(true, 'Permisos del usuario obtenidos correctamente.', $data);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar permisos: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /users-permisos/{users_permisos_id}
    public function eliminarUno(array $params): void
    {
        $id = $params['users_permisos_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro users_permisos_id es obligatorio.', null, 400);
        }
        try {
            $ok = $this->model->eliminarUno($id);
            if (!$ok)
                $this->jsonResponse(false, 'No se pudo eliminar (no existe).', null, 400);
            $this->jsonResponse(true, 'Permiso eliminado.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar permiso: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /users-permisos/user/{user_id}
    public function eliminarPorUsuario(array $params): void
    {
        $userId = $params['user_id'] ?? '';
        if ($userId === '') {
            $this->jsonResponse(false, 'Parámetro user_id es obligatorio.', null, 400);
        }
        try {
            $count = $this->model->eliminarPorUsuario($userId);
            $this->jsonResponse(true, 'Permisos del usuario eliminados.', ['deleted_count' => $count]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar permisos del usuario: ' . $e->getMessage(), null, 500);
        }
    }
}
