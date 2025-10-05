<?php
require_once __DIR__ . '/../models/AnimalPesoModel.php';

class AnimalPesoController
{
    private $model;

    public function __construct()
    {
        $this->model = new AnimalPesoModel();
    }

    private function getJsonInput(): array
    {
        $raw  = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    private function jsonResponse($value, string $message = '', $data = null, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'value'   => $value,
            'message' => $message,
            'data'    => $data
        ]);
        exit;
    }

    // GET /animal_pesos?animal_id=&desde=YYYY-MM-DD&hasta=YYYY-MM-DD&incluirEliminados=0|1&limit=&offset=
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        $animalId = isset($_GET['animal_id']) ? trim((string)$_GET['animal_id']) : null;
        $desde    = isset($_GET['desde']) ? trim((string)$_GET['desde']) : null;
        $hasta    = isset($_GET['hasta']) ? trim((string)$_GET['hasta']) : null;

        try {
            $data = $this->model->listar($limit, $offset, $incluir, $animalId, $desde, $hasta);
            $this->jsonResponse(true, 'Listado de pesos obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar pesos: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /animal_pesos/{animal_peso_id}
    public function mostrar(array $params): void
    {
        $id = $params['animal_peso_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro animal_peso_id es obligatorio.', null, 400);
        }

        try {
            $row = $this->model->obtenerPorId($id);
            if (!$row) $this->jsonResponse(false, 'Registro de peso no encontrado.', null, 404);
            $this->jsonResponse(true, 'Registro de peso encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener registro de peso: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /animal_pesos
    // JSON: { animal_id, fecha_peso(YYYY-MM-DD), peso, unidad('KG'|'LB'), metodo?, observaciones? }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Registro de peso creado correctamente.', ['animal_peso_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear registro de peso: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /animal_pesos/{animal_peso_id}
    // JSON: { fecha_peso?, peso?, unidad? (si envías peso, debes enviar unidad), metodo?, observaciones? }
    public function actualizar(array $params): void
    {
        $id = $params['animal_peso_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro animal_peso_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($id, $in);
            $this->jsonResponse(true, 'Registro de peso actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar registro de peso: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /animal_pesos/{animal_peso_id}
    public function eliminar(array $params): void
    {
        $id = $params['animal_peso_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro animal_peso_id es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->eliminar($id);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminado).', null, 400);
            $this->jsonResponse(true, 'Registro de peso eliminado correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar registro de peso: ' . $e->getMessage(), null, 500);
        }
    }
}
