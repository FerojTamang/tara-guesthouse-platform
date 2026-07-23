<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Provides strict conversion of PDO result values into domain constructor data.
 */
trait MapsRepositoryRows
{
    /**
     * @param array<string, mixed> $row
     */
    private function positiveInteger(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
        } else {
            throw new RuntimeException('The database record could not be mapped.');
        }

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The database record could not be mapped.');
        }

        return $integer;
    }


    /**
     * @param array<string, mixed> $row
     */
    private function nonNegativeInteger(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
        } else {
            throw new RuntimeException('The database record could not be mapped.');
        }

        if (!is_int($integer) || $integer < 0) {
            throw new RuntimeException('The database record could not be mapped.');
        }

        return $integer;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (!is_string($value)) {
            throw new RuntimeException('The database record could not be mapped.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if ($value !== null && !is_string($value)) {
            throw new RuntimeException('The database record could not be mapped.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function boolean(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;

        if ($value === 1 || $value === '1') {
            return true;
        }

        if ($value === 0 || $value === '0') {
            return false;
        }

        throw new RuntimeException('The database record could not be mapped.');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function dateTime(array $row, string $key): DateTimeImmutable
    {
        $value = $this->string($row, $key);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || (is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))) {
            throw new RuntimeException('The database record could not be mapped.');
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableDateTime(array $row, string $key): ?DateTimeImmutable
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->dateTime($row, $key);
    }
}
