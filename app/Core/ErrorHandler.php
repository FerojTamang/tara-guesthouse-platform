<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Provides centralized, environment-safe PHP error and exception handling.
 */
final class ErrorHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    /**
     * Registers the global PHP handlers.
     */
    public function register(): void
    {
        ini_set('display_errors', $this->debug ? '1' : '0');
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Converts reportable, unsuppressed PHP errors to ErrorException.
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Logs and delivers an uncaught exception response.
     */
    public function handleException(Throwable $throwable): void
    {
        $this->render($throwable)->send();
    }

    /**
     * Handles applicable fatal shutdown errors without terminating tests directly.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }

        $throwable = new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line'],
        );

        $response = $this->render($throwable);

        if (!headers_sent()) {
            $response->send();
        }
    }

    /**
     * Logs a throwable and creates the appropriate public response.
     */
    public function render(Throwable $throwable): Response
    {
        try {
            $this->logger->error('Unhandled application exception.', [
                'exception_class' => $throwable::class,
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ]);
        } catch (Throwable) {
            @error_log('Application exception logging failed.');
        }

        if (!$this->debug) {
            return Response::text('Internal Server Error', 500);
        }

        $details = sprintf(
            "%s: %s\n%s:%d\n%s",
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
            $throwable->getTraceAsString(),
        );

        return Response::text($details, 500);
    }
}
