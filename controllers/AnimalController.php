<?php
require_once __DIR__ . '/../models/AnimalModel.php';

class AnimalController
{
    private $model;
    public function __construct() { $this->model = new AnimalModel(); }

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

    // GET /animales?limit=&offset=&incluirEliminados=0|1&q=&sexo=&especie=&estado=&etapa=&categoria=&nacDesde=&nacHasta=&finca_id=&aprisco_id=&area_id=
    public function listar(): void
    {
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        $q        = $_GET['q']         ?? null;
        $sexo     = $_GET['sexo']      ?? null;
        $especie  = $_GET['especie']   ?? null;
        $estado   = $_GET['estado']    ?? null;
        $etapa    = $_GET['etapa']     ?? null;
        $categoria= $_GET['categoria'] ?? null;
        $nacDesde = $_GET['nacDesde']  ?? null;
        $nacHasta = $_GET['nacHasta']  ?? null;

        $fincaId   = $_GET['finca_id']   ?? null;
        $apriscoId = $_GET['aprisco_id'] ?? null;
        $areaId    = $_GET['area_id']    ?? null;

        try {
            $rows = $this->model->listar($limit,$offset,$incluir,$q,$sexo,$especie,$estado,$etapa,$categoria,$nacDesde,$nacHasta,$fincaId,$apriscoId,$areaId);
            $this->jsonResponse(true, 'Listado de animales obtenido correctamente.', $rows);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar animales: '.$e->getMessage(), null, 500);
        }
    }

    // GET /animales/{animal_id}
    public function mostrar(array $params): void
    {
        $id = $params['animal_id'] ?? '';
        if ($id === '') $this->jsonResponse(false,'Parámetro animal_id es obligatorio.',null,400);

        try {
            $row = $this->model->obtenerPorId($id);
            if (!$row) $this->jsonResponse(false, 'Animal no encontrado.', null, 404);
            $this->jsonResponse(true, 'Animal encontrado.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener animal: '.$e->getMessage(), null, 500);
        }
    }

    // GET /animales/options?q=
    public function options(): void
    {
        $q = $_GET['q'] ?? null;
        try {
            $rows = $this->model->getOptions($q);
            $this->jsonResponse(true, '', ['data'=>$rows]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener opciones: '.$e->getMessage(), null, 500);
        }
    }

    // POST /animales
    // JSON: { identificador, sexo, especie, raza?, color?, fecha_nacimiento?, estado?, etapa_productiva?, categoria?, origen?, madre_id?, padre_id? }
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Animal creado correctamente.', ['animal_id'=>$uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear animal: '.$e->getMessage(), null, 500);
        }
    }

    // POST /animales/{animal_id}
    public function actualizar(array $params): void
    {
        $id = $params['animal_id'] ?? '';
        if ($id === '') $this->jsonResponse(false,'Parámetro animal_id es obligatorio.',null,400);

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($id, $in);
            $this->jsonResponse(true, 'Animal actualizado correctamente.', ['updated'=>$ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar animal: '.$e->getMessage(), null, 500);
        }
    }

    // DELETE /animales/{animal_id}
    public function eliminar(array $params): void
    {
        $id = $params['animal_id'] ?? '';
        if ($id === '') $this->jsonResponse(false,'Parámetro animal_id es obligatorio.',null,400);
        try {
            $ok = $this->model->eliminar($id);
            if (!$ok) $this->jsonResponse(false,'No se pudo eliminar (o ya estaba eliminado).',null,400);
            $this->jsonResponse(true, 'Animal eliminado correctamente.', ['deleted'=>true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar animal: '.$e->getMessage(), null, 500);
        }
    }
}
