<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class AnimalModel
{
    private $db;
    private $table = 'animales';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /* ============ Utilidades ============ */

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

    private function validarFecha(?string $ymd, string $campo = 'fecha'): void
    {
        if ($ymd === null) return;
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

    private function animalExistePorId(string $animalId): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE animal_id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error verificación animal: " . $this->db->error);
        $stmt->bind_param('s', $animalId);
        $stmt->execute();
        $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    private function identificadorDisponible(string $identificador, ?string $exceptId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE identificador = ? AND deleted_at IS NULL";
        if ($exceptId) $sql .= " AND animal_id <> ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error verificación identificador: " . $this->db->error);
        if ($exceptId) $stmt->bind_param('ss', $identificador, $exceptId);
        else $stmt->bind_param('s', $identificador);
        $stmt->execute();
        $stmt->store_result();
        $ocupado = $stmt->num_rows > 0;
        $stmt->close();
        return !$ocupado;
    }

    /* ============ Lecturas ============ */

    /**
     * Lista animales con filtros y datos enriquecidos:
     * - último peso (fecha_peso, peso_kg)
     * - ubicación actual (finca/aprisco/area activos)
     *
     * Filtros: identificador (like), sexo, especie, estado, etapa, categoria, fecha_nacimiento (rango),
     *          finca_id/aprisco_id/area_id (por ubicación actual), incluirEliminados, paginación.
     */
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $q = null,
        ?string $sexo = null,
        ?string $especie = null,
        ?string $estado = null,
        ?string $etapa = null,
        ?string $categoria = null,
        ?string $nacDesde = null,
        ?string $nacHasta = null,
        ?string $fincaId = null,
        ?string $apriscoId = null,
        ?string $areaId = null
    ): array {
        $w=[]; $p=[]; $t='';

        $w[] = $incluirEliminados ? 'a.deleted_at IS NOT NULL OR a.deleted_at IS NULL' : 'a.deleted_at IS NULL';

        if ($q)     { $w[]='a.identificador LIKE ?'; $p[]='%'.$q.'%'; $t.='s'; }
        if ($sexo)  { $w[]='a.sexo = ?'; $p[]=$this->validarEnum($sexo,['MACHO','HEMBRA'],'sexo'); $t.='s'; }
        if ($especie){$w[]='a.especie = ?'; $p[]=$this->validarEnum($especie,['BOVINO','OVINO','CAPRINO','PORCINO','OTRO'],'especie'); $t.='s';}
        if ($estado){ $w[]='a.estado = ?'; $p[]=$this->validarEnum($estado,['ACTIVO','INACTIVO','MUERTO','VENDIDO'],'estado'); $t.='s';}
        if ($etapa) { $w[]='a.etapa_productiva = ?'; $p[]=$this->validarEnum($etapa,['TERNERO','LEVANTE','CEBA','REPRODUCTOR','LACTANTE','SECA','GESTANTE','OTRO'],'etapa_productiva'); $t.='s';}
        if ($categoria){$w[]='a.categoria = ?'; $p[]=$this->validarEnum($categoria,['CRIA','MADRE','PADRE','ENGORDE','REEMPLAZO','OTRO'],'categoria'); $t.='s';}

        if ($nacDesde) { $this->validarFecha($nacDesde,'nac_desde'); $w[]='a.fecha_nacimiento >= ?'; $p[]=$nacDesde; $t.='s'; }
        if ($nacHasta) { $this->validarFecha($nacHasta,'nac_hasta'); $w[]='a.fecha_nacimiento <= ?'; $p[]=$nacHasta; $t.='s'; }

        // Filtros por ubicación actual
        if ($fincaId || $apriscoId || $areaId) {
            if ($fincaId)   { $w[]='ua.finca_id = ?';   $p[]=$fincaId;   $t.='s'; }
            if ($apriscoId) { $w[]='ua.aprisco_id = ?'; $p[]=$apriscoId; $t.='s'; }
            if ($areaId)    { $w[]='ua.area_id = ?';    $p[]=$areaId;    $t.='s'; }
        }

        $where = implode(' AND ', $w);

        // Subconsulta: última toma de peso por animal
        $sql = "
            SELECT
                a.animal_id,
                a.identificador,
                a.sexo,
                a.especie,
                a.raza,
                a.color,
                a.fecha_nacimiento,
                a.estado,
                a.etapa_productiva,
                a.categoria,
                a.origen,
                a.madre_id,
                a.padre_id,
                a.created_at, a.created_by, a.updated_at, a.updated_by,

                -- último peso
                pw.fecha_peso AS ultima_fecha_peso,
                pw.peso_kg    AS ultimo_peso_kg,

                -- ubicación actual
                ua.finca_id,  f.nombre AS nombre_finca,
                ua.aprisco_id, ap.nombre AS nombre_aprisco,
                ua.area_id,   ar.nombre_personalizado AS nombre_area, ar.numeracion AS area_numeracion

            FROM {$this->table} a

            -- ubicación activa (si existe)
            LEFT JOIN (
                SELECT u1.*
                FROM animal_ubicaciones u1
                JOIN (
                    SELECT animal_id, MAX(fecha_desde) AS md
                    FROM animal_ubicaciones
                    WHERE deleted_at IS NULL AND fecha_hasta IS NULL
                    GROUP BY animal_id
                ) u2 ON u2.animal_id = u1.animal_id AND u2.md = u1.fecha_desde
                WHERE u1.deleted_at IS NULL AND u1.fecha_hasta IS NULL
            ) ua ON ua.animal_id = a.animal_id
            LEFT JOIN fincas f    ON f.finca_id    = ua.finca_id
            LEFT JOIN apriscos ap ON ap.aprisco_id = ua.aprisco_id
            LEFT JOIN areas ar    ON ar.area_id    = ua.area_id

            -- última toma de peso (por fecha_peso)
            LEFT JOIN (
                SELECT p1.*
                FROM animal_pesos p1
                JOIN (
                    SELECT animal_id, MAX(fecha_peso) AS mf
                    FROM animal_pesos
                    WHERE deleted_at IS NULL
                    GROUP BY animal_id
                ) p2 ON p2.animal_id = p1.animal_id AND p2.mf = p1.fecha_peso
                WHERE p1.deleted_at IS NULL
            ) pw ON pw.animal_id = a.animal_id

            WHERE {$where}
            ORDER BY a.created_at DESC, a.identificador ASC
            LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $t .= 'ii'; $p[]=$limit; $p[]=$offset;
        $stmt->bind_param($t, ...$p);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function obtenerPorId(string $animalId): ?array
    {
        // mismo enriquecimiento que en listar, pero por ID
        $sql = "
            SELECT
                a.animal_id,
                a.identificador,
                a.sexo,
                a.especie,
                a.raza,
                a.color,
                a.fecha_nacimiento,
                a.estado,
                a.etapa_productiva,
                a.categoria,
                a.origen,
                a.madre_id,
                a.padre_id,
                a.created_at, a.created_by, a.updated_at, a.updated_by, a.deleted_at, a.deleted_by,

                pw.fecha_peso AS ultima_fecha_peso,
                pw.peso_kg    AS ultimo_peso_kg,

                ua.finca_id,  f.nombre AS nombre_finca,
                ua.aprisco_id, ap.nombre AS nombre_aprisco,
                ua.area_id,   ar.nombre_personalizado AS nombre_area, ar.numeracion AS area_numeracion

            FROM {$this->table} a
            LEFT JOIN (
                SELECT u1.*
                FROM animal_ubicaciones u1
                JOIN (
                    SELECT animal_id, MAX(fecha_desde) AS md
                    FROM animal_ubicaciones
                    WHERE deleted_at IS NULL AND fecha_hasta IS NULL
                    GROUP BY animal_id
                ) u2 ON u2.animal_id = u1.animal_id AND u2.md = u1.fecha_desde
                WHERE u1.deleted_at IS NULL AND u1.fecha_hasta IS NULL
            ) ua ON ua.animal_id = a.animal_id
            LEFT JOIN fincas f    ON f.finca_id    = ua.finca_id
            LEFT JOIN apriscos ap ON ap.aprisco_id = ua.aprisco_id
            LEFT JOIN areas ar    ON ar.area_id    = ua.area_id
            LEFT JOIN (
                SELECT p1.*
                FROM animal_pesos p1
                JOIN (
                    SELECT animal_id, MAX(fecha_peso) AS mf
                    FROM animal_pesos
                    WHERE deleted_at IS NULL
                    GROUP BY animal_id
                ) p2 ON p2.animal_id = p1.animal_id AND p2.mf = p1.fecha_peso
                WHERE p1.deleted_at IS NULL
            ) pw ON pw.animal_id = a.animal_id
            WHERE a.animal_id = ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);
        $stmt->bind_param('s', $animalId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getOptions(?string $q = null): array
    {
        $sql = "SELECT animal_id, identificador AS label
                FROM {$this->table}
                WHERE deleted_at IS NULL";
        $params=[]; $types='';
        if ($q) {
            $sql .= " AND identificador LIKE ?";
            $params[] = '%'.$q.'%'; $types.='s';
        }
        $sql .= " ORDER BY identificador ASC LIMIT 200";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar options: " . $this->db->error);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res=$stmt->get_result();
        $rows=$res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /* ============ Escrituras ============ */

    /**
     * Crear animal.
     * Requeridos: identificador, sexo, especie
     * Opcionales: raza, color, fecha_nacimiento, estado, etapa_productiva, categoria, origen, madre_id, padre_id
     */
    public function crear(array $in): string
    {
        foreach (['identificador','sexo','especie'] as $k) {
            if (!isset($in[$k]) || $in[$k] === '') {
                throw new InvalidArgumentException("Falta campo requerido: {$k}.");
            }
        }

        $identificador = trim((string)$in['identificador']);
        if (!$this->identificadorDisponible($identificador)) {
            throw new RuntimeException('El identificador ya está en uso.');
        }

        $sexo    = $this->validarEnum((string)$in['sexo'],    ['MACHO','HEMBRA'], 'sexo');
        $especie = $this->validarEnum((string)$in['especie'], ['BOVINO','OVINO','CAPRINO','PORCINO','OTRO'], 'especie');

        $raza   = isset($in['raza']) ? trim((string)$in['raza']) : null;
        $color  = isset($in['color']) ? trim((string)$in['color']) : null;

        $fechaNacimiento = isset($in['fecha_nacimiento']) ? (string)$in['fecha_nacimiento'] : null;
        $this->validarFecha($fechaNacimiento, 'fecha_nacimiento');

        $estado = isset($in['estado']) ? $this->validarEnum((string)$in['estado'], ['ACTIVO','INACTIVO','MUERTO','VENDIDO'], 'estado') : 'ACTIVO';
        $etapa  = isset($in['etapa_productiva']) ? $this->validarEnum((string)$in['etapa_productiva'], ['TERNERO','LEVANTE','CEBA','REPRODUCTOR','LACTANTE','SECA','GESTANTE','OTRO'], 'etapa_productiva') : null;
        $categ  = isset($in['categoria']) ? $this->validarEnum((string)$in['categoria'], ['CRIA','MADRE','PADRE','ENGORDE','REEMPLAZO','OTRO'], 'categoria') : null;
        $origen = isset($in['origen']) ? $this->validarEnum((string)$in['origen'], ['NACIMIENTO','COMPRA','TRASLADO','OTRO'], 'origen') : 'OTRO';

        $madreId = isset($in['madre_id']) ? (string)$in['madre_id'] : null;
        $padreId = isset($in['padre_id']) ? (string)$in['padre_id'] : null;
        if ($madreId && !$this->animalExistePorId($madreId)) $madreId = null; // tolerante
        if ($padreId && !$this->animalExistePorId($padreId)) $padreId = null;

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $uuid = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                (animal_id, identificador, sexo, especie, raza, color, fecha_nacimiento,
                 estado, etapa_productiva, categoria, origen, madre_id, padre_id,
                 created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            $stmt->bind_param(
                'sssssssssssssss',
                $uuid, $identificador, $sexo, $especie, $raza, $color, $fechaNacimiento,
                $estado, $etapa, $categ, $origen, $madreId, $padreId, $now, $actorId
            );

            if (!$stmt->execute()) {
                $err = strtolower($stmt->error);
                $stmt->close(); $this->db->rollback();

                if (str_contains($err, 'duplicate')) {
                    throw new RuntimeException('Identificador duplicado.');
                }
                if (str_contains($err, 'foreign key')) {
                    throw new RuntimeException('FK inválida (madre/padre).');
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

    /**
     * Actualiza campos explícitos.
     */
    public function actualizar(string $animalId, array $in): bool
    {
        if (!$this->animalExistePorId($animalId)) {
            throw new RuntimeException('Animal no existe o está eliminado.');
        }

        $campos=[]; $params=[]; $types='';

        if (isset($in['identificador'])) {
            $ident = trim((string)$in['identificador']);
            if (!$this->identificadorDisponible($ident, $animalId)) {
                throw new RuntimeException('El identificador ya está en uso.');
            }
            $campos[]='identificador = ?'; $params[]=$ident; $types.='s';
        }
        if (isset($in['sexo'])) {
            $campos[]='sexo = ?'; $params[]=$this->validarEnum((string)$in['sexo'], ['MACHO','HEMBRA'], 'sexo'); $types.='s';
        }
        if (isset($in['especie'])) {
            $campos[]='especie = ?'; $params[]=$this->validarEnum((string)$in['especie'], ['BOVINO','OVINO','CAPRINO','PORCINO','OTRO'], 'especie'); $types.='s';
        }
        if (array_key_exists('raza',$in))  { $campos[]='raza = ?';  $params[]=$in['raza']!==null?trim((string)$in['raza']):null; $types.='s'; }
        if (array_key_exists('color',$in)) { $campos[]='color = ?'; $params[]=$in['color']!==null?trim((string)$in['color']):null; $types.='s'; }

        if (isset($in['fecha_nacimiento'])) {
            $this->validarFecha((string)$in['fecha_nacimiento'], 'fecha_nacimiento');
            $campos[]='fecha_nacimiento = ?'; $params[]=(string)$in['fecha_nacimiento']; $types.='s';
        }
        if (isset($in['estado'])) {
            $campos[]='estado = ?'; $params[]=$this->validarEnum((string)$in['estado'], ['ACTIVO','INACTIVO','MUERTO','VENDIDO'], 'estado'); $types.='s';
        }
        if (isset($in['etapa_productiva'])) {
            $campos[]='etapa_productiva = ?'; $params[]=$this->validarEnum((string)$in['etapa_productiva'], ['TERNERO','LEVANTE','CEBA','REPRODUCTOR','LACTANTE','SECA','GESTANTE','OTRO'], 'etapa_productiva'); $types.='s';
        }
        if (isset($in['categoria'])) {
            $campos[]='categoria = ?'; $params[]=$this->validarEnum((string)$in['categoria'], ['CRIA','MADRE','PADRE','ENGORDE','REEMPLAZO','OTRO'], 'categoria'); $types.='s';
        }
        if (isset($in['origen'])) {
            $campos[]='origen = ?'; $params[]=$this->validarEnum((string)$in['origen'], ['NACIMIENTO','COMPRA','TRASLADO','OTRO'], 'origen'); $types.='s';
        }
        if (array_key_exists('madre_id', $in)) {
            $madreId = $in['madre_id'] !== null ? (string)$in['madre_id'] : null;
            if ($madreId && !$this->animalExistePorId($madreId)) $madreId = null;
            $campos[]='madre_id = ?'; $params[]=$madreId; $types.='s';
        }
        if (array_key_exists('padre_id', $in)) {
            $padreId = $in['padre_id'] !== null ? (string)$in['padre_id'] : null;
            if ($padreId && !$this->animalExistePorId($padreId)) $padreId = null;
            $campos[]='padre_id = ?'; $params[]=$padreId; $types.='s';
        }

        if (empty($campos)) {
            throw new InvalidArgumentException('No hay campos para actualizar.');
        }

        [$now,$env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $animalId;
        $campos[]='updated_at = ?'; $params[]=$now; $types.='s';
        $campos[]='updated_by = ?'; $params[]=$actorId; $types.='s';

        $sql = "UPDATE {$this->table} SET ".implode(', ', $campos)." WHERE animal_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);
        $types.='s'; $params[]=$animalId;

        $stmt->bind_param($types, ...$params);
        $ok  = $stmt->execute();
        $err = strtolower($stmt->error);
        $stmt->close();

        if (!$ok) {
            if (str_contains($err,'duplicate')) throw new RuntimeException('Identificador duplicado.');
            if (str_contains($err,'foreign key')) throw new RuntimeException('FK inválida (madre/padre).');
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    /**
     * Soft delete
     */
    public function eliminar(string $animalId): bool
    {
        [$now,$env] = $this->nowWithAudit();
        $actorId = $_SESSION['user_id'] ?? $animalId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE animal_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);
        $stmt->bind_param('sss', $now, $actorId, $animalId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
