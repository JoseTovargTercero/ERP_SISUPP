<?php

namespace App\Core;


class ViewRenderer
{
    private $basePath;
    private $defaultLayout = 'layouts/main'; // Plantilla por defecto

    public function __construct(string $basePath = 'views/')
    {

        $this->basePath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR;

    }

    /**
     * Renderiza una vista, opcionalmente dentro de una plantilla.
     *
     * @param string $view La ruta al archivo de la vista (ej. 'admin/dashboard')
     * @param array $data Datos para la vista. Se puede pasar ['layout' => false] para deshabilitar la plantilla.
     */
    public function render(string $view, array $data = []): void
    {
        // Determina si se debe usar una plantilla desde los datos pasados
        $layout = $data['layout'] ?? $this->defaultLayout;
        unset($data['layout']); // Evita pasar 'layout' a la vista final

        $viewPath = $this->basePath . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';


        if (!file_exists($viewPath)) {
            header("HTTP/1.0 404 Not Found");
            echo "Error 404: Vista no encontrada en '{$viewPath}'.";
            exit;
        }

        extract($data);

        // Renderiza el contenido principal de la vista primero
        ob_start();
        include $viewPath;
        $content = ob_get_clean(); // $content ahora contiene el HTML de la vista

        // Si no se usa plantilla, simplemente muestra el contenido
        if ($layout === false) {
            echo $content;
            return;
        }

        // Si se usa una plantilla, cárgala. La plantilla puede usar la variable $content.
        $layoutPath = $this->basePath . str_replace('/', DIRECTORY_SEPARATOR, $layout) . '.php';

        var_dump($layoutPath);


        if (file_exists($layoutPath)) {
            // La plantilla ahora puede incluir parciales y mostrar la variable $content
            include $layoutPath;
        } else {
            // Si la plantilla no se encuentra, muestra solo el contenido para evitar una página en blanco
            echo $content;
        }
    }
}