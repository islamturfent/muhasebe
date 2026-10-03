<?php

declare(strict_types=1);

namespace Muh\Core;

/**
 * Minimal HTTP router with middleware support and named routes.
 */
final class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function add(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $this->routes[] = [
            'method' => strtoupper($method),
            'path'   => $path,
            'handler'=> $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    public function group(array $attributes, callable $registrar): void
    {
        $registrar(new RouterGroup($this, $attributes));
    }

    public function addGroup(string $prefix, array $methods): void
    {
        foreach ($methods as $m => $path) {
            $this->add(method: $m == '*' ? 'ANY' : $m, path: $path, handler: []);
        }
    }

    public function dispatch(string $method, string $path, Request $request): Response
    {
        $method = strtoupper($method);
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }
            $params = $this->match($route['path'], $path);
            if ($params === null) {
                continue;
            }

            // Run middleware
            $handler = $route['handler'];
            foreach ($route['middleware'] as $mwClass) {
                $mw = new $mwClass();
                $result = $mw->handle($request);
                if ($result instanceof Response) {
                    return $result;
                }
            }

            return $this->invoke($handler, $request, $params);
        }

        return Response::json(['error' => 'not_found', 'message' => 'Route not found'], 404);
    }

    private function invoke(callable|array $handler, Request $request, array $params): Response
    {
        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
            $controller = new $handler[0]();
            // Append any extra handler args (e.g. a fixed 'kind' parameter) after the route params.
            $args = array_merge(array_values($params), array_slice($handler, 2));
            $result = $controller->{$handler[1]}($request, ...$args);
        } else {
            $result = $handler($request, ...array_values($params));
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }
        return Response::make((string) $result);
    }

    private function match(string $pattern, string $path): ?array
    {
        if ($pattern === '/') {
            return $path === '/' ? [] : null;
        }

        $patternParts = explode('/', $pattern);
        $pathParts = explode('/', $path);

        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];
        foreach ($patternParts as $i => $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $name = trim($part, '{}');
                // 'id' placeholders must be numeric, otherwise static sub-paths
                // like /app/inventory/warehouses or /create would be captured.
                if ($name === 'id' && !ctype_digit($pathParts[$i])) {
                    return null;
                }
                $params[$name] = urldecode($pathParts[$i]);
            } elseif ($part !== $pathParts[$i]) {
                return null;
            }
        }
        return $params;
    }

    public function routes(): array
    {
        return $this->routes;
    }
}

/**
 * Router group helper that prefixes paths and inherits middleware.
 */
final class RouterGroup
{
    private Router $router;
    private array $attributes;

    public function __construct(Router $router, array $attributes)
    {
        $this->router = $router;
        $this->attributes = $attributes;
    }

    private function path(string $path): string
    {
        $prefix = $this->attributes['prefix'] ?? '';
        return '/' . trim($prefix, '/') . '/' . trim($path, '/');
    }

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->router->get($this->path($path), $handler, $this->merge($middleware));
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->router->post($this->path($path), $handler, $this->merge($middleware));
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->router->put($this->path($path), $handler, $this->merge($middleware));
    }

    public function patch(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->router->patch($this->path($path), $handler, $this->merge($middleware));
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->router->delete($this->path($path), $handler, $this->merge($middleware));
    }

    private function merge(array $middleware): array
    {
        return array_merge($this->attributes['middleware'] ?? [], $middleware);
    }
}
