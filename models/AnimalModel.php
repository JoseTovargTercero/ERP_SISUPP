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

    // Dentro de AnimalModel (agrega al final de la clase, antes de la llave de cierre)

    /**
     * Verifica compatibilidad de cruce entre dos animales.
     * Regla: si comparten madre o comparten padre => son familiares => NO compatibles.
     * Retorna:
     *   ['compatible' => bool, 'motivo' => string|null, 'a' => array, 'b' => array]
     */
public function puedenCruzar(string $animalIdA, string $animalIdB, int $maxGenerations = 4): array
{
    $animalIdA = trim($animalIdA);
    $animalIdB = trim($animalIdB);

    if ($animalIdA === '' || $animalIdB === '') {
        throw new InvalidArgumentException('Se requieren ambos animal_id.');
    }
    if ($animalIdA === $animalIdB) {
        return ['compatible' => false, 'motivo' => 'Es el mismo animal.', 'a' => null, 'b' => null];
    }

    // 1) Cargar ambos animales (verifica existencia)
    $sql = "SELECT animal_id, madre_id, padre_id
            FROM {$this->table}
            WHERE animal_id IN (?, ?) AND deleted_at IS NULL";
    $stmt = $this->db->prepare($sql);
    if (!$stmt) throw new \mysqli_sql_exception("Error preparando verificación: ".$this->db->error);
    $stmt->bind_param('ss', $animalIdA, $animalIdB);
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($rows) < 2) {
        throw new RuntimeException('Uno o ambos animales no existen o están eliminados.');
    }

    // Normalizar
    $a = $rows[0]['animal_id'] === $animalIdA ? $rows[0] : $rows[1];
    $b = $rows[0]['animal_id'] === $animalIdB ? $rows[0] : $rows[1];

    // 2) Reglas de primer grado (hermanos/medio-hermanos) – rápido
    $mismaMadre = ($a['madre_id'] && $a['madre_id'] === $b['madre_id']);
    $mismoPadre = ($a['padre_id'] && $a['padre_id'] === $b['padre_id']);
    if ($mismaMadre && $mismoPadre) {
        return ['compatible' => false, 'motivo' => 'Parentesco prohibido: hermanos completos (misma madre y padre).', 'a' => $a, 'b' => $b];
    }
    if ($mismaMadre) {
        return ['compatible' => false, 'motivo' => 'Parentesco prohibido: comparten la misma madre (medio-hermanos).', 'a' => $a, 'b' => $b];
    }
    if ($mismoPadre) {
        return ['compatible' => false, 'motivo' => 'Parentesco prohibido: comparten el mismo padre (medio-hermanos).', 'a' => $a, 'b' => $b];
    }

    // 3) Construir ancestros hasta N generaciones para A y B
    $ancA = $this->getAncestorsMap($animalIdA, $maxGenerations); // id_ancestro => distancia (1=padre/madre, 2=abuelo/a, etc.)
    $ancB = $this->getAncestorsMap($animalIdB, $maxGenerations);

    // 3.1) ¿Alguno es ancestro del otro?
    if (isset($ancA[$animalIdB])) {
        $d = $ancA[$animalIdB];
        return ['compatible' => false, 'motivo' => 'Parentesco prohibido: B es ancestro de A ('. $this->labelAncestor($d) .').', 'a' => $a, 'b' => $b];
    }
    if (isset($ancB[$animalIdA])) {
        $d = $ancB[$animalIdA];
        return ['compatible' => false, 'motivo' => 'Parentesco prohibido: A es ancestro de B ('. $this->labelAncestor($d) .').', 'a' => $a, 'b' => $b];
    }

    // 3.2) ¿Comparten ancestros?
    $comunes = array_intersect_key($ancA, $ancB);
    if (!empty($comunes)) {
        // Elegir el ancestro común con menor (dA + dB)
        $mejor = null; $minSum = PHP_INT_MAX;
        foreach ($comunes as $ancId => $_) {
            $sum = $ancA[$ancId] + $ancB[$ancId];
            if ($sum < $minSum) { $minSum = $sum; $mejor = $ancId; }
        }
        $dA = $ancA[$mejor]; $dB = $ancB[$mejor];
        $motivo = 'Parentesco prohibido: ' . $this->labelCommonAncestor($dA, $dB);
        return ['compatible' => false, 'motivo' => $motivo, 'a' => $a, 'b' => $b];
    }

    // 4) Sin ancestros comunes hasta N generaciones
    return ['compatible' => true, 'motivo' => null, 'a' => $a, 'b' => $b];
}

