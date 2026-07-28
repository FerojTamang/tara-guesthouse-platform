<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Session;
use App\Services\PaymentMethodFlashMessages;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PaymentMethodFlashMessagesTest extends TestCase
{
    private string $savePath;

    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-payment-method-flash-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
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
                unlink($file);
            }
        }

        if (is_dir($this->savePath)) {
            rmdir($this->savePath);
        }

        parent::tearDown();
    }

    public function testSuccessMessageCanBePulledOnlyOnce(): void
    {
        $messages = new PaymentMethodFlashMessages($this->session);

        self::assertNull($messages->pullSuccess());

        $messages->success('Payment method created successfully.');

        self::assertSame(
            'Payment method created successfully.',
            $messages->pullSuccess(),
        );
        self::assertNull($messages->pullSuccess());
    }

    public function testMalformedSessionValueIsDiscardedSafely(): void
    {
        $this->session->set('administrator_payment_method_success', ['unexpected']);
        $messages = new PaymentMethodFlashMessages($this->session);

        self::assertNull($messages->pullSuccess());
        self::assertFalse(
            $this->session->has('administrator_payment_method_success'),
        );
    }

    /** @return array<string, mixed> */
    private function configuration(): array
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
}
