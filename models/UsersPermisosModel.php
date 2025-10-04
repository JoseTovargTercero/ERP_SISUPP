<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/ClientEnvironmentInfo.php';
require_once __DIR__ . '/../config/TimezoneManager.php';
require_once __DIR__ . '/MenuModel.php'; // Para la variante via MenuModel

class UsersPermisosModel
{
    private $db;
    private $table = 'users_permisos';

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

    private function validarUUID(string $id, string $label): void
    {
        if ($id === '' || strlen($id) < 4) {
            throw new InvalidArgumentException("$label inválido.");
        }
    }

    private function existeUsuario(string $userId): bool
    {
        $sql = "SELECT 1 FROM system_users WHERE user_id = ? AND (deleted_at IS NULL OR deleted_at = '') LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar verificación de usuario: " . $this->db->error);
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    private function existeMenu(string $menuId): bool
    {
        $sql = "SELECT 1 FROM menu WHERE menu_id = ? AND (deleted_at IS NULL OR deleted_at = '') LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar verificación de menú: " . $this->db->error);
        $stmt->bind_param('s', $menuId);
        $stmt->execute();
        $stmt->store_result();
        $ok = $stmt->num_rows > 0;
        $stmt->close();
        return $ok;
    }

    /* ===== Escrituras ===== */

    /**
     * Asigna permisos en lote: inserta N filas (user_id, menu_id) una por una.
     * Devuelve arrays con insertados, duplicados y errores.
     *
     * @param string $userId
     * @param array $menuIds
     * @return array { inserted: [], duplicates: [], errors: [] }
     */
    public function asignarPermisos(string $userId, array $menuIds): array
    {
        $this->validarUUID($userId, 'user_id');
        if (!$this->existeUsuario($userId)) {
            throw new InvalidArgumentException('user_id no existe o está eliminado.');
        }

        [$now, $env] = $this->nowWithAudit();
        $actorId     = $_SESSION['user_id'] ?? $userId;

        $sql = "INSERT INTO {$this->table}
                (users_permisos_id, user_id, menu_id, created_at, created_by, updated_at, updated_by, deleted_at, deleted_by)
                VALUES (?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar inserción: " . $this->db->error);

        $this->db->begin_transaction();
        $inserted   = [];
        $duplicates = [];
        $errors     = [];

        try {
            foreach ($menuIds as $menuId) {
                $menuId = trim((string)$menuId);
                if ($menuId === '') { $errors[] = ['menu_id' => $menuId, 'error' => 'menu_id vacío']; continue; }
                if (!$this->existeMenu($menuId)) { $errors[] = ['menu_id' => $menuId, 'error' => 'menú no existe']; continue; }

                $uuid = $this->generateUUIDv4();
                $stmt->bind_param('sssss', $uuid, $userId, $menuId, $now, $actorId);
                $ok = @$stmt->execute();
                if ($ok) {
                    $inserted[] = ['users_permisos_id' => $uuid, 'menu_id' => $menuId];
                } else {
                    $err = strtolower($stmt->error);
                    if (str_contains($err, 'duplicate')) {
                        $duplicates[] = $menuId;
                    } else {
                        $errors[] = ['menu_id' => $menuId, 'error' => $stmt->error];
                    }
                }
            }
            $stmt->close();
            $this->db->commit();

            return compact('inserted', 'duplicates', 'errors');
        } catch (\Throwable $e) {
            $stmt->close();
            $this->db->rollback();
            throw $e;
        }
    }

    /* ===== Lecturas ===== */

    /**
     * Variante RÁPIDA: devuelve permisos con datos del menú via JOIN.
     * Estructura: [{ users_permisos_id, user_id, menu: {menu_id, categoria, nombre, url, icono, user_level} }]
     */
    public function listarPermisosConMenu(string $userId): array
    {
        $this->validarUUID($userId, 'user_id');

        $sql = "SELECT up.users_permisos_id, up.user_id,
                       m.menu_id, m.categoria, m.nombre, m.url, m.icono, m.user_level
                FROM {$this->table} up
                INNER JOIN menu m ON m.menu_id = up.menu_id
                WHERE up.user_id = ? AND (m.deleted_at IS NULL OR m.deleted_at = '')";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'users_permisos_id' => $row['users_permisos_id'],
                'user_id'           => $row['user_id'],
                'menu' => [
                    'menu_id'   => $row['menu_id'],
                    'categoria' => $row['categoria'],
                    'nombre'    => $row['nombre'],
                    'url'       => $row['url'],
                    'icono'     => $row['icono'],
                    'user_level'=> (int)$row['user_level'],
                ],
            ];
        }
        $stmt->close();
        return $out;
    }

    /**
     * Variante solicitada: usa MenuModel::obtenerPorId($menuId) para traer el menú de cada permiso.
     * Devuelve misma estructura que la anterior.
     */
    public function listarPermisosConMenu_UsandoMenuModel(string $userId): array
    {
        $this->validarUUID($userId, 'user_id');

        $sql = "SELECT users_permisos_id, user_id, menu_id
                FROM {$this->table}
                WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar listado: " . $this->db->error);

        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        $menuModel = new MenuModel();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $menu = $menuModel->obtenerPorId((string)$row['menu_id']);
            if ($menu && ($menu['deleted_at'] ?? null) === null) {
                $out[] = [
                    'users_permisos_id' => $row['users_permisos_id'],
                    'user_id'           => $row['user_id'],
                    'menu' => [
                        'menu_id'   => $menu['menu_id'],
                        'categoria' => $menu['categoria'],
                        'nombre'    => $menu['nombre'],
                        'url'       => $menu['url'],
                        'icono'     => $menu['icono'],
                        'user_level'=> (int)$menu['user_level'],
                    ],
                ];
            }
        }
        $stmt->close();
        return $out;
    }

    /* ===== Eliminaciones ===== */

    // Elimina un permiso específico (delete físico)
    public function eliminarUno(string $usersPermisosId): bool
    {
        $this->validarUUID($usersPermisosId, 'users_permisos_id');

        $sql = "DELETE FROM {$this->table} WHERE users_permisos_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('s', $usersPermisosId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }

    // Elimina todos los permisos de un usuario (delete físico masivo)
    public function eliminarPorUsuario(string $userId): int
    {
        $this->validarUUID($userId, 'user_id');

        $sql = "DELETE FROM {$this->table} WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new mysqli_sql_exception("Error al preparar eliminación: " . $this->db->error);

        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }
}
