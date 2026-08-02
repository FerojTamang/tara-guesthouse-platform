<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Application;
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
final class ViewFoundationTest extends TestCase
{
    private string $savePath;

    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-view-feature-' . bin2hex(random_bytes(8));
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

    public function testGetRootReturnsOnlySemanticInfrastructureHtml(): void
    {
        $sessionId = session_id();
        $response = $this->application()->handle(Request::create('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertStringStartsWith('<!doctype html>', $response->body());
        self::assertStringContainsString('<html lang="en">', $response->body());
        self::assertStringContainsString(
            '<a class="tg-skip-link" href="#main-content">Skip to main content</a>',
            $response->body(),
        );
        self::assertStringContainsString('<nav class="tg-public-nav tg-container" aria-label="Primary navigation">', $response->body());
        self::assertStringContainsString('aria-controls="public-navigation"', $response->body());
        self::assertStringContainsString('aria-expanded="false"', $response->body());
        self::assertStringContainsString('href="/rooms"', $response->body());
        self::assertStringContainsString('href="/activities"', $response->body());
        self::assertStringContainsString('href="/availability"', $response->body());
        self::assertStringContainsString('<main id="main-content" class="tg-main tg-container tg-page-shell" tabindex="-1">', $response->body());
        self::assertStringContainsString('Application foundation is running', $response->body());
        self::assertStringContainsString('The PHP view renderer is operational.', $response->body());
        self::assertStringNotContainsString('Book now', $response->body());
        self::assertStringNotContainsString('Administrator', $response->body());
        self::assertStringNotContainsString('csrf', strtolower($response->body()));
        self::assertStringNotContainsString($sessionId, $response->body());
        self::assertStringNotContainsString(PHP_VERSION, $response->body());
        self::assertStringNotContainsString(dirname(__DIR__, 2), $response->body());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testHeadRootReturnsHtmlHeadersWithoutABody(): void
    {
        $response = $this->application()->handle(Request::create('HEAD', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('', $response->body());
        self::assertSame('text/html; charset=UTF-8', $response->headers()['Content-Type']);
    }

    public function testUnknownAndUnsupportedRoutesKeepPlainTextBehavior(): void
    {
        $notFound = $this->application()->handle(Request::create('GET', '/unknown'));

        self::assertSame(404, $notFound->statusCode());
        self::assertSame('Not Found', $notFound->body());
        self::assertSame('text/plain; charset=UTF-8', $notFound->headers()['Content-Type']);

        $this->session = Session::start($this->sessionConfiguration());
        $notAllowed = $this->application()->handle(Request::create('POST', '/'));

        self::assertSame(405, $notAllowed->statusCode());
        self::assertSame('Method Not Allowed', $notAllowed->body());
        self::assertSame('GET, HEAD', $notAllowed->headers()['Allow']);
        self::assertSame('text/plain; charset=UTF-8', $notAllowed->headers()['Content-Type']);
    }

    private function application(): Application
    {
        $router = new Router();
        $renderer = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');

        foreach (['web.php', 'admin.php', 'api.php'] as $routeFile) {
            $registrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . $routeFile;
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
