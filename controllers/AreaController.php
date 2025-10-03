<?php
require_once __DIR__ . '/../models/AreaModel.php';

class AreaController
{
    private $model;

    public function __construct()
    {
        $this->model = new AreaModel();
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

    // GET /areas?limit=&offset=&incluirEliminados=0|1&aprisco_id=UUID&tipo_area=...
    public function listar(): void
    {
        $limit      = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset     = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir    = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;
        $apriscoId  = isset($_GET['aprisco_id']) ? trim((string)$_GET['aprisco_id']) : null;
        $tipoArea   = isset($_GET['tipo_area']) ? trim((string)$_GET['tipo_area']) : null;

        try {
            $data = $this->model->listar($limit, $offset, $incluir, $apriscoId, $tipoArea);
            $this->jsonResponse(true, 'Listado de áreas obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar áreas: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /areas/{area_id}
    public function mostrar(array $params): void
    {
        $areaId = $params['area_id'] ?? '';
        if ($areaId === '') {
            $this->jsonResponse(false, 'Parámetro area_id es obligatorio.', null, 400);
        }

        try {
            $row = $this->model->obtenerPorId($areaId);
            if (!$row) $this->jsonResponse(false, 'Área no encontrada.', null, 404);
            $this->jsonResponse(true, 'Área encontrada.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener área: ' . $e->getMessage(), null, 500);
        }
    }
public function options()
{
    try {
        $apriscoId = $_GET['aprisco_id'] ?? null;
        $data = $this->model->getOptions($apriscoId);
        $this->jsonResponse(true, '', ['data' => $data]);
    } catch (Exception $e) {
        $this->errorResponse(500, $e->getMessage());
    }
}

    // POST /areas
    // JSON: { aprisco_id, tipo_area, nombre_personalizado?, numeracion?, estado?('ACTIVA'|'INACTIVA') }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Área creada correctamente.', ['area_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear área: ' . $e->getMessage(), null, 500);
        }
    }

    // PUT /areas/{area_id}
    // JSON: { aprisco_id?, tipo_area?, nombre_personalizado?, numeracion?, estado?('ACTIVA'|'INACTIVA') }
    public function actualizar(array $params): void
    {
        $areaId = $params['area_id'] ?? '';
        if ($areaId === '') {
            $this->jsonResponse(false, 'Parámetro area_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($areaId, $in);
            $this->jsonResponse(true, 'Área actualizada correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar área: ' . $e->getMessage(), null, 500);
        }
    }

    // PUT /areas/{area_id}/estado
    // JSON: { estado: 'ACTIVA'|'INACTIVA' }
    public function actualizarEstado(array $params): void
    {
        $areaId = $params['area_id'] ?? '';
        if ($areaId === '') {
            $this->jsonResponse(false, 'Parámetro area_id es obligatorio.', null, 400);
        }
        $in = $this->getJsonInput();
        if (!isset($in['estado'])) {
            $this->jsonResponse(false, 'El campo estado es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->actualizarEstado($areaId, (string)$in['estado']);
            $this->jsonResponse(true, 'Estado del área actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar estado: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /areas/{area_id}
    public function eliminar(array $params): void
    {
        $areaId = $params['area_id'] ?? '';
        if ($areaId === '') {
            $this->jsonResponse(false, 'Parámetro area_id es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->eliminar($areaId);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminada).', null, 400);
            $this->jsonResponse(true, 'Área eliminada correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar área: ' . $e->getMessage(), null, 500);
        }
    }
}
