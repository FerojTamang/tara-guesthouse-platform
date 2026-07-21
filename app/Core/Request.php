<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Represents immutable normalized HTTP request data.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @param array<string, mixed> $input
     */
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $headers,
        private readonly array $input,
        private readonly string $body,
    ) {
    }

    /**
     * Creates a request from PHP's server globals.
     */
    public static function fromGlobals(): self
    {
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[str_replace('_', '-', $key)] = $value;
            }
        }

        $body = file_get_contents('php://input');

        if ($body === false) {
            $body = '';
        }

        return self::create($method, $uri, $_GET, $headers, $_POST, $body);
    }

    /**
     * Creates a request from explicit test-friendly values.
     *
     * @param array<string, mixed>|null $query
     * @param array<string, string> $headers
     * @param array<string, mixed> $input
     */
    public static function create(
        string $method,
        string $uri,
        ?array $query = null,
        array $headers = [],
        array $input = [],
        string $body = '',
    ): self {
        $normalizedMethod = strtoupper(trim($method));

        if (preg_match('/^[A-Z][A-Z0-9!#$%&\'*+.^_`|~-]*$/', $normalizedMethod) !== 1) {
            throw new InvalidArgumentException('HTTP method is invalid.');
        }

        $path = self::normalizePath($uri);
        $normalizedHeaders = [];

        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new InvalidArgumentException('HTTP headers must use string names and values.');
            }

            $normalizedName = strtolower($name);
            self::validateHeader($normalizedName, $value);
            $normalizedHeaders[$normalizedName] = $value;
        }

        if ($query === null) {
            $query = [];
            $queryString = parse_url($uri, PHP_URL_QUERY);

            if (is_string($queryString)) {
                parse_str($queryString, $query);
            }
        }

        return new self($normalizedMethod, $path, $query, $normalizedHeaders, $input, $body);
    }

    /**
     * Returns the uppercase request method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Returns the validated URL path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Returns all query values or one named value.
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Returns all parsed body input or one named value.
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->input;
        }

        return $this->input[$key] ?? $default;
    }

    /**
     * Returns the exact raw request body.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Returns a normalized request header.
     */
    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    private static function normalizePath(string $uri): string
    {
        if (str_contains($uri, "\0") || preg_match('/%(?![0-9A-Fa-f]{2})/', $uri) === 1) {
            throw new InvalidArgumentException('Request URI is malformed.');
        }

        $path = parse_url($uri, PHP_URL_PATH);

        if ($path === false || !is_string($path)) {
            throw new InvalidArgumentException('Request URI path is malformed.');
        }

        if ($path === '') {
            $path = '/';
        }

        if (!str_starts_with($path, '/') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new InvalidArgumentException('Request path is invalid.');
        }

        $decodedPath = rawurldecode($path);

        if (str_contains($decodedPath, "\0") || str_contains($decodedPath, '\\')) {
            throw new InvalidArgumentException('Request path contains prohibited characters.');
        }

        foreach (explode('/', $decodedPath) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Request path traversal is prohibited.');
            }
        }

        return $path;
    }

    private static function validateHeader(string $name, string $value): void
    {
        if (preg_match("/^[!#$%&'*+.^_`|~0-9a-z-]+$/", $name) !== 1) {
            throw new InvalidArgumentException('HTTP header name is invalid.');
        }

        if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('HTTP header value contains prohibited control characters.');
        }
    }
}
