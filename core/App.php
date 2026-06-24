<?php
namespace App\Core;

/**
 * Minimal router / bootstrapper supporting route parameters such as
 * /jobs/{id} and named middleware.
 */
class App
{
    /** @var array<string, array<int, array{pattern:string, handler:callable, middleware:string[]}>> */
    private array $routes = [
        'GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => [], 'PATCH' => [],
    ];
    private array $middleware = [];
    private $notFound = null;

    public function get(string $path, $handler, array $middleware = []): void { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, $handler, array $middleware = []): void { $this->add('POST', $path, $handler, $middleware); }
    public function put(string $path, $handler, array $middleware = []): void { $this->add('PUT', $path, $handler, $middleware); }
    public function delete(string $path, $handler, array $middleware = []): void { $this->add('DELETE', $path, $handler, $middleware); }
    public function patch(string $path, $handler, array $middleware = []): void { $this->add('PATCH', $path, $handler, $middleware); }

    public function middleware(string $name, callable $handler): void
    {
        $this->middleware[$name] = $handler;
    }

    public function notFound(callable $handler): void
    {
        $this->notFound = $handler;
    }

    private function add(string $method, string $path, $handler, array $middleware): void
    {
        $pattern = $this->compile($path);
        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    private function compile(string $path): string
    {
        $path = '/' . trim($path, '/');
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $regex . '/?$#';
    }

    public function run(?string $method = null, ?string $uri = null): void
    {
        $method = $method ?? strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $uri ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $uri = '/' . trim($uri, '/');
        if ($uri === '/') {
            $uri = '/';
        }

        // Method override for HTML forms.
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = [];
                foreach ($matches as $k => $v) {
                    if (!is_int($k)) {
                        $params[$k] = $v;
                    }
                }
                foreach ($route['middleware'] as $mw) {
                    if (isset($this->middleware[$mw])) {
                        $result = ($this->middleware[$mw])($params);
                        if ($result === false) {
                            return;
                        }
                    }
                }
                $this->invoke($route['handler'], $params);
                return;
            }
        }

        if ($this->notFound) {
            ($this->notFound)();
        } else {
            Response::error('Not Found', 404);
        }
    }

    private function invoke($handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $params);
            return;
        }
        // "Class@method" string form.
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$class, $method] = explode('@', $handler);
            $instance = new $class();
            $instance->$method($params);
            return;
        }
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = is_object($class) ? $class : new $class();
            $instance->$method($params);
            return;
        }
        Response::error('Invalid route handler', 500);
    }
}
