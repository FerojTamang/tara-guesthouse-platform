<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use App\Models\SiteSetting;
use App\Repositories\SettingRepository;
use Throwable;

/** Coordinates allowlisted, typed site-setting reads and writes. */
final readonly class CmsSettingsService
{
    public function __construct(
        private SettingRepository $repository,
        private SettingDefinitionRegistry $definitions,
    ) {
    }

    /** @return list<SiteSetting> */
    public function listAll(): array
    {
        return $this->execute(function (): array {
            $settings = [];
            foreach ($this->repository->findAll() as $setting) {
                $settings[] = $this->validateStoredSetting($setting);
            }
            return $settings;
        });
    }

    /** @return list<\App\Models\SettingDefinition> */
    public function definitions(): array
    {
        return $this->definitions->all();
    }

    /** @return list<SiteSetting> */
    public function listPublic(): array
    {
        return $this->execute(function (): array {
            $public = [];
            foreach ($this->repository->findAllPublic() as $setting) {
                $setting = $this->validateStoredSetting($setting);
                if ($setting->isPublic()) {
                    $public[] = $setting;
                }
            }
            return $public;
        });
    }

    public function get(string $key): SiteSetting
    {
        return $this->execute(function () use ($key): SiteSetting {
            $this->definitions->get($key);
            $setting = $this->repository->findByKey($key);
            if (!$setting instanceof SiteSetting) {
                throw CmsException::settingNotFound();
            }
            return $this->validateStoredSetting($setting);
        });
    }

    public function upsert(string $key, string $value, int $actorId): SiteSetting
    {
        return $this->execute(function () use ($key, $value, $actorId): SiteSetting {
            if ($actorId <= 0) {
                throw CmsException::invalidInput('A valid Administrator actor is required.');
            }

            $definition = $this->definitions->get($key);
            $setting = $this->repository->upsert(
                $key,
                $definition->normalize($value),
                $definition->valueType(),
                $definition->isPublic(),
                $actorId,
            );

            if (
                $setting->key() !== $definition->key()
                || $setting->valueType() !== $definition->valueType()
                || $setting->isPublic() !== $definition->isPublic()
                || $setting->updatedBy() !== $actorId
            ) {
                throw CmsException::failure();
            }

            return $setting;
        });
    }


    private function validateStoredSetting(mixed $setting): SiteSetting
    {
        if (!$setting instanceof SiteSetting) {
            throw CmsException::failure();
        }

        try {
            $definition = $this->definitions->get($setting->key());
            $canonical = $definition->normalize($setting->value());
        } catch (CmsException $exception) {
            throw CmsException::failure($exception);
        }

        if (
            $setting->valueType() !== $definition->valueType()
            || $setting->isPublic() !== $definition->isPublic()
            || $canonical !== $setting->value()
        ) {
            throw CmsException::failure();
        }

        return $setting;
    }

    /** @template T @param callable(): T $operation @return T */
    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }
}
