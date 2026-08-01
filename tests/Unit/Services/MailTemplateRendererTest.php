<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\ViewRenderer;
use App\Exceptions\MailException;
use App\Services\MailTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class MailTemplateRendererTest extends TestCase
{
    private MailTemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MailTemplateRenderer(new ViewRenderer(
            dirname(__DIR__, 3) . '/resources/views',
        ));
    }

    public function testPairedTemplatesRenderPlainTextAndEscapedHtml(): void
    {
        $rendered = $this->renderer->render('booking-received', [
            'guestName' => '<script>Asha</script>',
            'applicationName' => 'Tara Guest House',
            'bookingReference' => 'TGH-2026-TEMPLATE00000001',
            'checkIn' => '2026-08-10',
            'checkOut' => '2026-08-12',
            'currency' => 'NPR',
            'totalAmount' => '9000.00',
            'confirmationDeadline' => '2026-08-01 18:00 NPT',
            'baseUrl' => 'https://stay.example.test',
        ]);

        self::assertStringContainsString('<script>Asha</script>', $rendered['text']);
        self::assertStringContainsString('&lt;script&gt;Asha&lt;/script&gt;', $rendered['html']);
        self::assertStringNotContainsString('<script>Asha</script>', $rendered['html']);
    }

    public function testInvalidOrMissingTemplateFailureIsSanitizedAndDetached(): void
    {
        foreach (['../private', 'missing-template'] as $template) {
            try {
                $this->renderer->render($template, []);
                self::fail('Invalid mail template was rendered.');
            } catch (MailException $exception) {
                self::assertTrue($exception->isTemplateFailure());
                self::assertSame(
                    'Transactional email could not be prepared.',
                    $exception->getMessage(),
                );
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString($template, $exception->getMessage());
            }
        }
    }
}
