<?php
require_once __DIR__ . '/../config/Database.php';

class MenuCategoriaModel
{
    private $db;
    private $table = 'menu_categorias';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Lista todas las categorías ordenadas por su campo 'orden'.
     * @return array
     */
    public function listar(): array
    {
        // =========================================================================
        // MODIFICADO: Se añade LEFT JOIN y COUNT() para obtener el total de ítems
        // =========================================================================
        $sql = "SELECT 
                    c.categoria_id, 
                    c.nombre, 
                    c.orden,
                    COUNT(m.menu_id) as item_count
                FROM 
                    {$this->table} c
                LEFT JOIN 
                    menu m ON c.nombre = m.categoria AND m.deleted_at IS NULL
                GROUP BY 
                    c.categoria_id, c.nombre, c.orden
                ORDER BY 
                    c.orden ASC";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new \mysqli_sql_exception("Error al preparar el listado de categorías: " . $this->db->error);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $data;
    }

    /**
     * Actualiza el orden de las categorías basándose en un array de nombres.
     * @param array $nombresCategorias Array de strings con los nombres de las categorías en el nuevo orden.
     * @return bool
     */
    public function reordenar(array $nombresCategorias): bool
    {
        if (empty($nombresCategorias)) {
            throw new \InvalidArgumentException('Se requiere un array de nombres de categorías.');
        }

        $this->db->begin_transaction();
        try {
            $sql = "UPDATE {$this->table} SET orden = ? WHERE nombre = ?";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                throw new \mysqli_sql_exception("Error al preparar la actualización de orden: " . $this->db->error);
            }

            foreach ($nombresCategorias as $index => $nombre) {
                $orden = $index;
                $stmt->bind_param('is', $orden, $nombre);
                if (!$stmt->execute()) {
                    throw new \mysqli_sql_exception("Error al actualizar el orden para la categoría {$nombre}: " . $stmt->error);
                }
            }

            $stmt->close();
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}