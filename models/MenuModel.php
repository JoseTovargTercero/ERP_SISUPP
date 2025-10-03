<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';

class MenuModel
{
    private $db;
    private $table = 'menu';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /* ===== Utilidades ===== */

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

    private function validarUrl(string $url): void
    {
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, '/')) {
            // Permitimos rutas internas tipo "/dashboard"
            throw new InvalidArgumentException('La URL del menú es inválida.');
        }
    }

    private function validarUserLevel($lvl): int
    {
        if ($lvl === null || $lvl === '') {
            throw new InvalidArgumentException('user_level es obligatorio.');
        }
        if (!is_numeric($lvl)) {
            throw new InvalidArgumentException('user_level debe ser numérico.');
        }
        $i = (int)$lvl;
        if ($i < 0 || $i > 10) {
            throw new InvalidArgumentException('user_level fuera de rango (0–10).');
        }
        return $i;
    }

    /* ===== Lecturas ===== */

    // Filtros: categoria, user_level, q (nombre/url). Por defecto excluye eliminados.
    public function listar(
        int $limit = 100,
        int $offset = 0,
        bool $incluirEliminados = false,
        ?string $categoria = null,
        $userLevel = null,
        ?string $q = null
    ): array {
        $where  = [];
        $params = [];
        $types  = '';

        $where[] = $incluirEliminados ? '(m.deleted_at IS NOT NULL OR m.deleted_at IS NULL)' : 'm.deleted_at IS NULL';

        if ($categoria) {
            $where[]  = 'm.categoria = ?';
            $params[] = $categoria;
            $types   .= 's';
        }
        if ($userLevel !== null && $userLevel !== '') {
            $lvl = $this->validarUserLevel($userLevel);
            $where[]  = 'm.user_level <= ?'; // muestra accesibles hasta ese nivel
            $params[] = $lvl;
            $types   .= 'i';
        }
        if ($q) {
            $like = '%' . $q . '%';
            $where[]  = '(m.nombre LIKE ? OR m.url LIKE ?)';
            $params[] = $like; $params[] = $like;
            $types   .= 'ss';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT m.menu_id, m.categoria, m.nombre, m.url, m.icono, m.user_level,
                       m.created_at, m.created_by, m.updated_at, m.updated_by
                FROM {$this->table} m
                WHERE {$whereSql}
                ORDER BY m.categoria ASC, m.user_level ASC, m.nombre ASC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $types   .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res  = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function obtenerPorId(string $menuId): ?array
    {
        $sql = "SELECT m.menu_id, m.categoria, m.nombre, m.url, m.icono, m.user_level,
                       m.created_at, m.created_by, m.updated_at, m.updated_by,
                       m.deleted_at, m.deleted_by
                FROM {$this->table} m
                WHERE m.menu_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar consulta: " . $this->db->error);

        $stmt->bind_param('s', $menuId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /* ===== Escrituras ===== */

    // Requeridos: categoria, nombre, url, user_level. Opcional: icono
    public function crear(array $data): string
    {
        if (empty($data['categoria']) || empty($data['nombre']) || empty($data['url'])) {
            throw new InvalidArgumentException('Faltan campos requeridos: categoria, nombre, url.');
        }

        $categoria = trim((string)$data['categoria']);
        $nombre    = trim((string)$data['nombre']);
        $url       = trim((string)$data['url']);
        $icono     = isset($data['icono']) ? trim((string)$data['icono']) : null;
        $userLevel = $this->validarUserLevel($data['user_level'] ?? null);

        $this->validarUrl($url);

        $this->db->begin_transaction();
        try {
            [$now, $env] = $this->nowWithAudit();

            $uuid    = $this->generateUUIDv4();
            $actorId = $_SESSION['user_id'] ?? $uuid;

            $sql = "INSERT INTO {$this->table}
                    (menu_id, categoria, nombre, url, icono, user_level,
                     created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

            $stmt->bind_param('ssssisss',
                $uuid, $categoria, $nombre, $url, $icono, $userLevel, $now, $actorId
            );

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                $this->db->rollback();

                if (str_contains(strtolower($err), 'duplicate')) {
                    // Suponiendo índice único razonable (p.ej. categoria + nombre) o url única
                    throw new RuntimeException('Ya existe un menú con esos datos (conflicto de unicidad).');
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

    // Actualiza campos explícitos
    public function actualizar(string $menuId, array $data): bool
    {
        $campos = [];
        $params = [];
        $types  = '';

        if (isset($data['categoria'])) {
            $campos[] = 'categoria = ?';
            $params[] = trim((string)$data['categoria']);
            $types   .= 's';
        }
        if (isset($data['nombre'])) {
            $campos[] = 'nombre = ?';
            $params[] = trim((string)$data['nombre']);
            $types   .= 's';
        }
        if (isset($data['url'])) {
            $url = trim((string)$data['url']);
            $this->validarUrl($url);
            $campos[] = 'url = ?';
            $params[] = $url;
            $types   .= 's';
        }
        if (array_key_exists('icono', $data)) {
            $campos[] = 'icono = ?';
            $params[] = $data['icono'] !== null ? trim((string)$data['icono']) : null;
            $types   .= 's';
        }
        if (isset($data['user_level'])) {
            $lvl = $this->validarUserLevel($data['user_level']);
            $campos[] = 'user_level = ?';
            $params[] = $lvl;
            $types   .= 'i';
        }

        if (empty($campos)) {
            throw new InvalidArgumentException('No hay campos para actualizar.');
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $menuId;

        $campos[] = 'updated_at = ?';
        $params[] = $now;    $types .= 's';
        $campos[] = 'updated_by = ?';
        $params[] = $actorId; $types .= 's';

        $sql = "UPDATE {$this->table}
                SET " . implode(', ', $campos) . "
                WHERE menu_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar actualización: " . $this->db->error);

        $types   .= 's';
        $params[] = $menuId;

        $stmt->bind_param($types, ...$params);
        $ok  = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            if (str_contains(strtolower($err), 'duplicate')) {
                throw new RuntimeException('Conflicto de unicidad (ver índice único).');
            }
            throw new mysqli_sql_exception("Error al actualizar: " . $err);
        }
        return true;
    }

    // Soft delete
    public function eliminar(string $menuId): bool
    {
        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $menuId;

        $sql = "UPDATE {$this->table}
                SET deleted_at = ?, deleted_by = ?
                WHERE menu_id = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('sss', $now, $actorId, $menuId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
