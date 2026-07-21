<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class HttpFoundationTest extends TestCase
{
    public function testWelcomeRouteReturnsGenericSuccessfulResponse(): void
    {
        $response = $this->application()->handle(Request::create('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('application bootstrap is running', $response->body());
        self::assertStringNotContainsString(PHP_VERSION, $response->body());
        self::assertStringNotContainsString(dirname(__DIR__, 2), $response->body());
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        $response = $this->application()->handle(Request::create('GET', '/unknown'));

        self::assertSame(404, $response->statusCode());
        self::assertSame('Not Found', $response->body());
    }

    public function testUnsupportedMethodReturnsMethodNotAllowed(): void
    {
        $response = $this->application()->handle(Request::create('POST', '/'));

        self::assertSame(405, $response->statusCode());
        self::assertSame('GET, HEAD', $response->headers()['Allow']);
    }

    public function testHeadReturnsNoBody(): void
    {
        $response = $this->application()->handle(Request::create('HEAD', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('', $response->body());
    }

    public function testProductionExceptionResponseContainsNoSensitiveDetails(): void
    {
        $router = new Router();
        $router->get('/failure', static function (Request $request, array $parameters): Response {
            throw new RuntimeException('secret-value at C:\\private\\application.php');
        });
        $application = new Application($router, new ErrorHandler(new NullLogger(), false));

        $response = $application->handle(Request::create('GET', '/failure'));

        self::assertSame(500, $response->statusCode());
        self::assertSame('Internal Server Error', $response->body());
        self::assertStringNotContainsString('secret-value', $response->body());
        self::assertStringNotContainsString('C:\\private', $response->body());
    }

    private function application(): Application
    {
        $router = new Router();

        foreach (['web.php', 'admin.php', 'api.php'] as $routeFile) {
            $registrar = require dirname(__DIR__, 2) . '/routes/' . $routeFile;
            $registrar($router);
        }

        return new Application($router, new ErrorHandler(new NullLogger(), false));
    }
}
