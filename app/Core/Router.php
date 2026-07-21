<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\Middleware;
use InvalidArgumentException;
use LogicException;

/**
 * Registers and dispatches the application's minimal HTTP routes.
 */
final class Router
{
    private const ALLOWED_METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * @var list<array{
     *     method: string,
     *     pattern: string,
     *     regex: string,
     *     parameters: list<string>,
     *     handler: callable,
     *     middleware: list<Middleware>
     * }>
     */
    private array $routes = [];

    /**
     * Registers a route for one approved HTTP method.
     *
     * @param list<Middleware> $middleware
     */
    public function add(string $method, string $pattern, callable $handler, array $middleware = []): self
    {
        $normalizedMethod = strtoupper(trim($method));

        if (!in_array($normalizedMethod, self::ALLOWED_METHODS, true)) {
            throw new InvalidArgumentException('The route HTTP method is not supported.');
        }

        if (!array_is_list($middleware)) {
            throw new InvalidArgumentException('Route middleware must be provided as a list.');
        }

        foreach ($middleware as $item) {
            if (!$item instanceof Middleware) {
                throw new InvalidArgumentException('Route middleware must implement the Middleware interface.');
            }
        }

        [$normalizedPattern, $regex, $parameters] = $this->compilePattern($pattern);

        foreach ($this->routes as $route) {
            if ($route['method'] === $normalizedMethod && $route['pattern'] === $normalizedPattern) {
                throw new LogicException(sprintf('Duplicate route registration for %s %s.', $normalizedMethod, $normalizedPattern));
            }
        }

        $this->routes[] = [
            'method' => $normalizedMethod,
            'pattern' => $normalizedPattern,
            'regex' => $regex,
            'parameters' => $parameters,
            'handler' => $handler,
            'middleware' => $middleware,
        ];

        return $this;
    }

    /**
     * Registers a GET route, which also permits HEAD requests.
     *
     * @param list<Middleware> $middleware
     */
    public function get(string $pattern, callable $handler, array $middleware = []): self
    {
        return $this->add('GET', $pattern, $handler, $middleware);
    }

    /**
     * Registers a POST route.
     *
     * @param list<Middleware> $middleware
     */
    public function post(string $pattern, callable $handler, array $middleware = []): self
    {
        return $this->add('POST', $pattern, $handler, $middleware);
    }

    /**
     * Registers a PUT route.
     *
     * @param list<Middleware> $middleware
     */
    public function put(string $pattern, callable $handler, array $middleware = []): self
    {
        return $this->add('PUT', $pattern, $handler, $middleware);
    }

    /**
     * Registers a PATCH route.
     *
     * @param list<Middleware> $middleware
     */
    public function patch(string $pattern, callable $handler, array $middleware = []): self
    {
        return $this->add('PATCH', $pattern, $handler, $middleware);
    }

    /**
     * Registers a DELETE route.
     *
     * @param list<Middleware> $middleware
     */
    public function delete(string $pattern, callable $handler, array $middleware = []): self
    {
        return $this->add('DELETE', $pattern, $handler, $middleware);
    }

    /**
     * Dispatches a request to a matching route or returns 404/405.
     */
    public function dispatch(Request $request): Response
    {
        $allowedForPath = [];

        foreach ($this->routes as $route) {
            $matches = [];

            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }

            $allowedForPath[] = $route['method'];

            if ($route['method'] === 'GET') {
                $allowedForPath[] = 'HEAD';
            }

            $methodMatches = $request->method() === $route['method']
                || ($request->method() === 'HEAD' && $route['method'] === 'GET');

            if (!$methodMatches) {
                continue;
            }

            $parameters = [];

            foreach ($route['parameters'] as $parameter) {
                $decodedValue = rawurldecode((string) $matches[$parameter]);

                if (
                    $decodedValue === '.'
                    || $decodedValue === '..'
                    || str_contains($decodedValue, '/')
                    || str_contains($decodedValue, '\\')
                    || str_contains($decodedValue, "\0")
                    || preg_match('/[\x00-\x1F\x7F]/', $decodedValue) === 1
                ) {
                    return Response::text('Not Found', 404);
                }

                $parameters[$parameter] = $decodedValue;
            }

            $next = static function (Request $request) use ($route, $parameters): Response {
                $response = ($route['handler'])($request, $parameters);

                if (!$response instanceof Response) {
                    throw new LogicException('Route handlers must return a Response instance.');
                }

                return $response;
            };

            foreach (array_reverse($route['middleware']) as $middleware) {
                $downstream = $next;
                $next = static fn (Request $request): Response => $middleware->process($request, $downstream);
            }

            $response = $next($request);

            return $request->method() === 'HEAD' ? $response->withoutBody() : $response;
        }

        if ($allowedForPath !== []) {
            $allowedForPath = array_values(array_unique($allowedForPath));
            $methodOrder = array_flip(self::ALLOWED_METHODS);
            usort($allowedForPath, static fn (string $left, string $right): int => $methodOrder[$left] <=> $methodOrder[$right]);

            return Response::text('Method Not Allowed', 405, [
                'Allow' => implode(', ', $allowedForPath),
            ]);
        }

        return Response::text('Not Found', 404);
    }

    /**
     * @return array{string, string, list<string>}
     */
    private function compilePattern(string $pattern): array
    {
        if (
            $pattern === ''
            || !str_starts_with($pattern, '/')
            || str_contains($pattern, "\0")
            || str_contains($pattern, '?')
            || str_contains($pattern, '#')
            || str_contains($pattern, '\\')
        ) {
            throw new InvalidArgumentException('Route pattern is invalid.');
        }

        $parameters = [];
        $compiledSegments = [];

        foreach (explode('/', ltrim($pattern, '/')) as $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $matches) === 1) {
                $parameter = $matches[1];

                if (in_array($parameter, $parameters, true)) {
                    throw new InvalidArgumentException('Route parameter names must be unique.');
                }

                $parameters[] = $parameter;
                $compiledSegments[] = sprintf('(?P<%s>[^/]+)', $parameter);
                continue;
            }

            if (str_contains($segment, '{') || str_contains($segment, '}')) {
                throw new InvalidArgumentException('Route parameter syntax is invalid.');
            }

            $compiledSegments[] = preg_quote($segment, '~');
        }

        $normalizedPattern = $pattern;
        $compiledPath = $pattern === '/' ? '' : implode('/', $compiledSegments);
        $regex = $pattern === '/' ? '~^/$~D' : sprintf('~^/%s$~D', $compiledPath);

        return [$normalizedPattern, $regex, $parameters];
    }
}
