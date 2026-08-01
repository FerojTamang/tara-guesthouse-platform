<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SiteSetting;

/** Defines persistence operations for allowlisted site settings. */
interface SettingRepository
{
    public function findByKey(string $key): ?SiteSetting;

    /** @return list<SiteSetting> */
    public function findAll(): array;

    /** @return list<SiteSetting> */
    public function findAllPublic(): array;

    public function upsert(
        string $key,
        string $value,
        string $valueType,
        bool $public,
        int $actorId,
    ): SiteSetting;
}
