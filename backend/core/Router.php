<?php
class Router
{
    public static function ejecutar($schema, $data, $conexion)
    {
        switch ($schema['accion']) {
            case "insert":
                return self::insert($schema, $data, $conexion);
            case "update":
                return self::update($schema, $data, $conexion);
            case "delete":
                return self::delete($schema, $data, $conexion);
            default:
                return ["error" => "Acción inválida"];
        }
    }

    private static function insert($schema, $data, $conexion)
    {
        $cols = array_keys($data);
        $placeholders = implode(",", array_fill(0, count($cols), "?"));
        $colNames = implode(",", $cols);

        $sql = "INSERT INTO {$schema['tabla']} ($colNames) VALUES ($placeholders)";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param(str_repeat("s", count($data)), ...array_values($data));
        if ($stmt->execute()) return ["success" => true, "id" => $stmt->insert_id];
        return ["error" => $stmt->error];
    }

    private static function update($schema, $data, $conexion)
    {
        if (!isset($data["id"])) return ["error" => "ID requerido"];
        $id = $data["id"];
        unset($data["id"]);

        $sets = implode(", ", array_map(fn($c) => "$c = ?", array_keys($data)));
        $sql = "UPDATE {$schema['tabla']} SET $sets WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param(str_repeat("s", count($data)) . "i", ...array_values($data), $id);
        if ($stmt->execute()) return ["success" => true];
        return ["error" => $stmt->error];
    }

    private static function delete($schema, $data, $conexion)
    {
        if (!isset($data["id"])) return ["error" => "ID requerido"];
        $sql = "DELETE FROM {$schema['tabla']} WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $data["id"]);
        if ($stmt->execute()) return ["success" => true];
        return ["error" => $stmt->error];
    }
}
