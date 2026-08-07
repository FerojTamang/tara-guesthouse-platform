<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\IssuedReviewInvitation;
use App\Models\Payment;
use App\Requests\GuestBookingRequest;
use App\Services\BookingAccessToken;
use App\Services\AdministratorEventNotifier;
use App\Services\BookingSubmissionWorkflow;
use App\Services\BookingSubmitter;
use App\Services\TransactionalMailDispatcher;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookingSubmissionWorkflowTest extends TestCase
{
    public function testItReturnsThePendingBookingAndScopedToken(): void
    {
        $submitter = new WorkflowSubmitterFake();
        $result = (new BookingSubmissionWorkflow(
            $submitter,
            new BookingAccessToken(str_repeat('k', 32)),
        ))->submit(self::request());

        self::assertSame($submitter->booking, $result->booking());
        self::assertMatchesRegularExpression(
            '/^v1\.[A-Za-z0-9_-]{43}$/D',
            $result->bookingAccessToken(),
        );
    }

    public function testBookingReceivedMailRunsAfterSubmissionAndCannotChangeTheResult(): void
    {
        $submitter = new WorkflowSubmitterFake();
        $mail = new BookingWorkflowMailDispatcherFake($submitter);
        $mail->failure = new \RuntimeException('private SMTP failure');
        $result = (new BookingSubmissionWorkflow(
            $submitter,
            new BookingAccessToken(str_repeat('k', 32)),
            $mail,
        ))->submit(self::request());

        self::assertSame($submitter->booking, $result->booking());
        self::assertSame($submitter->booking, $mail->booking);
        self::assertTrue($mail->submissionHadCompleted);
        self::assertSame(1, $mail->calls);
    }


    public function testAdministratorNotificationRunsAfterSubmissionAndCannotChangeTheResult(): void
    {
        $submitter = new WorkflowSubmitterFake();
        $notifications = new BookingWorkflowAdministratorNotifierFake($submitter);
        $notifications->failure = new \RuntimeException('private notification failure');
        $result = (new BookingSubmissionWorkflow(
            $submitter,
            new BookingAccessToken(str_repeat('k', 32)),
            null,
            $notifications,
        ))->submit(self::request());

        self::assertSame($submitter->booking, $result->booking());
        self::assertSame($submitter->booking, $notifications->booking);
        self::assertTrue($notifications->submissionHadCompleted);
        self::assertSame(1, $notifications->calls);
    }

    public function testDomainFailuresArePreserved(): void
    {
        $submitter = new WorkflowSubmitterFake();
        $submitter->failure = BookingException::roomUnavailable();

        try {
            (new BookingSubmissionWorkflow(
                $submitter,
                new BookingAccessToken(str_repeat('k', 32)),
            ))->submit(self::request());
            self::fail('Expected a booking conflict.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isConflict());
        }
    }

    private static function request(): GuestBookingRequest
    {
        return GuestBookingRequest::fromInput(
            12,
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            '2026-08-10',
            '2026-08-12',
            2,
            0,
            null,
            true,
            'booking-policy-v1',
        );
    }
}

final class WorkflowSubmitterFake implements BookingSubmitter
{
    public Booking $booking;

    public ?BookingException $failure = null;

    public bool $completed = false;

    public function __construct()
    {
        $createdAt = new DateTimeImmutable('2026-08-01 12:00:00 UTC');
        $this->booking = new Booking(
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
            new DateTimeImmutable('2026-08-01 18:00:00 UTC'),
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

    public function submit(GuestBookingRequest $request): Booking
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->completed = true;

        return $this->booking;
    }
}

final class BookingWorkflowMailDispatcherFake implements TransactionalMailDispatcher
{
    public ?Booking $booking = null;
    public int $calls = 0;
    public bool $submissionHadCompleted = false;
    public ?\Throwable $failure = null;

    public function __construct(private readonly WorkflowSubmitterFake $submitter)
    {
    }

    public function bookingReceived(Booking $booking): void
    {
        $this->calls++;
        $this->booking = $booking;
        $this->submissionHadCompleted = $this->submitter->completed;

        if ($this->failure !== null) {
            throw $this->failure;
        }
    }

    public function bookingStatusChanged(Booking $booking, string $status): void
    {
    }

    public function paymentSubmitted(Booking $booking, Payment $payment): void
    {
    }

    public function paymentReviewed(Booking $booking, Payment $payment): void
    {
    }

    public function reviewInvitation(Booking $booking, IssuedReviewInvitation $invitation): void
    {
    }
}


final class BookingWorkflowAdministratorNotifierFake implements AdministratorEventNotifier
{
    public ?Booking $booking = null;
    public int $calls = 0;
    public bool $submissionHadCompleted = false;
    public ?\Throwable $failure = null;

    public function __construct(private readonly WorkflowSubmitterFake $submitter)
    {
    }

    public function bookingReceived(Booking $booking): void
    {
        $this->calls++;
        $this->booking = $booking;
        $this->submissionHadCompleted = $this->submitter->completed;

        if ($this->failure !== null) {
            throw $this->failure;
        }
    }

    public function contactMessageReceived(\App\Models\ContactMessage $contact): void
    {
    }

    public function paymentSubmitted(Booking $booking, Payment $payment): void
    {
    }

    public function reviewSubmitted(\App\Models\Review $review): void
    {
    }
}
