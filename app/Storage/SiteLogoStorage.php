<?php

declare(strict_types=1);

namespace App\Storage;

use App\Requests\SiteLogoUploadRequest;

interface SiteLogoStorage
{
    public function store(SiteLogoUploadRequest $request): string;

    public function remove(string $relativePath): void;
}
