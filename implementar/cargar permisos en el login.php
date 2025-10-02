
<?php
$subdirectorios = [
    '11' => [
        'creditos_cliente.php'
    ],
    '8' => [
        'ficha.php'
    ]
];



if ($usuario['nivel'] != 1) {
    $permisos = [];

    $stmt_2 = mysqli_prepare($conexion, "SELECT sup.id_item_menu, menu.dir FROM `users_permisos` AS sup 
				LEFT JOIN menu ON menu.id = sup.id_item_menu
				WHERE id_user = ?");
    $stmt_2->bind_param('i', $id);
    $stmt_2->execute();
    $result = $stmt_2->get_result();
    if ($result->num_rows > 0) {
        while ($row_p = $result->fetch_assoc()) {
            $permisos[$row_p['id_item_menu']] = $row_p['dir'];

            if (isset($subdirectorios[$row_p['id_item_menu']])) {
                foreach ($subdirectorios[$row_p['id_item_menu']] as $sub) {
                    $permisos[] = $sub;
                }
            }
        }
    }
    $stmt_2->close();
    $_SESSION['permisos'] = $permisos;
}
