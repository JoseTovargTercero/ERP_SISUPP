<?php
require_once __DIR__ . '/../models/RecintoModel.php';

class RecintoController
{
    private $model;

    public function __construct()
    {
        $this->model = new RecintoModel();
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

    // GET /recintos?limit=&offset=&incluirEliminados=0|1&area_id=&estado=&codigo=
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        $areaId  = $_GET['area_id'] ?? null;
        $estado  = $_GET['estado'] ?? null;          // ACTIVO|INACTIVO
        $codigo  = $_GET['codigo'] ?? null;          // ej: rec_01

        try {
            $data = $this->model->listar($limit, $offset, $incluir, $areaId, $estado, $codigo);
            $this->jsonResponse(true, 'Listado de recintos obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar recintos: '.$e->getMessage(), null, 500);
        }
    }

    // GET /recintos/{recinto_id}
    public function mostrar(array $params): void
    {
        $recintoId = $params['recinto_id'] ?? '';
        if ($recintoId === '') {
            $this->jsonResponse(false, 'Parámetro recinto_id es obligatorio.', null, 400);
        }
        try {
            $row = $this->model->obtenerPorId($recintoId);
            if (!$row) $this->jsonResponse(false, 'Recinto no encontrado.', null, 404);
            $this->jsonResponse(true, 'Recinto encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener recinto: '.$e->getMessage(), null, 500);
        }
    }

    // POST /recintos
    // JSON: { area_id, capacidad?, estado?, observaciones? }
    // codigo_recinto se genera automáticamente por área (rec_01, rec_02, ...)
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Recinto creado correctamente.', ['recinto_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear recinto: '.$e->getMessage(), null, 500);
        }
    }

    // POST /recintos/{recinto_id}
    // JSON: { capacidad?, estado?, observaciones? }
    public function actualizar(array $params): void
    {
        $recintoId = $params['recinto_id'] ?? '';
        if ($recintoId === '') {
            $this->jsonResponse(false, 'Parámetro recinto_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($recintoId, $in);
            $this->jsonResponse(true, 'Recinto actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar recinto: '.$e->getMessage(), null, 500);
        }
    }

    // POST /recintos/{recinto_id}/estado
    // JSON: { estado: 'ACTIVO'|'INACTIVO' }
    public function actualizarEstado(array $params): void
    {
        $recintoId = $params['recinto_id'] ?? '';
        if ($recintoId === '') {
            $this->jsonResponse(false, 'Parámetro recinto_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        if (!isset($in['estado'])) {
            $this->jsonResponse(false, 'El campo estado es obligatorio.', null, 400);
        }

        $estado = (string)$in['estado'];

        try {
            $ok = $this->model->actualizarEstado($recintoId, $estado);
            $this->jsonResponse(true, 'Estado del recinto actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar estado: '.$e->getMessage(), null, 500);
        }
    }

    // DELETE /recintos/{recinto_id}
    public function eliminar(array $params): void
    {
        $recintoId = $params['recinto_id'] ?? '';
        if ($recintoId === '') {
            $this->jsonResponse(false, 'Parámetro recinto_id es obligatorio.', null, 400);
        }
        try {
            $ok = $this->model->eliminar($recintoId);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminado).', null, 400);
            $this->jsonResponse(true, 'Recinto eliminado correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar recinto: '.$e->getMessage(), null, 500);
        }
    }
}
