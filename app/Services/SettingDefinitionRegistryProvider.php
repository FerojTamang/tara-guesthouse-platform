<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Environment;
use App\Exceptions\CmsException;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use Throwable;

/** Resolves the required exact setting definition map from environment configuration. */
final class SettingDefinitionRegistryProvider
{
    private ?SettingDefinitionRegistry $registry = null;

    public function __construct(
        private readonly string $basePath,
        private readonly ?Environment $environment = null,
    ) {
    }

    public function registry(): SettingDefinitionRegistry
    {
        if ($this->registry instanceof SettingDefinitionRegistry) {
            return $this->registry;
        }

        try {
            $descriptor = $this->descriptor();
            $environment = $this->environment ?? Environment::load($this->basePath);
            $json = $environment->literalString($descriptor['definitions_environment_key'], '');

            if ($json === '' || $json !== trim($json)) {
                throw CmsException::failure();
            }

            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);

            if (!is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
                throw CmsException::failure();
            }

            $definitions = [];

            foreach ($decoded as $key => $definition) {
                $definitionKeys = is_array($definition) ? array_keys($definition) : [];
                sort($definitionKeys, SORT_STRING);

                if (
                    !is_string($key)
                    || !is_array($definition)
                    || $definitionKeys !== ['is_public', 'maximum_length', 'value_type']
                    || !is_string($definition['value_type'])
                    || !is_bool($definition['is_public'])
                    || !is_int($definition['maximum_length'])
                    || !in_array($definition['value_type'], $descriptor['supported_value_types'], true)
                ) {
                    throw CmsException::failure();
                }

                $definitions[] = new SettingDefinition(
                    $key,
                    $definition['value_type'],
                    $definition['is_public'],
                    $definition['maximum_length'],
                );
            }

            return $this->registry = new SettingDefinitionRegistry($definitions);
        } catch (CmsException $exception) {
            if ($exception->isSystemFailure()) {
                throw $exception;
            }

            throw CmsException::failure($exception);
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }

    /** @return array{definitions_environment_key: string, supported_value_types: list<string>} */
    private function descriptor(): array
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cms.php';

        if (!is_file($path)) {
            throw CmsException::failure();
        }

        $configuration = require $path;
        $settings = is_array($configuration) ? ($configuration['settings'] ?? null) : null;

        if (
            !is_array($settings)
            || array_keys($settings) !== ['definitions_environment_key', 'supported_value_types']
            || !is_string($settings['definitions_environment_key'])
            || $settings['definitions_environment_key'] !== 'CMS_SETTING_DEFINITIONS_JSON'
            || !is_array($settings['supported_value_types'])
            || $settings['supported_value_types'] !== SiteSetting::VALUE_TYPES
        ) {
            throw CmsException::failure();
        }

        return $settings;
    }
}
