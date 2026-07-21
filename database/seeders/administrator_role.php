<?php

declare(strict_types=1);

use App\Core\Seeder;

return new class extends Seeder {
    private const ROLE_NAME = 'Administrator';

    /**
     * Assigns this deterministic seeder to the production seed group.
     */
    public function group(): string
    {
        return 'production';
    }

    /**
     * Creates the one approved Version 1 role without rewriting existing data.
     */
    public function run(\PDO $connection): void
    {
        $lookup = $connection->prepare(
            'SELECT role_name, description FROM roles WHERE role_name = :role_name LIMIT 1',
        );
        $lookup->execute(['role_name' => self::ROLE_NAME]);
        $existing = $lookup->fetch();

        if (is_array($existing)) {
            if (
                ($existing['role_name'] ?? null) !== self::ROLE_NAME
                || ($existing['description'] ?? null) !== null
            ) {
                throw new \RuntimeException('The Administrator role conflicts with existing data.');
            }

            return;
        }

        $insert = $connection->prepare(
            'INSERT INTO roles (role_name, description) VALUES (:role_name, NULL)',
        );
        $insert->execute(['role_name' => self::ROLE_NAME]);
    }
};
