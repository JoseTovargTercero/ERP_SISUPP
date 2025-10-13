<?php
require_once __DIR__ . '/../models/MenuCategoriaModel.php';


class MenuCategoriaController
{
    private $model;

    public function __construct()
    {
        $this->model = new MenuCategoriaModel();
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

    /**
     * Maneja la petición GET /menus/categorias
     */
    public function listar(): void
    {
        try {
            $data = $this->model->listar();
            $this->jsonResponse(true, 'Listado de categorías obtenido correctamente.', $data);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar categorías: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Maneja la petición POST /menus/categorias/reordenar
     */
    public function reordenar(): void
    {
        $nombres = $this->getJsonInput();

        // Verificación básica del input
        if (empty($nombres) || !is_array($nombres) || array_filter($nombres, 'is_string') !== $nombres) {
            $this->jsonResponse(false, 'El cuerpo de la solicitud debe ser un array de strings (nombres de categoría).', null, 400);
        }

        try {
            $this->model->reordenar($nombres);
            $this->jsonResponse(true, 'Categorías reordenadas correctamente.', ['reordered' => true]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al reordenar las categorías: ' . $e->getMessage(), null, 500);
        }
    }
}