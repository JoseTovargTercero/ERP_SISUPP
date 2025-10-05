<?php
require_once __DIR__ . '/../models/AnimalSaludModel.php';

class AnimalSaludController
{
    private $model;

    public function __construct()
    {
        $this->model = new AnimalSaludModel();
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

    // GET /animal_salud?animal_id=&tipo_evento=&severidad=&estado=&desde=&hasta=&q=&incluirEliminados=0|1&limit=&offset=
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        $animalId   = isset($_GET['animal_id']) ? trim((string)$_GET['animal_id']) : null;
        $tipoEvento = isset($_GET['tipo_evento']) ? trim((string)$_GET['tipo_evento']) : null;
        $severidad  = isset($_GET['severidad']) ? trim((string)$_GET['severidad']) : null;
        $estado     = isset($_GET['estado']) ? trim((string)$_GET['estado']) : null;
        $desde      = isset($_GET['desde']) ? trim((string)$_GET['desde']) : null;
        $hasta      = isset($_GET['hasta']) ? trim((string)$_GET['hasta']) : null;
        $q          = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

        try {
            $data = $this->model->listar($limit, $offset, $incluir, $animalId, $tipoEvento, $severidad, $estado, $desde, $hasta, $q);
            $this->jsonResponse(true, 'Listado de salud obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar salud: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /animal_salud/{animal_salud_id}
    public function mostrar(array $params): void
    {
        $id = $params['animal_salud_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro animal_salud_id es obligatorio.', null, 400);
        }

        try {
            $row = $this->model->obtenerPorId($id);
            if (!$row) $this->jsonResponse(false, 'Evento de salud no encontrado.', null, 404);
            $this->jsonResponse(true, 'Evento de salud encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener evento de salud: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /animal_salud
    // JSON: { animal_id, fecha_evento, tipo_evento, diagnostico?, severidad?, tratamiento?, medicamento?, dosis?, via_administracion?, costo?, estado?, proxima_revision?, responsable?, observaciones? }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Evento de salud creado correctamente.', ['animal_salud_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear evento de salud: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /animal_salud/{animal_salud_id}
    // JSON: { fecha_evento?, tipo_evento?, diagnostico?, severidad?, tratamiento?, medicamento?, dosis?, via_administracion?, costo?, estado?, proxima_revision?, responsable?, observaciones? }
    public function actualizar(array $params): void
    {
        $id = $params['animal_salud_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro animal_salud_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($id, $in);
            $this->jsonResponse(true, 'Evento de salud actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar evento de salud: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /animal_salud/{animal_salud_id}
    public function eliminar(array $params): void
    {
        $id = $params['animal_salud_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro animal_salud_id es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->eliminar($id);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminado).', null, 400);
            $this->jsonResponse(true, 'Evento de salud eliminado correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar evento de salud: ' . $e->getMessage(), null, 500);
        }
    }
}
