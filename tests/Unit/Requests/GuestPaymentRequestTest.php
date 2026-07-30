<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Core\UploadedFile;
use App\Exceptions\GuestPaymentException;
use App\Models\Payment;
use App\Requests\GuestPaymentRequest;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GuestPaymentRequestTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    /** @var array<string, mixed> */
    private array $proofPolicy;

    protected function setUp(): void
    {
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        $this->proofPolicy = $configuration['payment_proofs'];
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            @unlink($path);
        }
    }

    public function testItNormalizesTheApprovedPaymentFieldsWithoutFloats(): void
    {
        $token = 'v1.' . str_repeat('A', 43);
        $request = GuestPaymentRequest::fromInput(
            ' TGH-7F3A9C2D ',
            $token,
            '7',
            ' 18000.00 ',
            ' BANK-001 ',
            " Transferred today.\r\nPlease review. ",
            null,
            $this->proofPolicy,
        );

        self::assertSame('TGH-7F3A9C2D', $request->bookingReference());
        self::assertSame($token, $request->bookingAccessToken());
        self::assertSame(7, $request->paymentMethodId());
        self::assertSame(Payment::CURRENCY_NPR, $request->currency());
        self::assertSame('18000.00', $request->amount());
        self::assertSame('BANK-001', $request->transactionReference());
        self::assertSame("Transferred today.\nPlease review.", $request->guestNotes());
        self::assertNull($request->proof());
        self::assertFalse($request->hasProof());
    }

    public function testItAcceptsOneServerValidatedOptionalProof(): void
    {
        $path = $this->temporary(self::pdf());
        $upload = new UploadedFile(
            'receipt.pdf',
            'text/plain',
            $path,
            UPLOAD_ERR_OK,
            filesize($path),
        );
        $request = $this->request(proof: $upload);

        self::assertTrue($request->hasProof());
        self::assertSame('application/pdf', $request->proof()?->detectedMediaType());
        self::assertSame('pdf', $request->proof()?->extension());
    }

    public function testNoFileUploadAndBlankOptionalTextBecomeNull(): void
    {
        $request = GuestPaymentRequest::fromInput(
            'TGH-7F3A9C2D',
            'v1.' . str_repeat('A', 43),
            7,
            '18000.00',
            '   ',
            " \r\n ",
            new UploadedFile('', '', '', UPLOAD_ERR_NO_FILE, 0),
            $this->proofPolicy,
        );

        self::assertNull($request->transactionReference());
        self::assertNull($request->guestNotes());
        self::assertNull($request->proof());
    }

    public function testDiagnosticsAndSerializationDoNotExposeTheSignedToken(): void
    {
        $token = 'v1.' . str_repeat('Z', 43);
        $request = GuestPaymentRequest::fromInput(
            'TGH-7F3A9C2D',
            $token,
            7,
            '18000.00',
            null,
            null,
            null,
            $this->proofPolicy,
        );

        $debug = print_r($request, true);
        self::assertStringNotContainsString($token, $debug);
        self::assertStringNotContainsString('bookingAccessToken', $debug);

        $this->expectException(LogicException::class);
        serialize($request);
    }

    /**
     * @param array{mixed, mixed, mixed, mixed, mixed, mixed, mixed} $arguments
     */
    #[DataProvider('invalidInput')]
    public function testItRejectsMalformedPaymentInput(
        string $message,
        array $arguments,
    ): void {
        try {
            GuestPaymentRequest::fromInput(...[...$arguments, $this->proofPolicy]);
            self::fail('Malformed Guest payment input was accepted.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame($message, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, array{mixed, mixed, mixed, mixed, mixed, mixed, mixed}}>
     */
    public static function invalidInput(): iterable
    {
        $valid = [
            'TGH-7F3A9C2D',
            'v1.' . str_repeat('A', 43),
            7,
            '18000.00',
            'BANK-001',
            'Transferred.',
            null,
        ];

        yield 'blank booking reference' => [
            'Booking reference is invalid.',
            ['', ...array_slice($valid, 1)],
        ];
        yield 'reference contains whitespace' => [
            'Booking reference is invalid.',
            ['TGH 7F3A9C2D', ...array_slice($valid, 1)],
        ];
        yield 'malformed token' => [
            'Booking access is invalid.',
            [$valid[0], 'v1.invalid', ...array_slice($valid, 2)],
        ];
        yield 'token is not trimmed' => [
            'Booking access is invalid.',
            [$valid[0], ' ' . $valid[1], ...array_slice($valid, 2)],
        ];
        yield 'zero method id' => [
            'Payment method is invalid.',
            [$valid[0], $valid[1], 0, ...array_slice($valid, 3)],
        ];
        yield 'decimal method id' => [
            'Payment method is invalid.',
            [$valid[0], $valid[1], '7.0', ...array_slice($valid, 3)],
        ];
        yield 'float amount' => [
            'Payment amount is invalid.',
            [$valid[0], $valid[1], $valid[2], 18000.00, ...array_slice($valid, 4)],
        ];
        yield 'zero amount' => [
            'Payment amount is invalid.',
            [$valid[0], $valid[1], $valid[2], '0.00', ...array_slice($valid, 4)],
        ];
        yield 'three decimal places' => [
            'Payment amount is invalid.',
            [$valid[0], $valid[1], $valid[2], '18000.000', ...array_slice($valid, 4)],
        ];
        yield 'long transaction reference' => [
            'Transaction reference is invalid.',
            [$valid[0], $valid[1], $valid[2], $valid[3], str_repeat('A', 101), $valid[5], null],
        ];
        yield 'multiline transaction reference' => [
            'Transaction reference is invalid.',
            [$valid[0], $valid[1], $valid[2], $valid[3], "BANK\n001", $valid[5], null],
        ];
        yield 'controlled notes' => [
            'Guest notes are invalid.',
            [$valid[0], $valid[1], $valid[2], $valid[3], $valid[4], "safe\0unsafe", null],
        ];
        yield 'upload tree is invalid' => [
            'Payment proof is required.',
            [$valid[0], $valid[1], $valid[2], $valid[3], $valid[4], $valid[5], []],
        ];
    }

    private function request(?UploadedFile $proof = null): GuestPaymentRequest
    {
        return GuestPaymentRequest::fromInput(
            'TGH-7F3A9C2D',
            'v1.' . str_repeat('A', 43),
            7,
            '18000.00',
            'BANK-001',
            null,
            $proof,
            $this->proofPolicy,
        );
    }

    private function temporary(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tgh-guest-payment-request-');
        self::assertIsString($path);
        self::assertSame(strlen($bytes), file_put_contents($path, $bytes));
        $this->temporaryPaths[] = $path;

        return $path;
    }

    private static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }
}
