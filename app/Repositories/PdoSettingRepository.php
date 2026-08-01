<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\SiteSetting;
use PDO;
use RuntimeException;
use Throwable;

/** Provides PDO persistence for allowlisted site settings. */
final class PdoSettingRepository implements SettingRepository
{
    use MapsRepositoryRows;

    private const SELECT = <<<'SQL'
        SELECT id, setting_key, setting_value, value_type, is_public, updated_by, created_at, updated_at
        FROM settings
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findByKey(string $key): ?SiteSetting
    {
        if ($key === '') {
            return null;
        }

        try {
            $statement = $this->connection->prepare(self::SELECT . ' WHERE setting_key = :setting_key LIMIT 1');
            $statement->execute(['setting_key' => $key]);
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The setting record could not be mapped.');
            }

            return $this->map($row);
        } catch (Throwable) {
            throw new DatabaseException('The setting lookup failed.');
        }
    }

    public function findAll(): array
    {
        return $this->listing(self::SELECT . ' ORDER BY setting_key ASC, id ASC');
    }

    public function findAllPublic(): array
    {
        return $this->listing(self::SELECT . ' WHERE is_public = 1 ORDER BY setting_key ASC, id ASC');
    }

    public function upsert(
        string $key,
        string $value,
        string $valueType,
        bool $public,
        int $actorId,
    ): SiteSetting {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO settings (setting_key, setting_value, value_type, is_public, updated_by) '
                . 'VALUES (:setting_key, :setting_value, :value_type, :is_public, :updated_by) '
                . 'ON DUPLICATE KEY UPDATE '
                . 'setting_value = VALUES(setting_value), value_type = VALUES(value_type), '
                . 'is_public = VALUES(is_public), updated_by = VALUES(updated_by)',
            );
            $statement->execute([
                'setting_key' => $key,
                'setting_value' => $value,
                'value_type' => $valueType,
                'is_public' => $public ? 1 : 0,
                'updated_by' => $actorId,
            ]);

            $setting = $this->findByKey($key);

            if (!$setting instanceof SiteSetting) {
                throw new RuntimeException('The written setting could not be loaded.');
            }

            return $setting;
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The setting write failed.');
        }
    }

    /** @return list<SiteSetting> */
    private function listing(string $sql): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The setting records could not be mapped.');
            }

            return array_map(function (mixed $row): SiteSetting {
                if (!is_array($row)) {
                    throw new RuntimeException('The setting records could not be mapped.');
                }

                return $this->map($row);
            }, $rows);
        } catch (Throwable) {
            throw new DatabaseException('The setting listing failed.');
        }
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): SiteSetting
    {
        return new SiteSetting(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'setting_key'),
            $this->string($row, 'setting_value'),
            $this->string($row, 'value_type'),
            $this->boolean($row, 'is_public'),
            $this->positiveInteger($row, 'updated_by'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }
}
