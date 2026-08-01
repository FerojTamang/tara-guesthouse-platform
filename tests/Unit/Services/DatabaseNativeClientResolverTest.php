<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseNativeClientResolver;
use App\Services\NativeProcessRequest;
use App\Services\NativeProcessResult;
use App\Services\NativeProcessRunner;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseNativeClientResolverTest extends TestCase
{
    public function testBackupResolutionRemainsBackwardCompatible(): void
    {
        $runner = new NativeClientResolverFake([1, 1, 1, 0]);

        $result = (new DatabaseNativeClientResolver($runner))->resolve();

        self::assertSame('mysqldump.exe', $result['executable']);
        self::assertSame('mysql', $result['family']);
        self::assertSame(
            ['mariadb-dump', 'mariadb-dump.exe', 'mysqldump', 'mysqldump.exe'],
            $runner->executables(),
        );
        $runner->assertVersionOnlyRequests();
    }

    #[DataProvider('restoreCandidateProvider')]
    public function testRestoreCandidatesAreSelectedInExactOrder(
        array $outcomes,
        string $expectedExecutable,
        string $expectedFamily,
        array $expectedOrder,
    ): void {
        $runner = new NativeClientResolverFake($outcomes);

        $result = (new DatabaseNativeClientResolver($runner))->resolve('restore');

        self::assertSame($expectedExecutable, $result['executable']);
        self::assertSame($expectedFamily, $result['family']);
        self::assertSame($expectedOrder, $runner->executables());
        $runner->assertVersionOnlyRequests();
    }

    /** @return iterable<string, array{array<int|Throwable>, string, string, list<string>}> */
    public static function restoreCandidateProvider(): iterable
    {
        yield 'mariadb first' => [[0], 'mariadb', 'mariadb', ['mariadb']];
        yield 'mariadb exe fallback' => [[1, 0], 'mariadb.exe', 'mariadb', ['mariadb', 'mariadb.exe']];
        yield 'mysql fallback after start failure' => [
            [new RuntimeException('unavailable'), 1, 0],
            'mysql',
            'mysql',
            ['mariadb', 'mariadb.exe', 'mysql'],
        ];
        yield 'mysql exe fallback' => [
            [1, 1, 1, 0],
            'mysql.exe',
            'mysql',
            ['mariadb', 'mariadb.exe', 'mysql', 'mysql.exe'],
        ];
    }

    public function testUnavailableRestoreClientHasExactSanitizedFailure(): void
    {
        $runner = new NativeClientResolverFake([1, 1, 1, 1]);

        try {
            (new DatabaseNativeClientResolver($runner))->resolve('restore');
            self::fail('Expected unavailable restore client.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Database restore client is unavailable.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('mysql', $exception->getMessage());
            self::assertStringNotContainsString('PATH', $exception->getMessage());
        }

        self::assertSame(['mariadb', 'mariadb.exe', 'mysql', 'mysql.exe'], $runner->executables());
        $runner->assertVersionOnlyRequests();
    }

    public function testVersionIsBoundedAndControlCharactersAreRemoved(): void
    {
        $runner = new NativeClientResolverFake([0], "  MariaDB\0\r\n" . str_repeat('v', 120));

        $result = (new DatabaseNativeClientResolver($runner))->resolve('restore');

        self::assertIsString($result['version']);
        self::assertSame(100, strlen($result['version']));
        self::assertDoesNotMatchRegularExpression('/[\x00\r\n]/', $result['version']);
        self::assertStringStartsWith('MariaDB', $result['version']);
    }
}

final class NativeClientResolverFake implements NativeProcessRunner
{
    /** @var list<NativeProcessRequest> */
    private array $requests = [];

    /** @param list<int|Throwable> $outcomes */
    public function __construct(
        private array $outcomes,
        private readonly string $stderr = '',
    ) {
    }

    public function run(NativeProcessRequest $request): NativeProcessResult
    {
        $this->requests[] = $request;
        $outcome = array_shift($this->outcomes);
        if ($outcome instanceof Throwable) {
            throw $outcome;
        }
        if (!is_int($outcome)) {
            throw new RuntimeException('Missing fake outcome.');
        }

        return new NativeProcessResult($outcome, $this->stderr);
    }

    /** @return list<string> */
    public function executables(): array
    {
        return array_map(static fn (NativeProcessRequest $request): string => $request->executable, $this->requests);
    }

    public function assertVersionOnlyRequests(): void
    {
        foreach ($this->requests as $request) {
            TestCase::assertSame(['--version'], $request->arguments);
            TestCase::assertNull($request->stdin);
            TestCase::assertNull($request->stdout);
        }
    }
}
