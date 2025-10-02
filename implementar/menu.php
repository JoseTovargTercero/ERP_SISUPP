<?php
ob_start();
?>
<nav id="navbar">
    <ul class="navbar-items flexbox-col">
        <li class="navbar-logo d-flex justify-content-between" style="padding-left: 10px;">
            <a class="navbar-item-inner flexbox">
                <img src='images/logo1-inv-compact.png' style='max-width:40px; opacity: 0.8'>
            </a>
            <a class="navbar-item-inner flexbox" id="menu_button"></a>
        </li>

        <?php
        require_once '../../configurar/configuracion.php';
        $menu_list = [];

        $stmt = mysqli_prepare($conexion, "SELECT * FROM `menu`");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $listar = ($_SESSION["nivel"] == 1 || isset($_SESSION['permisos'][$row['id']]));
                if ($listar) {
                    $item = [
                        "tipo"      => is_null($row['categoria']) ? 'item' : 'categoria',
                        "categoria" => $row['categoria'],
                        "icono"     => $row['icono'] ?? 'help-outline',
                        "enlace"    => $row['dir'] ?? '#',
                        "nombre"    => $row['nombre'] ?? 'Sin nombre',
                        "sub-item"  => null
                    ];

                    if ($item['tipo'] === 'item') {
                        $menu_list[$row['id']] = $item;
                    } else {
                        if (!isset($menu_list[$row['categoria']])) {
                            $menu_list[$row['categoria']] = [
                                "tipo"      => "categoria",
                                "categoria" => $row['categoria'],
                                "icono"     => $row['icono'] ?? 'help-outline',
                                "sub-item"  => []
                            ];
                        }
                        $menu_list[$row['categoria']]['sub-item'][] = [
                            "enlace" => $row['dir'] ?? '#',
                            "nombre" => $row['nombre'] ?? 'Sin nombre'
                        ];
                    }
                }
            }
        }
        $stmt->close();

        function generarMenuAdaptado(array $menu_list): void
        {
            foreach ($menu_list as $item) {
                if ($item['tipo'] === 'item') {
                    echo '<li class="navbar-item flexbox-left">';
                    echo   '<a class="navbar-item-inner flexbox-left" href="' . htmlspecialchars($item['enlace']) . '">';
                    echo     '<div class="navbar-item-inner-icon-wrapper flexbox">';
                    echo       '<ion-icon name="' . htmlspecialchars($item['icono']) . '"></ion-icon>';
                    echo     '</div>';
                    echo     '<span class="link-text">' . htmlspecialchars($item['nombre']) . '</span>';
                    echo   '</a>';
                    echo '</li>';
                } else {
                    echo '<li class="navbar-item navdrop">';
                    echo   '<div class="navdrop-toggle navbar-item-inner flexbox-left">';
                    echo     '<div class="navbar-item-inner-icon-wrapper flexbox">';
                    echo       '<ion-icon name="' . htmlspecialchars($item['icono']) . '"></ion-icon>';
                    echo     '</div>';
                    echo     '<span class="link-text">' . htmlspecialchars($item['categoria']) . '</span>';
                    echo   '</div>';
                    echo   '<div class="navdrop-menu">';
                    foreach ($item['sub-item'] as $sub) {
                        echo '<a href="' . htmlspecialchars($sub['enlace']) . '">' . htmlspecialchars($sub['nombre']) . '</a>';
                    }
                    echo   '</div>';
                    echo '</li>';
                }
            }
        }

        generarMenuAdaptado($menu_list);

        if ($_SESSION["nivel"] == 1): ?>
            <li class="navbar-item navdrop">
                <div class="navdrop-toggle navbar-item-inner flexbox-left">
                    <div class="navbar-item-inner-icon-wrapper flexbox">
                        <ion-icon name="people-outline"></ion-icon>
                    </div>
                    <span class="link-text">Usuarios</span>
                </div>
                <div class="navdrop-menu">
                    <a href="users.php">Nuevos</a>
                    <a href="permisos.php">Permisos</a>
                </div>
            </li>
        <?php endif; ?>
    </ul>
</nav>

<?php
$menu = ob_get_clean();
?>