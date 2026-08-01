<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseBackupManifest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DatabaseBackupManifestTest extends TestCase
{
    public function testItProducesStableNonSecretManifestMetadata(): void
    {
        $manifest = new DatabaseBackupManifest('2026-08-01T14:30:00Z', 'tara-guest-house-20260801T143000Z-a1b2c3d4e5f6.sql', str_repeat('a', 64), 123, 'mariadb', 'mysql', '8.0.44', null, 25);
        self::assertSame(1, $manifest->toArray()['format_version']);
        self::assertSame('logical_sql', $manifest->toArray()['backup_format']);
        self::assertArrayNotHasKey('password', $manifest->toArray());
    }

    public function testItRejectsUnsafeBackupFilename(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DatabaseBackupManifest('2026-08-01T14:30:00Z', '../private.sql', str_repeat('a', 64), 1, 'mariadb', 'mariadb');
    }
}
