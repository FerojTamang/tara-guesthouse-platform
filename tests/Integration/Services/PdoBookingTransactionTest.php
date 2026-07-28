<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Exceptions\DatabaseException;
use App\Services\PdoBookingTransaction;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PdoBookingTransactionTest extends DatabaseTestCase
{
    public function testItCommitsAndRollsBackExplicitTransactions(): void
    {
        $transaction = new PdoBookingTransaction($this->connection);

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

    public function testInvalidTransitionsExposeOnlyStableMessages(): void
    {
        $transaction = new PdoBookingTransaction($this->connection);

        foreach ([
            ['commit', 'The booking transaction could not be committed.'],
            ['rollBack', 'The booking transaction could not be rolled back.'],
        ] as [$method, $message]) {
            try {
                $transaction->{$method}();
                self::fail('Expected an invalid booking transaction transition.');
            } catch (DatabaseException $exception) {
                self::assertSame($message, $exception->getMessage());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            }
        }

        $transaction->begin();

        try {
            $transaction->begin();
            self::fail('Expected a nested booking transaction failure.');
        } catch (DatabaseException $exception) {
            self::assertSame(
                'The booking transaction could not be started.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        } finally {
            if ($transaction->inProgress()) {
                $transaction->rollBack();
            }
        }
    }
}
