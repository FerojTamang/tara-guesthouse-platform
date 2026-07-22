<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Core\SeederRunner;
use App\Exceptions\DatabaseException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $repository;

    /** @var array<string, int> */
    private array $userIds = [];

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

        $administratorRoleId = (int) $this->connection->query(
            "SELECT id FROM roles WHERE role_name = 'Administrator'",
        )->fetchColumn();
        $otherRoleId = $this->insertRole('TestOnlyNonAdministrator');

        $this->userIds['active'] = $this->insertUser(
            $administratorRoleId,
            'CaseAdmin',
            'CaseAdmin@Example.test',
            User::STATUS_ACTIVE,
            phone: '+977000000001',
            profileImage: 'profiles/case-admin.webp',
            lastLoginAt: '2026-07-21 03:04:05',
        );
        $this->userIds['inactive'] = $this->insertUser(
            $administratorRoleId,
            'inactive-admin',
            'inactive@example.test',
            User::STATUS_INACTIVE,
        );
        $this->userIds['locked'] = $this->insertUser(
            $administratorRoleId,
            'locked-admin',
            'locked@example.test',
            User::STATUS_LOCKED,
        );
        $this->userIds['deleted'] = $this->insertUser(
            $administratorRoleId,
            'deleted-admin',
            'deleted@example.test',
            User::STATUS_ACTIVE,
            deletedAt: '2026-07-21 04:05:06',
        );
        $this->userIds['other_role'] = $this->insertUser(
            $otherRoleId,
            'test-other-role',
            'test-other-role@example.test',
            User::STATUS_ACTIVE,
        );
        $this->repository = new UserRepository($this->connection);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->cleanupAuthenticationTables();
        }

        parent::tearDown();
    }

    public function testItFindsByIdAndMapsTheCompleteAdministratorRecord(): void
    {
        $user = $this->repository->findAdministratorById($this->userIds['active']);

        self::assertInstanceOf(User::class, $user);
        self::assertSame($this->userIds['active'], $user->id());
        self::assertSame('Administrator Fixture', $user->fullName());
        self::assertSame('CaseAdmin', $user->username());
        self::assertSame('CaseAdmin@Example.test', $user->email());
        self::assertSame('+977000000001', $user->phone());
        self::assertSame('test-only-password-hash-sentinel', $user->passwordHash());
        self::assertSame('profiles/case-admin.webp', $user->profileImage());
        self::assertEquals(new DateTimeImmutable('2026-07-21 03:04:05 UTC'), $user->lastLoginAt());
        self::assertSame(User::STATUS_ACTIVE, $user->accountStatus());
        self::assertTrue($user->isActive());
        self::assertFalse($user->isDeleted());
        self::assertTrue($user->isAdministrator());
        self::assertSame('UTC', $user->createdAt()->getTimezone()->getName());
        self::assertSame('UTC', $user->updatedAt()->getTimezone()->getName());

        $role = $user->role();
        self::assertInstanceOf(Role::class, $role);
        self::assertSame(Role::ADMINISTRATOR, $role->name());
        self::assertNull($role->description());
        self::assertTrue($role->isAdministrator());
        self::assertSame('UTC', $role->createdAt()->getTimezone()->getName());
    }

    public function testUsernameAndEmailLookupsUseTheApprovedDatabaseCollationWithoutNormalization(): void
    {
        $byUsername = $this->repository->findAdministratorByUsername('caseadmin');
        $byEmail = $this->repository->findAdministratorByEmail('caseadmin@example.test');

        self::assertInstanceOf(User::class, $byUsername);
        self::assertInstanceOf(User::class, $byEmail);
        self::assertSame($this->userIds['active'], $byUsername->id());
        self::assertSame($this->userIds['active'], $byEmail->id());
        self::assertSame('CaseAdmin', $byUsername->username());
        self::assertSame('CaseAdmin@Example.test', $byEmail->email());
    }

    public function testItReturnsEveryApprovedAccountStatusForLaterPolicyEvaluation(): void
    {
        $active = $this->repository->findAdministratorById($this->userIds['active']);
        $inactive = $this->repository->findAdministratorByUsername('inactive-admin');
        $locked = $this->repository->findAdministratorByEmail('locked@example.test');

        self::assertInstanceOf(User::class, $active);
        self::assertInstanceOf(User::class, $inactive);
        self::assertInstanceOf(User::class, $locked);
        self::assertTrue($active->isActive());
        self::assertTrue($inactive->isInactive());
        self::assertTrue($locked->isLocked());
    }

    public function testItExcludesDeletedAndNonAdministratorRecords(): void
    {
        self::assertNull($this->repository->findAdministratorById($this->userIds['deleted']));
        self::assertNull($this->repository->findAdministratorByUsername('deleted-admin'));
        self::assertNull($this->repository->findAdministratorByEmail('deleted@example.test'));
        self::assertNull($this->repository->findAdministratorById($this->userIds['other_role']));
        self::assertNull($this->repository->findAdministratorByUsername('test-other-role'));
        self::assertNull($this->repository->findAdministratorByEmail('test-other-role@example.test'));
    }

    public function testItRequiresExactAdministratorRoleCasing(): void
    {
        $this->connection->exec("UPDATE roles SET role_name = 'administrator' WHERE role_name = 'Administrator'");

        self::assertNull($this->repository->findAdministratorById($this->userIds['active']));
        self::assertNull($this->repository->findAdministratorByUsername('CaseAdmin'));
        self::assertNull($this->repository->findAdministratorByEmail('CaseAdmin@Example.test'));
    }

    public function testMissingAndInjectionShapedLookupsReturnNullWithoutMutation(): void
    {
        $before = $this->databaseSnapshot();

        self::assertNull($this->repository->findAdministratorById(0));
        self::assertNull($this->repository->findAdministratorById(999999));
        self::assertNull($this->repository->findAdministratorByUsername("' OR 1=1 --"));
        self::assertNull($this->repository->findAdministratorByEmail("x' UNION SELECT password FROM users --"));

        self::assertSame($before, $this->databaseSnapshot());
    }

    public function testItRecordsASuccessfulAdministratorLoginWithoutChangingTheHash(): void
    {
        $loggedInAt = new DateTimeImmutable('2026-07-28 10:11:12 UTC');

        $this->repository->recordSuccessfulLogin(
            $this->userIds['active'],
            'test-only-password-hash-sentinel',
            null,
            $loggedInAt,
        );

        $row = $this->connection->query(
            'SELECT password, last_login_at FROM users WHERE id = ' . $this->userIds['active'],
        )->fetch();

        self::assertIsArray($row);
        self::assertSame('test-only-password-hash-sentinel', $row['password']);
        self::assertSame('2026-07-28 10:11:12', $row['last_login_at']);
        self::assertFalse($this->connection->inTransaction());
    }

    public function testItAtomicallyReplacesTheHashAndRecordsTheLoginTime(): void
    {
        $replacementHash = password_hash('runtime-test-replacement', PASSWORD_DEFAULT);
        self::assertIsString($replacementHash);

        $this->repository->recordSuccessfulLogin(
            $this->userIds['active'],
            'test-only-password-hash-sentinel',
            $replacementHash,
            new DateTimeImmutable('2026-07-28 11:12:13 UTC'),
        );

        $row = $this->connection->query(
            'SELECT password, last_login_at FROM users WHERE id = ' . $this->userIds['active'],
        )->fetch();

        self::assertIsArray($row);
        self::assertSame($replacementHash, $row['password']);
        self::assertSame('2026-07-28 11:12:13', $row['last_login_at']);
        self::assertFalse($this->connection->inTransaction());
    }

    public function testItRejectsStaleOrIneligibleLoginStateWithoutPartialMutation(): void
    {
        $cases = [
            'stale hash' => [$this->userIds['active'], 'wrong-expected-hash'],
            'inactive' => [$this->userIds['inactive'], 'test-only-password-hash-sentinel'],
            'locked' => [$this->userIds['locked'], 'test-only-password-hash-sentinel'],
            'deleted' => [$this->userIds['deleted'], 'test-only-password-hash-sentinel'],
            'other role' => [$this->userIds['other_role'], 'test-only-password-hash-sentinel'],
        ];

        foreach ($cases as $case => [$userId, $expectedHash]) {
            $before = $this->connection->query(
                'SELECT password, last_login_at FROM users WHERE id = ' . $userId,
            )->fetch();

            try {
                $this->repository->recordSuccessfulLogin(
                    $userId,
                    $expectedHash,
                    password_hash('runtime-rejected-rehash-' . $case, PASSWORD_DEFAULT),
                    new DateTimeImmutable('2026-07-28 12:13:14 UTC'),
                );
                self::fail('Expected login update rejection for ' . $case);
            } catch (DatabaseException $exception) {
                self::assertSame('The Administrator login update failed.', $exception->getMessage());
                self::assertSame(
                    $before,
                    $this->connection->query(
                        'SELECT password, last_login_at FROM users WHERE id = ' . $userId,
                    )->fetch(),
                );
                self::assertFalse($this->connection->inTransaction());
            }
        }
    }

    private function insertRole(string $name): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO roles (role_name, description) VALUES (:role_name, :description)',
        );
        $statement->execute(['role_name' => $name, 'description' => 'Test fixture only']);

        return (int) $this->connection->lastInsertId();
    }

    private function insertUser(
        int $roleId,
        string $username,
        string $email,
        string $status,
        ?string $phone = null,
        ?string $profileImage = null,
        ?string $lastLoginAt = null,
        ?string $deletedAt = null,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO users (role_id, full_name, username, email, phone, password, profile_image, '
            . 'last_login_at, account_status, deleted_at) '
            . 'VALUES (:role_id, :full_name, :username, :email, :phone, :password, :profile_image, '
            . ':last_login_at, :account_status, :deleted_at)',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Administrator Fixture',
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password' => 'test-only-password-hash-sentinel',
            'profile_image' => $profileImage,
            'last_login_at' => $lastLoginAt,
            'account_status' => $status,
            'deleted_at' => $deletedAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function databaseSnapshot(): string
    {
        $roles = $this->connection->query('SELECT * FROM roles ORDER BY id')->fetchAll();
        $users = $this->connection->query('SELECT * FROM users ORDER BY id')->fetchAll();

        return json_encode(['roles' => $roles, 'users' => $users], JSON_THROW_ON_ERROR);
    }

    private function cleanupAuthenticationTables(): void
    {
        $this->dropTables(['users', 'roles', 'schema_migrations']);
    }
}
