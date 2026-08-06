<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/** Resolves private backup artifacts beneath one fixed writable storage directory. */
final readonly class DatabaseBackupRoot
{
    private string $root;

    public function __construct(string $storageRoot, string $publicRoot)
    {
        if (preg_match('/[\x00\r\n]/', $storageRoot . $publicRoot) === 1 || !is_dir($storageRoot)) {
            throw new InvalidArgumentException('Backup path is unsafe.');
        }

        $storage = realpath($storageRoot);
        $public = realpath($publicRoot);
        if ($storage === false || $public === false) {
            throw new InvalidArgumentException('Backup path is unsafe.');
        }

        $root = $storage . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($root) && !@mkdir($root, 0700)) {
            throw new InvalidArgumentException('Backup path is unsafe.');
        }

        $resolved = realpath($root);
        $normalize = static fn (string $path): string => DIRECTORY_SEPARATOR === '\\'
            ? strtolower($path)
            : $path;
        if (
            $resolved === false
            || !str_starts_with(
                $normalize($resolved),
                rtrim($normalize($storage), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
            )
            || str_starts_with(
                $normalize($resolved),
                rtrim($normalize($public), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
            )
            || !is_readable($resolved)
            || !is_writable($resolved)
        ) {
            throw new InvalidArgumentException('Backup path is unsafe.');
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            @chmod($resolved, 0700);
            clearstatcache(true, $resolved);
            $permissions = fileperms($resolved);
            if (!is_int($permissions) || ($permissions & 0077) !== 0) {
                throw new InvalidArgumentException('Backup path is unsafe.');
            }
        }

        $this->root = $resolved;
    }

    public function resolve(string $basename): string
    {
        if (
            $basename === ''
            || $basename === '.'
            || $basename === '..'
            || preg_match('/^[^\\\\\/\:\x00\r\n]+$/D', $basename) !== 1
            || $basename !== basename($basename)
        ) {
            throw new InvalidArgumentException('Backup path is unsafe.');
        }

        return $this->root . DIRECTORY_SEPARATOR . $basename;
    }

    public function protectFile(string $path): void
    {
        $resolved = realpath($path);
        $normalize = static fn (string $value): string => DIRECTORY_SEPARATOR === '\\'
            ? strtolower($value)
            : $value;
        if (
            $resolved === false
            || !is_file($resolved)
            || is_link($path)
            || dirname($normalize($resolved)) !== $normalize($this->root)
            || !is_readable($resolved)
            || !is_writable($resolved)
        ) {
            throw new InvalidArgumentException('Backup file permissions are unsafe.');
        }

        @chmod($resolved, 0600);
        if (DIRECTORY_SEPARATOR !== '\\') {
            clearstatcache(true, $resolved);
            $permissions = fileperms($resolved);
            if (!is_int($permissions) || ($permissions & 0077) !== 0) {
                throw new InvalidArgumentException('Backup file permissions are unsafe.');
            }
        }
    }
}
