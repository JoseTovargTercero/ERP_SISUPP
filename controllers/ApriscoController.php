<?php
require_once __DIR__ . '/../models/ApriscoModel.php';

class ApriscoController
{
    private $model;

    public function __construct()
    {
        $this->model = new ApriscoModel();
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

    // GET /apriscos?limit=&offset=&incluirEliminados=0|1&finca_id=UUID
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;
        $fincaId = isset($_GET['finca_id']) ? trim((string)$_GET['finca_id']) : null;

        try {
            $data = $this->model->listar($limit, $offset, $incluir, $fincaId);
            $this->jsonResponse(true, 'Listado de apriscos obtenido correctamente.', $data);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar apriscos: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /apriscos/{aprisco_id}
    public function mostrar(array $params): void
    {
        $apriscoId = $params['aprisco_id'] ?? '';
        if ($apriscoId === '') {
            $this->jsonResponse(false, 'Parámetro aprisco_id es obligatorio.', null, 400);
        }

        try {
            $row = $this->model->obtenerPorId($apriscoId);
            if (!$row) $this->jsonResponse(false, 'Aprisco no encontrado.', null, 404);
            $this->jsonResponse(true, 'Aprisco encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener aprisco: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /apriscos
    // JSON: { finca_id, nombre, estado?('ACTIVO'|'INACTIVO') }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Aprisco creado correctamente.', ['aprisco_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear aprisco: ' . $e->getMessage(), null, 500);
        }
    }

    // PUT /apriscos/{aprisco_id}
    // JSON: { finca_id?, nombre?, estado?('ACTIVO'|'INACTIVO') }
    public function actualizar(array $params): void
    {
        $apriscoId = $params['aprisco_id'] ?? '';
        if ($apriscoId === '') {
            $this->jsonResponse(false, 'Parámetro aprisco_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($apriscoId, $in);
            $this->jsonResponse(true, 'Aprisco actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar aprisco: ' . $e->getMessage(), null, 500);
        }
    }

    // PUT /apriscos/{aprisco_id}/estado
    // JSON: { estado: 'ACTIVO'|'INACTIVO' }
    public function actualizarEstado(array $params): void
    {
        $apriscoId = $params['aprisco_id'] ?? '';
        if ($apriscoId === '') {
            $this->jsonResponse(false, 'Parámetro aprisco_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        if (!isset($in['estado'])) {
            $this->jsonResponse(false, 'El campo estado es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->actualizarEstado($apriscoId, (string)$in['estado']);
            $this->jsonResponse(true, 'Estado del aprisco actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar estado: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /apriscos/{aprisco_id}
    public function eliminar(array $params): void
    {
        $apriscoId = $params['aprisco_id'] ?? '';
        if ($apriscoId === '') {
            $this->jsonResponse(false, 'Parámetro aprisco_id es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->eliminar($apriscoId);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminado).', null, 400);
            $this->jsonResponse(true, 'Aprisco eliminado correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar aprisco: ' . $e->getMessage(), null, 500);
        }
    }
}
