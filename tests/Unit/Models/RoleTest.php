<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Role;
use DateTimeImmutable;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RoleTest extends TestCase
{
    public function testItDefinesOnlyTheApprovedRoleConstant(): void
    {
        $constants = (new ReflectionClass(Role::class))->getConstants();

        self::assertSame(['ADMINISTRATOR' => 'Administrator'], $constants);
        self::assertNotContains('Guest', $constants);
        self::assertNotContains('Customer', $constants);
        self::assertNotContains('Manager', $constants);
        self::assertNotContains('Receptionist', $constants);
        self::assertNotContains('Accountant', $constants);
        self::assertNotContains('Content Manager', $constants);
    }

    public function testItExposesImmutablePassiveRoleData(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-21 01:02:03 UTC');
        $updatedAt = new DateTimeImmutable('2026-07-21 02:03:04 UTC');
        $role = new Role(7, Role::ADMINISTRATOR, null, $createdAt, $updatedAt);

        self::assertTrue((new ReflectionClass($role))->isReadOnly());
        self::assertSame(7, $role->id());
        self::assertSame(Role::ADMINISTRATOR, $role->name());
        self::assertNull($role->description());
        self::assertSame($createdAt, $role->createdAt());
        self::assertSame($updatedAt, $role->updatedAt());
        self::assertTrue($role->isAdministrator());

        $property = new \ReflectionProperty($role, 'name');
        $this->expectException(Error::class);
        $property->setValue($role, 'Changed');
    }

    public function testAStoredNonAdministratorRoleIsNotRecognizedAsAdministrator(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-21 01:02:03 UTC');
        $role = new Role(8, 'TestOnlyRole', 'Fixture', $timestamp, $timestamp);

        self::assertFalse($role->isAdministrator());
    }

    public function testItRejectsNonUtcTimestampData(): void
    {
        $utc = new DateTimeImmutable('2026-07-21 01:02:03 UTC');
        $nonUtc = new DateTimeImmutable('2026-07-21 01:02:03 Asia/Kathmandu');

        $this->expectException(InvalidArgumentException::class);
        new Role(1, Role::ADMINISTRATOR, null, $nonUtc, $utc);
    }

    #[DataProvider('invalidIdentityProvider')]
    public function testItRejectsInvalidStoredIdentityData(int $id, string $name): void
    {
        $timestamp = new DateTimeImmutable('2026-07-21 01:02:03 UTC');

        $this->expectException(InvalidArgumentException::class);
        new Role($id, $name, null, $timestamp, $timestamp);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function invalidIdentityProvider(): iterable
    {
        yield 'zero identifier' => [0, Role::ADMINISTRATOR];
        yield 'negative identifier' => [-1, Role::ADMINISTRATOR];
        yield 'empty name' => [1, ''];
        yield 'surrounding whitespace' => [1, ' Administrator'];
    }
}
