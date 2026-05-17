<?php
/**
 * SociAI OS - URL Router
 * Fast pattern-matching router with middleware, parameters, and grouping.
 */

declare(strict_types=1);

namespace SociAI\Core;

class Router
{
    private array $routes      = [];
    private array $middleware  = [];
    private array $groupStack  = [];
    private array $namedRoutes = [];

    private static array $resolvedControllers = [];

    // --------------------------------------------------------
    // Route registration
    // --------------------------------------------------------
    public function get(string $path, callable|array|string $handler, array $middleware = []): static
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array|string $handler, array $middleware = []): static
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array|string $handler, array $middleware = []): static
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array|string $handler, array $middleware = []): static
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array|string $handler, array $middleware = []): static
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function any(string $path, callable|array|string $handler, array $middleware = []): static
    {
        foreach (['GET','POST','PUT','PATCH','DELETE'] as $method) {
            $this->addRoute($method, $path, $handler, $middleware);
        }
        return $this;
    }

    private function addRoute(string $method, string $path, mixed $handler, array $mw): static
    {
        // Apply group prefix/middleware
        $prefix = implode('', array_column($this->groupStack, 'prefix'));
        $groupMw = array_merge(...array_column($this->groupStack, 'middleware'));

        $fullPath   = $prefix . '/' . ltrim($path, '/');
        $fullPath   = '/' . ltrim($fullPath, '/');
        $fullPath   = rtrim($fullPath, '/') ?: '/';

        $this->routes[] = [
            'method'     => $method,
            'path'       => $fullPath,
            'pattern'    => $this->buildPattern($fullPath),
            'handler'    => $handler,
            'middleware' => array_merge($groupMw, $mw),
        ];
        return $this;
    }

    // --------------------------------------------------------
    // Route grouping
    // --------------------------------------------------------
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $this->groupStack[] = [
            'prefix'     => '/' . trim($prefix, '/'),
            'middleware' => $middleware,
        ];
        $callback($this);
        array_pop($this->groupStack);
    }

    // --------------------------------------------------------
    // Global middleware
    // --------------------------------------------------------
    public function use(string $name, callable $handler): void
    {
        $this->middleware[$name] = $handler;
    }

    // --------------------------------------------------------
    // Dispatch
    // --------------------------------------------------------
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = $this->normalizeUri($_SERVER['REQUEST_URI'] ?? '/');

        // Handle method override (_method in POST body or X-HTTP-Method-Override header)
        if ($method === 'POST') {
            $override = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
            if ($override && in_array(strtoupper($override), ['PUT','PATCH','DELETE'], true)) {
                $method = strtoupper($override);
            }
        }

        $matched      = false;
        $methodExists = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }
            $methodExists = true;
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            $matched = true;

            // Extract named params
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            // Run middleware chain
            $middlewareQueue = $route['middleware'];
            $handler         = $route['handler'];

            $this->runMiddleware($middlewareQueue, $params, $handler);
            return;
        }

        if ($methodExists) {
            $this->handle405($method, $uri);
        } else {
            $this->handle404($uri);
        }
    }

    // --------------------------------------------------------
    // Middleware execution
    // --------------------------------------------------------
    private function runMiddleware(array $queue, array $params, mixed $finalHandler): void
    {
        $run = function(array $queue) use ($params, $finalHandler, &$run): void {
            if (empty($queue)) {
                $this->callHandler($finalHandler, $params);
                return;
            }
            $name = array_shift($queue);
            $mwFn = $this->middleware[$name] ?? null;
            if ($mwFn === null) {
                // Unknown middleware — skip
                $run($queue);
                return;
            }
            $mwFn($params, fn() => $run($queue));
        };
        $run($queue);
    }

    // --------------------------------------------------------
    // Handler calling
    // --------------------------------------------------------
    private function callHandler(mixed $handler, array $params): void
    {
        if (is_callable($handler)) {
            $result = $handler($params);
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $fqcn = str_contains($class, '\\') ? $class : 'SociAI\\Controllers\\' . $class;
            $controller = self::$resolvedControllers[$fqcn]
                ?? (self::$resolvedControllers[$fqcn] = new $fqcn());
            $result = $controller->$method($params);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $fqcn = is_object($class) ? get_class($class) : (str_contains($class, '\\') ? $class : 'SociAI\\Controllers\\' . $class);
            $instance = is_object($class) ? $class : (self::$resolvedControllers[$fqcn] ?? (self::$resolvedControllers[$fqcn] = new $fqcn()));
            $result = $instance->$method($params);
        } else {
            throw new \RuntimeException("Invalid route handler.");
        }
    }

    // --------------------------------------------------------
    // Pattern builder
    // --------------------------------------------------------
    private function buildPattern(string $path): string
    {
        // Convert {param} to named regex groups
        $pattern = preg_replace_callback(
            '/\{(\w+)(\?)?\}/',
            function($matches) {
                $name     = $matches[1];
                $optional = isset($matches[2]) ? '?' : '';
                return "(?P<{$name}>[^/]+){$optional}";
            },
            $path
        );
        return '#^' . $pattern . '/?$#';
    }

    private function normalizeUri(string $uri): string
    {
        $uri = strtok($uri, '?') ?: '/';
        $uri = '/' . ltrim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    // --------------------------------------------------------
    // Error Handlers
    // --------------------------------------------------------
    private function handle404(string $uri): void
    {
        http_response_code(404);
        if ($this->isApiRequest()) {
            $this->jsonError('Route not found.', 404);
            return;
        }
        $this->renderError(404, 'Page Not Found', "The page <code>" . htmlspecialchars($uri) . "</code> does not exist.");
    }

    private function handle405(string $method, string $uri): void
    {
        http_response_code(405);
        if ($this->isApiRequest()) {
            $this->jsonError("Method {$method} not allowed.", 405);
            return;
        }
        $this->renderError(405, 'Method Not Allowed', "HTTP method <code>{$method}</code> is not allowed for this route.");
    }

    private function isApiRequest(): bool
    {
        $accept  = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri     = $_SERVER['REQUEST_URI'] ?? '';
        return str_starts_with($uri, '/api/') || str_contains($accept, 'application/json');
    }

    private function jsonError(string $message, int $code): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message, 'code' => $code]);
        exit;
    }

    private function renderError(int $code, string $title, string $detail): void
    {
        $viewFile = VIEWS_PATH . "/errors/{$code}.php";
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "<!DOCTYPE html><html><head><title>{$code} {$title}</title></head>";
            echo "<body><h1>{$code} {$title}</h1><p>{$detail}</p></body></html>";
        }
        exit;
    }

    // --------------------------------------------------------
    // URL generation
    // --------------------------------------------------------
    public function url(string $name, array $params = []): string
    {
        $path = $this->namedRoutes[$name] ?? $name;
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string)$value, $path);
        }
        return APP_URL . $path;
    }
}
