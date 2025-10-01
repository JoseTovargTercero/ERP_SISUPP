<?php
return function ($data, $conexion) {
    // Encriptar contraseña
    if (!empty($data["contrasena"])) {
        $data["contrasena"] = password_hash($data["contrasena"], PASSWORD_BCRYPT);
    }

    // Validar que no exista el email
    $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $data["email"]);
    $stmt->execute();
    $existe = $stmt->get_result()->fetch_assoc();

    if ($existe) {
        return ["error" => "El correo ya está registrado"];
    }

    // Devolver data transformada para Router
    return $data;
};
