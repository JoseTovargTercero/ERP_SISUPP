<?php

// =================================================================
// 1. CONFIGURACIÓN DEL MENÚ
// =================================================================
// Define la estructura completa del sidebar.
// - 'submenu' crea un menú desplegable.
// - 'badge' añade una insignia al item.
$menuConfig = [
    [
        'is_title' => true,
        'title' => 'Navegación'
    ],
    [
        'title' => 'Dashboards',
        'icon' => 'uil-home-alt',
        'url' => '#sidebarDashboards', // ID para el collapse
        'badge' => [
            'text' => '4',
            'class' => 'badge bg-success float-end'
        ],
        'submenu' => [
            ['title' => 'Analytics', 'url' => 'dashboard-analytics.html'],
        ]
    ],
    [
        'is_title' => true,
        'title' => 'Administrador'
    ],
    [
        'title' => 'Usuarios',
        'icon' => 'uil-user-circle',
        'url' => 'users',
    ],
    [
        'title' => 'Fincas',
        'icon' => 'uil-building',
        'url' => 'fincas_vista',
    ]

];




// =================================================================
// 2. FUNCIÓN PARA RENDERIZAR EL MENÚ
// =================================================================
// Esta función recursiva genera el HTML para cada nivel del menú.
function renderMenuItems($items, $level = 1)
{
    // Define las clases para cada nivel de submenú
    $levelClasses = [
        1 => 'side-nav-second-level',
        2 => 'side-nav-third-level',
        3 => 'side-nav-forth-level',
    ];
    $levelClass = $levelClasses[$level] ?? '';

    echo "<ul class='$levelClass'>";

    foreach ($items as $item) {
        $hasSubmenu = !empty($item['submenu']);

        echo '<li class="side-nav-item">';

        // Atributos del enlace
        $url = $item['url'] ?? 'javascript: void(0);';
        $target = isset($item['target']) ? "target='{$item['target']}'" : "";
        $toggleCollapse = $hasSubmenu ? 'data-bs-toggle="collapse"' : '';

        echo "<a href='{$url}' {$toggleCollapse} {$target} class='side-nav-link'>";

        // Icono (solo para el primer nivel en esta plantilla)
        if (isset($item['icon'])) {
            echo "<i class='{$item['icon']}'></i>";
        }

        // Badge
        if (isset($item['badge'])) {
            echo "<span class='{$item['badge']['class']}'>{$item['badge']['text']}</span>";
        }

        // Título del item
        echo "<span> {$item['title']} </span>";

        // Flecha si hay submenú
        if ($hasSubmenu) {
            echo '<span class="menu-arrow"></span>';
        }

        echo "</a>";

        // Si hay submenú, renderizarlo recursivamente
        if ($hasSubmenu) {
            // El ID del div colapsable es la URL sin el '#'
            $collapseId = ltrim($item['url'], '#');
            echo "<div class='collapse' id='{$collapseId}'>";
            renderMenuItems($item['submenu'], $level + 1); // Llamada recursiva
            echo "</div>";
        }

        echo '</li>';
    }

    echo "</ul>";
}

?>

<div class="leftside-menu">

    <a href="index.html" class="logo text-center logo-light">
        <span class="logo-lg">
            <img src="<?= BASE_URL ?>public/assets/images/logo.png" alt="" height="16">
        </span>
        <span class="logo-sm">
            <img src="<?= BASE_URL ?>public/assets/images/logo_sm.png" alt="" height="16">
        </span>
    </a>

    <a href="index.html" class="logo text-center logo-dark">
        <span class="logo-lg">
            <img src="<?= BASE_URL ?>public/assets/images/logo-dark.png" alt="" height="16">
        </span>
        <span class="logo-sm">
            <img src="<?= BASE_URL ?>public/assets/images/logo_sm_dark.png" alt="" height="16">
        </span>
    </a>

    <div class="h-100" id="leftside-menu-container" data-simplebar="">

        <ul class="side-nav">

            <?php
            // Generar el menú dinámicamente
            foreach ($menuConfig as $item) {
                if (isset($item['is_title']) && $item['is_title']) {
                    // Es un título de sección
                    echo "<li class='side-nav-title side-nav-item'>{$item['title']}</li>";
                } else {
                    // Es un item de menú (puede tener submenú)
                    $hasSubmenu = !empty($item['submenu']);

                    echo '<li class="side-nav-item">';

                    $url = $item['url'] ?? 'javascript: void(0);';
                    $target = isset($item['target']) ? "target='{$item['target']}'" : "";
                    $toggleCollapse = $hasSubmenu ? 'data-bs-toggle="collapse"' : '';

                    echo "<a href='{$url}' {$toggleCollapse} {$target} class='side-nav-link'>";
                    echo "<i class='{$item['icon']}'></i>";

                    if (isset($item['badge'])) {
                        echo "<span class='{$item['badge']['class']}'>{$item['badge']['text']}</span>";
                    }

                    echo "<span> {$item['title']} </span>";

                    if ($hasSubmenu) {
                        echo '<span class="menu-arrow"></span>';
                    }

                    echo "</a>";

                    if ($hasSubmenu) {
                        $collapseId = ltrim($item['url'], '#');
                        echo "<div class='collapse' id='{$collapseId}'>";
                        renderMenuItems($item['submenu'], 1); // Inicia la recursión
                        echo "</div>";
                    }

                    echo '</li>';
                }
            }
            ?>

        </ul>

        <div class="clearfix"></div>

    </div>
</div>