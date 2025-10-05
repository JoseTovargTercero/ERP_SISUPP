<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class AnimalMovimientoModel
{
    private $db;
    private $table = 'animal_movimientos';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /* ========= Utilidades ========= */

    private function generateUUIDv4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function nowWithAudit(): array
    {
        $env = new ClientEnvironmentInfo(APP_ROOT . '/app/config/geolite.mmdb');
        $env->applyAuditContext($this->db, 0);
        $tzManager = new TimezoneManager($this->db);
        $tzManager->applyTimezone();
        return [$env->getCurrentDatetime(), $env];
    }

    private function validarFecha(string $ymd, string $campo = 'fecha'): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
            throw new InvalidArgumentException("$campo inválida. Formato esperado YYYY-MM-DD.");
        }
        [$y,$m,$d] = array_map('intval', explode('-', $ymd));
        if (!checkdate($m,$d,$y)) {
            throw new InvalidArgumentException("$campo no es una fecha válida.");
        }
    }

    private function validarEnum(string $valor, array $permitidos, string $campo): string
    {
        $v = strtoupper(trim($valor));
        if (!in_array($v, $permitidos, true)) {
            throw new InvalidArgumentException("$campo inválido. Use uno de: " . implode(', ', $permitidos));
        }
        return $v;
    }

    private function animalExiste(string $animalId): bool
    {
        $sql = "SELECT 1 FROM animales WHERE animal_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error verificación animal: " . $this->db->error);
        $stmt->bind_param('s', $animalId);
        $stmt->execute();
        $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    private function fincaExiste(?string $id): bool
    {
        if ($id === null) return true;
        $sql = "SELECT 1 FROM fincas WHERE finca_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if(!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id); $stmt->execute(); $stmt->store_result();
        $ok = $stmt->num_rows > 0; $stmt->close(); return $ok;
    }
    private function apriscoExiste(?string $id): bool
    {
        if ($id === null) return true;
        $sql = "SELECT finca_id FROM apriscos WHERE aprisco_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if(!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id); $stmt->execute(); $res = $stmt->get_result();
        $row = $res->fetch_assoc(); $stmt->close(); return !!$row;
    }
    private function areaExiste(?string $id): bool
    {
        if ($id === null) return true;
        $sql = "SELECT aprisco_id FROM areas WHERE area_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql); if(!$stmt) throw new mysqli_sql_exception($this->db->error);
        $stmt->bind_param('s', $id); $stmt->execute(); $res = $stmt->get_result();
        $row = $res->fetch_assoc(); $stmt->close(); return !!$row;
    }

    private function validarJerarquia(?string $fincaId, ?string $apriscoId, ?string $areaId): void
    {
        if ($areaId !== null) {
            $sql = "SELECT ap.aprisco_id, ap.finca_id
                    FROM areas a JOIN apriscos ap ON ap.aprisco_id = a.aprisco_id
                    WHERE a.area_id = ? AND a.deleted_at IS NULL";
            $stmt = $this->db->prepare($sql); if(!$stmt) throw new mysqli_sql_exception($this->db->error);
            $stmt->bind_param('s', $areaId); $stmt->execute(); $res = $stmt->get_result();
            $row = $res->fetch_assoc(); $stmt->close();
            if (!$row) throw new InvalidArgumentException("El área no existe o está eliminada.");
            if ($apriscoId !== null && $row['aprisco_id'] !== $apriscoId) {
                throw new InvalidArgumentException("El área no pertenece al aprisco indicado.");
            }
            if ($fincaId !== null && $row['finca_id'] !== $fincaId) {
                throw new InvalidArgumentException("El área no pertenece a la finca indicada.");
            }
        }
        if ($apriscoId !== null) {
            $sql = "SELECT finca_id FROM apriscos WHERE aprisco_id = ? AND deleted_at IS NULL";
            $stmt = $this->db->prepare($sql); if(!$stmt) throw new mysqli_sql_exception($this->db->error);
            $stmt->bind_param('s', $apriscoId); $stmt->execute(); $res = $stmt->get_result();
            $row = $res->fetch_assoc(); $stmt->close();
            if (!$row) throw new InvalidArgumentException("El aprisco no existe o está eliminado.");
            if ($fincaId !== null && $row['finca_id'] !== $fincaId) {
                throw new InvalidArgumentException("El aprisco no pertenece a la finca indicada.");
            }
        }
    }

    /* ========= Lecturas ========= */

    /**
     * Filtros: animal_id, tipo_movimiento, motivo, estado, fecha_desde..fecha_hasta,
     *          origen/destino (finca/aprisco/area), incluirEliminados
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $animalId = null,
        ?string $tipo = null,
        ?string $motivo = null,
        ?string $estado = null,
        ?string $desde = null,
        ?string $hasta = null,
        ?string $fincaOri = null, ?string $apriscoOri = null, ?string $areaOri = null,
        ?string $fincaDes = null, ?string $apriscoDes = null, ?string $areaDes = null
    ): array {
        $w=[]; $p=[]; $t='';

        $w[] = $incluirEliminados ? 'm.deleted_at IS NOT NULL OR m.deleted_at IS NULL' : 'm.deleted_at IS NULL';
        if ($animalId) { $w[]='m.animal_id = ?'; $p[]=$animalId; $t.='s'; }
        if ($tipo)     { $w[]='m.tipo_movimiento = ?'; $p[]=$this->validarEnum($tipo, ['INGRESO','EGRESO','TRASLADO','VENTA','COMPRA','NACIMIENTO','MUERTE','OTRO'], 'tipo_movimiento'); $t.='s'; }
        if ($motivo)   { $w[]='m.motivo = ?'; $p[]=$this->validarEnum($motivo, ['TRASLADO','INGRESO','EGRESO','AISLAMIENTO','VENTA','OTRO'], 'motivo'); $t.='s'; }
        if ($estado)   { $w[]='m.estado = ?'; $p[]=$this->validarEnum($estado, ['REGISTRADO','ANULADO'], 'estado'); $t.='s'; }
        if ($desde)    { $this->validarFecha($desde,'desde'); $w[]='m.fecha_mov >= ?'; $p[]=$desde; $t.='s'; }
        if ($hasta)    { $this->validarFecha($hasta,'hasta'); $w[]='m.fecha_mov <= ?'; $p[]=$hasta; $t.='s'; }

        if ($fincaOri)   { $w[]='m.finca_origen_id   = ?'; $p[]=$fincaOri;   $t.='s'; }
        if ($apriscoOri) { $w[]='m.aprisco_origen_id = ?'; $p[]=$apriscoOri; $t.='s'; }
        if ($areaOri)    { $w[]='m.area_origen_id    = ?'; $p[]=$areaOri;    $t.='s'; }
        if ($fincaDes)   { $w[]='m.finca_destino_id  = ?'; $p[]=$fincaDes;   $t.='s'; }
        if ($apriscoDes) { $w[]='m.aprisco_destino_id= ?'; $p[]=$apriscoDes; $t.='s'; }
        if ($areaDes)    { $w[]='m.area_destino_id   = ?'; $p[]=$areaDes;    $t.='s'; }

        $where = implode(' AND ', $w);

        $sql = "SELECT
                    m.animal_movimiento_id, m.animal_id, a.identificador AS animal_identificador,
                    m.fecha_mov, m.tipo_movimiento, m.motivo, m.estado, m.costo, m.documento_ref,
                    m.finca_origen_id, fo.nombre AS finca_origen,
                    m.aprisco_origen_id, ao.nombre AS aprisco_origen,
                    m.area_origen_id, aro.nombre_personalizado AS area_origen_nombre, aro.numeracion AS area_origen_nro,
                    m.finca_destino_id, fd.nombre AS finca_destino,
                    m.aprisco_destino_id, ad.nombre AS aprisco_destino,
                    m.area_destino_id, ard.nombre_personalizado AS area_destino_nombre, ard.numeracion AS area_destino_nro,
                    m.observaciones,
                    m.created_at, m.created_by, m.updated_at, m.updated_by
                FROM {$this->table} m
                LEFT JOIN animales a ON a.animal_id = m.animal_id
                LEFT JOIN fincas fo ON fo.finca_id = m.finca_origen_id
                LEFT JOIN apriscos ao ON ao.aprisco_id = m.aprisco_origen_id
                LEFT JOIN areas aro ON aro.area_id = m.area_origen_id
                LEFT JOIN fincas fd ON fd.finca_id = m.finca_destino_id
                LEFT JOIN apriscos ad ON ad.aprisco_id = m.aprisco_destino_id
                LEFT JOIN areas ard ON ard.area_id = m.area_destino_id
                WHERE {$where}
                ORDER BY m.fecha_mov DESC, m.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparar listado: " . $this->db->error);

        $t .= 'ii'; $p[]=$limit; $p[]=$offset;
        $stmt->bind_param($t, ...$p);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function obtenerPorId(string $id): ?array
    {
        $sql = "SELECT
                    m.*, a.identificador AS animal_identificador
                FROM {$this->table} m
                LEFT JOIN animales a ON a.animal_id = m.animal_id
                WHERE m.animal_movimiento_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error preparar consulta: " . $this->db->error);
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ========= Escrituras ========= */

    /**
     * Reglas por tipo_movimiento:
     * - INGRESO/COMPRA/NACIMIENTO: requiere destino (al menos finca/aprisco/area) — origen opcional
     * - EGRESO/VENTA/MUERTE:       requiere origen  — destino opcional
     * - TRASLADO:                  requiere origen y destino
     */
    private function validarReglasTipo(string $tipo, ?string $fOri, ?string $aOri, ?string $arOri, ?string $fDes, ?string $aDes, ?string $arDes): void
    {
        $t = $tipo;
        $hayOri = $fOri || $aOri || $arOri;
        $hayDes = $fDes || $aDes || $arDes;

        if (in_array($t, ['INGRESO','COMPRA','NACIMIENTO'], true)) {
            if (!$hayDes) throw new InvalidArgumentException("Para $t es obligatorio indicar destino (finca/aprisco/area).");
        } elseif (in_array($t, ['EGRESO','VENTA','MUERTE'], true)) {
            if (!$hayOri) throw new InvalidArgumentException("Para $t es obligatorio indicar origen (finca/aprisco/area).");
        } elseif ($t === 'TRASLADO') {
            if (!$hayOri || !$hayDes) throw new InvalidArgumentException("TRASLADO requiere origen y destino.");
        }
    }

    public function crear(array $data): string
    {
        foreach (['animal_id','fecha_mov','tipo_movimiento'] as $k) {
            if (!isset($data[$k]) || $data[$k] === '') {
                throw new InvalidArgumentException("Falta campo requerido: {$k}.");
            }
        }

        $animalId = (string)trim($data['animal_id']);
        if (!$this->animalExiste($animalId)) {
            throw new RuntimeException('El animal especificado no existe o está eliminado.');
        }

        $fechaMov = (string)trim($data['fecha_mov']);
        $this->validarFecha($fechaMov, 'fecha_mov');

        $tipo = $this->validarEnum((string)$data['tipo_movimiento'], ['INGRESO','EGRESO','TRASLADO','VENTA','COMPRA','NACIMIENTO','MUERTE','OTRO'], 'tipo_movimiento');
        $motivo = isset($data['motivo']) ? $this->validarEnum((string)$data['motivo'], ['TRASLADO','INGRESO','EGRESO','AISLAMIENTO','VENTA','OTRO'], 'motivo') : 'OTRO';
        $estado = isset($data['estado']) ? $this->validarEnum((string)$data['estado'], ['REGISTRADO','ANULADO'], 'estado') : 'REGISTRADO';

        // Origen/destino
        $fOri = $data['finca_origen_id']   ?? null;
        $aOri = $data['aprisco_origen_id'] ?? null;
        $arOri= $data['area_origen_id']    ?? null;

        $fDes = $data['finca_destino_id']   ?? null;
        $aDes = $data['aprisco_destino_id'] ?? null;
        $arDes= $data['area_destino_id']    ?? null;

        if (!$this->fincaExiste($fOri) || !$this->apriscoExiste($aOri) || !$this->areaExiste($arOri) ||
            !$this->fincaExiste($fDes) || !$this->apriscoExiste($aDes) || !$this->areaExiste($arDes)) {
            throw new RuntimeException('Finca/aprisco/área (origen/destino) no existen o están eliminados.');
        }
        // Consistencia jerárquica
        $this->validarJerarquia($fOri, $aOri, $arOri);
        $this->validarJerarquia($fDes, $aDes, $arDes);

        // Reglas por tipo
        $this->validarReglasTipo($tipo, $fOri, $aOri, $arOri, $fDes, $aDes, $arDes);

        $costo = array_key_exists('costo',$data) && $data['costo'] !== null ? (float)$data['costo'] : null;
        if ($costo !== null && ($costo < 0 || $costo > 999999.99)) {
            throw new InvalidArgumentException("costo fuera de rango.");
        }
        $documento = isset($data['documento_ref']) ? trim((string)$data['documento_ref']) : null;
        $obs = isset($data['observaciones']) ? trim((string)$data['observaciones']) : null;

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $uuid = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                (animal_movimiento_id, animal_id, fecha_mov, tipo_movimiento, motivo, estado,
                 finca_origen_id, aprisco_origen_id, area_origen_id,
                 finca_destino_id, aprisco_destino_id, area_destino_id,
                 costo, documento_ref, observaciones,
                 created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error preparar inserción: " . $this->db->error);

            // s s s s s s s s s s s s d s s s s
            $types = 'sssssssss ssss dsss s'; // compactaremos
            $types = 'sssssssssssss dsss s';  // evitar espacios (claridad visual no importa)
            $types = str_replace(' ', '', 'sssssssssssssdssss');

            $stmt->bind_param(
                $types,
                $uuid, $animalId, $fechaMov, $tipo, $motivo, $estado,
                $fOri, $aOri, $arOri,
                $fDes, $aDes, $arDes,
                $costo, $documento, $obs,
                $now, $actorId
            );

            if (!$stmt->execute()) {
                $err = strtolower($stmt->error);
                $stmt->close(); $this->db->rollback();
                if (str_contains($err, 'foreign key')) {
                    throw new RuntimeException('Violación de clave foránea (animal/origen/destino).');
                }
                throw new mysqli_sql_exception("Error al ejecutar inserción: " . $err);
            }

            $stmt->close();
            $this->db->commit();
            return $uuid;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function actualizar(string $id, array $data): bool
    {
        $row = $this->obtenerPorId($id);
        if (!$row || $row['deleted_at'] !== null) {
            throw new RuntimeException("Movimiento no existe o está eliminado.");
        }

        $campos=[]; $params=[]; $types='';

        if (isset($data['fecha_mov'])) {
            $this->validarFecha((string)$data['fecha_mov'], 'fecha_mov');
            $campos[]='fecha_mov = ?'; $params[]=(string)$data['fecha_mov']; $types.='s';
        }
        if (isset($data['tipo_movimiento'])) {
            $tipo = $this->validarEnum((string)$data['tipo_movimiento'], ['INGRESO','EGRESO','TRASLADO','VENTA','COMPRA','NACIMIENTO','MUERTE','OTRO'], 'tipo_movimiento');
            $campos[]='tipo_movimiento = ?'; $params[]=$tipo; $types.='s';
        } else {
            $tipo = $row['tipo_movimiento'];
        }

        if (isset($data['motivo'])) {
            $motivo = $this->validarEnum((string)$data['motivo'], ['TRASLADO','INGRESO','EGRESO','AISLAMIENTO','VENTA','OTRO'], 'motivo');
            $campos[]='motivo = ?'; $params[]=$motivo; $types.='s';
        }
        if (isset($data['estado'])) {
            $estado = $this->validarEnum((string)$data['estado'], ['REGISTRADO','ANULADO'], 'estado');
            $campos[]='estado = ?'; $params[]=$estado; $types.='s';
        }

        // Posibles cambios de origen/destino
        $fOri = array_key_exists('finca_origen_id',   $data) ? ($data['finca_origen_id']   ?? null) : $row['finca_origen_id'];
        $aOri = array_key_exists('aprisco_origen_id', $data) ? ($data['aprisco_origen_id'] ?? null) : $row['aprisco_origen_id'];
        $arOri= array_key_exists('area_origen_id',    $data) ? ($data['area_origen_id']    ?? null) : $row['area_origen_id'];

        $fDes = array_key_exists('finca_destino_id',   $data) ? ($data['finca_destino_id']   ?? null) : $row['finca_destino_id'];
        $aDes = array_key_exists('aprisco_destino_id', $data) ? ($data['aprisco_destino_id'] ?? null) : $row['aprisco_destino_id'];
        $arDes= array_key_exists('area_destino_id',    $data) ? ($data['area_destino_id']    ?? null) : $row['area_destino_id'];

        // Si se cambió algo de FKs, validar:
        if (array_key_exists('finca_origen_id', $data) || array_key_exists('aprisco_origen_id', $data) || array_key_exists('area_origen_id', $data) ||
            array_key_exists('finca_destino_id', $data) || array_key_exists('aprisco_destino_id', $data) || array_key_exists('area_destino_id', $data)) {

            if (!$this->fincaExiste($fOri) || !$this->apriscoExiste($aOri) || !$this->areaExiste($arOri) ||
                !$this->fincaExiste($fDes) || !$this->apriscoExiste($aDes) || !$this->areaExiste($arDes)) {
                throw new RuntimeException('Finca/aprisco/área (origen/destino) no existen o están eliminados.');
            }
            $this->validarJerarquia($fOri, $aOri, $arOri);
            $this->validarJerarquia($fDes, $aDes, $arDes);
            $this->validarReglasTipo($tipo, $fOri, $aOri, $arOri, $fDes, $aDes, $arDes);
        }

        if (array_key_exists('finca_origen_id', $data))   { $campos[]='finca_origen_id = ?';   $params[]=$fOri; $types.='s'; }
        if (array_key_exists('aprisco_origen_id', $data)) { $campos[]='aprisco_origen_id = ?'; $params[]=$aOri; $types.='s'; }
        if (array_key_exists('area_origen_id', $data))    { $campos[]='area_origen_id = ?';    $params[]=$arOri; $types.='s'; }

        if (array_key_exists('finca_destino_id', $data))   { $campos[]='finca_destino_id = ?';   $params[]=$fDes; $types.='s'; }
        if (array_key_exists('aprisco_destino_id', $data)) { $campos[]='aprisco_destino_id = ?'; $params[]=$aDes; $types.='s'; }
        if (array_key_exists('area_destino_id', $data))    { $campos[]='area_destino_id = ?';    $params[]=$arDes; $types.='s'; }

        if (array_key_exists('costo', $data)) {
            $costo = $data['costo'] !== null ? (float)$data['costo'] : null;
            if ($costo !== null && ($costo < 0 || $costo > 999999.99)) {
                throw new InvalidArgumentException("costo fuera de rango.");
            }
            $campos[]='costo = ?'; $params[]=$costo; $types.='d';
        }
        if (array_key_exists('documento_ref', $data)) {
            $campos[]='documento_ref = ?'; $params[] = $data['documento_ref'] !== null ? trim((string)$data['documento_ref']) : null; $types.='s';
        }
        if (array_key_exists('observaciones', $data)) {
            $campos[]='observaciones = ?'; $params[] = $data['observaciones'] !== null ? trim((string)$data['observaciones']) : null; $types.='s';
        }

        if (empty($campos)) throw new InvalidArgumentException('No hay campos para actualizar.');

        [$now, $env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $id;

        $campos[]='updated_at = ?'; $params[]=$now; $types.='s';
        $campos[]='updated_by = ?'; $params[]=$actorId; $types.='s';

        $sql = "UPDATE {$this->table} SET ".implode(', ',$campos)." WHERE animal_movimiento_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql); if(!$stmt) throw new mysqli_sql_exception("Error preparar actualización: ".$this->db->error);
        $types.='s'; $params[]=$id;

        $stmt->bind_param($types, ...$params);
        $ok=$stmt->execute(); $err=strtolower($stmt->error);
        $stmt->close();
        if(!$ok) {
            if (str_contains($err,'foreign key')) throw new RuntimeException('Violación de clave foránea (origen/destino).');
            throw new mysqli_sql_exception("Error al actualizar: ".$err);
        }
        return true;
    }

    public function eliminar(string $id): bool
    {
        [$now,$env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $id;

        $sql = "UPDATE {$this->table} SET deleted_at = ?, deleted_by = ? WHERE animal_movimiento_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql); if(!$stmt) throw new mysqli_sql_exception("Error preparar eliminación: ".$this->db->error);
        $stmt->bind_param('sss', $now, $actorId, $id);
        $ok = $stmt->execute(); $stmt->close();
        return $ok;
    }
}
