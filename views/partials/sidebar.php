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
        'title' => 'Navigation'
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
            ['title' => 'CRM', 'url' => 'dashboard-crm.html'],
            ['title' => 'Ecommerce', 'url' => 'index.html'],
            ['title' => 'Projects', 'url' => 'dashboard-projects.html'],
        ]
    ],
    [
        'is_title' => true,
        'title' => 'Apps'
    ],
    [
        'title' => 'Calendar',
        'icon' => 'uil-calender',
        'url' => 'apps-calendar.html'
    ],
    [
        'title' => 'Ecommerce',
        'icon' => 'uil-store',
        'url' => '#sidebarEcommerce',
        'submenu' => [
            ['title' => 'Products', 'url' => 'apps-ecommerce-products.html'],
            ['title' => 'Products Details', 'url' => 'apps-ecommerce-products-details.html'],
            ['title' => 'Orders', 'url' => 'apps-ecommerce-orders.html'],
            ['title' => 'Customers', 'url' => 'apps-ecommerce-customers.html'],
        ]
    ],
    [
        'is_title' => true,
        'title' => 'Custom'
    ],
    [
        'title' => 'Pages',
        'icon' => 'uil-copy-alt',
        'url' => '#sidebarPages',
        'submenu' => [
            ['title' => 'Profile', 'url' => 'pages-profile.html'],
            ['title' => 'Invoice', 'url' => 'pages-invoice.html'],
            [
                'title' => 'Authentication',
                'url' => '#sidebarPagesAuth',
                'submenu' => [
                    ['title' => 'Login', 'url' => 'pages-login.html'],
                    ['title' => 'Register', 'url' => 'pages-register.html'],
                    ['title' => 'Logout', 'url' => 'pages-logout.html'],
                ]
            ],
            [
                'title' => 'Error',
                'url' => '#sidebarPagesError',
                'submenu' => [
                    ['title' => 'Error 404', 'url' => 'pages-404.html'],
                    ['title' => 'Error 500', 'url' => 'pages-500.html'],
                ]
            ],
        ]
    ],
    [
        'title' => 'Landing',
        'icon' => 'uil-globe',
        'url' => 'landing.html',
        'target' => '_blank', // Para abrir en nueva pestaña
        'badge' => [
            'text' => 'New',
            'class' => 'badge bg-secondary text-light float-end'
        ]
    ],

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
            <img src="assets/images/logo.png" alt="" height="16">
        </span>
        <span class="logo-sm">
            <img src="assets/images/logo_sm.png" alt="" height="16">
        </span>
    </a>

    <a href="index.html" class="logo text-center logo-dark">
        <span class="logo-lg">
            <img src="assets/images/logo-dark.png" alt="" height="16">
        </span>
        <span class="logo-sm">
            <img src="assets/images/logo_sm_dark.png" alt="" height="16">
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

        <div class="help-box text-white text-center">
            <a href="javascript: void(0);" class="float-end close-btn text-white">
                <i class="mdi mdi-close"></i>
            </a>
            <img src="assets/images/help-icon.svg" height="90" alt="Helper Icon Image">
            <h5 class="mt-3">Unlimited Access</h5>
            <p class="mb-3">Upgrade to plan to get access to unlimited reports</p>
            <a href="javascript: void(0);" class="btn btn-outline-light btn-sm">Upgrade</a>
        </div>
        <div class="clearfix"></div>

    </div>
</div>