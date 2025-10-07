<?php
require_once __DIR__ . '/../models/PeriodoServicioModel.php';

class PeriodoServicioController
{
    private $model;

    public function __construct()
    {
        $this->model = new PeriodoServicioModel();
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
        echo json_encode(['value'=>$value,'message'=>$message,'data'=>$data]);
        exit;
    }

    // GET /periodos_servicio?limit=&offset=&incluirEliminados=0|1&hembra_id=&verraco_id=&estado_periodo=&desde=&hasta=
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        $hembraId = $_GET['hembra_id'] ?? null;
        $verracoId = $_GET['verraco_id'] ?? null;
        $estado = $_GET['estado_periodo'] ?? null;      // ABIERTO|CERRADO
        $desde  = $_GET['desde'] ?? null;               // YYYY-mm-dd
        $hasta  = $_GET['hasta'] ?? null;               // YYYY-mm-dd

        try {
            $data = $this->model->listar($limit, $offset, $incluir, $hembraId, $verracoId, $estado, $desde, $hasta);
            $this->jsonResponse(true, 'Listado de periodos de servicio obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar periodos: '.$e->getMessage(), null, 500);
        }
    }

    // GET /periodos_servicio/{periodo_id}
    public function mostrar(array $params): void
    {
        $periodoId = $params['periodo_id'] ?? '';
        if ($periodoId === '') {
            $this->jsonResponse(false, 'Parámetro periodo_id es obligatorio.', null, 400);
        }
        try {
            $row = $this->model->obtenerPorId($periodoId);
            if (!$row) $this->jsonResponse(false, 'Periodo no encontrado.', null, 404);
            $this->jsonResponse(true, 'Periodo encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener periodo: '.$e->getMessage(), null, 500);
        }
    }

    // POST /periodos_servicio
    // JSON: { hembra_id, verraco_id, fecha_inicio, observaciones?, estado_periodo? }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Periodo de servicio creado correctamente.', ['periodo_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear periodo: '.$e->getMessage(), null, 500);
        }
    }

    // POST /periodos_servicio/{periodo_id}
    // JSON: { hembra_id?, verraco_id?, fecha_inicio?, observaciones?, estado_periodo? }
    public function actualizar(array $params): void
    {
        $periodoId = $params['periodo_id'] ?? '';
        if ($periodoId === '') {
            $this->jsonResponse(false, 'Parámetro periodo_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($periodoId, $in);
            $this->jsonResponse(true, 'Periodo actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar periodo: '.$e->getMessage(), null, 500);
        }
    }

    // POST /periodos_servicio/{periodo_id}/estado
    // JSON: { estado_periodo: 'ABIERTO'|'CERRADO' }
    public function actualizarEstado(array $params): void
    {
        $periodoId = $params['periodo_id'] ?? '';
        if ($periodoId === '') {
            $this->jsonResponse(false, 'Parámetro periodo_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        if (!isset($in['estado_periodo'])) {
            $this->jsonResponse(false, 'El campo estado_periodo es obligatorio.', null, 400);
        }

        $estado = (string)$in['estado_periodo'];

        try {
            $ok = $this->model->actualizarEstado($periodoId, $estado);
            $this->jsonResponse(true, 'Estado del periodo actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar estado: '.$e->getMessage(), null, 500);
        }
    }

    // DELETE /periodos_servicio/{periodo_id}
    public function eliminar(array $params): void
    {
        $periodoId = $params['periodo_id'] ?? '';
        if ($periodoId === '') {
            $this->jsonResponse(false, 'Parámetro periodo_id es obligatorio.', null, 400);
        }
        try {
            $ok = $this->model->eliminar($periodoId);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminado).', null, 400);
            $this->jsonResponse(true, 'Periodo eliminado correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar periodo: '.$e->getMessage(), null, 500);
        }
    }
}
