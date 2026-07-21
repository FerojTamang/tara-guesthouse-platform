<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication;

use App\Authentication\AdministratorIdentity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdministratorIdentityTest extends TestCase
{
    public function testItExposesOnlyTheApprovedAdministratorIdentity(): void
    {
        $identity = new AdministratorIdentity(42);

        self::assertSame(42, $identity->userId());
        self::assertSame('Administrator', $identity->role());
        self::assertSame(1, $identity->formatVersion());
        self::assertSame([
            'version' => 1,
            'user_id' => 42,
            'role' => 'Administrator',
        ], $identity->toSessionData());
    }

    public function testItRejectsNonPositiveUserIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AdministratorIdentity(0);
    }

    public function testItRestoresTheExactApprovedSessionShape(): void
    {
        $identity = AdministratorIdentity::fromSessionData([
            'version' => 1,
            'user_id' => 7,
            'role' => 'Administrator',
        ]);

        self::assertInstanceOf(AdministratorIdentity::class, $identity);
        self::assertSame(7, $identity->userId());
    }

    #[DataProvider('invalidSessionData')]
    public function testItRejectsMalformedOrUnapprovedSessionData(mixed $data): void
    {
        self::assertNull(AdministratorIdentity::fromSessionData($data));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidSessionData(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['Administrator'];
        yield 'missing version' => [[
            'user_id' => 7,
            'role' => 'Administrator',
        ]];
        yield 'extra identity data' => [[
            'version' => 1,
            'user_id' => 7,
            'role' => 'Administrator',
            'email' => 'admin@example.test',
        ]];
        yield 'string user ID' => [[
            'version' => 1,
            'user_id' => '7',
            'role' => 'Administrator',
        ]];
        yield 'non-positive user ID' => [[
            'version' => 1,
            'user_id' => 0,
            'role' => 'Administrator',
        ]];
        yield 'wrong role' => [[
            'version' => 1,
            'user_id' => 7,
            'role' => 'Guest',
        ]];
        yield 'wrong role casing' => [[
            'version' => 1,
            'user_id' => 7,
            'role' => 'administrator',
        ]];
        yield 'unsupported version' => [[
            'version' => 2,
            'user_id' => 7,
            'role' => 'Administrator',
        ]];
    }
}
