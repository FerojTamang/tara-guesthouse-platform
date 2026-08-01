<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\CmsException;
use JsonException;

/** Defines one explicitly approved setting key and its typed validation contract. */
final readonly class SettingDefinition
{
    public function __construct(
        private string $key,
        private string $valueType,
        private bool $public,
        private int $maximumLength,
    ) {
        if (
            preg_match('/^[a-z][a-z0-9]*(?:\.[a-z0-9]+)*$/D', $key) !== 1
            || mb_strlen($key, 'UTF-8') > 150
            || !in_array($valueType, SiteSetting::VALUE_TYPES, true)
            || $maximumLength <= 0
            || $maximumLength > 1000000
        ) {
            throw CmsException::invalidInput('The setting definition is invalid.');
        }
    }

    public function key(): string { return $this->key; }
    public function valueType(): string { return $this->valueType; }
    public function isPublic(): bool { return $this->public; }
    public function maximumLength(): int { return $this->maximumLength; }

    public function normalize(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            throw CmsException::invalidInput('The setting value is invalid.');
        }

        $normalized = match ($this->valueType) {
            'string' => $this->requiredText($value),
            'integer' => $this->integer($value),
            'boolean' => $this->boolean($value),
            'email' => $this->email($value),
            'url' => $this->url($value),
            'relative_path' => $this->relativePath($value),
            'json' => $this->json($value),
            default => throw CmsException::invalidInput('The setting value type is invalid.'),
        };

        if (mb_strlen($normalized, 'UTF-8') > $this->maximumLength) {
            throw CmsException::invalidInput('The setting value exceeds its approved limit.');
        }

        return $normalized;
    }

    private function requiredText(string $value): string
    {
        if ($value === '' || $value !== trim($value)) {
            throw CmsException::invalidInput('The setting value is required and must be trimmed.');
        }

        return $value;
    }

    private function integer(string $value): string
    {
        if (preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) !== 1) {
            throw CmsException::invalidInput('The setting value must be an integer.');
        }

        return $value === '-0' ? '0' : $value;
    }

    private function boolean(string $value): string
    {
        if (!in_array($value, ['true', 'false'], true)) {
            throw CmsException::invalidInput('The setting value must be true or false.');
        }

        return $value;
    }

    private function email(string $value): string
    {
        $value = $this->requiredText($value);

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw CmsException::invalidInput('The setting value must be a valid email address.');
        }

        return strtolower($value);
    }

    private function url(string $value): string
    {
        $value = $this->requiredText($value);
        $parts = parse_url($value);

        if (
            filter_var($value, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw CmsException::invalidInput('The setting value must be a safe HTTP URL.');
        }

        return $value;
    }

    private function relativePath(string $value): string
    {
        $value = $this->requiredText($value);

        if (
            str_starts_with($value, '/')
            || str_starts_with($value, '\\')
            || str_contains($value, '\\')
            || str_contains($value, '//')
        ) {
            throw CmsException::invalidInput('The setting value must be a safe relative path.');
        }

        foreach (explode('/', $value) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw CmsException::invalidInput('The setting value must be a safe relative path.');
            }
        }

        return $value;
    }

    private function json(string $value): string
    {
        if ($value === '' || $value !== trim($value)) {
            throw CmsException::invalidInput('The setting value must be valid JSON.');
        }

        try {
            $decoded = json_decode($value, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw CmsException::invalidInput('The setting value must be valid JSON.');
        }

        if (!is_array($decoded) && !is_object($decoded)) {
            throw CmsException::invalidInput('The setting JSON value must be an object or array.');
        }

        try {
            return json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw CmsException::invalidInput('The setting value must be valid JSON.');
        }
    }
}
