<?php
// =================================================================
// 1. INICIAR SESIÓN Y CARGAR MODELOS
// =================================================================
// Asegúrate de que la sesión esté iniciada en tu punto de entrada principal (ej. index.php)
// Si no, descomenta la siguiente línea:
// if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once APP_ROOT . 'models/MenuModel.php';
require_once APP_ROOT . 'models/UsersPermisosModel.php';


// =================================================================
// 2. OBTENER DATOS DEL USUARIO LOGUEADO
// =================================================================
// Asumimos que los datos del usuario se guardan en la sesión tras el login
$isLoggedIn = isset($_SESSION['user_id']);
$userLevel = $isLoggedIn ? (int) $_SESSION['user_level'] : -1; // -1 para no autenticado
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

$flatMenuItems = [];

if ($isLoggedIn) {
    if ($userLevel === 0) {
        // El Administrador (nivel 0) tiene acceso a todo
        $menuModel = new MenuModel();
        // Usamos el método listar para traer todos los menús activos
        $flatMenuItems = $menuModel->listar(999, 0, false);
    } else {
        // Para otros usuarios, obtenemos sus permisos específicos
        $permisosModel = new UsersPermisosModel();
        // Usamos el método que ya une los datos del menú
        $permisos = $permisosModel->listarPermisosConMenu($userId);
        // Extraemos solo la información del menú de cada permiso
        foreach ($permisos as $permiso) {
            $flatMenuItems[] = $permiso['menu'];
        }
    }
}


// =================================================================
// 3. FUNCIÓN PARA TRANSFORMAR DATOS DE DB A FORMATO DE MENÚ
// =================================================================
/**
 * Convierte una lista plana de items de menú desde la DB
 * a una estructura jerárquica agrupada por categoría.
 * @param array $flatItems Lista de menús desde el modelo.
 * @return array Estructura de menú compatible con el renderizador.
 */
function buildMenuTree(array $flatItems): array
{
    $groupedByCategory = [];
    // Agrupar todos los items por su categoría
    foreach ($flatItems as $item) {
        $category = $item['categoria'] ?? 'General';
        $groupedByCategory[$category][] = $item;
    }

    $menuConfig = [];
    $menuConfig[] = ['is_title' => true, 'title' => 'Navegación'];

    // Construir el array final
    foreach ($groupedByCategory as $categoryName => $items) {
        // Añadir el título de la categoría
        $menuConfig[] = ['is_title' => true, 'title' => $categoryName];

        // Añadir cada item de menú bajo esa categoría
        foreach ($items as $item) {
            $menuConfig[] = [
                'title' => $item['nombre'],
                'icon' => $item['icono'] ?? 'uil-circle', // Icono por defecto
                'url' => BASE_URL . $item['url'], // Asumiendo que BASE_URL está definida
            ];
        }
    }
    return $menuConfig;
}

// Generamos la configuración del menú dinámicamente
$menuConfig = buildMenuTree($flatMenuItems);


// =================================================================
// 4. FUNCIÓN PARA RENDERIZAR EL MENÚ (sin cambios)
// =================================================================
function renderMenuItems($items, $level = 1)
{
    $levelClasses = [1 => 'side-nav-second-level', 2 => 'side-nav-third-level'];
    $levelClass = $levelClasses[$level] ?? '';
    echo "<ul class='$levelClass'>";
    foreach ($items as $item) {
        $hasSubmenu = !empty($item['submenu']);
        echo '<li class="side-nav-item">';
        $url = $item['url'] ?? 'javascript: void(0);';
        $target = isset($item['target']) ? "target='{$item['target']}'" : "";
        $toggleCollapse = $hasSubmenu ? 'data-bs-toggle="collapse"' : '';
        echo "<a href='{$url}' {$toggleCollapse} {$target} class='side-nav-link'>";
        if (isset($item['icon'])) {
            echo "<i class='{$item['icon']}'></i>";
        }
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
            renderMenuItems($item['submenu'], $level + 1);
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
            <img src="<?= BASE_URL ?>/public/assets/images/logo.png" alt="" height="16">
        </span>
        <span class="logo-sm">
            <img src="<?= BASE_URL ?>/public/assets/images/logo_sm.png" alt="" height="16">
        </span>
    </a>

    <div class="h-100" id="leftside-menu-container" data-simplebar="">

        <ul class="side-nav">

            <?php
            // La magia ocurre aquí: Iteramos sobre el $menuConfig generado dinámicamente
            foreach ($menuConfig as $item) {
                if (isset($item['is_title']) && $item['is_title']) {
                    echo "<li class='side-nav-title side-nav-item'>{$item['title']}</li>";
                } else {
                    $hasSubmenu = !empty($item['submenu']);
                    echo '<li class="side-nav-item">';
                    $url = $item['url'] ?? 'javascript: void(0);';
                    $target = isset($item['target']) ? "target='{$item['target']}'" : "";
                    $toggleCollapse = $hasSubmenu ? 'data-bs-toggle="collapse"' : '';
                    echo "<a href='{$url}' {$toggleCollapse} {$target} class='side-nav-link'>";
                    if (isset($item['icon']))
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
                        // La función renderMenuItems maneja los submenús
                        renderMenuItems($item['submenu'], 1);
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