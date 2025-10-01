<?php

namespace App;

use Exception;



class Router
{
    protected $rutas = [];
    private $viewRenderer;
    protected $groupAttributes = [];
    private $basePath = '';



    public function __construct($viewRenderer)
    {
        $this->viewRenderer = $viewRenderer;




        // Detecta si el proyecto está en un subdirectorio
        $this->basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($this->basePath === '/' || $this->basePath === '\\') {
            $this->basePath = '';
        }




    }

    public function group(array $attributes, callable $callback)
    {
        $parentGroupAttributes = $this->groupAttributes;
        $this->groupAttributes = array_merge($parentGroupAttributes, $attributes);
        $callback($this);
        $this->groupAttributes = $parentGroupAttributes;
    }

    public function agregarRuta($metodo, $ruta, $opciones)
    {


        $metodo = strtoupper($metodo);
        $prefix = $this->groupAttributes['prefix'] ?? '';

        // Construye la ruta completa, asegurando una sola barra entre segmentos
        $rutaCompleta = rtrim($prefix, '/') . '/' . ltrim($ruta, '/');
        // Maneja el caso de la ruta raíz dentro de un grupo
        if ($prefix && ($ruta === '' || $ruta === '/')) {
            $rutaCompleta = $prefix;
        }

        $rutaRegex = $this->rutaARegex($rutaCompleta);

        if (is_string($opciones)) {
            $opciones = ['vista' => $opciones];
        }



        // Heredar middleware y roles del grupo si no están definidos en la ruta
        if (isset($this->groupAttributes['middleware']) && !isset($opciones['middleware'])) {
            $opciones['middleware'] = $this->groupAttributes['middleware'];
            if (isset($this->groupAttributes['roles']) && !isset($opciones['roles'])) {
                $opciones['roles'] = $this->groupAttributes['roles'];
            }
        }

        $this->rutas[$metodo][$rutaRegex] = [
            'vista' => $opciones['vista'] ?? null,
            'vistaData' => $opciones['vistaData'] ?? [],
            'controlador' => $opciones['controlador'] ?? null,
            'accion' => $opciones['accion'] ?? null,
            'middleware' => $opciones['middleware'] ?? null,
            'roles' => $opciones['roles'] ?? [],
        ];
    }

    protected function rutaARegex($ruta)
    {
        // Convierte rutas como /user/{id} a una expresión regular como #^/user/(?P<id>[^/]+)$#i
        $rutaRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $ruta);
        return '#^' . $rutaRegex . '$#i';
    }

    public function route()
    {
        $metodoSolicitado = strtoupper($_SERVER['REQUEST_METHOD']);

        // Obtiene la ruta de la URI sin la query string
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Elimina el subdirectorio base de la URI si es necesario
        if ($this->basePath && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }

        // Asegura que la ruta empiece con una barra o sea solo una barra
        $rutaSolicitada = '/' . trim($uri, '/');
        if (empty(trim($uri, '/'))) {
            $rutaSolicitada = '/';
        }


        if (isset($this->rutas[$metodoSolicitado])) {
            foreach ($this->rutas[$metodoSolicitado] as $rutaRegex => $datosRuta) {
                if (preg_match($rutaRegex, $rutaSolicitada, $matches)) {
                    // Aplica el middleware si está definido
                    if (isset($datosRuta['middleware'])) {
                        $middlewareNombre = $datosRuta['middleware'];
                        $roles = $datosRuta['roles'] ?? [];
                        $middleware = new $middlewareNombre($roles);
                        $middleware->handle();
                    }

                    // Filtra los parámetros de la URL (como 'id')
                    $parametros = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    try {
                        if (isset($datosRuta['controlador'])) {
                            $controladorNombre = $datosRuta['controlador'];
                            $accionNombre = $datosRuta['accion'];
                            $controlador = new $controladorNombre();
                            // Llama a la acción del controlador con los parámetros
                            call_user_func_array([$controlador, $accionNombre], [$parametros]);
                        } elseif (isset($datosRuta['vista'])) {
                            $this->viewRenderer->render($datosRuta['vista'], $datosRuta['vistaData'] ?? []);
                        }
                        return; // Ruta encontrada y procesada, termina la ejecución
                    } catch (Exception $e) {
                        // Manejo de errores básico
                        header("HTTP/1.1 500 Internal Server Error");
                        error_log($e->getMessage());
                        echo "Error en el servidor: " . $e->getMessage();
                        return;
                    }
                }
            }
        }
        $this->errorRutaNoEncontrada($metodoSolicitado, $rutaSolicitada);
    }

    protected function errorRutaNoEncontrada($metodo, $ruta)
    {
        header("HTTP/1.0 404 Not Found");
        $this->viewRenderer->render('404', ['metodo' => $metodo, 'ruta' => $ruta, 'layout' => false, 'titulo' => 'Página no encontrada']);
    }

    // Métodos de ayuda para definir rutas más limpiamente
    public function get($ruta, $opciones)
    {


        $this->agregarRuta('GET', $ruta, $opciones);
    }
    public function post($ruta, $opciones)
    {
        $this->agregarRuta('POST', $ruta, $opciones);
    }
    public function put($ruta, $opciones)
    {
        $this->agregarRuta('PUT', $ruta, $opciones);
    }
    public function delete($ruta, $opciones)
    {
        $this->agregarRuta('DELETE', $ruta, $opciones);
    }
}
