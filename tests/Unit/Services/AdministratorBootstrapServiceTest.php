<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AdministratorBootstrapException;
use App\Models\User;
use App\Repositories\AdministratorBootstrapRepository;
use App\Requests\AdministratorBootstrapRequest;
use App\Services\AdministratorBootstrapService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

final class AdministratorBootstrapServiceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $configuration = require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config'
            . DIRECTORY_SEPARATOR . 'security.php';
        $this->policy = $configuration['administrator_bootstrap'];
    }

    public function testItCreatesOneAdministratorInsideTheRepositoryTransaction(): void
    {
        $request = $this->request();
        $logger = new AdministratorBootstrapMemoryLogger();
        $repository = $this->repository();
        $repository->expects(self::once())
            ->method('withinTransaction')
            ->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $repository->expects(self::once())->method('lockAdministratorRole')->willReturn(7);
        $repository->expects(self::once())->method('administratorUsers')->with(7)->willReturn([]);
        $repository->expects(self::once())
            ->method('identityCollisions')
            ->with('bootstrap.operator', 'bootstrap.operator@example.test')
            ->willReturn([]);
        $repository->expects(self::once())
            ->method('insertAdministrator')
            ->willReturnCallback(function (
                int $roleId,
                string $fullName,
                string $username,
                string $email,
                ?string $phone,
                string $hash,
            ) use ($request): int {
                self::assertSame(7, $roleId);
                self::assertSame($request->fullName(), $fullName);
                self::assertSame($request->username(), $username);
                self::assertSame($request->email(), $email);
                self::assertSame($request->phone(), $phone);
                self::assertNotSame($request->password(), $hash);
                self::assertTrue(password_verify($request->password(), $hash));

                return 41;
            });

        $service = $this->service($repository, $logger);

        self::assertSame(41, $service->bootstrap($request, 'testing', false));
        self::assertSame('administrator_bootstrap_succeeded', $logger->records[0]['message']);
        self::assertSame([
            'event_code' => 'administrator_bootstrap_succeeded',
            'outcome' => 'created',
            'user_id' => 41,
            'context' => 'cli',
        ], $logger->records[0]['context']);
        $this->assertNoSensitiveLogData($logger, $request);
    }

    /** @return iterable<string, array{string, bool, bool}> */
    public static function environmentPolicy(): iterable
    {
        yield 'local' => ['local', false, true];
        yield 'testing' => ['testing', false, true];
        yield 'staging without force' => ['staging', false, false];
        yield 'staging with force' => ['staging', true, true];
        yield 'production without force' => ['production', false, false];
        yield 'production with force' => ['production', true, true];
        yield 'unknown' => ['preview', true, false];
    }

    #[DataProvider('environmentPolicy')]
    public function testItEnforcesTheApprovedEnvironmentPolicy(
        string $environment,
        bool $force,
        bool $allowed,
    ): void {
        $factoryCalled = false;
        $service = new AdministratorBootstrapService(
            static function () use (&$factoryCalled): AdministratorBootstrapRepository {
                $factoryCalled = true;
                throw new \LogicException('The repository factory should not be needed for this assertion.');
            },
            new AdministratorBootstrapMemoryLogger(),
            $this->policy,
        );

        if (!$allowed) {
            try {
                $service->assertEnvironment($environment, $force);
                self::fail('Expected environment rejection.');
            } catch (AdministratorBootstrapException $exception) {
                self::assertSame(2, $exception->exitCode());
                self::assertFalse($exception->isSystemFailure());
            }
        } else {
            $service->assertEnvironment($environment, $force);
            self::addToAssertionCount(1);
        }

        self::assertFalse($factoryCalled);
    }

    /** @return iterable<string, array{list<array{id: int, username: string, email: string, account_status: string, deleted: bool}>, list<array{id: int, username: string, email: string, account_status: string, deleted: bool}>, string}> */
    public static function rejectionStates(): iterable
    {
        $base = [
            'id' => 3,
            'username' => 'bootstrap.operator',
            'email' => 'bootstrap.operator@example.test',
            'account_status' => User::STATUS_ACTIVE,
            'deleted' => false,
        ];

        yield 'already complete' => [[$base], [$base], 'already complete'];
        yield 'inactive Administrator' => [[array_replace($base, ['account_status' => User::STATUS_INACTIVE])], [], 'account-management'];
        yield 'locked Administrator' => [[array_replace($base, ['account_status' => User::STATUS_LOCKED])], [], 'account-management'];
        yield 'deleted Administrator' => [[array_replace($base, ['deleted' => true])], [], 'account-management'];
        yield 'identity collision' => [[], [$base], 'conflicts'];
    }

    #[DataProvider('rejectionStates')]
    public function testItRejectsEveryFailSafeSingleUseState(
        array $administrators,
        array $collisions,
        string $messageFragment,
    ): void {
        $request = $this->request();
        $logger = new AdministratorBootstrapMemoryLogger();
        $repository = $this->repository();
        $repository->expects(self::once())->method('withinTransaction')
            ->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $repository->method('lockAdministratorRole')->willReturn(1);
        $repository->method('administratorUsers')->willReturn($administrators);
        $repository->method('identityCollisions')->willReturn($collisions);
        $repository->expects(self::never())->method('insertAdministrator');

        try {
            $this->service($repository, $logger)->bootstrap($request, 'local', false);
            self::fail('Expected bootstrap rejection.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertSame(1, $exception->exitCode());
            self::assertFalse($exception->isSystemFailure());
            self::assertStringContainsString($messageFragment, $exception->getMessage());
        }

        self::assertSame('administrator_bootstrap_rejected', $logger->records[0]['message']);
        $this->assertNoSensitiveLogData($logger, $request);
    }

    public function testMultipleAdministratorsFailGenerically(): void
    {
        $fact = [
            'id' => 1,
            'username' => 'one',
            'email' => 'one@example.test',
            'account_status' => User::STATUS_ACTIVE,
            'deleted' => false,
        ];
        $repository = $this->repository();
        $repository->method('withinTransaction')
            ->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $repository->method('lockAdministratorRole')->willReturn(1);
        $repository->method('administratorUsers')->willReturn([$fact, array_replace($fact, ['id' => 2])]);
        $repository->expects(self::never())->method('identityCollisions');
        $repository->expects(self::never())->method('insertAdministrator');

        try {
            $this->service($repository, new AdministratorBootstrapMemoryLogger())
                ->bootstrap($this->request(), 'testing', false);
            self::fail('Expected fail-safe rejection.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertSame('Administrator bootstrap failed.', $exception->getMessage());
            self::assertTrue($exception->isSystemFailure());
        }
    }

    public function testHashingFailureIsGenericAndPrecedesRepositoryCreation(): void
    {
        $factoryCalled = false;
        $logger = new AdministratorBootstrapMemoryLogger();
        $service = new AdministratorBootstrapService(
            static function () use (&$factoryCalled): AdministratorBootstrapRepository {
                $factoryCalled = true;
                throw new \LogicException('Repository must not be created.');
            },
            $logger,
            $this->policy,
            static fn (string $password): string => '',
        );

        try {
            $service->bootstrap($this->request(), 'testing', false);
            self::fail('Expected hashing failure.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertSame('Administrator bootstrap failed.', $exception->getMessage());
            self::assertTrue($exception->isSystemFailure());
        }

        self::assertFalse($factoryCalled);
        self::assertSame('administrator_bootstrap_failed', $logger->records[0]['message']);
    }

    public function testLoggingFailureDoesNotTurnSuccessfulCreationIntoFailure(): void
    {
        $repository = $this->repository();
        $repository->expects(self::once())->method('withinTransaction')
            ->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $repository->method('lockAdministratorRole')->willReturn(1);
        $repository->method('administratorUsers')->willReturn([]);
        $repository->method('identityCollisions')->willReturn([]);
        $repository->method('insertAdministrator')->willReturn(9);

        $logger = new class extends AbstractLogger {
            public function log($level, string|Stringable $message, array $context = []): void
            {
                throw new \RuntimeException('test logger failure');
            }
        };

        self::assertSame(9, $this->service($repository, $logger)->bootstrap($this->request(), 'testing', false));
    }

    private function repository(): AdministratorBootstrapRepository
    {
        return $this->getMockBuilder(AdministratorBootstrapRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'withinTransaction',
                'lockAdministratorRole',
                'administratorUsers',
                'identityCollisions',
                'insertAdministrator',
            ])
            ->getMock();
    }

    private function service(
        AdministratorBootstrapRepository $repository,
        AbstractLogger $logger,
    ): AdministratorBootstrapService {
        return new AdministratorBootstrapService(
            static fn (): AdministratorBootstrapRepository => $repository,
            $logger,
            $this->policy,
        );
    }

    private function request(): AdministratorBootstrapRequest
    {
        $secret = implode('', ['S1B-test-only-', 'Long-Secret-9087']);

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

    private function assertNoSensitiveLogData(
        AdministratorBootstrapMemoryLogger $logger,
        AdministratorBootstrapRequest $request,
    ): void {
        $serialized = json_encode($logger->records, JSON_THROW_ON_ERROR);

        foreach ([
            $request->fullName(),
            $request->username(),
            $request->email(),
            (string) $request->phone(),
            $request->password(),
        ] as $sensitiveValue) {
            self::assertStringNotContainsString($sensitiveValue, $serialized);
        }
    }
}

final class AdministratorBootstrapMemoryLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
