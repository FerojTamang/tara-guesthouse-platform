<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AuthenticationMigrationTest extends DatabaseTestCase
{
    private const APPLICATION_MIGRATIONS = [
        '20260721000100_create_roles_table',
        '20260721000200_create_users_table',
        '20260722000100_create_room_types_table',
        '20260722000200_create_rooms_table',
        '20260722000300_create_room_images_table',
        '20260722000400_create_amenities_table',
        '20260722000500_create_room_amenities_table',
        '20260723000100_add_room_image_persistence_constraints',
        '20260726000100_create_bookings_table',
        '20260726000200_create_booking_rooms_table',
        '20260726000300_create_booking_events_table',
        '20260727000100_create_audit_logs_table',
    ];

    private MigrationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupAuthenticationTables();
        $this->runner = new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->cleanupAuthenticationTables();
        }

        parent::tearDown();
    }

    public function testMigrationsApplyInOrderAndExposeTheApprovedSchema(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame([], $this->runner->migrate());
        self::assertSame(
            array_fill(0, count(self::APPLICATION_MIGRATIONS), 'applied'),
            array_column($this->runner->status(), 'status'),
        );

        $roles = $this->columns('roles');
        self::assertSame(['id', 'role_name', 'description', 'created_at', 'updated_at'], array_keys($roles));
        $this->assertBigIntUnsigned($roles['id'], nullable: false, autoIncrement: true);
        $this->assertVarchar($roles['role_name'], 50, false);
        $this->assertVarchar($roles['description'], 255, true);
        $this->assertTimestampColumn($roles['created_at'], onUpdate: false);
        $this->assertTimestampColumn($roles['updated_at'], onUpdate: true);

        $users = $this->columns('users');
        self::assertSame([
            'id',
            'role_id',
            'full_name',
            'username',
            'email',
            'phone',
            'password',
            'profile_image',
            'last_login_at',
            'account_status',
            'created_at',
            'updated_at',
            'deleted_at',
        ], array_keys($users));
        $this->assertBigIntUnsigned($users['id'], nullable: false, autoIncrement: true);
        $this->assertBigIntUnsigned($users['role_id'], nullable: false, autoIncrement: false);
        $this->assertVarchar($users['full_name'], 100, false);
        $this->assertVarchar($users['username'], 50, false);
        $this->assertVarchar($users['email'], 150, false);
        $this->assertVarchar($users['phone'], 20, true);
        $this->assertVarchar($users['password'], 255, false);
        $this->assertVarchar($users['profile_image'], 255, true);
        $this->assertNullableDateTime($users['last_login_at']);
        $this->assertVarchar($users['account_status'], 20, false);
        self::assertNull($users['account_status']['COLUMN_DEFAULT']);
        $this->assertTimestampColumn($users['created_at'], onUpdate: false);
        $this->assertTimestampColumn($users['updated_at'], onUpdate: true);
        $this->assertNullableDateTime($users['deleted_at']);

        self::assertSame('InnoDB', $this->tableOption('roles', 'ENGINE'));
        self::assertSame('InnoDB', $this->tableOption('users', 'ENGINE'));
        self::assertSame('utf8mb4_unicode_ci', $this->tableOption('roles', 'TABLE_COLLATION'));
        self::assertSame('utf8mb4_unicode_ci', $this->tableOption('users', 'TABLE_COLLATION'));

        self::assertSame([
            'PRIMARY' => 'PRIMARY KEY',
            'uq_roles_role_name' => 'UNIQUE',
        ], $this->constraints('roles'));
        self::assertSame([
            'PRIMARY' => 'PRIMARY KEY',
            'chk_users_account_status' => 'CHECK',
            'fk_users_role' => 'FOREIGN KEY',
            'uq_users_email' => 'UNIQUE',
            'uq_users_username' => 'UNIQUE',
        ], $this->constraints('users'));

        $referential = $this->connection->query(
            "SELECT UNIQUE_CONSTRAINT_NAME, UPDATE_RULE, DELETE_RULE "
            . "FROM information_schema.REFERENTIAL_CONSTRAINTS "
            . "WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_users_role'",
        )->fetch();
        self::assertIsArray($referential);
        self::assertSame('PRIMARY', $referential['UNIQUE_CONSTRAINT_NAME']);
        self::assertSame('RESTRICT', $referential['UPDATE_RULE']);
        self::assertSame('RESTRICT', $referential['DELETE_RULE']);

        $checkClause = $this->connection->query(
            "SELECT cc.CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS cc "
            . "INNER JOIN information_schema.TABLE_CONSTRAINTS tc "
            . "ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME "
            . "WHERE tc.CONSTRAINT_SCHEMA = DATABASE() AND tc.TABLE_NAME = 'users' "
            . "AND tc.CONSTRAINT_NAME = 'chk_users_account_status'",
        )->fetchColumn();
        self::assertIsString($checkClause);

        foreach (['account_status', 'active', 'inactive', 'locked'] as $requiredValue) {
            self::assertStringContainsString($requiredValue, $checkClause);
        }

        self::assertSame(
            count(self::APPLICATION_MIGRATIONS),
            (int) $this->connection->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn(),
        );
    }

    public function testDatabaseConstraintsDefaultsAndTimestampBehaviorAreEnforced(): void
    {
        $this->runner->migrate();
        $roleId = $this->insertRole('Administrator');
        $userId = $this->insertUser($roleId, 'AdminUser', 'Admin@Example.test', 'active');

        self::assertGreaterThan(0, $roleId);
        self::assertGreaterThan(0, $userId);

        $stored = $this->connection->query(
            'SELECT phone, profile_image, last_login_at, deleted_at, created_at, updated_at FROM users WHERE id = ' . $userId,
        )->fetch();
        self::assertIsArray($stored);
        self::assertNull($stored['phone']);
        self::assertNull($stored['profile_image']);
        self::assertNull($stored['last_login_at']);
        self::assertNull($stored['deleted_at']);
        self::assertIsString($stored['created_at']);
        self::assertIsString($stored['updated_at']);

        $this->assertPdoFailure(fn () => $this->insertRole('administrator'));
        $this->assertPdoFailure(fn () => $this->insertUser($roleId, 'adminuser', 'other@example.test', 'active'));
        $this->assertPdoFailure(fn () => $this->insertUser($roleId, 'other-user', 'admin@example.test', 'active'));
        $this->assertPdoFailure(fn () => $this->insertUser($roleId, 'bad-status', 'bad-status@example.test', 'disabled'));

        foreach (['ACTIVE', 'Inactive', 'LOCKED'] as $index => $status) {
            $this->assertPdoFailure(fn () => $this->insertUser(
                $roleId,
                'case-status-' . $index,
                'case-status-' . $index . '@example.test',
                $status,
            ));
        }

        $this->assertPdoFailure(fn () => $this->insertUser($roleId + 999, 'bad-role', 'bad-role@example.test', 'active'));

        $this->assertPdoFailure(function () use ($roleId): void {
            $statement = $this->connection->prepare(
                'INSERT INTO users (role_id, full_name, username, email, password) '
                . 'VALUES (:role_id, :full_name, :username, :email, :password)',
            );
            $statement->execute([
                'role_id' => $roleId,
                'full_name' => 'Missing Status',
                'username' => 'missing-status',
                'email' => 'missing-status@example.test',
                'password' => 'test-only-password-hash-sentinel',
            ]);
        });

        $this->assertPdoFailure(function () use ($roleId): void {
            $statement = $this->connection->prepare('DELETE FROM roles WHERE id = :id');
            $statement->execute(['id' => $roleId]);
        });
        $this->assertPdoFailure(function () use ($roleId): void {
            $statement = $this->connection->prepare('UPDATE roles SET id = :new_id WHERE id = :id');
            $statement->execute(['new_id' => $roleId + 100, 'id' => $roleId]);
        });

        $this->connection->exec("UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE id = {$userId}");
        $this->assertPdoFailure(fn () => $this->insertUser($roleId, 'ADMINUSER', 'fresh@example.test', 'active'));
        $this->assertPdoFailure(fn () => $this->insertUser($roleId, 'fresh-user', 'ADMIN@EXAMPLE.TEST', 'active'));

        $this->connection->exec("UPDATE roles SET updated_at = '2000-01-01 00:00:00' WHERE id = {$roleId}");
        $this->connection->exec("UPDATE roles SET description = 'Updated' WHERE id = {$roleId}");
        self::assertNotSame(
            '2000-01-01 00:00:00',
            $this->connection->query("SELECT updated_at FROM roles WHERE id = {$roleId}")->fetchColumn(),
        );

        $this->connection->exec("UPDATE users SET updated_at = '2000-01-01 00:00:00' WHERE id = {$userId}");
        $this->connection->exec("UPDATE users SET full_name = 'Updated Administrator' WHERE id = {$userId}");
        self::assertNotSame(
            '2000-01-01 00:00:00',
            $this->connection->query("SELECT updated_at FROM users WHERE id = {$userId}")->fetchColumn(),
        );
    }

    public function testRollbackIsReversedAndRemigrationSucceedsCleanly(): void
    {
        $this->runner->migrate();

        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->applicationTableCount());
        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());

        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(11, $this->applicationTableCount());
        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->applicationTableCount());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function columns(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, '
            . 'CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME '
            . 'FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['table_name' => $table]);
        $columns = [];

        foreach ($statement->fetchAll() as $column) {
            self::assertIsArray($column);
            $columns[$column['COLUMN_NAME']] = $column;
        }

        return $columns;
    }

    /** @param array<string, mixed> $column */
    private function assertBigIntUnsigned(array $column, bool $nullable, bool $autoIncrement): void
    {
        self::assertSame('bigint', $column['DATA_TYPE']);
        self::assertStringContainsString('unsigned', $column['COLUMN_TYPE']);
        self::assertSame($nullable ? 'YES' : 'NO', $column['IS_NULLABLE']);
        self::assertSame($autoIncrement ? 'auto_increment' : '', strtolower((string) $column['EXTRA']));
    }

    /** @param array<string, mixed> $column */
    private function assertVarchar(array $column, int $length, bool $nullable): void
    {
        self::assertSame('varchar', $column['DATA_TYPE']);
        self::assertSame($length, (int) $column['CHARACTER_MAXIMUM_LENGTH']);
        self::assertSame($nullable ? 'YES' : 'NO', $column['IS_NULLABLE']);
        self::assertSame('utf8mb4_unicode_ci', $column['COLLATION_NAME']);
    }

    /** @param array<string, mixed> $column */
    private function assertTimestampColumn(array $column, bool $onUpdate): void
    {
        self::assertSame('datetime', $column['DATA_TYPE']);
        self::assertSame('NO', $column['IS_NULLABLE']);
        self::assertStringContainsString('current_timestamp', strtolower((string) $column['COLUMN_DEFAULT']));

        if ($onUpdate) {
            self::assertStringContainsString('on update current_timestamp', strtolower((string) $column['EXTRA']));
        } else {
            self::assertStringNotContainsString('on update', strtolower((string) $column['EXTRA']));
        }
    }

    /** @param array<string, mixed> $column */
    private function assertNullableDateTime(array $column): void
    {
        self::assertSame('datetime', $column['DATA_TYPE']);
        self::assertSame('YES', $column['IS_NULLABLE']);
        self::assertSame('NULL', $column['COLUMN_DEFAULT']);
    }

    private function tableOption(string $table, string $column): string
    {
        if (!in_array($column, ['ENGINE', 'TABLE_COLLATION'], true)) {
            self::fail('Unsafe information-schema column.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT %s FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
                $column,
            ),
        );
        $statement->execute(['table_name' => $table]);
        $value = $statement->fetchColumn();
        self::assertIsString($value);

        return $value;
    }

    /** @return array<string, string> */
    private function constraints(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY CONSTRAINT_NAME',
        );
        $statement->execute(['table_name' => $table]);
        $constraints = [];

        foreach ($statement->fetchAll() as $constraint) {
            $constraints[$constraint['CONSTRAINT_NAME']] = $constraint['CONSTRAINT_TYPE'];
        }

        ksort($constraints, SORT_STRING);

        return $constraints;
    }

    private function insertRole(string $name): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO roles (role_name, description) VALUES (:role_name, NULL)',
        );
        $statement->execute(['role_name' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertUser(int $roleId, string $username, string $email, string $status): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (role_id, full_name, username, email, phone, password, profile_image, '
            . 'last_login_at, account_status, deleted_at) '
            . 'VALUES (:role_id, :full_name, :username, :email, NULL, :password, NULL, NULL, :status, NULL)',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Administrator Fixture',
            'username' => $username,
            'email' => $email,
            'password' => 'test-only-password-hash-sentinel',
            'status' => $status,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid authentication data.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }

    private function applicationTableCount(): int
    {
        return (int) $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME IN ('roles', 'users', 'room_types', 'rooms', 'room_images', "
            . "'amenities', 'room_amenities', 'bookings', 'booking_rooms', 'booking_events', 'audit_logs')",
        )->fetchColumn();
    }

    private function cleanupAuthenticationTables(): void
    {
        $this->cleanupApplicationTables();
    }
}
