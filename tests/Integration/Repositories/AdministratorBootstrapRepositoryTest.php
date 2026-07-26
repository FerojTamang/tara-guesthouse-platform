<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\ConnectionFactory;
use App\Core\MigrationRunner;
use App\Core\SeederRunner;
use App\Exceptions\AdministratorBootstrapException;
use App\Models\User;
use App\Repositories\AdministratorBootstrapRepository;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AdministratorBootstrapRepositoryTest extends DatabaseTestCase
{
    private AdministratorBootstrapRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupAuthenticationTables();
        $basePath = dirname(__DIR__, 3);
        (new MigrationRunner(
            $this->connection,
            $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        (new SeederRunner(
            $this->connection,
            $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders',
        ))->run('production', 'testing');
        $this->repository = new AdministratorBootstrapRepository($this->connection);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            $this->cleanupAuthenticationTables();
        }

        parent::tearDown();
    }

    public function testItLocksTheExactRoleAndInsertsOneApprovedAdministrator(): void
    {
        $hash = password_hash('test-only-bootstrap-secret', PASSWORD_DEFAULT);
        self::assertIsString($hash);

        $userId = $this->repository->withinTransaction(function () use ($hash): int {
            $roleId = $this->repository->lockAdministratorRole();
            self::assertSame([], $this->repository->administratorUsers($roleId));
            self::assertSame([], $this->repository->identityCollisions('bootstrap.operator', 'operator@example.test'));

            return $this->repository->insertAdministrator(
                $roleId,
                'Bootstrap Operator',
                'bootstrap.operator',
                'operator@example.test',
                null,
                $hash,
            );
        });

        $statement = $this->connection->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch();

        self::assertIsArray($row);
        self::assertSame('active', $row['account_status']);
        self::assertSame($hash, $row['password']);
        self::assertNull($row['phone']);
        self::assertNull($row['profile_image']);
        self::assertNull($row['last_login_at']);
        self::assertNull($row['deleted_at']);
        self::assertNotEmpty($row['created_at']);
        self::assertNotEmpty($row['updated_at']);
    }

    public function testRolePrerequisiteRejectsMissingWrongCaseAndUnexpectedDescription(): void
    {
        foreach ([
            "DELETE FROM roles WHERE role_name = 'Administrator'",
            "UPDATE roles SET role_name = 'administrator' WHERE role_name = 'Administrator'",
            "UPDATE roles SET description = 'unexpected' WHERE role_name = 'Administrator'",
        ] as $mutation) {
            $this->connection->exec("DELETE FROM users");
            $this->connection->exec("DELETE FROM roles");
            $this->connection->exec("INSERT INTO roles (role_name, description) VALUES ('Administrator', NULL)");
            $this->connection->exec($mutation);

            try {
                $this->repository->withinTransaction(fn (): int => $this->repository->lockAdministratorRole());
                self::fail('Expected invalid role prerequisite rejection.');
            } catch (AdministratorBootstrapException $exception) {
                self::assertFalse($exception->isSystemFailure());
                self::assertStringContainsString('prerequisite', $exception->getMessage());
            }
        }
    }

    public function testAdministratorScanIncludesInactiveLockedAndDeletedAccounts(): void
    {
        $roleId = $this->administratorRoleId();
        $this->insertUser($roleId, 'inactive-one', 'inactive@example.test', User::STATUS_INACTIVE);
        $this->insertUser($roleId, 'locked-one', 'locked@example.test', User::STATUS_LOCKED);
        $this->insertUser(
            $roleId,
            'deleted-one',
            'deleted@example.test',
            User::STATUS_ACTIVE,
            '2026-07-21 00:00:00',
        );

        $facts = $this->repository->withinTransaction(
            fn (): array => $this->repository->administratorUsers($roleId),
        );

        self::assertCount(3, $facts);
        self::assertSame(['inactive', 'locked', 'active'], array_column($facts, 'account_status'));
        self::assertSame([false, false, true], array_column($facts, 'deleted'));
    }

    public function testIdentityScanIncludesOtherRolesAndDeletedAccounts(): void
    {
        $otherRoleId = $this->insertRole('TestOnlyOtherRole');
        $this->insertUser(
            $otherRoleId,
            'collision.operator',
            'collision@example.test',
            User::STATUS_ACTIVE,
            '2026-07-21 00:00:00',
        );

        $facts = $this->repository->withinTransaction(
            fn (): array => $this->repository->identityCollisions(
                'collision.operator',
                'collision@example.test',
            ),
        );

        self::assertCount(1, $facts);
        self::assertTrue($facts[0]['deleted']);
    }

    public function testIdentityScanTreatsInjectionShapedValuesAsData(): void
    {
        $before = (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $facts = $this->repository->withinTransaction(
            fn (): array => $this->repository->identityCollisions(
                "' OR 1=1 --",
                "x' UNION SELECT password FROM users --",
            ),
        );

        self::assertSame([], $facts);
        self::assertSame($before, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testWorkflowExceptionRollsBackWithoutPartialAdministrator(): void
    {
        try {
            $this->repository->withinTransaction(function (): void {
                $this->repository->insertAdministrator(
                    $this->repository->lockAdministratorRole(),
                    'Rollback Fixture',
                    'rollback.fixture',
                    'rollback@example.test',
                    null,
                    password_hash('test-only-rollback-secret', PASSWORD_DEFAULT),
                );
                throw AdministratorBootstrapException::workflow('Test rollback.');
            });
            self::fail('Expected test rollback exception.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertSame('Test rollback.', $exception->getMessage());
        }

        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testNestedTransactionsAreRejectedWithoutChangingTheOuterTransaction(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->repository->withinTransaction(static fn (): int => 1);
            self::fail('Expected nested transaction rejection.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertTrue($this->connection->inTransaction());
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testConcurrentRoleLockTimesOutSafelyAndCreatesNoUser(): void
    {
        $secondConnection = (new ConnectionFactory($this->databaseConfiguration))->create();
        $secondConnection->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $secondRepository = new AdministratorBootstrapRepository($secondConnection);

        $this->connection->beginTransaction();

        try {
            $this->repository->lockAdministratorRole();

            try {
                $secondRepository->withinTransaction(fn (): int => $secondRepository->lockAdministratorRole());
                self::fail('Expected the competing bootstrap lock to time out.');
            } catch (AdministratorBootstrapException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Administrator bootstrap failed.', $exception->getMessage());
            }
        } finally {
            $this->connection->rollBack();
        }

        self::assertFalse($secondConnection->inTransaction());
        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testDatabaseFailuresDoNotDiscloseSqlOrIdentifiers(): void
    {
        $this->dropTables([
            'booking_events',
            'booking_rooms',
            'bookings',
            'users',
        ]);

        try {
            $this->repository->withinTransaction(
                fn (): array => $this->repository->identityCollisions(
                    'private-username-sentinel',
                    'private-email-sentinel@example.test',
                ),
            );
            self::fail('Expected safe database failure.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertSame('Administrator bootstrap failed.', $exception->getMessage());
            self::assertStringNotContainsString('SELECT', $exception->getMessage());
            self::assertStringNotContainsString('private-', $exception->getMessage());
        }
    }

    private function administratorRoleId(): int
    {
        return (int) $this->connection->query(
            "SELECT id FROM roles WHERE BINARY role_name = 'Administrator'",
        )->fetchColumn();
    }

    private function insertRole(string $name): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO roles (role_name, description) VALUES (:name, :description)',
        );
        $statement->execute(['name' => $name, 'description' => 'Test fixture only']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertUser(
        int $roleId,
        string $username,
        string $email,
        string $status,
        ?string $deletedAt = null,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO users (role_id, full_name, username, email, phone, password, profile_image, '
            . 'last_login_at, account_status, deleted_at) VALUES '
            . '(:role_id, :full_name, :username, :email, NULL, :password, NULL, NULL, :status, :deleted_at)',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Test Administrator Fixture',
            'username' => $username,
            'email' => $email,
            'password' => 'not-a-usable-hash',
            'status' => $status,
            'deleted_at' => $deletedAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function cleanupAuthenticationTables(): void
    {
        $this->dropTables(['users', 'roles', 'schema_migrations']);
    }
}
