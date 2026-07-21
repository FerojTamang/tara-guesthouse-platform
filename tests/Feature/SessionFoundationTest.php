<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SessionFoundationTest extends TestCase
{
    private string $savePath;

    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-session-feature-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->sessionConfiguration());
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }

        session_id('');

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

    public function testNormalGetSucceedsThenClosesWithoutDisclosingSessionOrCsrfData(): void
    {
        $sessionId = session_id();
        $token = (new CsrfTokenManager($this->session))->token();
        $response = $this->application()->handle(Request::create('GET', '/'));
        $serializedHeaders = json_encode($response->headers(), JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Application foundation is running', $response->body());
        self::assertStringContainsString('The PHP view renderer is operational.', $response->body());
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertStringNotContainsString($sessionId, $response->body());
        self::assertStringNotContainsString($sessionId, $serializedHeaders);
        self::assertStringNotContainsString($token, $response->body());
        self::assertStringNotContainsString($token, $serializedHeaders);
    }

    public function testFailedDispatchStillClosesTheSession(): void
    {
        $response = $this->application()->handle(Request::create('GET', '/missing'));

        self::assertSame(404, $response->statusCode());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testNoPublicSessionCsrfOrProtectedTestRouteWasIntroduced(): void
    {
        $application = $this->application();

        foreach (['/session-debug', '/csrf-debug', '/protected-test'] as $path) {
            $response = $application->handle(Request::create('GET', $path));
            self::assertSame(404, $response->statusCode(), sprintf('%s must not be public.', $path));
        }
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
