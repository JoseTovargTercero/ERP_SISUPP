<?php
require_once __DIR__ . '/../models/MenuModel.php';

class MenuController
{
    private $model;

    public function __construct()
    {
        $this->model = new MenuModel();
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

    // ... (métodos listar, mostrar, crear, actualizar, eliminar sin cambios) ...

    // GET /menus?limit=&offset=&incluirEliminados=0|1&categoria=...&user_level=...
    // Opcional: ?q= (busca por nombre o url)
    public function listar(): void
    {
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int) $_GET['incluirEliminados'] === 1) : false;
        $categoria = isset($_GET['categoria']) ? trim((string) $_GET['categoria']) : null;
        $userLevel = isset($_GET['user_level']) ? (string) $_GET['user_level'] : null;
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : null;

        try {
            $data = $this->model->listar($limit, $offset, $incluir, $categoria, $userLevel, $q);
            $this->jsonResponse(true, 'Listado de menús obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar menús: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /menus/{menu_id}
    public function mostrar(array $params): void
    {
        $menuId = $params['menu_id'] ?? '';
        if ($menuId === '') {
            $this->jsonResponse(false, 'Parámetro menu_id es obligatorio.', null, 400);
        }

        try {
            $row = $this->model->obtenerPorId($menuId);
            if (!$row)
                $this->jsonResponse(false, 'Menú no encontrado.', null, 404);
            $this->jsonResponse(true, 'Menú encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener menú: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /menus
    // JSON: { categoria, nombre, url, icono?, user_level, orden? }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Menú creado correctamente.', ['menu_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear menú: ' . $e->getMessage(), null, 500);
        }
    }

    // PUT/POST /menus/{menu_id}
    // JSON: { categoria?, nombre?, url?, icono?, user_level?, orden? }
    public function actualizar(array $params): void
    {
        $menuId = $params['menu_id'] ?? '';
        if ($menuId === '') {
            $this->jsonResponse(false, 'Parámetro menu_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($menuId, $in);
            $this->jsonResponse(true, 'Menú actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar menú: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /menus/{menu_id} (soft delete)
    public function eliminar(array $params): void
    {
        $menuId = $params['menu_id'] ?? '';
        if ($menuId === '') {
            $this->jsonResponse(false, 'Parámetro menu_id es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->eliminar($menuId);
            if (!$ok)
                $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminado).', null, 400);
            $this->jsonResponse(true, 'Menú eliminado correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar menú: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * AÑADIDO: Nuevo método para reordenar.
     * POST /menus/reordenar
     * JSON: ["uuid-menu-3", "uuid-menu-1", "uuid-menu-2"]
     */
    public function reordenar(): void
    {
        $menuIds = $this->getJsonInput();

        // Verificación básica del input
        if (empty($menuIds) || !is_array($menuIds) || array_filter($menuIds, 'is_string') !== $menuIds) {
            $this->jsonResponse(false, 'El cuerpo de la solicitud debe ser un array de strings (menu_id).', null, 400);
        }

        try {
            $this->model->reordenar($menuIds);
            $this->jsonResponse(true, 'Menús reordenados correctamente.', ['reordered' => true]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al reordenar los menús: ' . $e->getMessage(), null, 500);
        }
    }
}