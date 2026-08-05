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
        private readonly Session $session,
        private readonly ?ResponseSecurityPolicy $responseSecurityPolicy = null,
    ) {
    }

    /**
     * Dispatches the request and converts failures into safe responses.
     */
    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (Throwable $throwable) {
            $response = $this->errorHandler->render($throwable);
        }

        if ($this->responseSecurityPolicy !== null) {
            try {
                $response = $this->responseSecurityPolicy->apply($request, $response);
            } catch (Throwable $throwable) {
                $response = $this->errorHandler->render($throwable);
            }
        }

        try {
            $this->session->close();
        } catch (Throwable $throwable) {
            $this->errorHandler->render($throwable);
        }

        return $response;
    }
}
