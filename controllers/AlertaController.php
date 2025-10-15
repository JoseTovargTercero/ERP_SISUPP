<?php
require_once __DIR__ . '/../models/AlertaModel.php';

class AlertaController
{
    private $model;

    public function __construct()
    {
        $this->model = new AlertaModel();
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

    // GET /alertas?limit=&offset=&periodo_id=&animal_id=&tipo_alerta=&estado_alerta=&desde=&hasta=&incluirEliminados=0|1
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $periodoId = isset($_GET['periodo_id']) ? trim((string)$_GET['periodo_id']) : null;
        $animalId  = isset($_GET['animal_id'])  ? trim((string)$_GET['animal_id'])  : null;
        $tipo      = isset($_GET['tipo_alerta'])   ? trim((string)$_GET['tipo_alerta'])   : null;
        $estado    = isset($_GET['estado_alerta']) ? trim((string)$_GET['estado_alerta']) : null;
        $desde     = isset($_GET['desde']) ? trim((string)$_GET['desde']) : null; // YYYY-MM-DD
        $hasta     = isset($_GET['hasta']) ? trim((string)$_GET['hasta']) : null; // YYYY-MM-DD

        $incluirEliminados = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        try {
            $data = $this->model->listar(
                $limit, $offset,
                $periodoId, $animalId,
                $tipo, $estado,
                $desde, $hasta,
                $incluirEliminados
            );
            $this->jsonResponse(true, 'Listado de alertas obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar alertas: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /alertas/{alerta_id}
    public function mostrar(array $params): void
    {
        $alertaId = $params['alerta_id'] ?? '';
        if ($alertaId === '') {
            $this->jsonResponse(false, 'Parámetro alerta_id es obligatorio.', null, 400);
        }

        try {
            $row = $this->model->obtenerPorId($alertaId);
            if (!$row) $this->jsonResponse(false, 'Alerta no encontrada.', null, 404);
            $this->jsonResponse(true, 'Alerta encontrada.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener alerta: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /alertas
    // JSON: { tipo_alerta, fecha_objetivo, periodo_id?, animal_id?, estado_alerta?, detalle? }
    public function crear(): void
    {
        $in = $this->getJsonInput();

        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Alerta creada correctamente.', ['alerta_id' => $uuid], 201);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear alerta: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /alertas/{alerta_id}
    // JSON: { tipo_alerta?, periodo_id?, animal_id?, fecha_objetivo?, estado_alerta?, detalle? }
    public function actualizar(array $params): void
    {
        $alertaId = $params['alerta_id'] ?? '';
        if ($alertaId === '') {
            $this->jsonResponse(false, 'Parámetro alerta_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($alertaId, $in);
            $this->jsonResponse(true, 'Alerta actualizada correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar alerta: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /alertas/{alerta_id}/estado
    // JSON: { estado_alerta: 'PENDIENTE'|'CUMPLIDA'|'VENCIDA'|'CANCELADA' }
    public function cambiarEstado(array $params): void
    {
        $alertaId = $params['alerta_id'] ?? '';
        if ($alertaId === '') {
            $this->jsonResponse(false, 'Parámetro alerta_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        if (!isset($in['estado_alerta'])) {
            $this->jsonResponse(false, 'El campo estado_alerta es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->cambiarEstado($alertaId, (string)$in['estado_alerta']);
            $this->jsonResponse(true, 'Estado de la alerta actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al cambiar estado: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /alertas/{alerta_id}
    public function eliminar(array $params): void
    {
        $alertaId = $params['alerta_id'] ?? '';
        if ($alertaId === '') {
            $this->jsonResponse(false, 'Parámetro alerta_id es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->eliminar($alertaId);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminada).', null, 400);
            $this->jsonResponse(true, 'Alerta eliminada correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar alerta: ' . $e->getMessage(), null, 500);
        }
    }
}
