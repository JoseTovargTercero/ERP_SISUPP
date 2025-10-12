<?php
require_once __DIR__ . '/../models/AnimalMovimientoModel.php';

class AnimalMovimientoController
{
    private $model;
    public function __construct() { $this->model = new AnimalMovimientoModel(); }

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
        echo json_encode(['value'=>$value,'message'=>$message,'data'=>$data]); exit;
    }

    // GET /animal_movimientos?animal_id=&tipo_movimiento=&motivo=&estado=&desde=&hasta=
    //     &finca_origen_id=&aprisco_origen_id=&area_origen_id=&recinto_id_origen=
    //     &finca_destino_id=&aprisco_destino_id=&area_destino_id=&recinto_id_destino=
    //     &incluirEliminados=0|1&limit=&offset=
    public function listar(): void
    {
        // (opcional) limitar el rango de page size
        $limit  = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 500)) : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        $animalId = $_GET['animal_id'] ?? null;
        $tipo     = $_GET['tipo_movimiento'] ?? null;
        $motivo   = $_GET['motivo'] ?? null;
        $estado   = $_GET['estado'] ?? null;
        $desde    = $_GET['desde'] ?? null;
        $hasta    = $_GET['hasta'] ?? null;

        $fOri  = $_GET['finca_origen_id']    ?? null;
        $aOri  = $_GET['aprisco_origen_id']  ?? null;
        $arOri = $_GET['area_origen_id']     ?? null;
        $rOri  = $_GET['recinto_id_origen']  ?? null;

        $fDes  = $_GET['finca_destino_id']    ?? null;
        $aDes  = $_GET['aprisco_destino_id']  ?? null;
        $arDes = $_GET['area_destino_id']     ?? null;
        $rDes  = $_GET['recinto_id_destino']  ?? null;

        try {
            $rows = $this->model->listar(
                $limit, $offset, $incluir,
                $animalId, $tipo, $motivo, $estado, $desde, $hasta,
                $fOri, $aOri, $arOri, $rOri,
                $fDes, $aDes, $arDes, $rDes
            );
            $this->jsonResponse(true, 'Listado de movimientos obtenido correctamente.', $rows);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar movimientos: '.$e->getMessage(), null, 500);
        }
    }

    // GET /animal_movimientos/{animal_movimiento_id}
    public function mostrar(array $params): void
    {
        $id = $params['animal_movimiento_id'] ?? '';
        if ($id === '') $this->jsonResponse(false,'Parámetro animal_movimiento_id es obligatorio.',null,400);
        try {
            $row = $this->model->obtenerPorId($id);
            if (!$row) $this->jsonResponse(false, 'Movimiento no encontrado.', null, 404);
            $this->jsonResponse(true, 'Movimiento encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener movimiento: '.$e->getMessage(), null, 500);
        }
    }

    // POST /animal_movimientos
    // JSON: {
    //   animal_id, fecha_mov(YYYY-MM-DD), tipo_movimiento,
    //   motivo?, estado?,
    //   finca_origen_id?, aprisco_origen_id?, area_origen_id?, recinto_id_origen?,
    //   finca_destino_id?, aprisco_destino_id?, area_destino_id?, recinto_id_destino?,
    //   costo?, documento_ref?, observaciones?
    // }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Movimiento creado correctamente.', ['animal_movimiento_id'=>$uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear movimiento: '.$e->getMessage(), null, 500);
        }
    }

    // POST /animal_movimientos/{animal_movimiento_id}
    public function actualizar(array $params): void
    {
        $id = $params['animal_movimiento_id'] ?? '';
        if ($id === '') $this->jsonResponse(false,'Parámetro animal_movimiento_id es obligatorio.',null,400);
        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($id, $in);
            $this->jsonResponse(true, 'Movimiento actualizado correctamente.', ['updated'=>$ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar movimiento: '.$e->getMessage(), null, 500);
        }
    }

    // DELETE /animal_movimientos/{animal_movimiento_id}
    public function eliminar(array $params): void
    {
        $id = $params['animal_movimiento_id'] ?? '';
        if ($id === '') $this->jsonResponse(false,'Parámetro animal_movimiento_id es obligatorio.',null,400);
        try {
            $ok = $this->model->eliminar($id);
            if (!$ok) $this->jsonResponse(false,'No se pudo eliminar (o ya estaba eliminado).',null,400);
            $this->jsonResponse(true, 'Movimiento eliminado correctamente.', ['deleted'=>true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar movimiento: '.$e->getMessage(), null, 500);
        }
    }
}
