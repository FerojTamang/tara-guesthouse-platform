<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ErrorHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use RuntimeException;
use Stringable;

final class ErrorHandlerTest extends TestCase
{
    public function testProductionResponseDoesNotExposeExceptionDetails(): void
    {
        $handler = new ErrorHandler(new NullLogger(), false);
        $response = $handler->render(new RuntimeException('secret-value'));

        self::assertSame(500, $response->statusCode());
        self::assertSame('Internal Server Error', $response->body());
        self::assertStringNotContainsString('secret-value', $response->body());
    }

    public function testLocalDebugResponseContainsDiagnosticDetailsAsPlainText(): void
    {
        $handler = new ErrorHandler(new NullLogger(), true);
        $response = $handler->render(new RuntimeException('diagnostic-message'));

        self::assertSame(500, $response->statusCode());
        self::assertStringContainsString('diagnostic-message', $response->body());
        self::assertSame('text/plain; charset=UTF-8', $response->headers()['Content-Type']);
    }

    public function testLoggerFailureCannotEscapeExceptionRendering(): void
    {
        $logger = new class extends AbstractLogger {
            public function log($level, string|Stringable $message, array $context = []): void
            {
                throw new RuntimeException('logger-failure');
            }
        };
        $handler = new ErrorHandler($logger, false);

        $response = $handler->render(new RuntimeException('application-failure'));

        self::assertSame(500, $response->statusCode());
        self::assertSame('Internal Server Error', $response->body());
    }
}
