<?php

declare(strict_types=1);

namespace App\Storage;

use App\Requests\ContentMediaUploadRequest;

/** Defines storage operations for validated public content images. */
interface ContentMediaStorage
{
    public function store(ContentMediaUploadRequest $request): StoredContentMedia;
    public function remove(string $relativePath): void;
}
