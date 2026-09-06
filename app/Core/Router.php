<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

final class Router
{
    private array $routes = [];
    private array $namedRoutes = [];
    private array $prefixMiddleware = [];

    public function get(string $path, array|Closure $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('GET', $path, $handler, $middleware, $name);
    }

    public function post(string $path, array|Closure $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('POST', $path, $handler, $middleware, $name);
    }

    public function add(string $method, string $path, array|Closure $handler, array $middleware = [], ?string $name = null): self
    {
        $normalized = '/' . trim($path, '/');
        $route = compact('method', 'handler', 'middleware', 'name') + ['path' => $normalized];
        $this->routes[] = $route;
        if ($name !== null) {
            $this->namedRoutes[$name] = $normalized;
        }
        return $this;
    }

    public function named(string $name, array $parameters = []): string
    {
        $path = $this->namedRoutes[$name] ?? throw new \InvalidArgumentException("Unknown route name: {$name}");
        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', rawurlencode((string) $value), $path);
        }
        return $path;
    }

    public function middlewarePrefix(string $prefix, string $middlewareClass): self
    {
        $this->prefixMiddleware['/' . trim($prefix, '/')][] = $middlewareClass;
        return $this;
    }

    public function dispatch(Request $request, App $app): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }
            $pattern = preg_replace('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['path']);
            if (!preg_match('#^' . $pattern . '/?$#', $request->path(), $matches)) {
                continue;
            }
            $request->setRouteParameters(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            $middleware = $route['middleware'];
            foreach ($this->prefixMiddleware as $prefix => $classes) {
                if ($request->path() === $prefix || str_starts_with($request->path(), $prefix . '/')) {
                    $middleware = array_merge($classes, $middleware);
                }
            }
            $destination = fn (Request $resolvedRequest): Response => $this->invoke($route['handler'], $resolvedRequest, $app);
            foreach (array_reverse(array_unique($middleware)) as $middlewareClass) {
                $next = $destination;
                $destination = fn (Request $resolvedRequest): Response => (new $middlewareClass($app))->handle($resolvedRequest, $next);
            }
            return $destination($request);
        }
        return Response::html(View::render('errors/404', ['pageTitle' => 'Page Not Found'], 'layouts/error'), 404);
    }

    private function invoke(array|Closure $handler, Request $request, App $app): Response
    {
        if ($handler instanceof Closure) {
            $response = $handler($request);
        } else {
            [$class, $method] = $handler;
            $response = (new $class($app))->{$method}($request);
        }
        return $response instanceof Response ? $response : Response::html((string) $response);
    }
}
