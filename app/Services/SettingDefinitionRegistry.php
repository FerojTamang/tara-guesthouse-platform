<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use App\Models\SettingDefinition;

/** Holds the exact environment-approved setting-key allowlist. */
final readonly class SettingDefinitionRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions;

    /** @param list<SettingDefinition> $definitions */
    public function __construct(array $definitions)
    {
        if ($definitions === []) {
            throw CmsException::invalidInput('No approved setting definitions are configured.');
        }

        $indexed = [];

        foreach ($definitions as $definition) {
            if (!$definition instanceof SettingDefinition || isset($indexed[$definition->key()])) {
                throw CmsException::invalidInput('The setting definitions are invalid.');
            }

            $indexed[$definition->key()] = $definition;
        }

        ksort($indexed, SORT_STRING);
        $this->definitions = $indexed;
    }

    public function get(string $key): SettingDefinition
    {
        $definition = $this->definitions[$key] ?? null;

        if (!$definition instanceof SettingDefinition) {
            throw CmsException::invalidInput('The setting key is not approved.');
        }

        return $definition;
    }

    /** @return list<SettingDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
