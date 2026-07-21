<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Handles one request through the configured router.
 */
final class Application
{
    public function __construct(
        private readonly Router $router,
        private readonly ErrorHandler $errorHandler,
    ) {
    }

    /**
     * Dispatches the request and converts failures into safe responses.
     */
    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $throwable) {
            return $this->errorHandler->render($throwable);
        }
    }
}
