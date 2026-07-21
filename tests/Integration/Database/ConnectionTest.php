<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use PDO;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class ConnectionTest extends DatabaseTestCase
{
    public function testItConnectsToMariaDbWithTheApprovedSessionSettings(): void
    {
        $row = $this->connection->query(
            'SELECT VERSION() AS version, @@session.time_zone AS session_timezone, '
            . '@@character_set_connection AS connection_charset, @@collation_connection AS connection_collation',
        )->fetch();

        self::assertIsArray($row);
        self::assertStringContainsString('MariaDB', $row['version']);
        self::assertStringStartsWith('11.8.', $row['version']);
        self::assertSame('+00:00', $row['session_timezone']);
        self::assertSame('utf8mb4', $row['connection_charset']);
        self::assertSame('utf8mb4_unicode_ci', $row['connection_collation']);
        self::assertSame(PDO::ERRMODE_EXCEPTION, $this->connection->getAttribute(PDO::ATTR_ERRMODE));
        self::assertSame(PDO::FETCH_ASSOC, $this->connection->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
        self::assertFalse((bool) $this->connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        self::assertFalse((bool) $this->connection->getAttribute(PDO::ATTR_STRINGIFY_FETCHES));
        self::assertFalse((bool) $this->connection->getAttribute(PDO::ATTR_PERSISTENT));
    }
}
