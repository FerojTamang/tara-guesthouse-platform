<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use JsonException;

/**
 * Represents an immutable HTTP response.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $statusCode = 200,
        array $headers = [],
    ) {
        self::validateStatusCode($statusCode);

        $validatedHeaders = [];

        foreach ($headers as $name => $value) {
            self::validateHeader($name, $value);

            foreach (array_keys($validatedHeaders) as $existingName) {
                if (strcasecmp($existingName, $name) === 0) {
                    throw new InvalidArgumentException('HTTP response header names must be unique.');
                }
            }

            $validatedHeaders[$name] = $value;
        }

        $this->headers = $validatedHeaders;
    }

    /** @var array<string, string> */
    private readonly array $headers;

    /**
     * Creates a UTF-8 plain-text response.
     *
     * @param array<string, string> $headers
     */
    public static function text(string $body, int $statusCode = 200, array $headers = []): self
    {
        if (!self::hasHeader($headers, 'Content-Type')) {
            $headers['Content-Type'] = 'text/plain; charset=UTF-8';
        }

        return new self($body, $statusCode, $headers);
    }

    /**
     * Creates a UTF-8 HTML response.
     *
     * @param array<string, string> $headers
     */
    public static function html(string $body, int $statusCode = 200, array $headers = []): self
    {
        if (!self::hasHeader($headers, 'Content-Type')) {
            $headers['Content-Type'] = 'text/html; charset=UTF-8';
        }

        return new self($body, $statusCode, $headers);
    }

    /**
     * Creates a UTF-8 JSON response.
     *
     * @param array<string, string> $headers
     *
     * @throws JsonException
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        if (!self::hasHeader($headers, 'Content-Type')) {
            $headers['Content-Type'] = 'application/json; charset=UTF-8';
        }

        $body = json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return new self($body, $statusCode, $headers);
    }

    /**
     * Creates an internal application redirect.
     *
     * @param array<string, string> $headers
     */
    public static function redirect(string $location, int $statusCode = 302, array $headers = []): self
    {
        if (!in_array($statusCode, [301, 302, 303, 307, 308], true)) {
            throw new InvalidArgumentException('Redirect status code is invalid.');
        }

        if (
            $location === ''
            || !str_starts_with($location, '/')
            || str_starts_with($location, '//')
            || str_contains($location, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $location) === 1
        ) {
            throw new InvalidArgumentException('Redirect location must be a safe internal path.');
        }

        if (self::hasHeader($headers, 'Location')) {
            throw new InvalidArgumentException('Redirect Location header is managed by the response.');
        }

        $headers['Location'] = $location;

        return new self('', $statusCode, $headers);
    }

    /**
     * Returns the response status code.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Returns the response body.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Returns a copy containing the supplied header.
     */
    public function withHeader(string $name, string $value): self
    {
        self::validateHeader($name, $value);

        $headers = $this->headers;

        foreach (array_keys($headers) as $existingName) {
            if (strcasecmp($existingName, $name) === 0) {
                unset($headers[$existingName]);
            }
        }

        $headers[$name] = $value;

        return new self($this->body, $this->statusCode, $headers);
    }

    /**
     * Returns a copy without a response body, suitable for HEAD.
     */
    public function withoutBody(): self
    {
        return new self('', $this->statusCode, $this->headers);
    }

    /**
     * Delivers the response without terminating execution.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header(sprintf('%s: %s', $name, $value), true);
            }
        }

        echo $this->body;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function hasHeader(array $headers, string $expectedName): bool
    {
        foreach (array_keys($headers) as $name) {
            if (strcasecmp($name, $expectedName) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function validateStatusCode(int $statusCode): void
    {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new InvalidArgumentException('HTTP status codes must be between 100 and 599.');
        }
    }

    private static function validateHeader(string $name, string $value): void
    {
        if (preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/", $name) !== 1) {
            throw new InvalidArgumentException('HTTP header name is invalid.');
        }

        if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('HTTP header value contains prohibited control characters.');
        }
    }
}
