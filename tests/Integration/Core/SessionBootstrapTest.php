<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Bootstrap;
use App\Core\Config;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\LoggerFactory;
use App\Core\Request;
use App\Core\Session;
use App\Exceptions\ConfigurationException;
use Monolog\Level;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SessionBootstrapTest extends TestCase
{
    /** @var array<string, array{env: mixed, server: mixed, process: string|false}> */
    private array $originalEnvironment = [];

    /** @var list<string> */
    private array $sessionIds = [];

    private string $basePath;

    private string $logPath;

    private bool $handlersRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = dirname(__DIR__, 3);
        $this->logPath = $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs'
            . DIRECTORY_SEPARATOR . 'session-bootstrap-' . getmypid() . '-' . bin2hex(random_bytes(4)) . '.log';

        $this->setEnvironmentValues([
            'APP_NAME' => 'Tara Guest House Session Test',
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost',
            'APP_TIMEZONE' => 'Asia/Kathmandu',
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3307',
            'DB_DATABASE' => 'tara_guest_house',
            'DB_USERNAME' => 'tara_local',
            'DB_PASSWORD' => 'test-only-password',
            'DB_CHARSET' => 'utf8mb4',
            'DB_COLLATION' => 'utf8mb4_unicode_ci',
            'DB_TIMEZONE' => '+00:00',
            'DB_CONNECT_TIMEOUT' => '5',
            'DB_SSL_CA' => '',
            'TEST_DB_DRIVER' => 'mysql',
            'TEST_DB_HOST' => '127.0.0.1',
            'TEST_DB_PORT' => '3307',
            'TEST_DB_DATABASE' => 'tara_guest_house_test',
            'TEST_DB_USERNAME' => 'tara_test',
            'TEST_DB_PASSWORD' => '',
            'TEST_DB_CHARSET' => 'utf8mb4',
            'TEST_DB_COLLATION' => 'utf8mb4_unicode_ci',
            'TEST_DB_TIMEZONE' => '+00:00',
            'TEST_DB_CONNECT_TIMEOUT' => '5',
            'TEST_DB_SSL_CA' => '',
            'MAIL_HOST' => '',
            'MAIL_PORT' => '587',
            'MAIL_ENCRYPTION' => 'tls',
            'MAIL_USERNAME' => '',
            'MAIL_PASSWORD' => '',
            'MAIL_FROM_ADDRESS' => '',
            'MAIL_FROM_NAME' => 'Tara Guest House',
            'MAIL_REPLY_TO_ADDRESS' => '',
            'MAIL_REPLY_TO_NAME' => '',
            'MAIL_TIMEOUT' => '10',
            'LOG_CHANNEL' => 'file',
            'LOG_LEVEL' => 'debug',
            'LOG_PATH' => str_replace($this->basePath . DIRECTORY_SEPARATOR, '', $this->logPath),
            'SESSION_NAME' => 'tgh_session_test',
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

        foreach (array_unique($this->sessionIds) as $sessionId) {
            $sessionFile = $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions'
                . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;

            if (is_file($sessionFile)) {
                unlink($sessionFile);
            }
        }

        if (is_file($this->logPath)) {
            unlink($this->logPath);
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

            $original['process'] === false
                ? putenv($key)
                : putenv(sprintf('%s=%s', $key, $original['process']));
        }

        parent::tearDown();
    }

    public function testBootstrapStartsTheApprovedPrivateSessionConfiguration(): void
    {
        $application = Bootstrap::boot($this->basePath);
        $this->handlersRegistered = true;
        $this->rememberCurrentSessionId();

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame('tgh_session_test', session_name());
        self::assertSame(realpath($this->basePath . '/storage/sessions'), ini_get('session.save_path'));
        self::assertSame('1', ini_get('session.use_strict_mode'));
        self::assertSame('1', ini_get('session.use_cookies'));
        self::assertSame('1', ini_get('session.use_only_cookies'));
        self::assertSame('0', ini_get('session.use_trans_sid'));

        $cookie = session_get_cookie_params();
        self::assertSame(0, $cookie['lifetime']);
        self::assertFalse($cookie['secure']);
        self::assertTrue($cookie['httponly']);
        self::assertSame('Lax', $cookie['samesite']);
        self::assertSame('/', $cookie['path']);
        self::assertSame('', $cookie['domain']);

        $response = $application->handle(Request::create('GET', '/__session-bootstrap/close'));

        self::assertSame(404, $response->statusCode());
        self::assertSame(PHP_SESSION_NONE, session_status());

        $configuration = Config::load(CONFIG_PATH, \App\Core\Environment::load($this->basePath));
        self::assertTrue($configuration->has('app'));
        self::assertTrue($configuration->has('logging'));
        self::assertTrue($configuration->has('session'));
        self::assertTrue($configuration->has('database'));
        self::assertTrue($configuration->has('mail'));
        self::assertFalse($configuration->require('mail.enabled'));
        self::assertFalse($configuration->has('authentication'));
        self::assertFalse($configuration->has('upload'));
    }

    public function testProductionRejectsAnInsecureSessionCookie(): void
    {
        $this->setEnvironmentValues([
            'APP_ENV' => 'production',
            'SESSION_SECURE_COOKIE' => 'false',
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
        ]);

        $this->expectException(ConfigurationException::class);
        Bootstrap::boot($this->basePath);
    }

    public function testProductionAcceptsAnExplicitSecureSessionCookie(): void
    {
        $this->setEnvironmentValues([
            'APP_ENV' => 'production',
            'SESSION_SECURE_COOKIE' => 'true',
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_FROM_ADDRESS' => 'stay@example.test',
        ]);

        $application = Bootstrap::boot($this->basePath);
        $this->handlersRegistered = true;
        $this->rememberCurrentSessionId();

        self::assertTrue(session_get_cookie_params()['secure']);

        $application->handle(Request::create('GET', '/__session-bootstrap/close'));
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testStatePersistsAcrossSafeCloseAndReopen(): void
    {
        $session = Session::start($this->sessionConfiguration());
        $session->set('booking_context', ['room' => 3]);
        $createdAt = time() - 20;
        $_SESSION['__tgh']['created_at'] = $createdAt;
        $_SESSION['__tgh']['last_activity_at'] = time() - 10;
        $sessionId = $this->rememberCurrentSessionId();
        $session->close();

        session_id($sessionId);
        $reopened = Session::start($this->sessionConfiguration());
        $this->rememberCurrentSessionId();

        self::assertSame(['room' => 3], $reopened->get('booking_context'));
        self::assertSame($createdAt, $_SESSION['__tgh']['created_at']);
        self::assertGreaterThan(time() - 10, $_SESSION['__tgh']['last_activity_at']);
        $reopened->destroy();
    }

    public function testQueryAndFormSessionIdentifiersAreIgnored(): void
    {
        $configuration = $this->sessionConfiguration();
        $candidate = 'attacker-supplied-session-id';
        $_GET[$configuration['name']] = $candidate;
        $_POST[$configuration['name']] = $candidate;

        try {
            $session = Session::start($configuration);
            $this->rememberCurrentSessionId();

            self::assertNotSame($candidate, session_id());
            self::assertSame('1', ini_get('session.use_only_cookies'));
            self::assertSame('0', ini_get('session.use_trans_sid'));
            $session->destroy();
        } finally {
            unset($_GET[$configuration['name']], $_POST[$configuration['name']]);
        }
    }

    public function testRegenerationPreservesApprovedStateAndDeletesTheOldFile(): void
    {
        $session = Session::start($this->sessionConfiguration());
        $session->set('approved', 'preserved');
        $oldId = $this->rememberCurrentSessionId();
        $oldFile = $this->sessionFile($oldId);

        $session->regenerate();
        $newId = $this->rememberCurrentSessionId();

        self::assertNotSame($oldId, $newId);
        self::assertSame('preserved', $session->get('approved'));
        self::assertFileDoesNotExist($oldFile);
        $session->destroy();
    }

    public function testExpiredStateIsResetWithAFreshIdentifier(): void
    {
        $session = Session::start($this->sessionConfiguration());
        $session->set('expired', 'must disappear');
        $oldId = $this->rememberCurrentSessionId();
        $_SESSION['__tgh']['created_at'] = time() - 43300;
        $_SESSION['__tgh']['last_activity_at'] = time() - 10;
        $session->close();

        session_id($oldId);
        $reset = Session::start($this->sessionConfiguration());
        $newId = $this->rememberCurrentSessionId();

        self::assertNotSame($oldId, $newId);
        self::assertFalse($reset->has('expired'));
        $reset->destroy();
    }

    public function testDestroyRemovesStateAndServerSideFile(): void
    {
        $session = Session::start($this->sessionConfiguration());
        $session->set('temporary', 'value');
        $sessionId = $this->rememberCurrentSessionId();
        $sessionFile = $this->sessionFile($sessionId);

        $session->destroy();

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
        self::assertFileDoesNotExist($sessionFile);
    }

    public function testErrorLogsExcludePlantedSessionAndCsrfValues(): void
    {
        $session = Session::start($this->sessionConfiguration());
        $sessionId = $this->rememberCurrentSessionId();
        $token = (new CsrfTokenManager($session))->token();
        $logger = LoggerFactory::create(Config::fromArray([
            'logging' => [
                'channel' => 'session-disclosure-test',
                'level' => Level::Debug,
                'path' => $this->logPath,
            ],
        ]));
        $errorHandler = new ErrorHandler($logger, false);

        $response = $errorHandler->render(new RuntimeException($sessionId . ' ' . $token));
        $contents = file_get_contents($this->logPath);

        self::assertSame(500, $response->statusCode());
        self::assertIsString($contents);
        self::assertStringNotContainsString($sessionId, $contents);
        self::assertStringNotContainsString($token, $contents);
        $session->destroy();
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
            'save_path' => realpath($this->basePath . '/storage/sessions'),
            'gc_maxlifetime' => 43200,
        ];
    }

    private function rememberCurrentSessionId(): string
    {
        $sessionId = session_id();
        self::assertNotSame('', $sessionId);
        $this->sessionIds[] = $sessionId;

        return $sessionId;
    }

    private function sessionFile(string $sessionId): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions'
            . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
    }

    /**
     * @param array<string, string> $values
     */
    private function setEnvironmentValues(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $this->originalEnvironment)) {
                $this->originalEnvironment[$key] = [
                    'env' => $_ENV[$key] ?? null,
                    'server' => $_SERVER[$key] ?? null,
                    'process' => getenv($key),
                ];
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv(sprintf('%s=%s', $key, $value));
        }
    }
}
