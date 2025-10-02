<?php
require_once __DIR__ . '/../models/FincaModel.php';

class FincaController
{
    private $model;

    public function __construct()
    {
        $this->model = new FincaModel();
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

    /* ============ Endpoints (GET/POST/DELETE) ============ */

    // GET /fincas?limit=&offset=&incluirEliminados=0|1
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        try {
            $data = $this->model->listar($limit, $offset, $incluir);
            $this->jsonResponse(true, 'Listado de fincas obtenido correctamente.', $data);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar fincas: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /fincas/show?finca_id=UUID
    public function mostrar($parametros): void
    {
        $fincaId = $parametros['finca_id'] ?? '';
        if ($fincaId === '') {
            $this->jsonResponse(false, 'Parámetro finca_id es obligatorio.', null, 400);
        }

        try {
            $row = $this->model->obtenerPorId($fincaId);
            if (!$row) $this->jsonResponse(false, 'Finca no encontrada.', null, 404);
            $this->jsonResponse(true, 'Finca encontrada.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener finca: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /fincas/create
    // JSON: { nombre, ubicacion?, estado?('ACTIVA'|'INACTIVA') }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Finca creada correctamente.', ['finca_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear finca: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /fincas/update?finca_id=UUID
    // JSON (cualquiera de): { nombre?, ubicacion?, estado?('ACTIVA'|'INACTIVA') }
    public function actualizar($parametros): void
    {
        $fincaId = $parametros['finca_id'] ?? '';
        if ($fincaId === '') {
            $this->jsonResponse(false, 'Parámetro finca_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($fincaId, $in);
            $this->jsonResponse(true, 'Finca actualizada correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar finca: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /fincas/status?finca_id=UUID
    // JSON: { estado: 'ACTIVA'|'INACTIVA' }
    public function actualizarEstado($parametros): void
    {
        $fincaId = $parametros['finca_id'] ?? '';
        if ($fincaId === '') {
            $this->jsonResponse(false, 'Parámetro finca_id es obligatorio.', null, 400);
        }
        $in = $this->getJsonInput();
        if (!isset($in['estado'])) {
            $this->jsonResponse(false, 'El campo estado es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->actualizarEstado($fincaId, (string)$in['estado']);
            $this->jsonResponse(true, 'Estado de la finca actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar estado: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /fincas/delete?finca_id=UUID
    public function eliminar($parametros): void
    {
        $fincaId = $parametros['finca_id'] ?? '';
        if ($fincaId === '') {
            $this->jsonResponse(false, 'Parámetro finca_id es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->eliminar($fincaId);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminada).', null, 400);
            $this->jsonResponse(true, 'Finca eliminada correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar finca: ' . $e->getMessage(), null, 500);
        }
    }
}
