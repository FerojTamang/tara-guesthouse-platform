<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use App\Core\SeederRunner;
use App\Exceptions\DatabaseException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AdministratorRoleSeederTest extends DatabaseTestCase
{
    private SeederRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupAuthenticationTables();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->runner = new SeederRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders',
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->cleanupAuthenticationTables();
        }

        parent::tearDown();
    }

    public function testItSeedsOnlyTheExactAdministratorRoleAndNoUser(): void
    {
        self::assertSame(['administrator_role'], $this->runner->run('production', 'testing'));
        $roles = $this->connection->query(
            'SELECT role_name, description FROM roles ORDER BY id',
        )->fetchAll();

        self::assertSame([[
            'role_name' => 'Administrator',
            'description' => null,
        ]], $roles);
        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());

        $serialized = json_encode($roles, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Guest', $serialized);
        self::assertStringNotContainsString('Customer', $serialized);
        self::assertStringNotContainsString('Manager', $serialized);
        self::assertStringNotContainsString('Receptionist', $serialized);
        self::assertStringNotContainsString('Accountant', $serialized);
        self::assertStringNotContainsString('Content Manager', $serialized);
    }

    public function testRepeatedExecutionIsAnIdempotentNoOp(): void
    {
        self::assertSame(['administrator_role'], $this->runner->run('production', 'testing'));
        $first = $this->connection->query('SELECT id, role_name, description, created_at, updated_at FROM roles')->fetch();
        self::assertIsArray($first);

        self::assertSame(['administrator_role'], $this->runner->run('production', 'testing'));
        $second = $this->connection->query('SELECT id, role_name, description, created_at, updated_at FROM roles')->fetch();

        self::assertSame($first, $second);
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM roles')->fetchColumn());
        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testConflictingRoleCasingFailsWithoutDestructiveChanges(): void
    {
        $this->insertRole('administrator', null);
        $before = $this->connection->query('SELECT id, role_name, description FROM roles')->fetchAll();

        try {
            $this->runner->run('production', 'testing');
            self::fail('Conflicting Administrator role casing was accepted.');
        } catch (DatabaseException) {
            self::assertSame($before, $this->connection->query('SELECT id, role_name, description FROM roles')->fetchAll());
            self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
        }
    }

    public function testIncompatibleExistingDescriptionFailsWithoutBeingRewritten(): void
    {
        $this->insertRole('Administrator', 'Unapproved description');
        $before = $this->connection->query('SELECT id, role_name, description FROM roles')->fetchAll();

        try {
            $this->runner->run('production', 'testing');
            self::fail('Incompatible Administrator role data was accepted.');
        } catch (DatabaseException) {
            self::assertSame($before, $this->connection->query('SELECT id, role_name, description FROM roles')->fetchAll());
            self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
        }
    }

    private function insertRole(string $name, ?string $description): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO roles (role_name, description) VALUES (:role_name, :description)',
        );
        $statement->execute(['role_name' => $name, 'description' => $description]);
    }

    private function cleanupAuthenticationTables(): void
    {
        $this->dropTables(['users', 'roles', 'schema_migrations']);
    }
}
