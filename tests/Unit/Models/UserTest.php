<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Role;
use App\Models\User;
use DateTimeImmutable;
use Error;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class UserTest extends TestCase
{
    public function testItDefinesExactlyTheApprovedPublicAccountStatuses(): void
    {
        $reflection = new ReflectionClass(User::class);
        $statuses = [];

        foreach ($reflection->getReflectionConstants() as $constant) {
            if ($constant->isPublic() && str_starts_with($constant->getName(), 'STATUS_')) {
                $statuses[$constant->getName()] = $constant->getValue();
            }
        }

        self::assertSame([
            'STATUS_ACTIVE' => 'active',
            'STATUS_INACTIVE' => 'inactive',
            'STATUS_LOCKED' => 'locked',
        ], $statuses);
    }

    public function testItExposesImmutableMappedDataWithoutAPlaintextPasswordApi(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-21 01:02:03 UTC');
        $updatedAt = new DateTimeImmutable('2026-07-21 02:03:04 UTC');
        $lastLoginAt = new DateTimeImmutable('2026-07-21 01:30:00 UTC');
        $role = new Role(2, Role::ADMINISTRATOR, null, $createdAt, $updatedAt);
        $passwordHash = 'test-only-password-hash-sentinel';
        $user = new User(
            9,
            $role,
            'System Administrator',
            'administrator',
            'administrator@example.test',
            '+977000000000',
            $passwordHash,
            'profiles/administrator.webp',
            $lastLoginAt,
            User::STATUS_ACTIVE,
            $createdAt,
            $updatedAt,
            null,
        );

        self::assertTrue((new ReflectionClass($user))->isReadOnly());
        self::assertSame(9, $user->id());
        self::assertSame($role, $user->role());
        self::assertSame('System Administrator', $user->fullName());
        self::assertSame('administrator', $user->username());
        self::assertSame('administrator@example.test', $user->email());
        self::assertSame('+977000000000', $user->phone());
        self::assertSame($passwordHash, $user->passwordHash());
        self::assertSame('profiles/administrator.webp', $user->profileImage());
        self::assertSame($lastLoginAt, $user->lastLoginAt());
        self::assertSame(User::STATUS_ACTIVE, $user->accountStatus());
        self::assertSame($createdAt, $user->createdAt());
        self::assertSame($updatedAt, $user->updatedAt());
        self::assertNull($user->deletedAt());
        self::assertFalse(method_exists($user, 'password'));
        self::assertFalse(method_exists($user, 'plainTextPassword'));
        self::assertSame('{}', json_encode($user, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($passwordHash, json_encode($user, JSON_THROW_ON_ERROR));

        $property = new \ReflectionProperty($user, 'username');
        $this->expectException(Error::class);
        $property->setValue($user, 'changed');
    }

    #[DataProvider('statusProvider')]
    public function testItsSmallStateHelpersReflectStoredState(
        string $status,
        bool $active,
        bool $inactive,
        bool $locked,
    ): void {
        $user = $this->user(status: $status);

        self::assertSame($active, $user->isActive());
        self::assertSame($inactive, $user->isInactive());
        self::assertSame($locked, $user->isLocked());
        self::assertFalse($user->isDeleted());
        self::assertTrue($user->isAdministrator());
    }

    public function testItRecognizesDeletedAndNonAdministratorStoredState(): void
    {
        $deletedAt = new DateTimeImmutable('2026-07-21 03:04:05 UTC');
        $user = $this->user(
            roleName: 'TestOnlyRole',
            deletedAt: $deletedAt,
        );

        self::assertTrue($user->isDeleted());
        self::assertSame($deletedAt, $user->deletedAt());
        self::assertFalse($user->isAdministrator());
    }

    public function testDebugAndSerializationBehaviorCannotExposeThePasswordHash(): void
    {
        $user = $this->user();
        $hash = $user->passwordHash();

        ob_start();
        var_dump($user);
        $debugOutput = ob_get_clean();
        self::assertIsString($debugOutput);
        self::assertStringNotContainsString($hash, $debugOutput);
        self::assertStringNotContainsString('passwordHash', $debugOutput);
        self::assertFalse(method_exists($user, '__toString'));

        try {
            serialize($user);
            self::fail('Native User serialization unexpectedly succeeded.');
        } catch (LogicException $exception) {
            self::assertSame('User serialization is not supported.', $exception->getMessage());
            self::assertStringNotContainsString($hash, $exception->getMessage());
        }
    }

    public function testItRejectsNonUtcTimestampData(): void
    {
        $utc = new DateTimeImmutable('2026-07-21 01:02:03 UTC');
        $nonUtc = new DateTimeImmutable('2026-07-21 01:02:03 Asia/Kathmandu');
        $role = new Role(1, Role::ADMINISTRATOR, null, $utc, $utc);

        $this->expectException(InvalidArgumentException::class);
        new User(
            1,
            $role,
            'Administrator',
            'admin',
            'admin@example.test',
            null,
            'test-only-password-hash-sentinel',
            null,
            null,
            User::STATUS_ACTIVE,
            $nonUtc,
            $utc,
            null,
        );
    }

    #[DataProvider('invalidUserProvider')]
    public function testItRejectsInvalidMappedData(int $id, string $fullName, string $username, string $email, string $hash, string $status): void
    {
        $timestamp = new DateTimeImmutable('2026-07-21 01:02:03 UTC');
        $role = new Role(1, Role::ADMINISTRATOR, null, $timestamp, $timestamp);

        $this->expectException(InvalidArgumentException::class);
        new User(
            $id,
            $role,
            $fullName,
            $username,
            $email,
            null,
            $hash,
            null,
            null,
            $status,
            $timestamp,
            $timestamp,
            null,
        );
    }

    /**
     * @return iterable<string, array{string, bool, bool, bool}>
     */
    public static function statusProvider(): iterable
    {
        yield 'active' => [User::STATUS_ACTIVE, true, false, false];
        yield 'inactive' => [User::STATUS_INACTIVE, false, true, false];
        yield 'locked' => [User::STATUS_LOCKED, false, false, true];
    }

    /**
     * @return iterable<string, array{int, string, string, string, string, string}>
     */
    public static function invalidUserProvider(): iterable
    {
        yield 'invalid id' => [0, 'Administrator', 'admin', 'admin@example.test', 'hash', User::STATUS_ACTIVE];
        yield 'empty name' => [1, '', 'admin', 'admin@example.test', 'hash', User::STATUS_ACTIVE];
        yield 'empty username' => [1, 'Administrator', '', 'admin@example.test', 'hash', User::STATUS_ACTIVE];
        yield 'empty email' => [1, 'Administrator', 'admin', '', 'hash', User::STATUS_ACTIVE];
        yield 'empty hash' => [1, 'Administrator', 'admin', 'admin@example.test', '', User::STATUS_ACTIVE];
        yield 'unknown status' => [1, 'Administrator', 'admin', 'admin@example.test', 'hash', 'disabled'];
    }

    private function user(
        string $status = User::STATUS_ACTIVE,
        string $roleName = Role::ADMINISTRATOR,
        ?DateTimeImmutable $deletedAt = null,
    ): User {
        $timestamp = new DateTimeImmutable('2026-07-21 01:02:03 UTC');
        $role = new Role(1, $roleName, null, $timestamp, $timestamp);

        return new User(
            1,
            $role,
            'Administrator',
            'admin',
            'admin@example.test',
            null,
            'test-only-password-hash-sentinel',
            null,
            null,
            $status,
            $timestamp,
            $timestamp,
            $deletedAt,
        );
    }
}
