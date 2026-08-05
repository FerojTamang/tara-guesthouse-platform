<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PublishedContentItem;

/** Reads publication-ready news and notice entries without exposing private content. */
interface PublicContentCatalogRepository
{
    /** @return list<PublishedContentItem> */
    public function findAllPublishedByType(string $contentType): array;
}
