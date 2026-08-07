<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\Role;
use App\Models\User;
use PDO;
use RuntimeException;
use Throwable;

/** Resolves non-deleted active Administrator accounts for notification fan-out. */
final readonly class PdoAdministratorNotificationRecipientRepository implements AdministratorNotificationRecipientRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function findActiveAdministratorIds(): array
    {
        try {
            $statement = $this->connection->prepare(
                'SELECT u.id '
                . 'FROM users u '
                . 'INNER JOIN roles r ON r.id = u.role_id '
                . 'WHERE BINARY r.role_name = BINARY :administrator '
                . 'AND u.account_status = :active '
                . 'AND u.deleted_at IS NULL '
                . 'ORDER BY u.id ASC',
            );
            $statement->bindValue('administrator', Role::ADMINISTRATOR, PDO::PARAM_STR);
            $statement->bindValue('active', User::STATUS_ACTIVE, PDO::PARAM_STR);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

            if (!is_array($rows)) {
                throw new RuntimeException('Administrator notification recipients could not be loaded.');
            }

            $identifiers = [];

            foreach ($rows as $row) {
                if ((!is_int($row) && !is_string($row)) || !ctype_digit((string) $row)) {
                    throw new RuntimeException('Administrator notification recipient data is invalid.');
                }

                $identifier = (int) $row;

                if ($identifier <= 0) {
                    throw new RuntimeException('Administrator notification recipient data is invalid.');
                }

                $identifiers[] = $identifier;
            }

            return $identifiers;
        } catch (Throwable) {
            throw new DatabaseException('The Administrator notification recipient lookup failed.');
        }
    }
}
