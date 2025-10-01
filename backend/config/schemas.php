<?php
return [
    "nuevos_usuarios" => [
        "tabla" => "system_users",
        "campos" => ["nombre", "email", "contrasena", "nivel", "estado"],
        "origenes" => ["text.php"],
        "accion" => "insert"
    ],
    "empleado_update" => [
        "tabla" => "empleados",
        "campos" => ["id", "nombre", "email"],
        "origenes" => ["empleados_edit.php"],
        "accion" => "update"
    ],
    "empleado_delete" => [
        "tabla" => "empleados",
        "campos" => ["id"],
        "origenes" => ["empleados_list.php"],
        "accion" => "delete"
    ]
];
