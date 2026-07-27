<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\BookingException;
use App\Models\AdministratorBookingDetail;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Services\AdministratorBookingServiceProvider;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Formats authenticated, read-only Administrator booking pages.
 */
final readonly class AdministratorBookingController
{
    public function __construct(
        private ViewRenderer $views,
        private AdministratorBookingServiceProvider $services,
        private CsrfTokenManager $csrfTokens,
        private DateTimeZone $timezone,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        try {
            $status = $this->statusFilter($request->query('status'));
            $bookings = array_map(
                $this->listData(...),
                $this->services->service()->list($status),
            );

            return $this->page(
                'admin.bookings.index',
                [
                    'title' => 'Bookings | Tara Guest House',
                    'pageTitle' => 'Bookings',
                    'activeNavigation' => 'bookings',
                    'bookings' => $bookings,
                    'statusOptions' => $this->statusOptions(),
                    'selectedStatus' => $status ?? '',
                    'error' => null,
                ],
            );
        } catch (BookingException $exception) {
            if ($exception->isValidationFailure()) {
                return $this->page(
                    'admin.bookings.index',
                    [
                        'title' => 'Bookings | Tara Guest House',
                        'pageTitle' => 'Bookings',
                        'activeNavigation' => 'bookings',
                        'bookings' => [],
                        'statusOptions' => $this->statusOptions(),
                        'selectedStatus' => '',
                        'error' => $exception->getMessage(),
                    ],
                    422,
                );
            }

            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $bookingId = $this->routeId($parameters);

        if ($bookingId === null) {
            return $this->notFound();
        }

        try {
            $detail = $this->services->service()->detail($bookingId);

            if (!$detail instanceof AdministratorBookingDetail) {
                return $this->notFound();
            }

            return $this->page(
                'admin.bookings.show',
                [
                    'title' => $detail->booking()->bookingReference()
                        . ' | Tara Guest House',
                    'pageTitle' => 'Booking detail',
                    'activeNavigation' => 'bookings',
                    'booking' => $this->detailData($detail),
                ],
            );
        } catch (BookingException) {
            return $this->failure();
        }
    }

    /**
     * @return array{
     *     id: int,
     *     reference: string,
     *     guestName: string,
     *     checkIn: string,
     *     checkOut: string,
     *     guests: string,
     *     total: string,
     *     statusLabel: string,
     *     statusBadge: string,
     *     createdAt: string
     * }
     */
    private function listData(Booking $booking): array
    {
        [$statusLabel, $statusBadge] = $this->statusPresentation(
            $booking->bookingStatus(),
        );

        return [
            'id' => $booking->id(),
            'reference' => $booking->bookingReference(),
            'guestName' => $booking->guestFullName(),
            'checkIn' => $booking->checkIn()->format('Y-m-d'),
            'checkOut' => $booking->checkOut()->format('Y-m-d'),
            'guests' => $booking->adults()
                . ' adult'
                . ($booking->adults() === 1 ? '' : 's')
                . ', '
                . $booking->children()
                . ' child'
                . ($booking->children() === 1 ? '' : 'ren'),
            'total' => $booking->currency() . ' ' . $booking->totalAmount(),
            'statusLabel' => $statusLabel,
            'statusBadge' => $statusBadge,
            'createdAt' => $this->dateTime($booking->createdAt()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(AdministratorBookingDetail $detail): array
    {
        $booking = $detail->booking();
        $room = $detail->room();
        [$statusLabel, $statusBadge] = $this->statusPresentation(
            $booking->bookingStatus(),
        );

        return [
            'id' => $booking->id(),
            'reference' => $booking->bookingReference(),
            'status' => $booking->bookingStatus(),
            'statusLabel' => $statusLabel,
            'statusBadge' => $statusBadge,
            'guestName' => $booking->guestFullName(),
            'guestEmail' => $booking->guestEmail(),
            'guestPhone' => $booking->guestPhone(),
            'checkIn' => $booking->checkIn()->format('Y-m-d'),
            'checkOut' => $booking->checkOut()->format('Y-m-d'),
            'adults' => $booking->adults(),
            'children' => $booking->children(),
            'specialRequest' => $booking->specialRequest(),
            'roomId' => $room->roomId(),
            'pricePerNight' => $booking->currency()
                . ' '
                . $room->pricePerNight(),
            'nights' => $room->nights(),
            'roomSubtotal' => $booking->currency()
                . ' '
                . $room->subtotal(),
            'roomAmount' => $booking->currency()
                . ' '
                . $booking->roomAmount(),
            'totalAmount' => $booking->currency()
                . ' '
                . $booking->totalAmount(),
            'termsAcceptedAt' => $this->dateTime(
                $booking->termsAcceptedAt(),
            ),
            'policyVersion' => $booking->policyVersion(),
            'confirmationDeadlineAt' => $this->dateTime(
                $booking->confirmationDeadlineAt(),
            ),
            'confirmedAt' => $this->optionalDateTime(
                $booking->confirmedAt(),
            ),
            'checkedInAt' => $this->optionalDateTime(
                $booking->checkedInAt(),
            ),
            'checkedOutAt' => $this->optionalDateTime(
                $booking->checkedOutAt(),
            ),
            'cancelledAt' => $this->optionalDateTime(
                $booking->cancelledAt(),
            ),
            'cancellationReason' => $booking->cancellationReason(),
            'rejectedAt' => $this->optionalDateTime(
                $booking->rejectedAt(),
            ),
            'rejectionReason' => $booking->rejectionReason(),
            'createdAt' => $this->dateTime($booking->createdAt()),
            'updatedAt' => $this->dateTime($booking->updatedAt()),
            'events' => array_map(
                $this->eventData(...),
                $detail->events(),
            ),
        ];
    }

    /**
     * @return array{
     *     eventType: string,
     *     fromStatus: string,
     *     toStatus: string,
     *     actor: string,
     *     reason: ?string,
     *     metadataJson: ?string,
     *     createdAt: string
     * }
     */
    private function eventData(BookingEvent $event): array
    {
        return [
            'eventType' => ucwords(
                str_replace('_', ' ', $event->eventType()),
            ),
            'fromStatus' => $event->fromStatus() === null
                ? 'New'
                : $this->statusPresentation($event->fromStatus())[0],
            'toStatus' => $event->toStatus() === null
                ? 'No status change'
                : $this->statusPresentation($event->toStatus())[0],
            'actor' => $event->performedBy() === null
                ? 'System or Guest'
                : 'Administrator #' . $event->performedBy(),
            'reason' => $event->reason(),
            'metadataJson' => $event->metadataJson(),
            'createdAt' => $this->dateTime($event->createdAt()),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => Booking::STATUS_PENDING, 'label' => 'Pending'],
            ['value' => Booking::STATUS_CONFIRMED, 'label' => 'Confirmed'],
            ['value' => Booking::STATUS_CHECKED_IN, 'label' => 'Checked In'],
            ['value' => Booking::STATUS_CHECKED_OUT, 'label' => 'Checked Out'],
            ['value' => Booking::STATUS_CANCELLED, 'label' => 'Cancelled'],
            ['value' => Booking::STATUS_REJECTED, 'label' => 'Rejected'],
        ];
    }

    /**
     * @return array{string, string}
     */
    private function statusPresentation(string $status): array
    {
        return match ($status) {
            Booking::STATUS_PENDING => ['Pending', 'text-bg-warning'],
            Booking::STATUS_CONFIRMED => ['Confirmed', 'text-bg-primary'],
            Booking::STATUS_CHECKED_IN => ['Checked In', 'text-bg-info'],
            Booking::STATUS_CHECKED_OUT => ['Checked Out', 'text-bg-success'],
            Booking::STATUS_CANCELLED => ['Cancelled', 'text-bg-secondary'],
            Booking::STATUS_REJECTED => ['Rejected', 'text-bg-danger'],
        };
    }

    private function statusFilter(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw BookingException::validation(
                'Booking status filter is invalid.',
            );
        }

        $status = trim($value);

        if ($status === '') {
            return null;
        }

        if (!Booking::isStatus($status)) {
            throw BookingException::validation(
                'Booking status filter is invalid.',
            );
        }

        return $status;
    }

    /** @param array<string, string> $parameters */
    private function routeId(array $parameters): ?int
    {
        $value = $parameters['id'] ?? null;

        if (
            !is_string($value)
            || preg_match('/^[1-9][0-9]*$/D', $value) !== 1
        ) {
            return null;
        }

        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        return is_int($id) ? $id : null;
    }

    private function dateTime(DateTimeImmutable $value): string
    {
        return $value
            ->setTimezone($this->timezone)
            ->format('Y-m-d H:i');
    }

    private function optionalDateTime(?DateTimeImmutable $value): ?string
    {
        return $value === null ? null : $this->dateTime($value);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(
        string $view,
        array $data,
        int $statusCode = 200,
    ): Response {
        $html = $this->views->render(
            $view,
            [...$data, 'csrfToken' => $this->csrfTokens->token()],
            'layouts.admin',
        );

        return Response::html(
            $html,
            $statusCode,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function notFound(): Response
    {
        return Response::text(
            'Not Found',
            404,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function failure(): Response
    {
        return Response::text(
            'Internal Server Error',
            500,
            ['Cache-Control' => 'no-store'],
        );
    }
}
