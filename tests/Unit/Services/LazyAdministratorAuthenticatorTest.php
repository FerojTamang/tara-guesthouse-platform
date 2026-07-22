<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Authentication\AdministratorIdentity;
use App\Exceptions\AdministratorAuthenticationException;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\LazyAdministratorAuthenticator;
use PHPUnit\Framework\TestCase;

final class LazyAdministratorAuthenticatorTest extends TestCase
{
    public function testItDefersConstructionUntilAuthenticationAndReusesTheService(): void
    {
        $factoryCalls = 0;
        $delegate = new class implements AdministratorAuthenticator {
            public int $calls = 0;

            /** @var list<string> */
            public array $contexts = [];

            public function authenticate(
                AdministratorLoginRequest $request,
                string $remoteAddress,
                string $context = 'http',
            ): AdministratorIdentity {
                $this->calls++;
                $this->contexts[] = $context;

                return new AdministratorIdentity(17);
            }
        };
        $lazy = new LazyAdministratorAuthenticator(
            static function () use (&$factoryCalls, $delegate): AdministratorAuthenticator {
                $factoryCalls++;

                return $delegate;
            },
        );
        $request = AdministratorLoginRequest::fromInput(
            'administrator',
            'test-only-password',
            self::policy(),
        );

        self::assertSame(0, $factoryCalls);

        self::assertSame(17, $lazy->authenticate($request, '127.0.0.1', 'api')->userId());
        self::assertSame(17, $lazy->authenticate($request, '127.0.0.1')->userId());
        self::assertSame(1, $factoryCalls);
        self::assertSame(2, $delegate->calls);
        self::assertSame(['api', 'http'], $delegate->contexts);
    }

    public function testItRejectsAnInvalidFactoryResultWithoutDisclosure(): void
    {
        $lazy = new LazyAdministratorAuthenticator(static fn (): object => new \stdClass());
        $request = AdministratorLoginRequest::fromInput(
            'administrator',
            'test-only-password',
            self::policy(),
        );

        try {
            $lazy->authenticate($request, '127.0.0.1');
            self::fail('Expected the invalid lazy factory result to fail.');
        } catch (AdministratorAuthenticationException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Administrator authentication failed.', $exception->getMessage());
        }
    }

    /** @return array<string, int> */
    private static function policy(): array
    {
        return [
            'username_min_characters' => 3,
            'username_max_characters' => 50,
            'identifier_max_bytes' => 150,
            'password_max_bytes' => 72,
            'max_failed_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ];
    }
}
