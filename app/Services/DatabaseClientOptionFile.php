<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use Throwable;

/** Creates one short-lived private option file for native database clients. */
final class DatabaseClientOptionFile
{
    private ?string $path = null;

    /** @var \Closure(int): string */
    private readonly \Closure $random;

    /**
     * @param array{host: string, port: int, username: string, password: string, charset: string} $database
     * @param (callable(int): string)|null $random
     */
    public function __construct(
        DatabaseBackupRoot $root,
        array $database,
        ?callable $random = null,
    ) {
        $handle = null;

        try {
            foreach (['host', 'username', 'password', 'charset'] as $key) {
                if (
                    !array_key_exists($key, $database)
                    || !is_string($database[$key])
                    || preg_match('/[\x00\r\n]/', $database[$key]) === 1
                ) {
                    throw new \RuntimeException();
                }
            }
            if (!isset($database['port']) || !is_int($database['port'])) {
                throw new \RuntimeException();
            }

            $this->random = \Closure::fromCallable(
                $random ?? static fn (int $length): string => random_bytes($length),
            );
            $bytes = ($this->random)(12);
            if (!is_string($bytes) || strlen($bytes) !== 12) {
                throw new \RuntimeException();
            }

            $this->path = $root->resolve('.db-client-' . bin2hex($bytes) . '.cnf');
            $handle = @fopen($this->path, 'xb');
            if ($handle === false) {
                throw new \RuntimeException();
            }

            $quote = static fn (string $value): string => '"'
                . str_replace(['\\', '"'], ['\\\\', '\\"'], $value)
                . '"';
            $contents = "[client]\n"
                . 'host=' . $quote($database['host']) . "\n"
                . 'port=' . $database['port'] . "\n"
                . 'user=' . $quote($database['username']) . "\n"
                . 'password=' . $quote($database['password']) . "\n"
                . 'default-character-set=' . $quote($database['charset']) . "\n";

            $remaining = $contents;
            while ($remaining !== '') {
                $written = fwrite($handle, $remaining);
                if ($written === false || $written === 0) {
                    throw new \RuntimeException();
                }
                $remaining = substr($remaining, $written);
            }
            if (!fflush($handle)) {
                throw new \RuntimeException();
            }
            fclose($handle);
            $handle = null;

            $root->protectFile($this->path);
        } catch (Throwable) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($this->path !== null && (is_file($this->path) || is_link($this->path))) {
                @unlink($this->path);
            }
            $this->path = null;

            throw new InvalidArgumentException('Temporary database client configuration failed.');
        }
    }

    public function path(): string
    {
        if ($this->path === null) {
            throw new InvalidArgumentException('Temporary database client configuration failed.');
        }

        return $this->path;
    }

    public function cleanup(): void
    {
        if ($this->path !== null) {
            @unlink($this->path);
            $this->path = null;
        }
    }
}
