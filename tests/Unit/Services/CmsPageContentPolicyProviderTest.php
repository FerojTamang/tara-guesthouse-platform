<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Environment;
use App\Exceptions\CmsException;
use App\Services\CmsPageContentPolicyProvider;
use App\Services\LazyCmsPageContentSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CmsPageContentPolicyProviderTest extends TestCase
{
    public function testApprovedPlainTextPolicyNormalizesAndCaches(): void
    {
        $provider = new CmsPageContentPolicyProvider(dirname(__DIR__, 3), Environment::fromArray([
            'CMS_PAGE_CONTENT_POLICY_JSON' => '{"format":"plain_text","maximum_length":2000}',
        ]));

        $sanitizer = $provider->sanitizer();

        self::assertSame($sanitizer, $provider->sanitizer());
        self::assertSame('plain_text', $sanitizer->format());
        self::assertSame(2000, $sanitizer->maximumLength());
        self::assertSame("First line\nSecond line", $sanitizer->sanitize(" First line  \r\nSecond line \r\n"));
    }

    public function testPlainTextPolicyRejectsHtmlMarkup(): void
    {
        $provider = new CmsPageContentPolicyProvider(dirname(__DIR__, 3), Environment::fromArray([
            'CMS_PAGE_CONTENT_POLICY_JSON' => '{"format":"plain_text","maximum_length":2000}',
        ]));

        $this->expectException(CmsException::class);
        $provider->sanitizer()->sanitize('<script>alert(1)</script>');
    }

    /** @return iterable<string, array{array<string, mixed>} */
    public static function invalidPolicies(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty' => [['CMS_PAGE_CONTENT_POLICY_JSON' => '']];
        yield 'malformed' => [['CMS_PAGE_CONTENT_POLICY_JSON' => '{']];
        yield 'unsupported' => [['CMS_PAGE_CONTENT_POLICY_JSON' => '{"format":"html","maximum_length":2000}']];
        yield 'unknown field' => [['CMS_PAGE_CONTENT_POLICY_JSON' => '{"format":"plain_text","maximum_length":2000,"extra":true}']];
        yield 'invalid limit' => [['CMS_PAGE_CONTENT_POLICY_JSON' => '{"format":"plain_text","maximum_length":0}']];
    }

    #[DataProvider('invalidPolicies')]
    public function testMissingOrMalformedPolicyFailsClosed(array $values): void
    {
        $provider = new CmsPageContentPolicyProvider(dirname(__DIR__, 3), Environment::fromArray($values));

        try {
            $provider->sanitizer();
            self::fail('Expected the content policy to fail closed.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('CMS operation failed.', $exception->getMessage());
        }
    }

    public function testLazySanitizerDoesNotResolveUntilUsed(): void
    {
        $calls = 0;
        $provider = new CmsPageContentPolicyProvider(dirname(__DIR__, 3), Environment::fromArray([
            'CMS_PAGE_CONTENT_POLICY_JSON' => '{"format":"plain_text","maximum_length":100}',
        ]));
        $lazy = new LazyCmsPageContentSanitizer(function () use (&$calls, $provider) {
            $calls++;
            return $provider->sanitizer();
        });

        self::assertSame(0, $calls);
        self::assertSame('plain_text', $lazy->format());
        self::assertSame(1, $calls);
        self::assertSame('Content', $lazy->sanitize('Content'));
        self::assertSame(1, $calls);
    }
}