/**
 * Retorna mapa id_ancestro => distancia (1=progenitor, 2=abuelo/a, 3=bisabuelo/a, ...)
 * Busca en capas (BFS) hasta $maxGenerations. Ignora registros con deleted_at.
 */
private function getAncestorsMap(string $animalId, int $maxGenerations): array
{
    $ancestors = [];                   // ancId => dist
    $frontera = [$animalId];           // ids a expandir
    $dist = 0;

    while ($dist < $maxGenerations && !empty($frontera)) {
        // Obtener padres de toda la frontera en una sola consulta
        $placeholders = implode(',', array_fill(0, count($frontera), '?'));
        $types = str_repeat('s', count($frontera));
        $sql = "SELECT animal_id, madre_id, padre_id
                FROM {$this->table}
                WHERE animal_id IN ($placeholders) AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new \mysqli_sql_exception("Error preparando ancestros: " . $this->db->error);
        $stmt->bind_param($types, ...$frontera);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $siguientes = [];
        $nivel = $dist + 1;

        foreach ($rows as $row) {
            foreach (['madre_id', 'padre_id'] as $col) {
                $p = $row[$col] ?? null;
                if ($p && !isset($ancestors[$p])) {
                    $ancestors[$p] = $nivel;
                    $siguientes[] = $p;
                }
            }
        }

        $frontera = array_values(array_unique($siguientes));
        $dist++;
    }

    return $ancestors;
}

/**
 * Etiqueta legible para ancestro directo a distancia d (1..N)
 */
private function labelAncestor(int $d): string
{
    return match ($d) {
        1 => 'padre/madre',
        2 => 'abuelo/abuela',
        3 => 'bisabuelo/bisabuela',
        4 => 'tatarabuelo/tatarabuela',
        default => $d.' generaciones arriba'
    };
}

/**
 * Etiqueta legible para compartir ancestro con distancias (dA, dB)
 * Casos clásicos:
 *  - (2,2) primos hermanos (1er grado)
 *  - (3,3) primos segundos (2º grado)
 *  - (2,3) primos hermanos “una vez removidos” (tío abuelo / sobrino-nieto)
 *  - (1,2) tío/tía con sobrino/a
 */
