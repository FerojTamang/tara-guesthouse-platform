<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Authentication\LoginRateLimiter;
use App\Core\MigrationRunner;
use App\Core\SeederRunner;
use App\Exceptions\AdministratorAuthenticationException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticationService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AdministratorAuthenticationServiceTest extends DatabaseTestCase
{
    private string $rateLimitDirectory;

    private int $administratorRoleId;

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
        $this->administratorRoleId = (int) $this->connection->query(
            "SELECT id FROM roles WHERE role_name = 'Administrator'",
        )->fetchColumn();
        $this->rateLimitDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-auth-integration-'
            . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->cleanupAuthenticationTables();
        }

        if (isset($this->rateLimitDirectory) && is_dir($this->rateLimitDirectory)) {
            foreach (glob($this->rateLimitDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }

            @rmdir($this->rateLimitDirectory);
        }

        parent::tearDown();
    }

    public function testItAuthenticatesByUsernameRecordsTheTimeAndClearsPriorFailures(): void
    {
        $password = 'integration-only-password';
        $userId = $this->insertAdministrator(
            'administrator',
            'administrator@example.test',
            password_hash($password, PASSWORD_DEFAULT),
            User::STATUS_ACTIVE,
        );
        $rateLimiter = new LoginRateLimiter($this->rateLimitDirectory, self::policy());
        $rateLimiter->recordFailure('127.0.0.1');
        $service = $this->service(
            $rateLimiter,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-28 10:11:12 UTC'),
        );
        $request = AdministratorLoginRequest::fromInput('ADMINISTRATOR', $password, self::policy());

        $identity = $service->authenticate($request, '127.0.0.1');

        self::assertSame($userId, $identity->userId());
        $row = $this->connection->query(
            'SELECT last_login_at FROM users WHERE id = ' . $userId,
        )->fetch();
        self::assertIsArray($row);
        self::assertSame('2026-07-28 10:11:12', $row['last_login_at']);
        self::assertSame(5, $rateLimiter->inspect('127.0.0.1')->remainingAttempts());
    }

    public function testItAuthenticatesByEmailAndRehashesAnOutdatedPassword(): void
    {
        $password = 'integration-only-rehash-password';
        $oldHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        self::assertIsString($oldHash);
        self::assertTrue(password_needs_rehash($oldHash, PASSWORD_DEFAULT));
        $userId = $this->insertAdministrator(
            'rehash-admin',
            'rehash-admin@example.test',
            $oldHash,
            User::STATUS_ACTIVE,
        );
        $service = $this->service(
            new LoginRateLimiter($this->rateLimitDirectory, self::policy()),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-28 11:12:13 UTC'),
        );
        $request = AdministratorLoginRequest::fromInput(
            'REHASH-ADMIN@EXAMPLE.TEST',
            $password,
            self::policy(),
        );

        $service->authenticate($request, '2001:db8::1');

        $row = $this->connection->query(
            'SELECT password, last_login_at FROM users WHERE id = ' . $userId,
        )->fetch();
        self::assertIsArray($row);
        self::assertNotSame($oldHash, $row['password']);
        self::assertTrue(password_verify($password, $row['password']));
        self::assertFalse(password_needs_rehash($row['password'], PASSWORD_DEFAULT));
        self::assertSame('2026-07-28 11:12:13', $row['last_login_at']);
    }

    public function testWrongMissingInactiveAndLockedAccountsUseTheSameGenericFailure(): void
    {
        $password = 'integration-only-generic-password';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        self::assertIsString($hash);
        $this->insertAdministrator('active-admin', 'active@example.test', $hash, User::STATUS_ACTIVE);
        $this->insertAdministrator('inactive-admin', 'inactive@example.test', $hash, User::STATUS_INACTIVE);
        $this->insertAdministrator('locked-admin', 'locked@example.test', $hash, User::STATUS_LOCKED);
        $service = $this->service(new LoginRateLimiter($this->rateLimitDirectory, self::policy()));
        $cases = [
            ['active-admin', 'wrong-password', '127.0.0.1'],
            ['missing-admin', $password, '127.0.0.2'],
            ['inactive-admin', $password, '127.0.0.3'],
            ['locked-admin', $password, '127.0.0.4'],
        ];

        foreach ($cases as [$identifier, $suppliedPassword, $remoteAddress]) {
            try {
                $service->authenticate(
                    AdministratorLoginRequest::fromInput($identifier, $suppliedPassword, self::policy()),
                    $remoteAddress,
                );
                self::fail('Expected generic credential failure.');
            } catch (AdministratorAuthenticationException $exception) {
                self::assertTrue($exception->isCredentialFailure());
                self::assertSame('The supplied credentials are invalid.', $exception->getMessage());
            }
        }
    }

    public function testTheFifthFailedAttemptBlocksWithoutDatabaseMutation(): void
    {
        $service = $this->service(new LoginRateLimiter($this->rateLimitDirectory, self::policy()));
        $request = AdministratorLoginRequest::fromInput(
            'missing-admin',
            'integration-only-password',
            self::policy(),
        );

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $service->authenticate($request, '127.0.0.1');
                self::fail('Expected credential failure.');
            } catch (AdministratorAuthenticationException $exception) {
                self::assertTrue($exception->isCredentialFailure());
            }
        }

        try {
            $service->authenticate($request, '127.0.0.1');
            self::fail('Expected rate-limit failure.');
        } catch (AdministratorAuthenticationException $exception) {
            self::assertTrue($exception->isRateLimited());
            self::assertGreaterThan(0, $exception->retryAfterSeconds());
            self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
        }
    }

    private function service(
        LoginRateLimiter $rateLimiter,
        ?callable $clock = null,
    ): AdministratorAuthenticationService {
        return new AdministratorAuthenticationService(
            new UserRepository($this->connection),
            $rateLimiter,
            new NullLogger(),
            clock: $clock,
        );
    }

    private function insertAdministrator(
        string $username,
        string $email,
        string $passwordHash,
        string $status,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO users (role_id, full_name, username, email, password, account_status) '
            . 'VALUES (:role_id, :full_name, :username, :email, :password, :account_status)',
        );
        $statement->execute([
            'role_id' => $this->administratorRoleId,
            'full_name' => 'Administrator Fixture',
            'username' => $username,
            'email' => $email,
            'password' => $passwordHash,
            'account_status' => $status,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function cleanupAuthenticationTables(): void
    {
        $this->dropTables(['users', 'roles', 'schema_migrations']);
    }

    /** @return array<string, int> */
    private static function policy(): array
    {
        return [
            'username_min_characters' => 3,
            'username_max_characters' => 50,
            'identifier_max_bytes' => 150,
            'password_max_bytes' => 72,
            'max_failed_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ];
    }
}
