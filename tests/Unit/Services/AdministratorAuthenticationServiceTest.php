<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Authentication\AdministratorIdentity;
use App\Authentication\LoginRateLimiter;
use App\Exceptions\AdministratorAuthenticationException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\AdministratorAuthenticationRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

final class AdministratorAuthenticationServiceTest extends TestCase
{
    private string $rateLimitDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateLimitDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-auth-service-'
            . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->rateLimitDirectory)) {
            foreach (glob($this->rateLimitDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                @unlink($path);
            }

            @rmdir($this->rateLimitDirectory);
        }

        parent::tearDown();
    }

    public function testItAuthenticatesByUsernameAndRecordsTheSuccessfulLogin(): void
    {
        $repository = new AuthenticationRepositoryFake($this->user());
        $logger = new AuthenticationLoggerFake();
        $service = $this->service($repository, $logger);
        $request = AdministratorLoginRequest::fromInput(
            'ADMINISTRATOR',
            'test-only-password',
            self::policy(),
        );

        $identity = $service->authenticate($request, '127.0.0.1');

        self::assertInstanceOf(AdministratorIdentity::class, $identity);
        self::assertSame(42, $identity->userId());
        self::assertSame('administrator', $repository->lastUsernameLookup);
        self::assertNull($repository->lastEmailLookup);
        self::assertSame(42, $repository->recordedUserId);
        self::assertSame('stored-password-hash', $repository->recordedExpectedHash);
        self::assertNull($repository->recordedReplacementHash);
        self::assertEquals(new DateTimeImmutable('2026-07-28 10:11:12 UTC'), $repository->recordedAt);
        self::assertSame('administrator_login_succeeded', $logger->records[0]['message']);
        self::assertSame([
            'event_code' => 'administrator_login_succeeded',
            'outcome' => 'authenticated',
            'user_id' => 42,
            'context' => 'http',
        ], $logger->records[0]['context']);
    }

    public function testItUsesTheEmailLookupWhenRequested(): void
    {
        $repository = new AuthenticationRepositoryFake($this->user());
        $logger = new AuthenticationLoggerFake();
        $service = $this->service($repository, $logger);
        $request = AdministratorLoginRequest::fromInput(
            'ADMIN@EXAMPLE.TEST',
            'test-only-password',
            self::policy(),
        );

        $identity = $service->authenticate($request, '2001:db8::1', 'api');

        self::assertSame(42, $identity->userId());
        self::assertSame('admin@example.test', $repository->lastEmailLookup);
        self::assertNull($repository->lastUsernameLookup);
        self::assertSame('api', $logger->records[0]['context']['context']);
    }

    public function testMissingWrongInactiveAndLockedAccountsShareOneGenericFailure(): void
    {
        foreach ([
            'missing' => null,
            'wrong password' => $this->user(),
            'inactive' => $this->user(User::STATUS_INACTIVE),
            'locked' => $this->user(User::STATUS_LOCKED),
        ] as $case => $user) {
            $this->removeRateLimitDirectory();
            $repository = new AuthenticationRepositoryFake($user);
            $logger = new AuthenticationLoggerFake();
            $passwordVerifier = $case === 'wrong password'
                ? static fn (string $password, string $hash): bool => false
                : static fn (string $password, string $hash): bool => true;
            $service = $this->service($repository, $logger, $passwordVerifier);
            $request = AdministratorLoginRequest::fromInput(
                'administrator',
                'test-only-password',
                self::policy(),
            );

            try {
                $service->authenticate($request, '127.0.0.1');
                self::fail('Expected generic credential rejection for ' . $case);
            } catch (AdministratorAuthenticationException $exception) {
                self::assertTrue($exception->isCredentialFailure());
                self::assertSame('The supplied credentials are invalid.', $exception->getMessage());
                self::assertNull($repository->recordedUserId);
                self::assertSame('administrator_login_rejected', $logger->records[0]['message']);
                self::assertArrayNotHasKey('identifier', $logger->records[0]['context']);
                self::assertArrayNotHasKey('remote_address', $logger->records[0]['context']);
            }
        }
    }

    public function testTheFifthFailureImmediatelyReturnsTheRateLimitFailure(): void
    {
        $repository = new AuthenticationRepositoryFake(null);
        $logger = new AuthenticationLoggerFake();
        $service = $this->service($repository, $logger);
        $request = AdministratorLoginRequest::fromInput(
            'administrator',
            'test-only-password',
            self::policy(),
        );

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $service->authenticate($request, '127.0.0.1');
                self::fail('Expected credential rejection.');
            } catch (AdministratorAuthenticationException $exception) {
                self::assertTrue($exception->isCredentialFailure());
            }
        }

        try {
            $service->authenticate($request, '127.0.0.1');
            self::fail('Expected rate limiting.');
        } catch (AdministratorAuthenticationException $exception) {
            self::assertTrue($exception->isRateLimited());
            self::assertGreaterThan(0, $exception->retryAfterSeconds());
            self::assertLessThanOrEqual(900, $exception->retryAfterSeconds());
            self::assertSame('administrator_login_rate_limited', $logger->records[4]['message']);
        }
    }


    public function testEveryUnblockedAuthenticationPathBuildsTheRuntimeDummyHashOnce(): void
    {
        $existingFactoryCalls = 0;
        $existingService = $this->service(
            new AuthenticationRepositoryFake($this->user()),
            new AuthenticationLoggerFake(),
            dummyHashFactory: static function () use (&$existingFactoryCalls): string {
                $existingFactoryCalls++;

                return password_hash('runtime-existing-dummy-sentinel', PASSWORD_DEFAULT);
            },
        );
        $request = AdministratorLoginRequest::fromInput(
            'administrator',
            'test-only-password',
            self::policy(),
        );

        $existingService->authenticate($request, '127.0.0.1');
        $existingService->authenticate($request, '127.0.0.1');

        self::assertSame(1, $existingFactoryCalls);

        $this->removeRateLimitDirectory();
        $missingFactoryCalls = 0;
        $missingService = $this->service(
            new AuthenticationRepositoryFake(null),
            new AuthenticationLoggerFake(),
            dummyHashFactory: static function () use (&$missingFactoryCalls): string {
                $missingFactoryCalls++;

                return password_hash('runtime-missing-dummy-sentinel', PASSWORD_DEFAULT);
            },
        );

        try {
            $missingService->authenticate($request, '127.0.0.1');
            self::fail('Expected missing-account rejection.');
        } catch (AdministratorAuthenticationException $exception) {
            self::assertTrue($exception->isCredentialFailure());
        }

        self::assertSame(1, $missingFactoryCalls);
    }

    public function testItRehashesOnlyWhenRequired(): void
    {
        $repository = new AuthenticationRepositoryFake($this->user());
        $service = $this->service(
            $repository,
            new AuthenticationLoggerFake(),
            passwordRehashChecker: static fn (string $hash): bool => true,
            passwordHasher: static fn (string $password): string => password_hash(
                'runtime-rehash-' . $password,
                PASSWORD_DEFAULT,
            ),
        );
        $request = AdministratorLoginRequest::fromInput(
            'administrator',
            'test-only-password',
            self::policy(),
        );

        $service->authenticate($request, '127.0.0.1');

        self::assertIsString($repository->recordedReplacementHash);
        self::assertNotSame('test-only-password', $repository->recordedReplacementHash);
        self::assertNotSame('stored-password-hash', $repository->recordedReplacementHash);
        self::assertNotSame('unknown', password_get_info($repository->recordedReplacementHash)['algoName']);
    }

    public function testARepositoryFailureBecomesOneSafeSystemFailure(): void
    {
        $repository = new AuthenticationRepositoryFake($this->user());
        $repository->failOnRecord = true;
        $logger = new AuthenticationLoggerFake();
        $service = $this->service($repository, $logger);
        $request = AdministratorLoginRequest::fromInput(
            'administrator',
            'test-only-password',
            self::policy(),
        );

        try {
            $service->authenticate($request, '127.0.0.1');
            self::fail('Expected system failure.');
        } catch (AdministratorAuthenticationException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Administrator authentication failed.', $exception->getMessage());
            self::assertSame('administrator_login_failed', $logger->records[0]['message']);
            self::assertSame([
                'event_code' => 'administrator_login_failed',
                'outcome' => 'failed',
                'context' => 'http',
            ], $logger->records[0]['context']);
        }
    }

    private function service(
        AuthenticationRepositoryFake $repository,
        AuthenticationLoggerFake $logger,
        ?callable $passwordVerifier = null,
        ?callable $passwordRehashChecker = null,
        ?callable $passwordHasher = null,
        ?callable $dummyHashFactory = null,
    ): AdministratorAuthenticationService {
        return new AdministratorAuthenticationService(
            $repository,
            new LoginRateLimiter($this->rateLimitDirectory, self::policy()),
            $logger,
            $passwordVerifier ?? static fn (string $password, string $hash): bool => $hash !== '',
            $passwordRehashChecker ?? static fn (string $hash): bool => false,
            $passwordHasher,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-28 10:11:12 UTC'),
            $dummyHashFactory
                ?? static fn (): string => password_hash('runtime-dummy-sentinel', PASSWORD_DEFAULT),
        );
    }

    private function user(string $status = User::STATUS_ACTIVE): User
    {
        $created = new DateTimeImmutable('2026-07-21 00:00:00 UTC');
        $role = new Role(1, Role::ADMINISTRATOR, null, $created, $created);

        return new User(
            42,
            $role,
            'Administrator Fixture',
            'administrator',
            'admin@example.test',
            null,
            'stored-password-hash',
            null,
            null,
            $status,
            $created,
            $created,
            null,
        );
    }

    private function removeRateLimitDirectory(): void
    {
        if (!is_dir($this->rateLimitDirectory)) {
            return;
        }

        foreach (glob($this->rateLimitDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($this->rateLimitDirectory);
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

final class AuthenticationRepositoryFake implements AdministratorAuthenticationRepository
{
    public ?string $lastUsernameLookup = null;

    public ?string $lastEmailLookup = null;

    public ?int $recordedUserId = null;

    public ?string $recordedExpectedHash = null;

    public ?string $recordedReplacementHash = null;

    public ?DateTimeImmutable $recordedAt = null;

    public bool $failOnRecord = false;

    public function __construct(private readonly ?User $user)
    {
    }

    public function findAdministratorByUsername(string $username): ?User
    {
        $this->lastUsernameLookup = $username;

        return $this->user;
    }

    public function findAdministratorByEmail(string $email): ?User
    {
        $this->lastEmailLookup = $email;

        return $this->user;
    }

    public function recordSuccessfulLogin(
        int $userId,
        string $expectedPasswordHash,
        ?string $replacementPasswordHash,
        DateTimeImmutable $loggedInAt,
    ): void {
        if ($this->failOnRecord) {
            throw new \RuntimeException('Private repository detail.');
        }

        $this->recordedUserId = $userId;
        $this->recordedExpectedHash = $expectedPasswordHash;
        $this->recordedReplacementHash = $replacementPasswordHash;
        $this->recordedAt = $loggedInAt;
    }
}

final class AuthenticationLoggerFake extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /** @param array<string, mixed> $context */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
