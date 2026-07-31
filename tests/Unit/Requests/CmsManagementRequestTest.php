<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\CmsException;
use App\Requests\CmsPageContentRequest;
use App\Requests\CmsPageStatusRequest;
use App\Requests\CmsSettingsUpdateRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CmsManagementRequestTest extends TestCase
{
    public function testSettingsPayloadIsExactTypedAndSorted(): void
    {
        $request = CmsSettingsUpdateRequest::fromInput([
            'hotel.phone' => '+977-56-000000',
            'contact.email' => 'stay@example.test',
        ]);

        self::assertSame([
            'contact.email' => 'stay@example.test',
            'hotel.phone' => '+977-56-000000',
        ], $request->values());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidSettings(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [[]];
        yield 'list' => [['value']];
        yield 'invalid key' => [['Bad Key' => 'value']];
        yield 'non-string value' => [['hotel.name' => true]];
    }

    #[DataProvider('invalidSettings')]
    public function testInvalidSettingsPayloadIsRejected(mixed $input): void
    {
        $this->expectException(CmsException::class);
        CmsSettingsUpdateRequest::fromInput($input);
    }

    public function testPageContentAndNullableMetadataArePreserved(): void
    {
        $request = CmsPageContentRequest::fromInput(
            'About Tara',
            'about-tara',
            "Welcome\nHome",
            '',
            null,
        );

        self::assertSame('About Tara', $request->title());
        self::assertSame('about-tara', $request->slug());
        self::assertSame("Welcome\nHome", $request->content());
        self::assertNull($request->metaTitle());
        self::assertNull($request->metaDescription());
    }

    public function testStatusRequestUsesOnlyCanonicalStatuses(): void
    {
        self::assertSame('published', CmsPageStatusRequest::fromInput('published')->status());

        $this->expectException(CmsException::class);
        CmsPageStatusRequest::fromInput('deleted');
    }
}