private function labelCommonAncestor(int $dA, int $dB): string
{
    // Ancestro directo ya se manejó antes; aquí dA>=1, dB>=1 y no hay (1,1)
    if (($dA === 1 && $dB >= 2) || ($dB === 1 && $dA >= 2)) {
        // uno es progenitor del progenitor del otro → tío/tía ↔ sobrino/a si (1,2)
        if ($dA + $dB === 3) return 'tío/tía con sobrino/a';
        // (1,3) ~ tío abuelo/bisabuelo político → sobrino-nieto/a
        return 'parientes en línea colateral (grado cercano)';
    }

    if ($dA === $dB) {
        if ($dA === 2) return 'primos hermanos (comparten abuelo/a)';
        if ($dA === 3) return 'primos segundos (comparten bisabuelo/a)';
        if ($dA === 4) return 'primos terceros (comparten tatarabuelo/a)';
        $k = $dA - 1;
        return "primos de grado {$k}";
    }

    // Distintos niveles: “removidos”
    $k = min($dA, $dB) - 1;                  // grado de primos
    $r = abs($dA - $dB);                      // veces removidos
    if ($k <= 0) {
        // casos como (2,3) también se pueden describir como “tío abuelo / sobrino nieto”
        if (($dA === 2 && $dB === 3) || ($dA === 3 && $dB === 2)) {
            return 'tío abuelo/tía abuela con sobrino-nieto/a';
        }
        return 'parientes en línea colateral (distinto grado)';
    }
    $grado = ($k === 1) ? 'primos hermanos' : "primos de grado {$k}";
    $remov = ($r === 1) ? 'una vez removidos' : "{$r} veces removidos";
    return "{$grado} ({$remov})";
}


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

    if ($q)       { $w[]='a.identificador LIKE ?'; $p[]='%'.$q.'%'; $t.='s'; }
    if ($sexo)    { $w[]='a.sexo = ?'; $p[]=$this->validarEnum($sexo,['MACHO','HEMBRA'],'sexo'); $t.='s'; }
    if ($especie) { $w[]='a.especie = ?'; $p[]=$this->validarEnum($especie,['BOVINO','OVINO','CAPRINO','PORCINO','OTRO'],'especie'); $t.='s';}
    if ($estado)  { $w[]='a.estado = ?'; $p[]=$this->validarEnum($estado,['ACTIVO','INACTIVO','MUERTO','VENDIDO'],'estado'); $t.='s';}
    if ($etapa)   { $w[]='a.etapa_productiva = ?'; $p[]=$this->validarEnum($etapa,['TERNERO','LEVANTE','CEBA','REPRODUCTOR','LACTANTE','SECA','GESTANTE','OTRO'],'etapa_productiva'); $t.='s';}
    if ($categoria){$w[]='a.categoria = ?'; $p[]=$this->validarEnum($categoria,['CRIA','MADRE','PADRE','ENGORDE','REEMPLAZO','OTRO'],'categoria'); $t.='s';}

    if ($nacDesde) { $this->validarFecha($nacDesde,'nac_desde'); $w[]='a.fecha_nacimiento >= ?'; $p[]=$nacDesde; $t.='s'; }
    if ($nacHasta) { $this->validarFecha($nacHasta,'nac_hasta'); $w[]='a.fecha_nacimiento <= ?'; $p[]=$nacHasta; $t.='s'; }

    if ($fincaId || $apriscoId || $areaId) {
        if ($fincaId)   { $w[]='ua.finca_id = ?';   $p[]=$fincaId;   $t.='s'; }
        if ($apriscoId) { $w[]='ua.aprisco_id = ?'; $p[]=$apriscoId; $t.='s'; }
        if ($areaId)    { $w[]='ua.area_id = ?';    $p[]=$areaId;    $t.='s'; }
    }

    $where = implode(' AND ', $w);

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
            a.fotografia_url,
            a.created_at, a.created_by, a.updated_at, a.updated_by,

            pw.fecha_peso AS ultima_fecha_peso,
            pw.peso_kg    AS ultimo_peso_kg,

            ua.finca_id,   f.nombre AS nombre_finca,
            ua.aprisco_id, ap.nombre AS nombre_aprisco,
            ua.area_id,    ar.nombre_personalizado AS nombre_area, ar.numeracion AS area_numeracion,
            ua.recinto_id,
            r.codigo_recinto AS codigo_recinto

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
        LEFT JOIN fincas   f  ON f.finca_id    = ua.finca_id
        LEFT JOIN apriscos ap ON ap.aprisco_id = ua.aprisco_id
        LEFT JOIN areas    ar ON ar.area_id    = ua.area_id
        LEFT JOIN recintos r  ON r.recinto_id  = ua.recinto_id

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
            a.fotografia_url,
            a.created_at, a.created_by, a.updated_at, a.updated_by, a.deleted_at, a.deleted_by,

            pw.fecha_peso AS ultima_fecha_peso,
            pw.peso_kg    AS ultimo_peso_kg,

            ua.finca_id,   f.nombre AS nombre_finca,
            ua.aprisco_id, ap.nombre AS nombre_aprisco,
            ua.area_id,    ar.nombre_personalizado AS nombre_area, ar.numeracion AS area_numeracion,
            ua.recinto_id,
            r.codigo_recinto AS codigo_recinto

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
        LEFT JOIN fincas   f  ON f.finca_id    = ua.finca_id
        LEFT JOIN apriscos ap ON ap.aprisco_id = ua.aprisco_id
        LEFT JOIN areas    ar ON ar.area_id    = ua.area_id
        LEFT JOIN recintos r  ON r.recinto_id  = ua.recinto_id

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
     * Crear animal (sin manejar archivos aquí).
     * Luego el controlador, si recibe archivo, actualizará fotografia_url.
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
        if ($madreId && !$this->animalExistePorId($madreId)) $madreId = null;
        if ($padreId && !$this->animalExistePorId($padreId)) $padreId = null;

        $fotoUrl = isset($in['fotografia_url']) ? (string)$in['fotografia_url'] : null;

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();
            $uuid = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
    (animal_id, identificador, sexo, especie, raza, color, fecha_nacimiento,
     estado, etapa_productiva, categoria, origen, madre_id, padre_id,
     fotografia_url,
     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)";

$stmt = $this->db->prepare($sql);
if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

