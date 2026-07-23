<?php

declare(strict_types=1);

namespace App\Presentation;

use InvalidArgumentException;

final class RoomImageUrl
{
    public function fromStoredPath(string $path): string
    {
        if (
            preg_match(
                '/\Auploads\/rooms\/[0-9a-f]{64}\.(?:jpg|png|webp)\z/D',
                $path,
            ) !== 1
        ) {
            throw new InvalidArgumentException('The room image path is invalid.');
        }

        return '/' . $path;
    }
}
