<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Core\SeederRunner;
use App\Exceptions\AdministratorBootstrapException;
use App\Models\User;
use App\Repositories\AdministratorBootstrapRepository;
use App\Requests\AdministratorBootstrapRequest;
use App\Services\AdministratorBootstrapService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AdministratorBootstrapServiceTest extends DatabaseTestCase
{
    /** @var array<string, mixed> */
    private array $policy;

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
        $security = require $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'security.php';
        $this->policy = $security['administrator_bootstrap'];
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->cleanupAuthenticationTables();
        }

        parent::tearDown();
    }

    public function testSuccessfulBootstrapStoresOnlyAnApprovedActiveAdministrator(): void
    {
        $request = $this->request();
        $userId = $this->service()->bootstrap($request, 'testing', false);
        $statement = $this->connection->prepare(
            'SELECT users.*, roles.role_name, roles.description AS role_description '
            . 'FROM users INNER JOIN roles ON roles.id = users.role_id WHERE users.id = :id',
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch();

        self::assertIsArray($row);
        self::assertSame('Administrator', $row['role_name']);
        self::assertNull($row['role_description']);
        self::assertSame('Bootstrap Operator', $row['full_name']);
        self::assertSame('bootstrap.operator', $row['username']);
        self::assertSame('bootstrap.operator@example.test', $row['email']);
        self::assertSame('+977 15550101', $row['phone']);
        self::assertSame(User::STATUS_ACTIVE, $row['account_status']);
        self::assertNull($row['profile_image']);
        self::assertNull($row['last_login_at']);
        self::assertNull($row['deleted_at']);
        self::assertNotSame($request->password(), $row['password']);
        self::assertTrue(password_verify($request->password(), $row['password']));
        self::assertNotEmpty($row['created_at']);
        self::assertNotEmpty($row['updated_at']);
    }

    public function testRepeatedBootstrapFailsWithoutChangingTheExistingHash(): void
    {
        $request = $this->request();
        $this->service()->bootstrap($request, 'testing', false);
        $before = (string) $this->connection->query('SELECT password FROM users')->fetchColumn();

        try {
            $this->service()->bootstrap($request, 'testing', false);
            self::fail('Expected repeated bootstrap rejection.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertStringContainsString('already complete', $exception->getMessage());
            self::assertFalse($exception->isSystemFailure());
        }

        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertSame($before, $this->connection->query('SELECT password FROM users')->fetchColumn());
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function unsafeAdministratorStates(): iterable
    {
        yield 'inactive' => [User::STATUS_INACTIVE, null];
        yield 'locked' => [User::STATUS_LOCKED, null];
        yield 'deleted' => [User::STATUS_ACTIVE, '2026-07-21 00:00:00'];
    }

    #[DataProvider('unsafeAdministratorStates')]
    public function testUnsafeExistingAdministratorStateRequiresAccountManagement(
        string $status,
        ?string $deletedAt,
    ): void {
        $this->insertUser(
            $this->administratorRoleId(),
            'existing.admin',
            'existing.admin@example.test',
            $status,
            $deletedAt,
        );

        try {
            $this->service()->bootstrap($this->request(), 'testing', false);
            self::fail('Expected unsafe-state rejection.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertStringContainsString('account-management', $exception->getMessage());
        }

        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    /** @return iterable<string, array{string, string}> */
    public static function otherRoleCollisions(): iterable
    {
        yield 'username' => ['bootstrap.operator', 'other@example.test'];
        yield 'email' => ['other.operator', 'bootstrap.operator@example.test'];
    }

    #[DataProvider('otherRoleCollisions')]
    public function testIdentityCollisionAcrossAnotherRoleFailsWithoutInsert(
        string $username,
        string $email,
    ): void
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) VALUES ('TestOnlyOtherRole', 'Test fixture only')",
        );
        $otherRoleId = (int) $this->connection->lastInsertId();
        $this->insertUser(
            $otherRoleId,
            $username,
            $email,
            User::STATUS_ACTIVE,
            null,
        );

        try {
            $this->service()->bootstrap($this->request(), 'testing', false);
            self::fail('Expected identity-collision rejection.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertStringContainsString('conflicts', $exception->getMessage());
        }

        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRoleMutations(): iterable
    {
        yield 'missing' => ["DELETE FROM roles WHERE role_name = 'Administrator'"];
        yield 'wrong case' => ["UPDATE roles SET role_name = 'administrator' WHERE role_name = 'Administrator'"];
        yield 'description populated' => ["UPDATE roles SET description = 'unexpected' WHERE role_name = 'Administrator'"];
    }

    #[DataProvider('invalidRoleMutations')]
    public function testInvalidRolePrerequisiteFailsWithoutPartialAccount(string $mutation): void
    {
        $this->connection->exec($mutation);

        try {
            $this->service()->bootstrap($this->request(), 'testing', false);
            self::fail('Expected role-prerequisite rejection.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertStringContainsString('prerequisite', $exception->getMessage());
        }

        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testMultipleAdministratorRowsFailGenericallyWithoutMutation(): void
    {
        $roleId = $this->administratorRoleId();
        $this->insertUser($roleId, 'first.admin', 'first.admin@example.test', User::STATUS_ACTIVE, null);
        $this->insertUser($roleId, 'second.admin', 'second.admin@example.test', User::STATUS_ACTIVE, null);

        try {
            $this->service()->bootstrap($this->request(), 'testing', false);
            self::fail('Expected fail-safe multiple-Administrator rejection.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertSame('Administrator bootstrap failed.', $exception->getMessage());
            self::assertTrue($exception->isSystemFailure());
        }

        self::assertSame(2, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    private function service(): AdministratorBootstrapService
    {
        return new AdministratorBootstrapService(
            fn (): AdministratorBootstrapRepository => new AdministratorBootstrapRepository($this->connection),
            new NullLogger(),
            $this->policy,
        );
    }

    private function request(): AdministratorBootstrapRequest
    {
        $secret = implode('', ['S1B-integration-', 'Secret-5709']);

        return AdministratorBootstrapRequest::fromInput(
            'Bootstrap Operator',
            'bootstrap.operator',
            'bootstrap.operator@example.test',
            '+977 15550101',
            $secret,
            $secret,
            $this->policy,
        );
    }

    private function administratorRoleId(): int
    {
        return (int) $this->connection->query(
            "SELECT id FROM roles WHERE BINARY role_name = 'Administrator'",
        )->fetchColumn();
    }

    private function insertUser(
        int $roleId,
        string $username,
        string $email,
        string $status,
        ?string $deletedAt,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO users (role_id, full_name, username, email, phone, password, profile_image, '
            . 'last_login_at, account_status, deleted_at) VALUES '
            . '(:role_id, :full_name, :username, :email, NULL, :password, NULL, NULL, :status, :deleted_at)',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Existing Test Fixture',
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
