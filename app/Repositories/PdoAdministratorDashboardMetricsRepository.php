<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\AdministratorDashboardMetrics;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class PdoAdministratorDashboardMetricsRepository implements AdministratorDashboardMetricsRepository
{
    public function __construct(private readonly PDO $connection) {}

    public function metrics(int $administratorId, DateTimeImmutable $start, DateTimeImmutable $end): AdministratorDashboardMetrics
    {
        if ($administratorId <= 0) {
            throw new DatabaseException('Dashboard metrics failed.');
        }

        try {
            $statement = $this->connection->prepare(<<<'SQL'
                SELECT
                    (SELECT COUNT(*) FROM rooms WHERE deleted_at IS NULL AND operational_status = 'active') AS active_rooms,
                    (SELECT COUNT(DISTINCT br.room_id)
                     FROM bookings b
                     INNER JOIN booking_rooms br ON br.booking_id = b.id
                     INNER JOIN rooms r ON r.id = br.room_id
                     WHERE b.booking_status = 'checked_in'
                       AND b.deleted_at IS NULL
                       AND r.deleted_at IS NULL
                       AND r.operational_status = 'active') AS occupied_rooms,
                    (SELECT COUNT(*) FROM bookings WHERE check_in >= :start_arrival AND check_in < :end_arrival AND deleted_at IS NULL) AS arrivals,
                    (SELECT COUNT(*) FROM bookings WHERE check_out >= :start_departure AND check_out < :end_departure AND deleted_at IS NULL) AS departures,
                    (SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending' AND deleted_at IS NULL) AS pending_bookings,
                    (SELECT COUNT(*) FROM bookings WHERE booking_status = 'confirmed' AND deleted_at IS NULL) AS confirmed_bookings,
                    (SELECT COUNT(*) FROM payments WHERE payment_status = 'submitted') AS pending_payments,
                    (SELECT COUNT(*) FROM reviews WHERE review_status = 'pending') AS pending_reviews,
                    (SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL) AS unread_notifications
                SQL);
            $kathmandu = new DateTimeZone('Asia/Kathmandu');
            $startDate = $start->setTimezone($kathmandu)->format('Y-m-d');
            $endDate = $end->setTimezone($kathmandu)->format('Y-m-d');
            $statement->execute([
                'start_arrival' => $startDate,
                'end_arrival' => $endDate,
                'start_departure' => $startDate,
                'end_departure' => $endDate,
                'user_id' => $administratorId,
            ]);
            $row = $statement->fetch();
            if (!is_array($row)) {
                throw new \RuntimeException();
            }

            $integer = static fn (string $key): int => filter_var($row[$key] ?? null, FILTER_VALIDATE_INT) !== false
                ? (int) $row[$key]
                : throw new \RuntimeException();

            return new AdministratorDashboardMetrics(
                $start,
                $end,
                $integer('active_rooms'),
                $integer('occupied_rooms'),
                $integer('arrivals'),
                $integer('departures'),
                $integer('pending_bookings'),
                $integer('confirmed_bookings'),
                $integer('pending_payments'),
                $integer('pending_reviews'),
                $integer('unread_notifications'),
            );
        } catch (Throwable) {
            throw new DatabaseException('Dashboard metrics failed.');
        }
    }
}
