<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use App\Core\Response;
use App\Core\ResponseSecurityPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseSecurityPolicyTest extends TestCase
{
    public function testItAppliesBrowserProtectionsWithoutReplacingStricterControllerHeaders(): void
    {
        $policy = new ResponseSecurityPolicy($this->configuration());
        $response = Response::html('<p>Safe</p>', headers: [
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);

        $secured = $policy->apply(Request::create('GET', '/rooms'), $response);

        self::assertSame('no-referrer', $secured->headers()['Referrer-Policy']);
        self::assertSame('SAMEORIGIN', $secured->headers()['X-Frame-Options']);
        self::assertSame('nosniff', $secured->headers()['X-Content-Type-Options']);
        self::assertSame('same-origin', $secured->headers()['Cross-Origin-Opener-Policy']);
        self::assertSame('?1', $secured->headers()['Origin-Agent-Cluster']);
        self::assertSame('none', $secured->headers()['X-Permitted-Cross-Domain-Policies']);
        self::assertArrayNotHasKey('Strict-Transport-Security', $secured->headers());
        self::assertArrayNotHasKey('Cache-Control', $secured->headers());
    }

    public function testAdministratorResponsesReceiveNoStoreAndProductionHsts(): void
    {
        $configuration = $this->configuration();
        $configuration['strict_transport_security'] = 'max-age=31536000';
        $policy = new ResponseSecurityPolicy($configuration);

        $secured = $policy->apply(
            Request::create('GET', '/api/v1/admin/pages'),
            Response::json(['ok' => true]),
        );

        self::assertSame('no-store', $secured->headers()['Cache-Control']);
        self::assertSame('no-cache', $secured->headers()['Pragma']);
        self::assertSame('max-age=31536000', $secured->headers()['Strict-Transport-Security']);
        self::assertSame($configuration['content_security_policy'], $secured->headers()['Content-Security-Policy']);
        self::assertSame($configuration['permissions_policy'], $secured->headers()['Permissions-Policy']);
    }

    public function testExistingAdministratorCachePolicyIsPreserved(): void
    {
        $policy = new ResponseSecurityPolicy($this->configuration());
        $response = Response::text('Denied', 403, ['Cache-Control' => 'no-store, private, max-age=0']);

        $secured = $policy->apply(Request::create('GET', '/admin/bookings'), $response);

        self::assertSame('no-store, private, max-age=0', $secured->headers()['Cache-Control']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidConfiguration(): iterable
    {
        yield 'missing key' => [[
            'content_security_policy' => "default-src 'self'",
            'permissions_policy' => 'camera=()',
        ]];
        yield 'empty policy' => [[
            'content_security_policy' => '',
            'permissions_policy' => 'camera=()',
            'strict_transport_security' => null,
        ]];
        yield 'invalid hsts' => [[
            'content_security_policy' => "default-src 'self'",
            'permissions_policy' => 'camera=()',
            'strict_transport_security' => 'max-age=0; preload',
        ]];
    }

    /** @param array<string, mixed> $configuration */
    #[DataProvider('invalidConfiguration')]
    public function testMalformedPolicyFailsClosed(array $configuration): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ResponseSecurityPolicy($configuration);
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return [
            'content_security_policy' => "default-src 'self'; frame-ancestors 'none'",
            'permissions_policy' => 'camera=(), microphone=()',
            'strict_transport_security' => null,
        ];
    }
}
