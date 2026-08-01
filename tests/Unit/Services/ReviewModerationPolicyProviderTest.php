<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Environment;
use App\Exceptions\ReviewException;
use App\Services\ReviewModerationPolicyProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewModerationPolicyProviderTest extends TestCase
{
    public function testApprovedMaximumIsLoadedAndCachedExactly(): void
    {
        $provider = new ReviewModerationPolicyProvider(dirname(__DIR__, 3), Environment::fromArray([
            'REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => '1000',
        ]));

        $policy = $provider->policy();

        self::assertSame(1000, $policy->maximumNoteCharacters());
        self::assertTrue($policy->rejectionNoteRequired());
        self::assertSame($policy, $provider->policy());
    }

    public function testMissingMalformedAndExplodingDescriptorsAreSanitized(): void
    {
        foreach ([
            null,
            "<?php return ['unexpected' => true];",
            "<?php throw new \RuntimeException('sensitive moderation configuration failure');",
        ] as $configuration) {
            $basePath = sys_get_temp_dir()
                . DIRECTORY_SEPARATOR
                . 'tgh-review-policy-'
                . bin2hex(random_bytes(8));
            self::assertTrue(mkdir($basePath . DIRECTORY_SEPARATOR . 'config', 0700, true));

            if (is_string($configuration)) {
                self::assertNotFalse(file_put_contents(
                    $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'review_moderation.php',
                    $configuration,
                ));
            }

            try {
                $provider = new ReviewModerationPolicyProvider(
                    $basePath,
                    Environment::fromArray([
                        'REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => '1000',
                    ]),
                );

                try {
                    $provider->policy();
                    self::fail('Invalid moderation policy descriptor was accepted.');
                } catch (ReviewException $exception) {
                    self::assertTrue($exception->isSystemFailure());
                    self::assertSame('Review workflow operation failed.', $exception->getMessage());
                    self::assertNull($exception->getPrevious());
                }
            } finally {
                $path = $basePath
                    . DIRECTORY_SEPARATOR
                    . 'config'
                    . DIRECTORY_SEPARATOR
                    . 'review_moderation.php';
                if (is_file($path)) {
                    @unlink($path);
                }
                @rmdir($basePath . DIRECTORY_SEPARATOR . 'config');
                @rmdir($basePath);
            }
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPolicies(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty' => [['REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => '']];
        yield 'leading whitespace' => [['REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => ' 1000']];
        yield 'trailing whitespace' => [['REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => '1000 ']];
        yield 'zero' => [['REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => '0']];
        yield 'negative' => [['REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => '-1']];
        yield 'decimal' => [['REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => '1.5']];
        yield 'text overflow' => [['REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => '65536']];
        yield 'non string' => [['REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => 1000]];
    }

    #[DataProvider('invalidPolicies')]
    public function testMissingOrMalformedPolicyFailsClosed(array $values): void
    {
        $provider = new ReviewModerationPolicyProvider(dirname(__DIR__, 3), Environment::fromArray($values));

        try {
            $provider->policy();
            self::fail('Expected review moderation policy to fail closed.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Review workflow operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}
