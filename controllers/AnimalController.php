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

    /** Detecta si la petición viene como multipart/form-data */
    private function isMultipart(): bool
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        return stripos($ct, 'multipart/form-data') !== false;
    }

    /** Valida y guarda la imagen (si existe) con nombre {uuid}.{ext} en APP_ROOT/uploads */
    private function saveFotoIfAny(string $uuid, ?array $file): ?string
    {
        if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null; // no hay archivo
        }

        // Validaciones básicas
        $maxBytes = 20 * 1024 * 1024; // 20MB
        if ($file['size'] > $maxBytes) {
            throw new InvalidArgumentException('La fotografía excede el tamaño máximo (20MB).');
        }

        // Determinar mimetype real
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
        $ext   = null;
        switch ($mime) {
            case 'image/jpeg': $ext = 'jpg'; break;
            case 'image/png':  $ext = 'png'; break;
            case 'image/webp': $ext = 'webp'; break;
            default:
                throw new InvalidArgumentException('Formato de imagen no permitido. Use JPG, PNG o WEBP.');
        }

        $uploadsDir = rtrim(APP_ROOT, '/\\') . '/uploads';
        if (!is_dir($uploadsDir)) {
            if (!@mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
                throw new RuntimeException('No se pudo crear el directorio de uploads.');
            }
        }
        if (!is_writable($uploadsDir)) {
            throw new RuntimeException('El directorio de uploads no es escribible.');
        }

        $destAbs  = $uploadsDir . '/' . $uuid . '.' . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $destAbs)) {
            throw new RuntimeException('No se pudo guardar la fotografía en el servidor.');
        }

        // Ruta relativa que guardamos en BD
        $rel = '/uploads/' . $uuid . '.' . $ext;
        return $rel;
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
// Dentro de AnimalController (agrega antes de la llave de cierre de la clase)

// Dentro de AnimalController

    // POST /animales/verificar_cruce
    // Cuerpo (JSON o form-data):
    //   - animal_a (o animalIdA)
    //   - animal_b (o animalIdB)
    public function verificarCruce(): void
    {
        if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? 'GET', 'POST') !== 0) {
            $this->jsonResponse(false, 'Método no permitido. Use POST.', null, 405);
        }

        try {
            if ($this->isMultipart()) {
                // form-data
                $a = $_POST['animal_a'] ?? ($_POST['animalIdA'] ?? null);
                $b = $_POST['animal_b'] ?? ($_POST['animalIdB'] ?? null);
            } else {
                // JSON
                $in = $this->getJsonInput();
                $a  = $in['animal_a'] ?? ($in['animalIdA'] ?? null);
                $b  = $in['animal_b'] ?? ($in['animalIdB'] ?? null);
            }

            if (!$a || !$b) {
                $this->jsonResponse(false, 'Debe proporcionar animal_a y animal_b.', null, 400);
            }

            $res = $this->model->puedenCruzar((string)$a, (string)$b);

            if ($res['compatible'] === true) {
                $this->jsonResponse(true, 'Pueden cruzarse.', ['compatible' => true]);
            } else {
                $this->jsonResponse(false, 'No pueden cruzarse: '.$res['motivo'], [
                    'compatible' => false,
                    'motivo'     => $res['motivo']
                ], 200);
            }
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al verificar compatibilidad: '.$e->getMessage(), null, 500);
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
    // JSON o multipart/form-data
    // Campos: identificador, sexo, especie, [raza, color, fecha_nacimiento, estado, etapa_productiva, categoria, origen, madre_id, padre_id]
    // Archivo: fotografia
    public function crear(): void
    {
        try {
            if ($this->isMultipart()) {
                // Campos por $_POST
                $in = [
                    'identificador'     => $_POST['identificador']    ?? null,
                    'sexo'              => $_POST['sexo']             ?? null,
                    'especie'           => $_POST['especie']          ?? null,
                    'raza'              => $_POST['raza']             ?? null,
                    'color'             => $_POST['color']            ?? null,
                    'fecha_nacimiento'  => $_POST['fecha_nacimiento'] ?? null,
                    'estado'            => $_POST['estado']           ?? null,
                    'etapa_productiva'  => $_POST['etapa_productiva'] ?? null,
                    'categoria'         => $_POST['categoria']        ?? null,
                    'origen'            => $_POST['origen']           ?? null,
                    'madre_id'          => $_POST['madre_id']         ?? null,
                    'padre_id'          => $_POST['padre_id']         ?? null,
                ];
                // Primero creo el animal (sin foto)
                $uuid = $this->model->crear($in);

                // Guardar fotografía si viene
                $fotoRel = $this->saveFotoIfAny($uuid, $_FILES['fotografia'] ?? null);
                if ($fotoRel) {
                    $this->model->actualizar($uuid, ['fotografia_url' => $fotoRel]);
                }

                $this->jsonResponse(true, 'Animal creado correctamente.', ['animal_id'=>$uuid, 'fotografia_url'=>$fotoRel]);
            } else {
                // JSON puro
                $in = $this->getJsonInput();

                // Si el JSON ya trae un fotografia_url, se guardará en el INSERT directamente
                $uuid = $this->model->crear($in);
                $this->jsonResponse(true, 'Animal creado correctamente.', ['animal_id'=>$uuid, 'fotografia_url'=>$in['fotografia_url'] ?? null]);
            }
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 400);
        } catch (RuntimeException $e) {
            $this->jsonResponse(false, $e->getMessage(), null, 409);
        } catch (Throwable $e) {
            $this->jsonResponse(false, 'Error al crear animal: '.$e->getMessage(), null, 500);
        }
    }

    // POST /animales/{animal_id}
    // Acepta JSON o multipart. Si envías archivo 'fotografia' en multipart, sustituye la foto.
    public function actualizar(array $params): void
    {
        $id = $params['animal_id'] ?? '';
        if ($id === '') $this->jsonResponse(false,'Parámetro animal_id es obligatorio.',null,400);

        try {
            if ($this->isMultipart()) {
                // Campos por $_POST (solo los que vengan)
                $in = [];
                foreach ([
                    'identificador','sexo','especie','raza','color','fecha_nacimiento',
                    'estado','etapa_productiva','categoria','origen','madre_id','padre_id'
                ] as $k) {
                    if (array_key_exists($k, $_POST)) $in[$k] = $_POST[$k];
                }

                // Si viene nueva foto, la guardamos y actualizamos fotografia_url
                $fotoRel = $this->saveFotoIfAny($id, $_FILES['fotografia'] ?? null);
                if ($fotoRel) {
                    $in['fotografia_url'] = $fotoRel;
                }

                if (empty($in)) {
                    $this->jsonResponse(false, 'No hay campos para actualizar.', null, 400);
                }

                $ok = $this->model->actualizar($id, $in);
                $this->jsonResponse(true, 'Animal actualizado correctamente.', ['updated'=>$ok, 'fotografia_url'=>$in['fotografia_url'] ?? null]);
            } else {
                // JSON
                $in = $this->getJsonInput();
                $ok = $this->model->actualizar($id, $in);
                $this->jsonResponse(true, 'Animal actualizado correctamente.', ['updated'=>$ok, 'fotografia_url'=>$in['fotografia_url'] ?? null]);
            }
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
