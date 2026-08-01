<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use Throwable;

/** Resolves approved native database clients without exposing process diagnostics. */
final readonly class DatabaseNativeClientResolver
{
    public function __construct(private NativeProcessRunner $runner)
    {
    }

    /** @return array{executable: string, family: string, version: string|null} */
    public function resolve(string $operation = 'backup'): array
    {
        $candidates = match ($operation) {
            'backup' => ['mariadb-dump', 'mariadb-dump.exe', 'mysqldump', 'mysqldump.exe'],
            'restore' => ['mariadb', 'mariadb.exe', 'mysql', 'mysql.exe'],
            default => throw new InvalidArgumentException('Native database client operation is invalid.'),
        };

        foreach ($candidates as $executable) {
            try {
                $result = $this->runner->run(new NativeProcessRequest($executable, ['--version']));
                if ($result->exitCode === 0) {
                    return [
                        'executable' => $executable,
                        'family' => str_starts_with($executable, 'mariadb') ? 'mariadb' : 'mysql',
                        'version' => self::version($result->stderr),
                    ];
                }
            } catch (Throwable) {
                // An unavailable candidate is expected; continue in the approved order.
            }
        }

        throw new InvalidArgumentException(
            $operation === 'restore'
                ? 'Database restore client is unavailable.'
                : 'Database backup client is unavailable.',
        );
    }

    private static function version(string $version): ?string
    {
        $version = trim(str_replace(["\0", "\r", "\n"], ' ', $version));

        return $version === '' ? null : substr($version, 0, 100);
    }
}
