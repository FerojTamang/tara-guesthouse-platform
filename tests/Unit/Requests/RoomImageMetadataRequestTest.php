<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\RoomCatalogException;
use App\Requests\RoomImageMetadataRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoomImageMetadataRequestTest extends TestCase
{
    public function testItNormalizesValidMetadataAndBlankAlternativeText(): void
    {
        $request = RoomImageMetadataRequest::fromInput('7', '9', "  Courtyard view  ", '65535');
        self::assertSame(7, $request->roomId());
        self::assertSame(9, $request->imageId());
        self::assertSame('Courtyard view', $request->alternativeText());
        self::assertSame(65535, $request->sortOrder());
        self::assertNull(RoomImageMetadataRequest::fromInput(7, 9, '  ', 0)->alternativeText());
    }

    public function testItAcceptsMaximumAlternativeTextAndOrderBoundaries(): void
    {
        self::assertSame(
            str_repeat('a', 255),
            RoomImageMetadataRequest::fromInput(1, 1, str_repeat('a', 255), 0)->alternativeText(),
        );
        self::assertSame(0, RoomImageMetadataRequest::fromInput(1, 1, null, 0)->sortOrder());
    }

    #[DataProvider('invalidInputs')]
    public function testItRejectsInvalidInputs(mixed $roomId, mixed $imageId, mixed $altText, mixed $sortOrder): void
    {
        $this->expectException(RoomCatalogException::class);
        RoomImageMetadataRequest::fromInput($roomId, $imageId, $altText, $sortOrder);
    }

    public static function invalidInputs(): iterable
    {
        yield 'room zero' => [0, 1, null, 0];
        yield 'image zero' => [1, 0, null, 0];
        yield 'missing order' => [1, 1, null, null];
        yield 'blank order' => [1, 1, null, ' '];
        yield 'negative order' => [1, 1, null, '-1'];
        yield 'order overflow' => [1, 1, null, '65536'];
        yield 'decimal order' => [1, 1, null, '1.5'];
        yield 'overlong alt' => [1, 1, str_repeat('a', 256), 0];
        yield 'invalid utf8' => [1, 1, "\xC3\x28", 0];
    }
}
