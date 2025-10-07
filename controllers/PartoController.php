<?php
require_once __DIR__ . '/../models/PartoModel.php';

class PartoController
{
    private $model;

    public function __construct()
    {
        $this->model = new PartoModel();
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

    // GET /partos?limit=&offset=&incluirEliminados=0|1&periodo_id=&estado_parto=&desde=&hasta=
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        $periodoId = $_GET['periodo_id'] ?? null;
        $estado    = $_GET['estado_parto'] ?? null;   // NORMAL|COMPLICADO|ABORTO
        $desde     = $_GET['desde'] ?? null;          // YYYY-mm-dd
        $hasta     = $_GET['hasta'] ?? null;          // YYYY-mm-dd

        try {
            $data = $this->model->listar($limit, $offset, $incluir, $periodoId, $estado, $desde, $hasta);
            $this->jsonResponse(true, 'Listado de partos obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar partos: '.$e->getMessage(), null, 500);
        }
    }

    // GET /partos/{parto_id}
    public function mostrar(array $params): void
    {
        $partoId = $params['parto_id'] ?? '';
        if ($partoId === '') {
            $this->jsonResponse(false, 'Parámetro parto_id es obligatorio.', null, 400);
        }
        try {
            $row = $this->model->obtenerPorId($partoId);
            if (!$row) $this->jsonResponse(false, 'Parto no encontrado.', null, 404);
            $this->jsonResponse(true, 'Parto encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener parto: '.$e->getMessage(), null, 500);
        }
    }

    // POST /partos
    // JSON: { periodo_id, fecha_parto, crias_machos?, crias_hembras?, peso_promedio_kg?, estado_parto?, observaciones? }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Parto creado correctamente.', ['parto_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear parto: '.$e->getMessage(), null, 500);
        }
    }

    // POST /partos/{parto_id}
    // JSON: { periodo_id?, fecha_parto?, crias_machos?, crias_hembras?, peso_promedio_kg?, estado_parto?, observaciones? }
    public function actualizar(array $params): void
    {
        $partoId = $params['parto_id'] ?? '';
        if ($partoId === '') {
            $this->jsonResponse(false, 'Parámetro parto_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($partoId, $in);
            $this->jsonResponse(true, 'Parto actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar parto: '.$e->getMessage(), null, 500);
        }
    }

    // POST /partos/{parto_id}/estado
    // JSON: { estado_parto: 'NORMAL'|'COMPLICADO'|'ABORTO' }
    public function actualizarEstado(array $params): void
    {
        $partoId = $params['parto_id'] ?? '';
        if ($partoId === '') {
            $this->jsonResponse(false, 'Parámetro parto_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        if (!isset($in['estado_parto'])) {
            $this->jsonResponse(false, 'El campo estado_parto es obligatorio.', null, 400);
        }

        $estado = (string)$in['estado_parto'];

        try {
            $ok = $this->model->actualizarEstado($partoId, $estado);
            $this->jsonResponse(true, 'Estado del parto actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar estado: '.$e->getMessage(), null, 500);
        }
    }

    // DELETE /partos/{parto_id}
    public function eliminar(array $params): void
    {
        $partoId = $params['parto_id'] ?? '';
        if ($partoId === '') {
            $this->jsonResponse(false, 'Parámetro parto_id es obligatorio.', null, 400);
        }
        try {
            $ok = $this->model->eliminar($partoId);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminado).', null, 400);
            $this->jsonResponse(true, 'Parto eliminado correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar parto: '.$e->getMessage(), null, 500);
        }
    }
}
