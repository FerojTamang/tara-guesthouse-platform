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
     * @param array<string, UploadedFile|array<array-key, mixed>> $files
     */
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $headers,
        private readonly array $input,
        private readonly string $body,
        private readonly ?string $remoteAddress,
        private readonly array $files,
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

        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        return self::create(
            $method,
            $uri,
            $_GET,
            $headers,
            $_POST,
            $body,
            is_string($remoteAddress) ? $remoteAddress : null,
            $_FILES,
        );
    }

    /**
     * Creates a request from explicit test-friendly values.
     *
     * @param array<string, mixed>|null $query
     * @param array<string, string> $headers
     * @param array<string, mixed> $input
     * @param array<string, mixed> $files
     */
    public static function create(
        string $method,
        string $uri,
        ?array $query = null,
        array $headers = [],
        array $input = [],
        string $body = '',
        ?string $remoteAddress = null,
        array $files = [],
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

        if ($remoteAddress !== null && filter_var($remoteAddress, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Remote address is invalid.');
        }

        $normalizedFiles = self::normalizeFiles($files);

        return new self(
            $normalizedMethod,
            $path,
            $query,
            $normalizedHeaders,
            $input,
            $body,
            $remoteAddress,
            $normalizedFiles,
        );
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
     * Returns all normalized uploads or one named upload tree.
     */
    public function file(?string $key = null): UploadedFile|array|null
    {
        if ($key === null) {
            return $this->files;
        }

        return $this->files[$key] ?? null;
    }

    /**
     * Returns the direct peer IP supplied by REMOTE_ADDR, when available.
     */
    public function remoteAddress(): ?string
    {
        return $this->remoteAddress;
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

    /**
     * @param array<string, mixed> $files
     * @return array<string, UploadedFile|array<array-key, mixed>>
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $field => $file) {
            if (!is_string($field) || !is_array($file)) {
                throw new InvalidArgumentException('Uploaded file structure is invalid.');
            }

            $normalized[$field] = self::normalizeFileNode($file);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $file
     * @return UploadedFile|array<array-key, mixed>
     */
    private static function normalizeFileNode(array $file): UploadedFile|array
    {
        $keys = ['name', 'type', 'tmp_name', 'error', 'size'];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $file)) {
                throw new InvalidArgumentException('Uploaded file structure is invalid.');
            }
        }

        $arrayFields = array_map(static fn (string $key): bool => is_array($file[$key]), $keys);

        if (in_array(true, $arrayFields, true)) {
            if (in_array(false, $arrayFields, true)) {
                throw new InvalidArgumentException('Uploaded file structure is invalid.');
            }

            $nodeKeys = array_keys($file['name']);

            foreach ($keys as $key) {
                if (array_keys($file[$key]) !== $nodeKeys) {
                    throw new InvalidArgumentException('Uploaded file structure is invalid.');
                }
            }

            $normalized = [];

            foreach ($nodeKeys as $nodeKey) {
                $child = [];

                foreach ($keys as $key) {
                    $child[$key] = $file[$key][$nodeKey];
                }

                $normalized[$nodeKey] = self::normalizeFileNode($child);
            }

            return $normalized;
        }

        if (
            !is_string($file['name'])
            || !is_string($file['type'])
            || !is_string($file['tmp_name'])
            || !is_int($file['error'])
            || !is_int($file['size'])
        ) {
            throw new InvalidArgumentException('Uploaded file structure is invalid.');
        }

        return new UploadedFile(
            $file['name'],
            $file['type'],
            $file['tmp_name'],
            $file['error'],
            $file['size'],
        );
    }
}
