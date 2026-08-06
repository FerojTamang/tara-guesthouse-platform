<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\CsrfTokenManager;
use App\Core\Session;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SessionTest extends TestCase
{
    private string $savePath;

    /** @var array<string, mixed> */
    private array $configuration;

    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-session-unit-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->configuration = $this->configuration($this->savePath);
        $this->session = Session::start($this->configuration);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            try {
                $this->session->destroy();
            } catch (\Throwable) {
                $_SESSION = [];
                @session_destroy();
            }
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

    public function testItSetsGetsAndReturnsDefaults(): void
    {
        $this->session->set('room', 'river-view');

        self::assertSame('river-view', $this->session->get('room'));
        self::assertSame('fallback', $this->session->get('missing', 'fallback'));
    }

    public function testHasUsesArrayKeyExistsSemanticsForStoredNull(): void
    {
        $this->session->set('nullable', null);

        self::assertTrue($this->session->has('nullable'));
        self::assertNull($this->session->get('nullable'));
    }

    public function testItRemovesValues(): void
    {
        $this->session->set('notice', 'ready');
        $this->session->remove('notice');

        self::assertFalse($this->session->has('notice'));
    }

    public function testPullReturnsAndRemovesAValue(): void
    {
        $this->session->set('once', 'message');

        self::assertSame('message', $this->session->pull('once'));
        self::assertFalse($this->session->has('once'));
        self::assertSame('fallback', $this->session->pull('missing', 'fallback'));
    }

    public function testItRejectsTheReservedNamespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->session->set('__tgh', 'forbidden');
    }

    public function testItRejectsEmptyApplicationKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->session->set('   ', 'forbidden');
    }

    public function testStartingWhileAnotherSessionIsActiveFailsSafely(): void
    {
        $this->expectException(\RuntimeException::class);
        Session::start($this->configuration);
    }

    public function testInactivityExpiryResetsTheSession(): void
    {
        $originalId = session_id();
        $now = time();
        $this->session->set('authenticated', true);
        $_SESSION['__tgh']['created_at'] = $now - 2000;
        $_SESSION['__tgh']['last_activity_at'] = $now - 1800;
        $this->session->close();

        $this->session = Session::start($this->configuration);

        self::assertNotSame($originalId, session_id());
        self::assertFalse($this->session->has('authenticated'));
    }

    public function testAbsoluteExpiryResetsTheSession(): void
    {
        $originalId = session_id();
        $now = time();
        $this->session->set('authenticated', true);
        $_SESSION['__tgh']['created_at'] = $now - 43200;
        $_SESSION['__tgh']['last_activity_at'] = $now - 1;
        $this->session->close();

        $this->session = Session::start($this->configuration);

        self::assertNotSame($originalId, session_id());
        self::assertFalse($this->session->has('authenticated'));
    }

    public function testMalformedMetadataResetsTheSession(): void
    {
        $originalId = session_id();
        $this->session->set('trusted', true);
        $_SESSION['__tgh'] = 'malformed';
        $this->session->close();

        $this->session = Session::start($this->configuration);

        self::assertNotSame($originalId, session_id());
        self::assertFalse($this->session->has('trusted'));
        self::assertIsArray($_SESSION['__tgh']);
    }

    public function testFutureMetadataResetsTheSession(): void
    {
        $originalId = session_id();
        $this->session->set('trusted', true);
        $_SESSION['__tgh']['created_at'] = time() + 60;
        $_SESSION['__tgh']['last_activity_at'] = time() + 60;
        $this->session->close();

        $this->session = Session::start($this->configuration);

        self::assertNotSame($originalId, session_id());
        self::assertFalse($this->session->has('trusted'));
    }

    public function testStringTimestampMetadataResetsTheSession(): void
    {
        $originalId = session_id();
        $this->session->set('trusted', true);
        $_SESSION['__tgh']['created_at'] = (string) time();
        $this->session->close();

        $this->session = Session::start($this->configuration);

        self::assertNotSame($originalId, session_id());
        self::assertFalse($this->session->has('trusted'));
    }

    public function testMissingInternalMetadataWithExistingStateResetsTheSession(): void
    {
        $originalId = session_id();
        $this->session->set('untrusted', true);
        unset($_SESSION['__tgh']);
        $this->session->close();

        $this->session = Session::start($this->configuration);

        self::assertNotSame($originalId, session_id());
        self::assertFalse($this->session->has('untrusted'));
    }

    public function testRegenerationChangesTheIdentifierAndPreservesData(): void
    {
        $originalId = session_id();
        $this->session->set('approved', 'value');

        $this->session->regenerate();

        self::assertNotSame($originalId, session_id());
        self::assertSame('value', $this->session->get('approved'));
        self::assertFileDoesNotExist($this->savePath . DIRECTORY_SEPARATOR . 'sess_' . $originalId);
    }

    public function testRenewPreservesApplicationStateAndRestartsTheAbsoluteLifetime(): void
    {
        $originalId = session_id();
        $originalToken = (new CsrfTokenManager($this->session))->token();
        $this->session->set('preserved', 'value');
        $_SESSION['__tgh']['created_at'] = time() - 43199;
        $_SESSION['__tgh']['last_activity_at'] = time() - 120;
        $beforeRenewal = time();

        $this->session->renew();

        self::assertNotSame($originalId, session_id());
        self::assertSame('value', $this->session->get('preserved'));
        self::assertNull($this->session->csrfToken());
        self::assertArrayNotHasKey('csrf_token', $_SESSION['__tgh']);
        self::assertNotSame('', $originalToken);
        self::assertGreaterThanOrEqual($beforeRenewal, $_SESSION['__tgh']['created_at']);
        self::assertLessThanOrEqual(time(), $_SESSION['__tgh']['created_at']);
        self::assertSame(
            $_SESSION['__tgh']['created_at'],
            $_SESSION['__tgh']['last_activity_at'],
        );
    }

    public function testResetClearsStateAndKeepsAFreshSessionActive(): void
    {
        $originalId = session_id();
        $this->session->set('approved', 'value');
        (new CsrfTokenManager($this->session))->token();

        $this->session->reset();

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertNotSame($originalId, session_id());
        self::assertFalse($this->session->has('approved'));
        self::assertArrayHasKey('__tgh', $_SESSION);
        self::assertArrayNotHasKey('csrf_token', $_SESSION['__tgh']);
    }

    public function testDestroyClearsStateAndStopsTheSession(): void
    {
        $this->session->set('approved', 'value');

        $this->session->destroy();

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
    }

    public function testCloseStopsOnlyTheActiveSession(): void
    {
        $this->session->set('approved', 'value');

        $this->session->close();
        $this->session->close();

        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testDataOperationsFailAfterClose(): void
    {
        $this->session->close();

        $this->expectException(\RuntimeException::class);
        $this->session->get('closed');
    }

    public function testDestroyFailsRatherThanPretendingToDestroyAClosedSession(): void
    {
        $this->session->close();

        $this->expectException(\RuntimeException::class);
        $this->session->destroy();
    }

    public function testItRejectsAnUnavailableSavePath(): void
    {
        $this->session->destroy();
        $invalidConfiguration = $this->configuration;
        $invalidConfiguration['save_path'] = $this->savePath . DIRECTORY_SEPARATOR . 'missing';

        $this->expectException(\RuntimeException::class);
        Session::start($invalidConfiguration);
    }

    public function testItRejectsANonFileSaveHandler(): void
    {
        $this->session->destroy();
        $invalidConfiguration = $this->configuration;
        $invalidConfiguration['save_handler'] = 'user';

        $this->expectException(\RuntimeException::class);
        Session::start($invalidConfiguration);
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(string $savePath): array
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
