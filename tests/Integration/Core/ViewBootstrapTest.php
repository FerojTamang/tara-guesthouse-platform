<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Application;
use App\Core\Bootstrap;
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
final class ViewBootstrapTest extends TestCase
{
    /** @var array<string, array{env: mixed, server: mixed, process: string|false}> */
    private array $originalEnvironment = [];

    /** @var list<string> */
    private array $repositorySessionIds = [];

    private bool $handlersRegistered = false;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = 'storage/logs/view-bootstrap-test-' . getmypid() . '.log';

        $this->setEnvironmentValues([
            'APP_NAME' => 'Tara Guest House Test',
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost',
            'APP_TIMEZONE' => 'Asia/Kathmandu',
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3307',
            'DB_DATABASE' => 'tara_guest_house',
            'DB_USERNAME' => 'local_user',
            'DB_PASSWORD' => '',
            'DB_CHARSET' => 'utf8mb4',
            'DB_COLLATION' => 'utf8mb4_unicode_ci',
            'DB_TIMEZONE' => '+00:00',
            'DB_CONNECT_TIMEOUT' => '5',
            'DB_SSL_CA' => '',
            'TEST_DB_DRIVER' => 'mysql',
            'TEST_DB_HOST' => 'not-a-real-database.invalid',
            'TEST_DB_PORT' => '3307',
            'TEST_DB_DATABASE' => 'tara_guest_house_test',
            'TEST_DB_USERNAME' => 'test_user',
            'TEST_DB_PASSWORD' => '',
            'TEST_DB_CHARSET' => 'utf8mb4',
            'TEST_DB_COLLATION' => 'utf8mb4_unicode_ci',
            'TEST_DB_TIMEZONE' => '+00:00',
            'TEST_DB_CONNECT_TIMEOUT' => '5',
            'TEST_DB_SSL_CA' => '',
            'LOG_CHANNEL' => 'file',
            'LOG_LEVEL' => 'debug',
            'LOG_PATH' => $this->logPath,
            'SESSION_NAME' => 'tgh_view_bootstrap_test',
            'SESSION_INACTIVITY_TIMEOUT' => '1800',
            'SESSION_ABSOLUTE_LIFETIME' => '43200',
            'SESSION_SECURE_COOKIE' => 'false',
            'SESSION_HTTP_ONLY' => 'true',
            'SESSION_SAME_SITE' => 'Lax',
            'SESSION_PATH' => '/',
            'SESSION_DOMAIN' => '',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->handlersRegistered) {
            restore_error_handler();
            restore_exception_handler();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        session_id('');

        foreach ($this->repositorySessionIds as $sessionId) {
            $sessionFile = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions'
                . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;

            if (is_file($sessionFile)) {
                unlink($sessionFile);
            }
        }

        foreach ($this->originalEnvironment as $key => $original) {
            if ($original['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $original['env'];
            }

            if ($original['server'] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $original['server'];
            }

            if ($original['process'] === false) {
                putenv($key);
            } else {
                putenv(sprintf('%s=%s', $key, $original['process']));
            }
        }

        $absoluteLogPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->logPath);

        if (is_file($absoluteLogPath)) {
            unlink($absoluteLogPath);
        }

        parent::tearDown();
    }

    public function testBootstrapConstructsRendererAndRendersLoginWithoutOpeningPdo(): void
    {
        $application = Bootstrap::boot(dirname(__DIR__, 3));
        $this->handlersRegistered = true;
        $this->repositorySessionIds[] = session_id();
        $response = $application->handle(Request::create('GET', '/admin/login'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertStringContainsString('<!doctype html>', $response->body());
        self::assertStringContainsString('Administrator login', $response->body());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testRouteRegistrarsUseTheExplicitRendererInstance(): void
    {
        $fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-route-views-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($fixtureRoot . DIRECTORY_SEPARATOR . 'infrastructure', 0700, true));
        self::assertTrue(mkdir($fixtureRoot . DIRECTORY_SEPARATOR . 'layouts', 0700));
        self::assertNotFalse(file_put_contents(
            $fixtureRoot . DIRECTORY_SEPARATOR . 'infrastructure' . DIRECTORY_SEPARATOR . 'welcome.php',
            'renderer supplied by registrar',
        ));
        self::assertNotFalse(file_put_contents(
            $fixtureRoot . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'base.php',
            '<?= $content ?>|<?= $e($title) ?>',
        ));

        try {
            $renderer = new ViewRenderer($fixtureRoot);
            $router = new Router();

            foreach (['web.php', 'admin.php', 'api.php'] as $routeFile) {
                $registrar = require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . $routeFile;
                $registrar($router, $renderer);
            }

            $response = $router->dispatch(Request::create('GET', '/'));
            self::assertSame('renderer supplied by registrar|Tara Guest House Application', $response->body());
        } finally {
            unlink($fixtureRoot . DIRECTORY_SEPARATOR . 'infrastructure' . DIRECTORY_SEPARATOR . 'welcome.php');
            unlink($fixtureRoot . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'base.php');
            rmdir($fixtureRoot . DIRECTORY_SEPARATOR . 'infrastructure');
            rmdir($fixtureRoot . DIRECTORY_SEPARATOR . 'layouts');
            rmdir($fixtureRoot);
        }
    }

    public function testRendererFailureUsesExistingProductionErrorHandling(): void
    {
        $savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-view-error-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($savePath, 0700));
        $session = Session::start($this->sessionConfiguration($savePath));
        $renderer = new ViewRenderer(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
        $router = new Router();
        $router->get('/internal-view-check', static function () use ($renderer) {
            return $renderer->render('missing.view');
        });
        $application = new Application($router, new ErrorHandler(new NullLogger(), false), $session);

        try {
            $response = $application->handle(Request::create('GET', '/internal-view-check'));

            self::assertSame(500, $response->statusCode());
            self::assertSame('Internal Server Error', $response->body());
            self::assertStringNotContainsString('resources', $response->body());
            self::assertStringNotContainsString('ViewRenderer.php', $response->body());
            self::assertStringNotContainsString('#0', $response->body());
        } finally {
            foreach (glob($savePath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir($savePath);
        }
    }

    /**
     * @param array<string, string> $values
     */
    private function setEnvironmentValues(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->originalEnvironment[$key] ??= [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'process' => getenv($key),
            ];

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv(sprintf('%s=%s', $key, $value));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionConfiguration(string $savePath): array
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
            'save_path' => realpath($savePath),
            'gc_maxlifetime' => 43200,
        ];
    }
}
