<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicPaymentController;
use App\Core\Request;
use App\Exceptions\GuestPaymentException;
use App\Models\Payment;
use App\Requests\GuestPaymentRequest;
use App\Services\GuestPaymentSubmissionServiceProvider;
use App\Services\GuestPaymentSubmitter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PublicPaymentControllerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $proofPolicy;

    protected function setUp(): void
    {
        $configuration = require dirname(__DIR__, 2) . '/config/upload.php';
        $this->proofPolicy = $configuration['payment_proofs'];
    }

    public function testApprovedSubmissionReturnsTheExactSuccessContract(): void
    {
        $submitter = new PublicPaymentSubmitterFake();
        $response = $this->controller($submitter)->create($this->request(), []);

        self::assertSame(201, $response->statusCode());
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame([
            'success' => true,
            'message' => 'Payment submitted for review.',
            'data' => [
                'payment_id' => 44,
                'payment_status' => Payment::STATUS_SUBMITTED,
                'booking_reference' => 'TGH-7F3A9C2D',
            ],
        ], json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR));
        self::assertSame(1, $submitter->calls);
        self::assertSame('18000.00', $submitter->request?->amount());
        self::assertSame('BANK-001', $submitter->request?->transactionReference());
        self::assertStringNotContainsString('booking_access_token', $response->body());
        self::assertStringNotContainsString('proof_path', $response->body());
    }

    public function testMultipartSubmissionUsesParsedFormInput(): void
    {
        $submitter = new PublicPaymentSubmitterFake();
        $response = $this->controller($submitter)->create(
            Request::create(
                'POST',
                '/api/v1/payments',
                headers: ['Content-Type' => 'multipart/form-data; boundary=test'],
                input: self::payload(),
            ),
            [],
        );

        self::assertSame(201, $response->statusCode());
        self::assertSame(1, $submitter->calls);
        self::assertSame('18000.00', $submitter->request?->amount());
    }

    public function testMissingUnknownAndGuestControlledSystemFieldsAreRejected(): void
    {
        $submitter = new PublicPaymentSubmitterFake();
        $controller = $this->controller($submitter);
        $payloads = [
            array_diff_key(self::payload(), ['amount' => true]),
            self::payload() + ['payment_status' => 'verified'],
            self::payload() + ['currency' => 'USD'],
            self::payload() + ['verified_by' => 1],
        ];

        foreach ($payloads as $payload) {
            $response = $controller->create($this->request($payload), []);

            self::assertSame(422, $response->statusCode());
            self::assertSame('no-store', $response->headers()['Cache-Control']);
        }

        self::assertSame(0, $submitter->calls);
    }

    public function testUnsupportedContentTypeAndUnknownUploadFieldFailBeforeSubmission(): void
    {
        $submitter = new PublicPaymentSubmitterFake();
        $controller = $this->controller($submitter);
        $unsupported = $controller->create(
            Request::create(
                'POST',
                '/api/v1/payments',
                headers: ['Content-Type' => 'text/plain'],
                body: json_encode(self::payload(), JSON_THROW_ON_ERROR),
            ),
            [],
        );
        $unknownFile = $controller->create(
            Request::create(
                'POST',
                '/api/v1/payments',
                headers: ['Content-Type' => 'multipart/form-data; boundary=test'],
                input: self::payload(),
                files: [
                    'unapproved' => [
                        'name' => '',
                        'type' => '',
                        'tmp_name' => '',
                        'error' => UPLOAD_ERR_NO_FILE,
                        'size' => 0,
                    ],
                ],
            ),
            [],
        );

        self::assertSame(415, $unsupported->statusCode());
        self::assertSame(422, $unknownFile->statusCode());
        self::assertSame(0, $submitter->calls);
    }

    public function testMalformedJsonAndNonObjectBodiesFailBeforeSubmission(): void
    {
        $submitter = new PublicPaymentSubmitterFake();
        $controller = $this->controller($submitter);
        $malformed = $controller->create(
            Request::create(
                'POST',
                '/api/v1/payments',
                headers: ['Content-Type' => 'application/json'],
                body: '{',
            ),
            [],
        );
        $list = $controller->create(
            Request::create(
                'POST',
                '/api/v1/payments',
                headers: ['Content-Type' => 'application/json'],
                body: '[]',
            ),
            [],
        );

        self::assertSame(400, $malformed->statusCode());
        self::assertSame(400, $list->statusCode());
        self::assertSame(0, $submitter->calls);
    }

    public function testDomainFailuresAreMappedWithoutPrivateDisclosure(): void
    {
        $cases = [
            [GuestPaymentException::validation('Payment proof is required.'), 422],
            [GuestPaymentException::accessDenied(), 403],
            [GuestPaymentException::bookingConflict(), 409],
            [GuestPaymentException::paymentMethodUnavailable(), 409],
            [GuestPaymentException::failure(new \RuntimeException('SQLSTATE private')), 500],
        ];

        foreach ($cases as [$failure, $statusCode]) {
            $submitter = new PublicPaymentSubmitterFake();
            $submitter->failure = $failure;
            $response = $this->controller($submitter)->create($this->request(), []);

            self::assertSame($statusCode, $response->statusCode());
            self::assertSame('no-store', $response->headers()['Cache-Control']);
            self::assertStringNotContainsString('SQLSTATE', $response->body());
            self::assertStringNotContainsString('booking_access_token', $response->body());
        }
    }

    private function controller(PublicPaymentSubmitterFake $submitter): PublicPaymentController
    {
        return new PublicPaymentController(
            new GuestPaymentSubmissionServiceProvider(
                static fn (): GuestPaymentSubmitter => $submitter,
            ),
            fn (): array => $this->proofPolicy,
        );
    }

    /** @param null|array<string, mixed> $input */
    private function request(?array $input = null): Request
    {
        return Request::create(
            'POST',
            '/api/v1/payments',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($input ?? self::payload(), JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    private static function payload(): array
    {
        return [
            'booking_reference' => 'TGH-7F3A9C2D',
            'booking_access_token' => 'v1.' . str_repeat('A', 43),
            'payment_method_id' => '7',
            'amount' => '18000.00',
            'transaction_reference' => 'BANK-001',
            'guest_notes' => 'Transferred today.',
        ];
    }
}

final class PublicPaymentSubmitterFake implements GuestPaymentSubmitter
{
    public int $calls = 0;

    public ?GuestPaymentRequest $request = null;

    public ?GuestPaymentException $failure = null;

    public function submit(GuestPaymentRequest $request): Payment
    {
        $this->calls++;
        $this->request = $request;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        $timestamp = new DateTimeImmutable('2026-08-01 12:05:00 UTC');

        return new Payment(
            44,
            7,
            $request->paymentMethodId(),
            Payment::CURRENCY_NPR,
            $request->amount(),
            $request->transactionReference(),
            null,
            $request->guestNotes(),
            Payment::STATUS_SUBMITTED,
            $timestamp,
            null,
            null,
            null,
            null,
            null,
            $timestamp,
            $timestamp,
        );
    }
}
