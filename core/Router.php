<?php
declare(strict_types=1);

/**
 * Router - Minimal HTTP router with path params, route groups and middleware.
 *
 * This project's front controller (public/index.php) performs its own
 * dispatch, so this class is provided as a self-contained, reusable router
 * matching the platform's flat (global-namespace) class convention. It works
 * with the flat Request/Response classes.
 *
 * Handlers may be closures or "Controller::method" strings. String handlers
 * are resolved as global classes (optionally found under MODULES_PATH).
 *
 * Path parameters use {name} syntax (optionally {name:regex}) and are passed
 * to handlers as an associative array.
 */
class Router
{
    /** @var array<int, array{method:string, regex:string, params:array, handler:mixed, middleware:array}> */
    protected array $routes = [];

    protected array $groupStack = [];

    protected array $middlewareAliases = [];

    /** @var callable|null */
    protected $notFoundHandler = null;

    protected Request $request;

    /** Route params for the matched route. */
    protected array $params = [];

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
    }

    public function middleware(string $name, callable $resolver): void
    {
        $this->middlewareAliases[$name] = $resolver;
    }

    public function get(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function match(array $methods, string $path, callable|string $handler, array $middleware = []): void
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler, $middleware);
        }
    }

    /**
     * Group routes under a shared prefix and middleware stack.
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $this->groupStack[] = ['prefix' => $prefix, 'middleware' => $middleware];
        $callback($this);
        array_pop($this->groupStack);
    }

    public function notFound(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    protected function addRoute(string $method, string $path, callable|string $handler, array $middleware): void
    {
        $prefix = '';
        $groupMiddleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }

        $full = '/' . trim($prefix . $path, '/');
        if ($full === '') {
            $full = '/';
        }

        [$regex, $params] = $this->compile($full);

        $this->routes[] = [
            'method'     => $method,
            'regex'      => $regex,
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
        ];
    }

    /**
     * Compile a route pattern into a regex + ordered parameter names.
     *
     * @return array{0:string,1:array<int,string>}
     */
    protected function compile(string $pattern): array
    {
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            function ($m) use (&$params) {
                $params[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $pattern
        );
        return ['#^' . $regex . '$#', $params];
    }

    /**
     * Match the current request to a route and execute it.
     */
    public function dispatch(): void
    {
        $method = $this->request->method();
        $path = $this->request->path();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            array_shift($matches);
            $params = [];
            foreach ($route['params'] as $i => $name) {
                $params[$name] = $matches[$i] ?? null;
            }
            $this->params = $params;

            if (!$this->runMiddleware($route['middleware'], $params)) {
                return;
            }

            $this->invoke($route['handler'], $params);
            return;
        }

        if (!empty($allowed)) {
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', array_unique($allowed)));
            }
            if ($this->expectsJson()) {
                Response::error('Method Not Allowed', 405);
            }
            http_response_code(405);
            echo '405 Method Not Allowed';
            exit;
        }

        $this->handleNotFound();
    }

    protected function runMiddleware(array $middleware, array $params): bool
    {
        foreach ($middleware as $mw) {
            $resolver = null;
            if (is_string($mw) && isset($this->middlewareAliases[$mw])) {
                $resolver = $this->middlewareAliases[$mw];
            } elseif (is_callable($mw)) {
                $resolver = $mw;
            }

            if ($resolver === null) {
                if ($this->expectsJson()) {
                    Response::error('Forbidden', 403);
                }
                Response::redirect('/unauthorized');
                return false;
            }

            if ($resolver($this->request, $params) === false) {
                if (!headers_sent()) {
                    if ($this->expectsJson()) {
                        Response::error('Forbidden', 403);
                    }
                    Response::redirect('/unauthorized');
                }
                return false;
            }
        }
        return true;
    }

    /**
     * Invoke a route handler (closure or "Controller::method" string).
     */
    protected function invoke(callable|string $handler, array $params): void
    {
        if (is_callable($handler)) {
            $handler($this->request, $params);
            return;
        }

        if (str_contains($handler, '::')) {
            [$class, $method] = explode('::', $handler, 2);
        } else {
            $class = $handler;
            $method = 'index';
        }

        // Resolve as a global class; optionally locate the file under modules.
        if (!class_exists($class) && defined('MODULES_PATH')) {
            $file = MODULES_PATH . '/' . str_replace('\\', '/', $class) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        }

        if (!class_exists($class)) {
            $this->serverError("Controller class not found: {$class}");
            return;
        }

        $instance = method_exists($class, $method) && (new \ReflectionMethod($class, $method))->isStatic()
            ? null
            : new $class();

        if (!method_exists($class, $method)) {
            $this->serverError("Method not found: {$class}::{$method}");
            return;
        }

        if ($instance === null) {
            $class::$method($this->request, $params);
        } else {
            $instance->{$method}($this->request, $params);
        }
    }

    protected function handleNotFound(): void
    {
        if ($this->notFoundHandler !== null) {
            ($this->notFoundHandler)($this->request);
            return;
        }
        if ($this->expectsJson()) {
            Response::error('Not Found', 404);
        }
        http_response_code(404);
        echo '404 Not Found';
        exit;
    }

    protected function expectsJson(): bool
    {
        return method_exists($this->request, 'expectsJson') && $this->request->expectsJson();
    }

    protected function serverError(string $message): void
    {
        http_response_code(500);
        $config = $GLOBALS['__app_config'] ?? [];
        if (!empty($config['debug'])) {
            echo '<pre style="padding:16px;font-family:monospace;">Router error: '
                . htmlspecialchars($message) . '</pre>';
        } else {
            echo 'Internal Server Error';
        }
        exit;
    }

    /**
     * Current matched route params.
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Static redirect helper (terminates).
     */
    public static function redirect(string $url, int $code = 302): never
    {
        Response::redirect($url, $code);
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
