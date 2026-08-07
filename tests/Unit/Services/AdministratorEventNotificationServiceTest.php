<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Review;
use App\Repositories\AdministratorNotificationRecipientRepository;
use App\Repositories\NotificationRepository;
use App\Services\AdministratorEventNotificationService;
use App\Services\AdministratorNotificationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AdministratorEventNotificationServiceTest extends TestCase
{
    public function testGuestEventsAreFannedOutToEveryActiveAdministrator(): void
    {
        $repository = new EventNotificationRepositoryFake();
        $service = new AdministratorEventNotificationService(
            new EventNotificationRecipientRepositoryFake([7, 11]),
            new AdministratorNotificationService(
                $repository,
                static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-07 07:00:00 UTC'),
            ),
        );

        $booking = self::booking();
        $service->bookingReceived($booking);
        $service->contactMessageReceived(self::contact());
        $service->paymentSubmitted($booking, self::payment($booking->id()));
        $service->reviewSubmitted(self::review($booking->id()));

        self::assertCount(8, $repository->created);
        self::assertSame([7, 11, 7, 11, 7, 11, 7, 11], array_column($repository->created, 'user_id'));
        self::assertSame('booking_received', $repository->created[0]['type']);
        self::assertSame('booking', $repository->created[0]['related_type']);
        self::assertSame(99, $repository->created[0]['related_id']);
        self::assertStringContainsString('TGH-2026-REFERENCE00000000001', $repository->created[0]['message']);
        self::assertSame('contact_message_received', $repository->created[2]['type']);
        self::assertSame('contact_message', $repository->created[2]['related_type']);
        self::assertSame(55, $repository->created[2]['related_id']);
        self::assertStringContainsString('Airport pickup', $repository->created[2]['message']);
        self::assertSame('payment_submitted', $repository->created[4]['type']);
        self::assertSame('payment', $repository->created[4]['related_type']);
        self::assertSame(44, $repository->created[4]['related_id']);
        self::assertSame('review_submitted', $repository->created[6]['type']);
        self::assertSame('review', $repository->created[6]['related_type']);
        self::assertSame(77, $repository->created[6]['related_id']);
    }

    private static function booking(): Booking
    {
        $createdAt = new DateTimeImmutable('2026-08-07 06:00:00 UTC');

        return new Booking(
            99,
            'TGH-2026-REFERENCE00000000001',
            null,
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            new DateTimeImmutable('2026-08-10 UTC'),
            new DateTimeImmutable('2026-08-12 UTC'),
            2,
            0,
            Booking::CURRENCY_NPR,
            '9000.00',
            '9000.00',
            Booking::STATUS_PENDING,
            null,
            $createdAt,
            'booking-policy-v1',
            new DateTimeImmutable('2026-08-07 12:00:00 UTC'),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $createdAt,
            null,
        );
    }


    private static function payment(int $bookingId): Payment
    {
        $createdAt = new DateTimeImmutable('2026-08-07 06:40:00 UTC');

        return new Payment(
            44,
            $bookingId,
            3,
            Payment::CURRENCY_NPR,
            '9000.00',
            'UAT-TRANSFER-44',
            null,
            null,
            Payment::STATUS_SUBMITTED,
            $createdAt,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $createdAt,
        );
    }

    private static function review(int $bookingId): Review
    {
        $createdAt = new DateTimeImmutable('2026-08-07 06:50:00 UTC');

        return new Review(
            77,
            $bookingId,
            88,
            'Asha Sharma',
            5,
            'A warm stay',
            'The room was clean and the team was welcoming.',
            Review::STATUS_PENDING,
            null,
            null,
            null,
            $createdAt,
            $createdAt,
        );
    }

    private static function contact(): ContactMessage
    {
        $createdAt = new DateTimeImmutable('2026-08-07 06:30:00 UTC');

        return new ContactMessage(
            55,
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            'Airport pickup',
            'Could you arrange an airport pickup?',
            ContactMessage::STATUS_NEW,
            $createdAt,
            $createdAt,
        );
    }
}

final readonly class EventNotificationRecipientRepositoryFake implements AdministratorNotificationRecipientRepository
{
    /** @param list<int> $identifiers */
    public function __construct(private array $identifiers)
    {
    }

    public function findActiveAdministratorIds(): array
    {
        return $this->identifiers;
    }
}

final class EventNotificationRepositoryFake implements NotificationRepository
{
    /** @var list<array{user_id:int,type:string,title:string,message:string,related_type:?string,related_id:?int}> */
    public array $created = [];

    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $relatedType,
        ?int $relatedId,
        DateTimeImmutable $createdAt,
    ): Notification {
        $this->created[] = [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ];

        return new Notification(
            count($this->created),
            $userId,
            $type,
            $title,
            $message,
            $relatedType,
            $relatedId,
            null,
            $createdAt,
        );
    }

    public function findPageForUser(int $userId, int $limit, int $offset): array { return []; }
    public function countForUser(int $userId): int { return 0; }
    public function countUnreadForUser(int $userId): int { return 0; }
    public function findByIdForUser(int $id, int $userId): ?Notification { return null; }
    public function markReadForUser(int $id, int $userId, DateTimeImmutable $readAt): ?Notification { return null; }
    public function markAllReadForUser(int $userId, DateTimeImmutable $readAt): int { return 0; }
}
