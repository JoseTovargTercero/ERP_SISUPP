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
        $rutaCompleta = rtrim($prefix, '/') . '/' . ltrim($ruta, '/');
        if ($prefix && ($ruta === '' || $ruta === '/')) {
            $rutaCompleta = $prefix;
        }

        $rutaRegex = $this->rutaARegex($rutaCompleta);

        if (is_string($opciones)) {
            $opciones = ['vista' => $opciones];
        }

        if (isset($this->groupAttributes['middleware']) && !isset($opciones['middleware'])) {
            $opciones['middleware'] = $this->groupAttributes['middleware'];
        }
        // No es necesario heredar roles aquí, el middleware los manejará internamente

        $this->rutas[$metodo][$rutaRegex] = [
            'vista' => $opciones['vista'] ?? null,
            'vistaData' => $opciones['vistaData'] ?? [],
            'controlador' => $opciones['controlador'] ?? null,
            'accion' => $opciones['accion'] ?? null,
            'middleware' => $opciones['middleware'] ?? null,
            'rutaOriginal' => $ruta, // Guardamos la ruta original para el middleware
        ];
    }

    protected function rutaARegex($ruta)
    {
        $rutaRegex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $ruta);
        return '#^' . $rutaRegex . '$#i';
    }

    public function route()
    {
        $metodoSolicitado = strtoupper($_SERVER['REQUEST_METHOD']);
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if ($this->basePath && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }

        $rutaSolicitada = '/' . trim($uri, '/');
        if (empty(trim($uri, '/'))) {
            $rutaSolicitada = '/';
        }

        if (isset($this->rutas[$metodoSolicitado])) {
            foreach ($this->rutas[$metodoSolicitado] as $rutaRegex => $datosRuta) {
                if (preg_match($rutaRegex, $rutaSolicitada, $matches)) {
                    if (isset($datosRuta['middleware'])) {
                        $middlewareNombre = $datosRuta['middleware'];
                        $middleware = new $middlewareNombre();

                        // ===== CAMBIO CLAVE AQUÍ =====
                        // Inyectamos la ruta solicitada al método handle del middleware
                        $middleware->handle($rutaSolicitada);
                        // ============================
                    }

                    $parametros = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    try {
                        if (isset($datosRuta['controlador'])) {
                            $controladorNombre = $datosRuta['controlador'];
                            $accionNombre = $datosRuta['accion'];
                            $controlador = new $controladorNombre();
                            call_user_func_array([$controlador, $accionNombre], [$parametros]);
                        } elseif (isset($datosRuta['vista'])) {
                            $this->viewRenderer->render($datosRuta['vista'], $datosRuta['vistaData'] ?? []);
                        }
                        return;
                    } catch (Exception $e) {
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