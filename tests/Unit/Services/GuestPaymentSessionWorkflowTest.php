<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Session;
use App\Models\Booking;
use App\Models\BookingSubmissionResult;
use App\Models\Payment;
use App\Services\BookingAccessToken;
use App\Services\GuestPaymentContinuation;
use App\Services\GuestPaymentSuccessFlash;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class GuestPaymentSessionWorkflowTest extends TestCase
{
    private string $savePath;

    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-payment-session-workflow-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->sessionConfiguration());
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_id('');
        }

        foreach (glob($this->savePath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->savePath);
        parent::tearDown();
    }

    public function testSignedContinuationPersistsUntilExplicitlyCleared(): void
    {
        $booking = $this->booking();
        $tokens = new BookingAccessToken(str_repeat('k', 32));
        $token = $tokens->issue(
            $booking->bookingReference(),
            $booking->confirmationDeadlineAt(),
        );
        $continuation = new GuestPaymentContinuation($this->session);
        $continuation->store(new BookingSubmissionResult($booking, $token));

        self::assertSame([
            'booking_reference' => $booking->bookingReference(),
            'booking_access_token' => $token,
        ], $continuation->current());
        self::assertSame($token, $continuation->current()['booking_access_token']);

        $continuation->clear();
        self::assertNull($continuation->current());
    }

    public function testMalformedContinuationIsRemovedWithoutBeingReturned(): void
    {
        $this->session->set('public_payment_continuation', [
            'version' => 1,
            'booking_reference' => 'TGH-7F3A9C2D',
            'booking_access_token' => 'not-a-token',
        ]);
        $continuation = new GuestPaymentContinuation($this->session);

        self::assertNull($continuation->current());
        self::assertFalse($this->session->has('public_payment_continuation'));
    }

    public function testPaymentSuccessFlashIsSafeAndOneTime(): void
    {
        $flash = new GuestPaymentSuccessFlash(
            $this->session,
            new DateTimeZone('Asia/Kathmandu'),
        );
        $submittedAt = self::timestamp('2026-08-01 12:05:00');
        $payment = new Payment(
            44,
            7,
            9,
            Payment::CURRENCY_NPR,
            '18000.00',
            'PRIVATE-REFERENCE',
            'payment-proofs/private-proof.pdf',
            'Private notes',
            Payment::STATUS_SUBMITTED,
            $submittedAt,
            null,
            null,
            null,
            null,
            null,
            $submittedAt,
            $submittedAt,
        );
        $flash->store($payment, 'TGH-7F3A9C2D');

        self::assertSame([
            'booking_reference' => 'TGH-7F3A9C2D',
            'payment_status' => Payment::STATUS_SUBMITTED,
            'currency' => Payment::CURRENCY_NPR,
            'amount' => '18000.00',
            'submitted_at' => '2026-08-01T17:50:00+05:45',
        ], $flash->pull());
        self::assertNull($flash->pull());
    }

    private function booking(): Booking
    {
        return new Booking(
            7,
            'TGH-7F3A9C2D',
            null,
            'Guest Example',
            'guest@example.test',
            '+9779812345678',
            self::date('2026-08-10'),
            self::date('2026-08-13'),
            2,
            1,
            Booking::CURRENCY_NPR,
            '18000.00',
            '18000.00',
            Booking::STATUS_PENDING,
            null,
            self::timestamp('2026-08-01 10:00:00'),
            'v1',
            self::timestamp('2026-08-01 18:00:00'),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            self::timestamp('2026-08-01 10:00:00'),
            self::timestamp('2026-08-01 10:00:00'),
            null,
        );
    }

    /** @return array<string, mixed> */
    private function sessionConfiguration(): array
    {
        return [
            'name' => 'tgh_' . bin2hex(random_bytes(8)),
            'inactivity_timeout' => 1800,
            'absolute_lifetime' => 43200,
            'cookie' => [
                'lifetime' => 0,
                'secure' => false,
                'http_only' => true,
                'same_site' => 'Lax',
                'path' => '/',
                'domain' => null,
            ],
            'strict_mode' => true,
            'use_cookies' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
            'save_handler' => 'files',
            'save_path' => realpath($this->savePath),
            'gc_maxlifetime' => 43200,
        ];
    }

    private static function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' 00:00:00 UTC');
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
