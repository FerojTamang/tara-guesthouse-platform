<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicBookingController;
use App\Core\Request;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Requests\GuestBookingRequest;
use App\Services\BookingAccessToken;
use App\Services\BookingSubmissionWorkflow;
use App\Services\BookingSubmissionWorkflowProvider;
use App\Services\BookingSubmitter;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class PublicBookingControllerTest extends TestCase
{
    public function testApprovedSubmissionReturnsTheExactSuccessContract(): void
    {
        $submitter = new PublicBookingSubmitterFake();
        $response = self::controller($submitter)->create(
            Request::create(
                'POST',
                '/api/v1/bookings',
                headers: ['Content-Type' => 'application/json; charset=UTF-8'],
                body: json_encode(self::payload(), JSON_THROW_ON_ERROR),
            ),
            [],
        );

        self::assertSame(201, $response->statusCode());
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame([
            'success' => true,
            'message' => 'Booking received.',
            'data' => [
                'booking_reference' => 'TGH-2026-REFERENCE00000000001',
                'booking_status' => Booking::STATUS_PENDING,
                'confirmation_deadline_at' => '2026-08-01T23:45:00+05:45',
                'currency' => Booking::CURRENCY_NPR,
                'total_amount' => '9000.00',
                'booking_access_token' => json_decode(
                    $response->body(),
                    true,
                    16,
                    JSON_THROW_ON_ERROR,
                )['data']['booking_access_token'],
            ],
        ], json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR));
        self::assertMatchesRegularExpression(
            '/^v1\.[A-Za-z0-9_-]{43}$/D',
            json_decode(
                $response->body(),
                true,
                16,
                JSON_THROW_ON_ERROR,
            )['data']['booking_access_token'],
        );
        self::assertStringNotContainsString('guest_email', $response->body());
        self::assertStringNotContainsString('guest_phone', $response->body());
        self::assertStringNotContainsString('room_id', $response->body());
        self::assertSame(1, $submitter->calls);
    }

    public function testOptionalFieldsMayBeOmitted(): void
    {
        $submitter = new PublicBookingSubmitterFake();
        $payload = self::payload();
        unset($payload['special_request'], $payload['policy_version']);

        $response = self::controller($submitter)->create(
            Request::create(
                'POST',
                '/api/v1/bookings',
                headers: ['Content-Type' => 'application/json'],
                body: json_encode($payload, JSON_THROW_ON_ERROR),
            ),
            [],
        );

        self::assertSame(201, $response->statusCode());
        self::assertSame(1, $submitter->calls);
        self::assertNull($submitter->request?->specialRequest());
        self::assertNull($submitter->request?->policyVersion());
    }

    public function testContentTypeMalformedJsonAndNonObjectBodiesFailBeforeSubmission(): void
    {
        $submitter = new PublicBookingSubmitterFake();
        $controller = self::controller($submitter);

        $responses = [
            $controller->create(
                Request::create('POST', '/api/v1/bookings', body: '{}'),
                [],
            ),
            $controller->create(
                Request::create(
                    'POST',
                    '/api/v1/bookings',
                    headers: ['Content-Type' => 'application/json'],
                    body: '{invalid',
                ),
                [],
            ),
            $controller->create(
                Request::create(
                    'POST',
                    '/api/v1/bookings',
                    headers: ['Content-Type' => 'application/json'],
                    body: '[]',
                ),
                [],
            ),
        ];

        self::assertSame([415, 400, 400], array_map(
            static fn ($response): int => $response->statusCode(),
            $responses,
        ));
        self::assertSame(0, $submitter->calls);
    }

    public function testMissingUnknownAndGuestControlledSystemFieldsAreRejected(): void
    {
        $submitter = new PublicBookingSubmitterFake();
        $controller = self::controller($submitter);
        $payloads = [
            array_diff_key(self::payload(), ['guest_phone' => true]),
            self::payload() + ['booking_status' => 'confirmed'],
            self::payload() + ['user_id' => 5],
            self::payload() + ['room_ids' => [12]],
            self::payload() + ['payment_status' => 'verified'],
        ];

        foreach ($payloads as $payload) {
            $response = $controller->create(
                Request::create(
                    'POST',
                    '/api/v1/bookings',
                    headers: ['Content-Type' => 'application/json'],
                    body: json_encode($payload, JSON_THROW_ON_ERROR),
                ),
                [],
            );

            self::assertSame(422, $response->statusCode());
            self::assertSame('no-store', $response->headers()['Cache-Control']);
        }

        self::assertSame(0, $submitter->calls);
    }

    public function testDomainFailuresAreMappedWithoutDisclosure(): void
    {
        $cases = [
            [BookingException::validation('Guest email is invalid.'), 422],
            [BookingException::roomUnavailable(), 409],
            [BookingException::roomNotFound(), 404],
            [BookingException::failure(), 500],
        ];

        foreach ($cases as [$failure, $statusCode]) {
            $submitter = new PublicBookingSubmitterFake();
            $submitter->failure = $failure;
            $response = self::controller($submitter)->create(
                Request::create(
                    'POST',
                    '/api/v1/bookings',
                    headers: ['Content-Type' => 'application/json'],
                    body: json_encode(self::payload(), JSON_THROW_ON_ERROR),
                ),
                [],
            );

            self::assertSame($statusCode, $response->statusCode());
            self::assertSame('no-store', $response->headers()['Cache-Control']);
            self::assertStringNotContainsString('SQLSTATE', $response->body());
            self::assertStringNotContainsString('Duplicate entry', $response->body());
        }
    }

    private static function controller(
        PublicBookingSubmitterFake $submitter,
    ): PublicBookingController {
        return new PublicBookingController(
            new BookingSubmissionWorkflowProvider(
                static fn (): BookingSubmissionWorkflow => new BookingSubmissionWorkflow(
                    $submitter,
                    new BookingAccessToken(str_repeat('k', 32)),
                ),
            ),
            new DateTimeZone('Asia/Kathmandu'),
        );
    }

    /** @return array<string, mixed> */
    private static function payload(): array
    {
        return [
            'room_id' => 12,
            'guest_full_name' => 'Asha Sharma',
            'guest_email' => 'asha@example.test',
            'guest_phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'adults' => 2,
            'children' => 0,
            'special_request' => 'Late arrival.',
            'terms_accepted' => true,
            'policy_version' => 'booking-policy-v1',
        ];
    }
}

final class PublicBookingSubmitterFake implements BookingSubmitter
{
    public int $calls = 0;

    public ?GuestBookingRequest $request = null;

    public ?BookingException $failure = null;

    public function submit(GuestBookingRequest $request): Booking
    {
        $this->calls++;
        $this->request = $request;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        $createdAt = new DateTimeImmutable('2026-08-01 12:00:00 UTC');

        return new Booking(
            99,
            'TGH-2026-REFERENCE00000000001',
            null,
            $request->guestFullName(),
            $request->guestEmail(),
            $request->guestPhone(),
            $request->stayPeriod()->checkIn(),
            $request->stayPeriod()->checkOut(),
            $request->adults(),
            $request->children(),
            Booking::CURRENCY_NPR,
            '9000.00',
            '9000.00',
            Booking::STATUS_PENDING,
            $request->specialRequest(),
            $createdAt,
            $request->policyVersion(),
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
}
