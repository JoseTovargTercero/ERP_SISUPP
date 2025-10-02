<?php
require_once __DIR__ . '/../models/ReporteDanoModel.php';

class ReporteDanoController
{
    private $model;

    public function __construct()
    {
        $this->model = new ReporteDanoModel();
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

    // GET /reportes_dano?limit=&offset=&incluirEliminados=0|1&finca_id=&aprisco_id=&area_id=&criticidad=&estado_reporte=
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        $fincaId   = $_GET['finca_id']   ?? null;
        $apriscoId = $_GET['aprisco_id'] ?? null;
        $areaId    = $_GET['area_id']    ?? null;
        $criticidad = $_GET['criticidad'] ?? null;        // BAJA|MEDIA|ALTA
        $estado     = $_GET['estado_reporte'] ?? null;    // ABIERTO|EN_PROCESO|CERRADO

        try {
            $data = $this->model->listar($limit, $offset, $incluir, $fincaId, $apriscoId, $areaId, $criticidad, $estado);
            $this->jsonResponse(true, 'Listado de reportes obtenido correctamente.', $data);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar reportes: '.$e->getMessage(), null, 500);
        }
    }

    // GET /reportes_dano/{reporte_id}
    public function mostrar(array $params): void
    {
        $reporteId = $params['reporte_id'] ?? '';
        if ($reporteId === '') {
            $this->jsonResponse(false, 'Parámetro reporte_id es obligatorio.', null, 400);
        }
        try {
            $row = $this->model->obtenerPorId($reporteId);
            if (!$row) $this->jsonResponse(false, 'Reporte no encontrado.', null, 404);
            $this->jsonResponse(true, 'Reporte encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener reporte: '.$e->getMessage(), null, 500);
        }
    }

    // POST /reportes_dano
    // JSON: { titulo, descripcion, criticidad?, estado_reporte?, finca_id?, aprisco_id?, area_id?, reportado_por? }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Reporte creado correctamente.', ['reporte_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear reporte: '.$e->getMessage(), null, 500);
        }
    }

    // PUT /reportes_dano/{reporte_id}
    // JSON: { finca_id?, aprisco_id?, area_id?, titulo?, descripcion?, criticidad?, estado_reporte?, solucionado_por?, fecha_cierre? }
    public function actualizar(array $params): void
    {
        $reporteId = $params['reporte_id'] ?? '';
        if ($reporteId === '') {
            $this->jsonResponse(false, 'Parámetro reporte_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($reporteId, $in);
            $this->jsonResponse(true, 'Reporte actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar reporte: '.$e->getMessage(), null, 500);
        }
    }

    // PUT /reportes_dano/{reporte_id}/estado
    // JSON: { estado_reporte: 'ABIERTO'|'EN_PROCESO'|'CERRADO', solucionado_por?, fecha_cierre? }
    public function actualizarEstado(array $params): void
    {
        $reporteId = $params['reporte_id'] ?? '';
        if ($reporteId === '') {
            $this->jsonResponse(false, 'Parámetro reporte_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        if (!isset($in['estado_reporte'])) {
            $this->jsonResponse(false, 'El campo estado_reporte es obligatorio.', null, 400);
        }

        $estado = (string)$in['estado_reporte'];
        $solPor = isset($in['solucionado_por']) ? (string)$in['solucionado_por'] : null;
        $fCierre = isset($in['fecha_cierre']) ? (string)$in['fecha_cierre'] : null;

        try {
            $ok = $this->model->actualizarEstado($reporteId, $estado, $solPor, $fCierre);
            $this->jsonResponse(true, 'Estado del reporte actualizado correctamente.', ['updated' => $ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar estado: '.$e->getMessage(), null, 500);
        }
    }

    // DELETE /reportes_dano/{reporte_id}
    public function eliminar(array $params): void
    {
        $reporteId = $params['reporte_id'] ?? '';
        if ($reporteId === '') {
            $this->jsonResponse(false, 'Parámetro reporte_id es obligatorio.', null, 400);
        }
        try {
            $ok = $this->model->eliminar($reporteId);
            if (!$ok) $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminado).', null, 400);
            $this->jsonResponse(true, 'Reporte eliminado correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar reporte: '.$e->getMessage(), null, 500);
        }
    }
}
