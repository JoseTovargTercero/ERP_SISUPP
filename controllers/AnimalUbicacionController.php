<?php
require_once __DIR__ . '/../models/AnimalUbicacionModel.php';

class AnimalUbicacionController
{
    private $model;

    public function __construct()
    {
        $this->model = new AnimalUbicacionModel();
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
        echo json_encode([
            'value'   => $value,
            'message' => $message,
            'data'    => $data
        ]);
        exit;
    }

    // GET /animal_ubicaciones?animal_id=&finca_id=&aprisco_id=&area_id=&recinto_id=&desde=&hasta=&soloActivas=0|1&incluirEliminados=0|1&limit=&offset=
    public function listar(): void
    {
        // Límite “clamp” 1..500
        $limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $limit   = max(1, min($limit, 500));
        $offset  = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $incluir = isset($_GET['incluirEliminados']) ? ((int)$_GET['incluirEliminados'] === 1) : false;

        $animalId    = isset($_GET['animal_id']) ? trim((string)$_GET['animal_id']) : null;
        $fincaId     = isset($_GET['finca_id']) ? trim((string)$_GET['finca_id']) : null;
        $apriscoId   = isset($_GET['aprisco_id']) ? trim((string)$_GET['aprisco_id']) : null;
        $areaId      = isset($_GET['area_id']) ? trim((string)$_GET['area_id']) : null;
        $recintoId   = isset($_GET['recinto_id']) ? trim((string)$_GET['recinto_id']) : null;
        $desde       = isset($_GET['desde']) ? trim((string)$_GET['desde']) : null;
        $hasta       = isset($_GET['hasta']) ? trim((string)$_GET['hasta']) : null;
        $soloActivas = isset($_GET['soloActivas']) ? ((int)$_GET['soloActivas'] === 1) : false;

        try {
            $rows = $this->model->listar(
                $limit,
                $offset,
                $incluir,
                $animalId,
                $fincaId,
                $apriscoId,
                $areaId,
                $recintoId,
                $desde,
                $hasta,
                $soloActivas
            );
            $this->jsonResponse(true, 'Listado de ubicaciones obtenido correctamente.', $rows);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al listar ubicaciones: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /animal_ubicaciones/{animal_ubicacion_id}
    public function mostrar(array $params): void
    {
        $id = $params['animal_ubicacion_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro animal_ubicacion_id es obligatorio.', null, 400);
        }

        try {
            $row = $this->model->obtenerPorId($id);
            if (!$row) {
                $this->jsonResponse(false, 'Ubicación no encontrada.', null, 404);
            }
            $this->jsonResponse(true, 'Ubicación encontrada.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener ubicación: ' . $e->getMessage(), null, 500);
        }
    }

    // GET /animal_ubicaciones/actual/{animal_id}
    public function actual(array $params): void
    {
        $animalId = $params['animal_id'] ?? '';
        if ($animalId === '') {
            $this->jsonResponse(false, 'Parámetro animal_id es obligatorio.', null, 400);
        }

        try {
            $row = $this->model->getActual($animalId);
            if (!$row) {
                $this->jsonResponse(false, 'No hay ubicación activa.', null, 404);
            }
            $this->jsonResponse(true, 'Ubicación actual encontrada.', $row);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al obtener ubicación actual: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /animal_ubicaciones
    public function crear(): void
    {
        $in = $this->getJsonInput();
        try {
            $uuid = $this->model->crear($in);
            $this->jsonResponse(true, 'Ubicación creada correctamente.', ['animal_ubicacion_id' => $uuid]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear ubicación: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /animal_ubicaciones/{animal_ubicacion_id}
    public function actualizar(array $params): void
    {
        $id = $params['animal_ubicacion_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro animal_ubicacion_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        try {
            $ok = $this->model->actualizar($id, $in);
            $this->jsonResponse(true, 'Ubicación actualizada correctamente.', ['updated' => (bool)$ok]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al actualizar ubicación: ' . $e->getMessage(), null, 500);
        }
    }

    // POST /animal_ubicaciones/{animal_ubicacion_id}/cerrar
    public function cerrar(array $params): void
    {
        $id = $params['animal_ubicacion_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro animal_ubicacion_id es obligatorio.', null, 400);
        }

        $in = $this->getJsonInput();
        // Si no envías fecha_hasta, se toma hoy por defecto (Y-m-d)
        $fechaHasta = (string)($in['fecha_hasta'] ?? date('Y-m-d'));

        try {
            // Este método del modelo:
            // - pone fecha_hasta = $fechaHasta
            // - estado = 'INACTIVA'
            // - updated_at / updated_by según auditoría
            // y sólo si actualmente está activa (fecha_hasta IS NULL)
            $ok = $this->model->cerrarUbicacion($id, $fechaHasta);
            if (!$ok) {
                $this->jsonResponse(false, 'Ubicación no encontrada o ya estaba cerrada.', null, 404);
            }
            $this->jsonResponse(true, 'Ubicación cerrada correctamente.', [
                'animal_ubicacion_id' => $id,
                'fecha_hasta' => $fechaHasta,
                'estado' => 'INACTIVA'
            ]);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al cerrar ubicación: ' . $e->getMessage(), null, 500);
        }
    }

    // DELETE /animal_ubicaciones/{animal_ubicacion_id}
    public function eliminar(array $params): void
    {
        $id = $params['animal_ubicacion_id'] ?? '';
        if ($id === '') {
            $this->jsonResponse(false, 'Parámetro animal_ubicacion_id es obligatorio.', null, 400);
        }

        try {
            $ok = $this->model->eliminar($id);
            if (!$ok) {
                $this->jsonResponse(false, 'No se pudo eliminar (o ya estaba eliminada).', null, 400);
            }
            $this->jsonResponse(true, 'Ubicación eliminada correctamente.', ['deleted' => true]);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al eliminar ubicación: ' . $e->getMessage(), null, 500);
        }
    }
}
