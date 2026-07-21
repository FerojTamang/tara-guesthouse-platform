<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Bootstrap;
use App\Core\Config;
use App\Core\Environment;
use App\Core\LoggerFactory;
use App\Core\Request;
use App\Exceptions\ConfigurationException;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    /** @var array<string, array{env: mixed, server: mixed, process: string|false}> */
    private array $originalEnvironment = [];

    private string $logPath;

    private bool $handlersRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = 'storage/logs/bootstrap-test-' . getmypid() . '.log';
        $this->setEnvironmentValues([
            'APP_NAME' => 'Tara Guest House Test',
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost',
            'APP_TIMEZONE' => 'Asia/Kathmandu',
            'LOG_CHANNEL' => 'file',
            'LOG_LEVEL' => 'debug',
            'LOG_PATH' => $this->logPath,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->handlersRegistered) {
            restore_error_handler();
            restore_exception_handler();
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

        $absoluteLogPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->logPath);

        if (is_file($absoluteLogPath)) {
            unlink($absoluteLogPath);
        }

        parent::tearDown();
    }

    public function testBootstrapLoadsApprovedConfigurationRoutesAndTimezoneOnly(): void
    {
        $sessionStatus = session_status();
        $application = Bootstrap::boot(dirname(__DIR__, 3));
        $this->handlersRegistered = true;
        $response = $application->handle(Request::create('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('Asia/Kathmandu', date_default_timezone_get());
        self::assertSame($sessionStatus, session_status());
        self::assertFileExists(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->logPath));

        $environment = Environment::fromArray([
            'APP_NAME' => 'Test',
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'Asia/Kathmandu',
            'LOG_CHANNEL' => 'file',
            'LOG_LEVEL' => 'debug',
            'LOG_PATH' => $this->logPath,
        ]);
        $config = Config::load(CONFIG_PATH, $environment);

        self::assertTrue($config->has('app'));
        self::assertTrue($config->has('logging'));
        self::assertFalse($config->has('database'));
        self::assertFalse($config->has('mail'));
        self::assertFalse($config->has('session'));
        self::assertFalse($config->has('upload'));
    }

    public function testInvalidConfigurationFailsBeforeDispatch(): void
    {
        $this->setEnvironmentValues(['APP_ENV' => 'invalid-environment']);

        $this->expectException(ConfigurationException::class);
        Bootstrap::boot(dirname(__DIR__, 3));
    }

    public function testRepositoryMarkerMustBeAFile(): void
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-bootstrap-' . bin2hex(random_bytes(8));
        $directories = ['composer.json', 'app', 'config', 'routes', 'public', 'storage'];
        self::assertTrue(mkdir($basePath, 0700));

        foreach ($directories as $directory) {
            self::assertTrue(mkdir($basePath . DIRECTORY_SEPARATOR . $directory, 0700));
        }

        try {
            $this->expectException(ConfigurationException::class);
            Bootstrap::boot($basePath);
        } finally {
            foreach (array_reverse($directories) as $directory) {
                rmdir($basePath . DIRECTORY_SEPARATOR . $directory);
            }

            rmdir($basePath);
        }
    }

    public function testLoggingPathTraversalFailsBeforeDispatch(): void
    {
        $this->setEnvironmentValues(['LOG_PATH' => '../public/application.log']);

        $this->expectException(ConfigurationException::class);
        Bootstrap::boot(dirname(__DIR__, 3));
    }

    public function testInvalidLogLevelFailsBeforeDispatch(): void
    {
        $this->setEnvironmentValues(['LOG_LEVEL' => 'not-a-level']);

        $this->expectException(ConfigurationException::class);
        Bootstrap::boot(dirname(__DIR__, 3));
    }

    public function testLoggerFallsBackWhenFilePathCannotBeOpened(): void
    {
        $blockingFile = tempnam(sys_get_temp_dir(), 'tgh-log-blocker-');
        self::assertIsString($blockingFile);

        try {
            $config = Config::fromArray([
                'logging' => [
                    'channel' => 'file',
                    'level' => Level::Debug,
                    'path' => $blockingFile . DIRECTORY_SEPARATOR . 'application.log',
                ],
            ]);

            $logger = LoggerFactory::create($config);

            self::assertInstanceOf(Logger::class, $logger);
            self::assertInstanceOf(ErrorLogHandler::class, $logger->getHandlers()[0]);
        } finally {
            if (is_file($blockingFile)) {
                unlink($blockingFile);
            }
        }
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
