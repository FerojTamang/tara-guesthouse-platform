<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\PaymentMethod;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Provides persistence access to configured manual payment methods.
 */
final class PaymentMethodRepository implements PaymentMethodCatalogRepository
{
    use MapsRepositoryRows;

    private const SELECT_COLUMNS = <<<'SQL'
        SELECT
            id,
            name,
            instructions,
            account_reference,
            qr_image_path,
            requires_proof,
            is_active,
            created_at,
            updated_at
        FROM payment_methods
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?PaymentMethod
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                self::SELECT_COLUMNS . ' WHERE id = :id LIMIT 1',
            );
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The payment method record could not be mapped.');
            }

            return $this->mapPaymentMethod($row);
        } catch (Throwable) {
            throw new DatabaseException('The payment method lookup failed.');
        }
    }

    /**
     * @return list<PaymentMethod>
     */
    public function findAll(): array
    {
        return $this->listing(
            self::SELECT_COLUMNS . ' ORDER BY name ASC, id ASC',
        );
    }

    /**
     * @return list<PaymentMethod>
     */
    public function findAllActive(): array
    {
        return $this->listing(
            self::SELECT_COLUMNS . ' WHERE is_active = 1 ORDER BY name ASC, id ASC',
        );
    }

    public function create(
        string $name,
        ?string $instructions,
        ?string $accountReference,
        ?string $qrImagePath,
        bool $requiresProof,
        bool $active,
    ): PaymentMethod {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO payment_methods ('
                . 'name, instructions, account_reference, qr_image_path, '
                . 'requires_proof, is_active'
                . ') VALUES ('
                . ':name, :instructions, :account_reference, :qr_image_path, '
                . ':requires_proof, :is_active'
                . ')',
            );
            $statement->execute([
                'name' => $name,
                'instructions' => $instructions,
                'account_reference' => $accountReference,
                'qr_image_path' => $qrImagePath,
                'requires_proof' => $requiresProof ? 1 : 0,
                'is_active' => $active ? 1 : 0,
            ]);

            $method = $this->findById($this->generatedIdentifier());

            if (!$method instanceof PaymentMethod) {
                throw new RuntimeException('The created payment method could not be loaded.');
            }

            return $method;
        } catch (Throwable) {
            throw new DatabaseException('The payment method write failed.');
        }
    }

    public function update(
        int $id,
        string $name,
        ?string $instructions,
        ?string $accountReference,
        ?string $qrImagePath,
        bool $requiresProof,
        bool $active,
    ): ?PaymentMethod {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE payment_methods SET '
                . 'name = :name, instructions = :instructions, '
                . 'account_reference = :account_reference, qr_image_path = :qr_image_path, '
                . 'requires_proof = :requires_proof, is_active = :is_active '
                . 'WHERE id = :id',
            );
            $statement->execute([
                'id' => $id,
                'name' => $name,
                'instructions' => $instructions,
                'account_reference' => $accountReference,
                'qr_image_path' => $qrImagePath,
                'requires_proof' => $requiresProof ? 1 : 0,
                'is_active' => $active ? 1 : 0,
            ]);

            return $this->findById($id);
        } catch (Throwable) {
            throw new DatabaseException('The payment method write failed.');
        }
    }

    public function setActive(int $id, bool $active): ?PaymentMethod
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE payment_methods SET is_active = :is_active WHERE id = :id',
            );
            $statement->execute([
                'id' => $id,
                'is_active' => $active ? 1 : 0,
            ]);

            return $this->findById($id);
        } catch (Throwable) {
            throw new DatabaseException('The payment method state update failed.');
        }
    }

    /**
     * @return list<PaymentMethod>
     */
    private function listing(string $sql): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The payment method records could not be mapped.');
            }

            $methods = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The payment method records could not be mapped.');
                }

                $methods[] = $this->mapPaymentMethod($row);
            }

            return $methods;
        } catch (Throwable) {
            throw new DatabaseException('The payment method listing failed.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapPaymentMethod(array $row): PaymentMethod
    {
        return new PaymentMethod(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'name'),
            $this->nullableString($row, 'instructions'),
            $this->nullableString($row, 'account_reference'),
            $this->nullableString($row, 'qr_image_path'),
            $this->boolean($row, 'requires_proof'),
            $this->boolean($row, 'is_active'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated payment method identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated payment method identifier is invalid.');
        }

        return $integer;
    }
}
