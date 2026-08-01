<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;

final class OperationalQueryIndexContractTest extends TestCase
{
    public function testChangedListingsUseBoundSargablePredicatesAndDeterministicOrdering(): void
    {
        $booking = $this->source('BookingRepository.php');
        self::assertStringContainsString('booking_status = :booking_status', $booking);
        self::assertStringContainsString(
            'bindValue(\'booking_status\', $status, PDO::PARAM_STR)',
            $booking,
        );
        self::assertStringContainsString('ORDER BY created_at DESC, id DESC', $booking);
        self::assertStringNotContainsString('BINARY booking_status = BINARY :booking_status', $booking);

        $room = $this->source('RoomRepository.php');
        self::assertStringContainsString(
            "r.deleted_at IS NULL AND r.operational_status = 'active'",
            $room,
        );
        self::assertStringContainsString('ORDER BY r.room_number ASC, r.id ASC', $room);

        $availability = $this->source('PdoAvailabilityRepository.php');
        self::assertStringContainsString("r.operational_status = 'active'", $availability);
        self::assertStringContainsString(':requested_check_in', $availability);
        self::assertStringContainsString(':requested_check_out', $availability);
        self::assertStringContainsString('ORDER BY r.room_number ASC, r.id ASC', $availability);

        foreach (['PaymentRepository.php', 'PdoAdministratorPaymentRepository.php'] as $file) {
            $payment = $this->source($file);
            self::assertStringContainsString('payment_status = :payment_status', $payment);
            self::assertStringContainsString(
                'bindValue(\'payment_status\', $status, PDO::PARAM_STR)',
                $payment,
            );
            self::assertStringContainsString('created_at DESC', $payment);
            self::assertStringNotContainsString('BINARY :payment_status', $payment);
        }
    }

    private function source(string $file): string
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
            . 'Repositories' . DIRECTORY_SEPARATOR . $file,
        );
        self::assertIsString($source);

        return $source;
    }
}
