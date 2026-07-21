<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class HttpFoundationTest extends TestCase
{
    private string $savePath;

    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-http-feature-' . bin2hex(random_bytes(8));
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
                unlink($file);
            }
        }

        if (is_dir($this->savePath)) {
            rmdir($this->savePath);
        }

        parent::tearDown();
    }

    public function testWelcomeRouteReturnsGenericSuccessfulResponse(): void
    {
        $response = $this->application()->handle(Request::create('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Application foundation is running', $response->body());
        self::assertStringContainsString('The PHP view renderer is operational.', $response->body());
        self::assertSame('text/html; charset=UTF-8', $response->headers()['Content-Type']);
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
        self::assertSame(PHP_SESSION_NONE, session_status());
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
        $application = new Application($router, new ErrorHandler(new NullLogger(), false), $this->session);

        $response = $application->handle(Request::create('GET', '/failure'));

        self::assertSame(500, $response->statusCode());
        self::assertSame('Internal Server Error', $response->body());
        self::assertStringNotContainsString('secret-value', $response->body());
        self::assertStringNotContainsString('C:\\private', $response->body());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    private function application(): Application
    {
        $router = new Router();
        $renderer = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');

        foreach (['web.php', 'admin.php', 'api.php'] as $routeFile) {
            $registrar = require dirname(__DIR__, 2) . '/routes/' . $routeFile;
            $registrar($router, $renderer);
        }

        return new Application($router, new ErrorHandler(new NullLogger(), false), $this->session);
    }

    /**
     * @return array<string, mixed>
     */
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
}
