<?php
require_once __DIR__ . '/../models/MontaModel.php';

class MontaController
{
    private $model;

    public function __construct()
    {
        $this->model = new MontaModel();
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

    // GET /montas?limit=&offset=&incluirEliminados=0|1&periodo_id=&numero_monta=&desde=&hasta=
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        $periodoId   = $_GET['periodo_id']   ?? null;
        $numeroMonta = isset($_GET['numero_monta']) && $_GET['numero_monta'] !== '' ? (int)$_GET['numero_monta'] : null;
        $desde       = $_GET['desde'] ?? null; // YYYY-mm-dd
        $hasta       = $_GET['hasta'] ?? null; // YYYY-mm-dd

        try {
            $data = $this->model->listar($limit, $offset, $incluir, $periodoId, $numeroMonta, $desde, $hasta);
            $this->jsonResponse(true, 'Listado de montas obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar montas: '.$e->getMessage(), null, 500);
        }
    }

    // GET /montas/{monta_id}
    public function mostrar(array $params): void
    {
        $montaId = $params['monta_id'] ?? '';
        if ($montaId === '') {
            $this->jsonResponse(false, 'Parámetro monta_id es obligatorio.', null, 400);
        }
        try {
            $row = $this->model->obtenerPorId($montaId);
            if (!$row) $this->jsonResponse(false, 'Monta no encontrada.', null, 404);
            $this->jsonResponse(true, 'Monta encontrada.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener monta: '.$e->getMessage(), null, 500);
        }
    }

    // POST /montas
    // JSON: { periodo_id, numero_monta, fecha_monta }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Monta creada correctamente.', ['monta_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear monta: '.$e->getMessage(), null, 500);
        }
    }

    // POST /montas/{monta_id}
    // JSON: { periodo_id?, numero_monta?, fecha_monta? }
    public function actualizar(array $params): void
    {
        $montaId = $params['monta_id'] ?? '';
        if ($montaId === '') {
            $this->jsonResponse(false, 'Parámetro monta_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($montaId, $in);
            $this->jsonResponse(true, 'Monta actualizada correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar monta: '.$e->getMessage(), null, 500);
        }
    }

    // DELETE /montas/{monta_id}
    public function eliminar(array $params): void
    {
        $montaId = $params['monta_id'] ?? '';
        if ($montaId === '') {
            $this->jsonResponse(false, 'Parámetro monta_id es obligatorio.', null, 400);
        }
        try {
            $ok = $this->model->eliminar($montaId);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminada).', null, 400);
            $this->jsonResponse(true, 'Monta eliminada correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar monta: '.$e->getMessage(), null, 500);
        }
    }
}
