<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Exceptions\DatabaseException;
use App\Services\PdoRoomCatalogTransaction;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PdoRoomCatalogTransactionTest extends DatabaseTestCase
{
    public function testItCommitsAndRollsBackExplicitTransactions(): void
    {
        $transaction = new PdoRoomCatalogTransaction($this->connection);

        self::assertFalse($transaction->inProgress());
        $transaction->begin();
        self::assertTrue($transaction->inProgress());
        $transaction->commit();
        self::assertFalse($transaction->inProgress());

        $transaction->begin();
        self::assertTrue($transaction->inProgress());
        $transaction->rollBack();
        self::assertFalse($transaction->inProgress());
    }

    public function testInvalidTransactionTransitionsFailWithoutDriverDisclosure(): void
    {
        $transaction = new PdoRoomCatalogTransaction($this->connection);

        foreach ([
            ['commit', 'The room transaction could not be committed.'],
            ['rollBack', 'The room transaction could not be rolled back.'],
        ] as [$method, $message]) {
            try {
                $transaction->{$method}();
                self::fail('Expected an invalid transaction-state failure.');
            } catch (DatabaseException $exception) {
                self::assertSame($message, $exception->getMessage());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            }
        }

        $transaction->begin();

        try {
            $transaction->begin();
            self::fail('Expected a nested transaction failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The room transaction could not be started.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }
}
