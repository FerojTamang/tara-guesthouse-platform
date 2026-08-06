<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Authentication\AdministratorIdentity;
use App\Models\Role;
use App\Models\User;
use App\Repositories\AdministratorSessionRepository;
use App\Services\AdministratorSessionValidator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class AdministratorSessionValidatorTest extends TestCase
{
    public function testActiveAdministratorIdentityRemainsValidAndRepositoryIsLazy(): void
    {
        $factoryCalls = 0;
        $repository = new AdministratorSessionRepositoryFake($this->user());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('log');
        $validator = new AdministratorSessionValidator(
            static function () use (&$factoryCalls, $repository): AdministratorSessionRepository {
                $factoryCalls++;

                return $repository;
            },
            $logger,
        );

        self::assertSame(0, $factoryCalls);
        self::assertTrue($validator->isValid(new AdministratorIdentity(42)));
        self::assertTrue($validator->isValid(new AdministratorIdentity(42)));
        self::assertSame(1, $factoryCalls);
        self::assertSame([42, 42], $repository->requestedIds);
    }

    /**
     * @return iterable<string, array{?User}>
     */
    public static function revokedAccounts(): iterable
    {
        yield 'missing' => [null];
        yield 'inactive' => [self::fixtureUser(status: User::STATUS_INACTIVE)];
        yield 'locked' => [self::fixtureUser(status: User::STATUS_LOCKED)];
        yield 'deleted' => [self::fixtureUser(deletedAt: new DateTimeImmutable('2026-08-06 00:00:00 UTC'))];
        yield 'non-administrator' => [self::fixtureUser(administrator: false)];
        yield 'wrong identifier' => [self::fixtureUser(id: 43)];
    }

    #[DataProvider('revokedAccounts')]
    public function testMissingOrIneligibleAccountsRevokeTheSession(?User $user): void
    {
        $repository = new AdministratorSessionRepositoryFake($user);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(
                'warning',
                'administrator_session_revoked',
                [
                    'event_code' => 'administrator_session_revoked',
                    'outcome' => 'revoked',
                    'user_id' => 42,
                    'context' => 'session',
                ],
            );
        $validator = new AdministratorSessionValidator(
            static fn (): AdministratorSessionRepository => $repository,
            $logger,
        );

        self::assertFalse($validator->isValid(new AdministratorIdentity(42)));
    }

    public function testRepositoryFailureRevokesTheSessionAndLogsOnlySafeContext(): void
    {
        $repository = new AdministratorSessionRepositoryFake(null);
        $repository->fail = true;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(
                'error',
                'administrator_session_validation_failed',
                [
                    'event_code' => 'administrator_session_validation_failed',
                    'outcome' => 'failed',
                    'user_id' => 42,
                    'context' => 'session',
                ],
            );
        $validator = new AdministratorSessionValidator(
            static fn (): AdministratorSessionRepository => $repository,
            $logger,
        );

        self::assertFalse($validator->isValid(new AdministratorIdentity(42)));
    }

    public function testInvalidRepositoryFactoryFailsClosed(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('log')
            ->with(
                'error',
                'administrator_session_validation_failed',
                self::isArray(),
            );
        $validator = new AdministratorSessionValidator(
            static fn (): object => new \stdClass(),
            $logger,
        );

        self::assertFalse($validator->isValid(new AdministratorIdentity(42)));
    }

    private function user(): User
    {
        return self::fixtureUser();
    }

    private static function fixtureUser(
        int $id = 42,
        string $status = User::STATUS_ACTIVE,
        bool $administrator = true,
        ?DateTimeImmutable $deletedAt = null,
    ): User {
        $created = new DateTimeImmutable('2026-08-01 00:00:00 UTC');
        $role = new Role(
            $administrator ? 1 : 2,
            $administrator ? Role::ADMINISTRATOR : 'Reception',
            null,
            $created,
            $created,
        );

        return new User(
            $id,
            $role,
            'Administrator Fixture',
            'administrator',
            'administrator@example.test',
            null,
            'test-only-password-hash',
            null,
            null,
            $status,
            $created,
            $created,
            $deletedAt,
        );
    }
}

final class AdministratorSessionRepositoryFake implements AdministratorSessionRepository
{
    /** @var list<int> */
    public array $requestedIds = [];

    public bool $fail = false;

    public function __construct(private readonly ?User $user)
    {
    }

    public function findAdministratorById(int $id): ?User
    {
        $this->requestedIds[] = $id;

        if ($this->fail) {
            throw new RuntimeException('test-only repository failure');
        }

        return $this->user;
    }
}
