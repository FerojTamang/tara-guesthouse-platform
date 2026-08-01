<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Environment;
use App\Exceptions\ReviewException;
use App\Services\ReviewInvitationPolicyProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewInvitationPolicyProviderTest extends TestCase
{
    public function testApprovedLifetimeIsLoadedAndCachedExactly(): void
    {
        $provider = new ReviewInvitationPolicyProvider(dirname(__DIR__, 3), Environment::fromArray([
            'REVIEW_INVITATION_LIFETIME_SECONDS' => '604800',
        ]));

        $policy = $provider->policy();

        self::assertSame(604800, $policy->lifetimeSeconds());
        self::assertSame($policy, $provider->policy());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPolicies(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty' => [['REVIEW_INVITATION_LIFETIME_SECONDS' => '']];
        yield 'whitespace' => [['REVIEW_INVITATION_LIFETIME_SECONDS' => ' 3600']];
        yield 'zero' => [['REVIEW_INVITATION_LIFETIME_SECONDS' => '0']];
        yield 'negative' => [['REVIEW_INVITATION_LIFETIME_SECONDS' => '-1']];
        yield 'decimal' => [['REVIEW_INVITATION_LIFETIME_SECONDS' => '1.5']];
        yield 'non string' => [['REVIEW_INVITATION_LIFETIME_SECONDS' => 3600]];
    }

    #[DataProvider('invalidPolicies')]
    public function testMissingOrMalformedLifetimeFailsClosed(array $values): void
    {
        $provider = new ReviewInvitationPolicyProvider(dirname(__DIR__, 3), Environment::fromArray($values));

        try {
            $provider->policy();
            self::fail('Expected review invitation policy to fail closed.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Review workflow operation failed.', $exception->getMessage());
        }
    }
}