// 18 parámetros => 18 tipos
$stmt->bind_param(
    'ssssssssssssssssss',
    $uuid,             // 1
    $identificador,    // 2
    $sexo,             // 3
    $especie,          // 4
    $raza,             // 5 (nullable)
    $color,            // 6 (nullable)
    $fechaNacimiento,  // 7 (nullable)
    $estado,           // 8
    $etapa,            // 9 (nullable)
    $categ,            //10 (nullable)
    $origen,           //11
    $madreId,          //12 (nullable)
    $padreId,          //13 (nullable)
    $fotoUrl,          //14 (nullable)
    $now,              //15 created_at
    $actorId,          //16 created_by
    $now,              //17 updated_at
    $actorId           //18 updated_by
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
 * Obtiene el árbol genealógico de un animal.
 * @param string      $animalId        ID del animal raíz.
 * @param string|null $direccion       'ARRIBA'|'ASC' = ascendencia (padres, abuelos...),
 *                                     'ABAJO'|'DESC' = descendencia (hijos, nietos...),
 *                                     null            = ambos.
 * @param int         $maxGeneraciones Límite de niveles (>=1). Por defecto 6.
 * @return array { animal, ascendencia|null, descendencia|null }
 */
public function getArbolGenealogico(string $animalId, ?string $direccion = null, int $maxGeneraciones = 6): array
{
    $animalId = trim($animalId);
    if ($animalId === '') {
        throw new InvalidArgumentException('animal_id requerido.');
    }
    $dir = $direccion ? strtoupper(trim($direccion)) : null;
    if ($dir !== null && !in_array($dir, ['ARRIBA','ASC','ABAJO','DESC'], true)) {
        throw new InvalidArgumentException("Parámetro 'direccion' inválido. Use ARRIBA|ASC|ABAJO|DESC o null.");
    }
    if ($maxGeneraciones < 1) $maxGeneraciones = 1;

    // --- Helpers locales con caché por llamada ---
    $cache = [];

    $fetchAnimal = function(string $id) use (&$cache) {
        if (isset($cache[$id])) return $cache[$id];

        $sql = "SELECT animal_id, identificador, sexo, especie, raza, color, fecha_nacimiento,
                       madre_id, padre_id, fotografia_url
                FROM {$this->table}
                WHERE animal_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new \mysqli_sql_exception("Error preparando fetchAnimal: ".$this->db->error);
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: null;
        $stmt->close();

        if ($row) {
            // Normalizamos fechas nulas a null explícito
            $row['fecha_nacimiento'] = $row['fecha_nacimiento'] ?: null;
            $cache[$id] = $row;
        }
        return $row;
    };

    $buildAsc = function(string $id, int $nivel, int $max) use (&$buildAsc, $fetchAnimal): ?array {
        $self = $fetchAnimal($id);
        if (!$self) return null;
        if ($nivel >= $max) {
            return [
                'animal' => $self,
                'madre'  => null,
                'padre'  => null,
                'nivel'  => $nivel,
            ];
        }
        $madre = !empty($self['madre_id']) ? $buildAsc($self['madre_id'], $nivel+1, $max) : null;
        $padre = !empty($self['padre_id']) ? $buildAsc($self['padre_id'], $nivel+1, $max) : null;

        return [
            'animal' => $self,
            'madre'  => $madre,
            'padre'  => $padre,
            'nivel'  => $nivel,
        ];
    };

    $buildDesc = function(string $id, int $nivel, int $max) use (&$buildDesc, $fetchAnimal): array {
        $self = $fetchAnimal($id);
        if (!$self) return [];

        if ($nivel >= $max) {
            return [[
                'animal' => $self,
                'hijos'  => [],
                'nivel'  => $nivel,
            ]];
        }

        // Buscar hijos donde este ID sea madre o padre
        $sql = "SELECT animal_id
                FROM {$this->table}
                WHERE deleted_at IS NULL AND (madre_id = ? OR padre_id = ?)
                ORDER BY fecha_nacimiento ASC, identificador ASC";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new \mysqli_sql_exception("Error preparando hijos: ".$this->db->error);
        $stmt->bind_param('ss', $id, $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $childIds = [];
        while ($r = $res->fetch_assoc()) $childIds[] = $r['animal_id'];
        $stmt->close();

        $nodosHijos = [];
        foreach ($childIds as $cid) {
            $childSelf = $fetchAnimal($cid);
            if (!$childSelf) continue;
            $sub = $buildDesc($cid, $nivel+1, $max); // sub devuelve un array con el nodo del hijo como primer nivel
            // Por claridad, el primer elemento de $sub es el propio hijo con su árbol
            $nodosHijos[] = $sub[0] ?? ['animal'=>$childSelf, 'hijos'=>[], 'nivel'=>$nivel+1];
        }

        return [[
            'animal' => $self,
            'hijos'  => $nodosHijos,
            'nivel'  => $nivel,
        ]];
    };

    // --- Armar respuesta ---
    $root = $fetchAnimal($animalId);
    if (!$root) {
        throw new RuntimeException('El animal no existe o está eliminado.');
    }

    $asc = null; $desc = null;

    if ($dir === null || in_array($dir, ['ARRIBA','ASC'], true)) {
        // Devolvemos el árbol SIN repetir el nodo raíz dos veces (convención: en ascendencia no duplicamos la raíz en 'animal')
        $ascNodo = $buildAsc($animalId, 0, $maxGeneraciones);
        // Para limpiar, removemos el nivel superior 'animal' si quieres solo ver ramas:
        $asc = [
            'madre' => $ascNodo['madre'] ?? null,
            'padre' => $ascNodo['padre'] ?? null,
        ];
    }
    if ($dir === null || in_array($dir, ['ABAJO','DESC'], true)) {
        $descNodo = $buildDesc($animalId, 0, $maxGeneraciones);
        // El primer elemento es el propio animal con su descendencia
        $desc = $descNodo[0]['hijos'] ?? [];
    }

    return [
        'animal'       => $root,
        'ascendencia'  => $asc,   // null si no se pidió
        'descendencia' => $desc,  // null si no se pidió
    ];
}
 /**
 * Devuelve TODOS los árboles genealógicos (bosque), desde los más viejos (antiguos) a los más recientes.
 * Criterio de orden: fecha_nacimiento ASC (nulos al final), luego identificador ASC.
 * Cada árbol parte de un "raíz" sin madre ni padre (ambos NULL) y construye solo DESCENDENCIA.
 *
 * @param int $maxGeneraciones Profundidad de descendencia por árbol (>=1). Por defecto 6.
 * @return array Lista de árboles: [ { animal(root), descendencia:[hijos...] }, ... ]
 */
public function getTodosLosArbolesGenealogicos(int $maxGeneraciones = 6): array
{
    if ($maxGeneraciones < 1) $maxGeneraciones = 1;

    // 1) Obtener raíces (sin madre ni padre)
    $sqlRoots = "SELECT animal_id
                 FROM {$this->table}
                 WHERE deleted_at IS NULL
                   AND madre_id IS NULL
                   AND padre_id IS NULL
                 ORDER BY (fecha_nacimiento IS NULL), fecha_nacimiento ASC, identificador ASC";
    $rs = $this->db->query($sqlRoots);
    if (!$rs) throw new \mysqli_sql_exception("Error listando raíces: ".$this->db->error);

    $rootIds = [];
    while ($r = $rs->fetch_assoc()) $rootIds[] = $r['animal_id'];

    // 2) Reutilizamos lógica de descenso con caché por llamada
    $cache = [];

    $fetchAnimal = function(string $id) use (&$cache) {
        if (isset($cache[$id])) return $cache[$id];
        $sql = "SELECT animal_id, identificador, sexo, especie, raza, color, fecha_nacimiento,
                       madre_id, padre_id, fotografia_url
                FROM {$this->table}
                WHERE animal_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new \mysqli_sql_exception("Error preparando fetchAnimal: ".$this->db->error);
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: null;
        $stmt->close();
        if ($row) {
            $row['fecha_nacimiento'] = $row['fecha_nacimiento'] ?: null;
            $cache[$id] = $row;
        }
        return $row;
    };

    $buildDesc = function(string $id, int $nivel, int $max) use (&$buildDesc, $fetchAnimal) {
        $self = $fetchAnimal($id);
        if (!$self) return null;

        // Obtener hijos
        $sql = "SELECT animal_id
                FROM {$this->table}
                WHERE deleted_at IS NULL AND (madre_id = ? OR padre_id = ?)
                ORDER BY fecha_nacimiento ASC, identificador ASC";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new \mysqli_sql_exception("Error preparando hijos: ".$this->db->error);
        $stmt->bind_param('ss', $id, $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $childIds = [];
        while ($r = $res->fetch_assoc()) $childIds[] = $r['animal_id'];
        $stmt->close();

        $nodosHijos = [];
        if ($nivel < $max) {
            foreach ($childIds as $cid) {
                $sub = $buildDesc($cid, $nivel+1, $max);
                if ($sub) $nodosHijos[] = $sub;
            }
        }

        return [
            'animal' => $self,
            'hijos'  => $nodosHijos,
            'nivel'  => $nivel,
        ];
    };

    // 3) Construir bosque
    $bosque = [];
    foreach ($rootIds as $rid) {
        $arbol = $buildDesc($rid, 0, $maxGeneraciones);
        if ($arbol) {
            // Para mantener salida homogénea con getArbolGenealogico (solo descendencia):
            $bosque[] = [
                'animal'       => $arbol['animal'],
                'descendencia' => $arbol['hijos'],
            ];
        }
    }

    return $bosque;
}


    /**
     * Actualiza campos explícitos (incluye fotografia_url).
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

        // NUEVO: fotografia_url (nullable)
        if (array_key_exists('fotografia_url', $in)) {
            $campos[] = 'fotografia_url = ?';
            $params[] = $in['fotografia_url'] !== null ? (string)$in['fotografia_url'] : null;
            $types .= 's';
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
