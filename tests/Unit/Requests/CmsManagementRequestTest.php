<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\CmsException;
use App\Requests\CmsPageContentRequest;
use App\Requests\CmsPagePublishingRequest;
use App\Requests\CmsPageStatusRequest;
use App\Requests\CmsSettingsUpdateRequest;
use DateTimeZone;
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

    public function testStatusRequestUsesCanonicalStatesAndNormalizesScheduledTime(): void
    {
        self::assertSame('published', CmsPageStatusRequest::fromInput('published')->status());

        $scheduled = CmsPageStatusRequest::fromInput(
            'scheduled',
            '2026-08-10T15:30',
            new DateTimeZone('Asia/Kathmandu'),
        );
        self::assertSame('scheduled', $scheduled->status());
        self::assertSame('2026-08-10 09:45:00', $scheduled->publishAt()?->format('Y-m-d H:i:s'));
        self::assertSame(0, $scheduled->publishAt()?->getOffset());
    }

    public function testInvalidStatusAndScheduledTimeAreRejected(): void
    {
        foreach ([
            fn () => CmsPageStatusRequest::fromInput('deleted'),
            fn () => CmsPageStatusRequest::fromInput('scheduled'),
            fn () => CmsPageStatusRequest::fromInput('scheduled', '2026-02-30T10:00'),
            fn () => CmsPageStatusRequest::fromInput('published', '2026-08-10T10:00'),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected the publication state request to fail.');
            } catch (CmsException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }

    public function testPublishingMetadataIsTypedAndConvertedToUtc(): void
    {
        $request = CmsPagePublishingRequest::fromInput(
            'important',
            '2026-08-10T15:30',
            '2026-08-12T15:30',
            true,
            true,
            12,
            'Social title',
            'Social description',
            13,
            true,
            new DateTimeZone('Asia/Kathmandu'),
        );

        self::assertSame('important', $request->noticeStyle());
        self::assertSame('2026-08-10 09:45:00', $request->publishAt()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-12 09:45:00', $request->expiresAt()?->format('Y-m-d H:i:s'));
        self::assertTrue($request->isFeatured());
        self::assertTrue($request->isPinned());
        self::assertSame(12, $request->featuredMediaId());
        self::assertSame(13, $request->socialMediaId());
        self::assertTrue($request->seoNoindex());
    }

    public function testPublishingMetadataRejectsInvalidExpiryAndLooseBooleans(): void
    {
        foreach ([
            fn () => CmsPagePublishingRequest::fromInput(null, null, '2026-08-12T15:30', false, false, null, null, null, null, false, new DateTimeZone('UTC')),
            fn () => CmsPagePublishingRequest::fromInput(null, '2026-08-12T15:30', '2026-08-11T15:30', false, false, null, null, null, null, false, new DateTimeZone('UTC')),
            fn () => CmsPagePublishingRequest::fromInput(null, null, null, 1, false, null, null, null, null, false, new DateTimeZone('UTC')),
            fn () => CmsPagePublishingRequest::fromInput('danger', null, null, false, false, null, null, null, null, false, new DateTimeZone('UTC')),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected the publishing metadata request to fail.');
            } catch (CmsException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }
}
