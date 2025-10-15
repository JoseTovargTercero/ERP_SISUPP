<?php
require_once __DIR__ . '/../models/RevisionesServicioModel.php';

class RevisionesServicioController
{
    private $model;

    public function __construct()
    {
        $this->model = new RevisionesServicioModel();
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

    // GET /revisiones-servicio?limit=&offset=&periodo_id=&resultado=
    public function listar(): void
    {
        $limit     = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset    = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $periodoId = isset($_GET['periodo_id']) ? trim((string)$_GET['periodo_id']) : null;
        $resultado = isset($_GET['resultado']) ? trim((string)$_GET['resultado']) : null;

        try {
            $data = $this->model->listar($limit, $offset, $periodoId, $resultado);
            $this->jsonResponse(true, 'Listado de revisiones obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar revisiones: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /revisiones-servicio/{revision_id}
    public function mostrar(array $params): void
    {
        $revisionId = $params['revision_id'] ?? '';
        if ($revisionId === '') {
            $this->jsonResponse(false, 'Parámetro revision_id es obligatorio.', null, 400);
        }
        try {
            $row = $this->model->obtenerPorId($revisionId);
            if (!$row) $this->jsonResponse(false, 'Revisión no encontrada.', null, 404);
            $this->jsonResponse(true, 'Revisión encontrada.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener revisión: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /revisiones-servicio/periodo/{periodo_id}
    public function listarPorPeriodo(array $params): void
    {
        $periodoId = $params['periodo_id'] ?? '';
        if ($periodoId === '') {
            $this->jsonResponse(false, 'Parámetro periodo_id es obligatorio.', null, 400);
        }
        try {
            $data = $this->model->listar(200, 0, $periodoId, null);
            $this->jsonResponse(true, 'Revisiones del período obtenidas correctamente.', $data);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar por período: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /revisiones-servicio
    // JSON: { periodo_id, fecha_programada, ciclo_control?, fecha_realizada?, resultado?, observaciones? }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Revisión creada correctamente.', ['revision_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear revisión: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /revisiones-servicio/{revision_id}
    // JSON: { fecha_programada?, fecha_realizada?, resultado?, observaciones? }
    public function actualizar(array $params): void
    {
        $revisionId = $params['revision_id'] ?? '';
        if ($revisionId === '') {
            $this->jsonResponse(false, 'Parámetro revision_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($revisionId, $in);
            $this->jsonResponse(true, 'Revisión actualizada correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar revisión: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /revisiones-servicio/{revision_id}
    public function eliminar(array $params): void
    {
        $revisionId = $params['revision_id'] ?? '';
        if ($revisionId === '') {
            $this->jsonResponse(false, 'Parámetro revision_id es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->eliminar($revisionId);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar.', null, 400);
            $this->jsonResponse(true, 'Revisión eliminada correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar revisión: ' . $e->getMessage(), null, 500);
        }
    }
}
