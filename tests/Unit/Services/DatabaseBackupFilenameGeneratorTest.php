<?php
declare(strict_types=1);
namespace Tests\Unit\Services;
use App\Services\DatabaseBackupFilenameGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
final class DatabaseBackupFilenameGeneratorTest extends TestCase
{
 public function testItGeneratesUtcWindowsSafeManifestCompatibleNames(): void { $name=(new DatabaseBackupFilenameGenerator(static fn(int $n):string=>str_repeat("\xAB",$n)))->generate(new DateTimeImmutable('2026-08-01 20:15:00+05:45')); self::assertSame('tara-guest-house-20260801T143000Z-abababababab.sql',$name); foreach(['\\','/',':','*','?','"','<','>','|',' '] as $forbidden) self::assertStringNotContainsString($forbidden,$name); }
}
