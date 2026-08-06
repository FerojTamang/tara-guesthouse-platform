<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorIdentityValidator;
use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Session;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorSessionTest extends TestCase
{
    private string $savePath;

    private Session $session;

    private CsrfTokenManager $csrfTokens;

    private AdministratorSession $administratorSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-admin-session-unit-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->csrfTokens);
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

    public function testAnonymousSessionHasNoAdministratorIdentity(): void
    {
        self::assertNull($this->administratorSession->identity());
        self::assertFalse($this->administratorSession->isAuthenticated());
        self::assertArrayNotHasKey('administrator_identity', $_SESSION);
    }

    public function testEstablishRegeneratesTheSessionAndRotatesCsrf(): void
    {
        $originalId = session_id();
        $originalToken = $this->csrfTokens->token();

        $this->administratorSession->establish(new AdministratorIdentity(12));

        self::assertNotSame($originalId, session_id());
        self::assertNotSame($originalToken, $this->csrfTokens->token());
        self::assertSame([
            'version' => 1,
            'user_id' => 12,
            'role' => 'Administrator',
        ], $_SESSION['administrator_identity']);
    }

    public function testEstablishRestartsTheAbsoluteAuthenticatedLifetime(): void
    {
        $_SESSION['__tgh']['created_at'] = time() - 43199;
        $_SESSION['__tgh']['last_activity_at'] = time() - 120;
        $beforeEstablish = time();

        $this->administratorSession->establish(new AdministratorIdentity(18));

        self::assertGreaterThanOrEqual($beforeEstablish, $_SESSION['__tgh']['created_at']);
        self::assertLessThanOrEqual(time(), $_SESSION['__tgh']['created_at']);
        self::assertSame(
            $_SESSION['__tgh']['created_at'],
            $_SESSION['__tgh']['last_activity_at'],
        );
    }

    public function testEstablishedIdentityCanBeReadBack(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(19));

        $identity = $this->administratorSession->identity();

        self::assertInstanceOf(AdministratorIdentity::class, $identity);
        self::assertSame(19, $identity->userId());
        self::assertTrue($this->administratorSession->isAuthenticated());
    }

    public function testEstablishingAReplacementIdentityRegeneratesAgain(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(19));
        $firstAuthenticatedId = session_id();
        $firstAuthenticatedToken = $this->csrfTokens->token();

        $this->administratorSession->establish(new AdministratorIdentity(20));

        self::assertNotSame($firstAuthenticatedId, session_id());
        self::assertNotSame($firstAuthenticatedToken, $this->csrfTokens->token());
        self::assertSame(20, $this->administratorSession->identity()?->userId());
    }


    public function testTerminateDestroysTheAuthenticatedSession(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(31));
        $authenticatedId = session_id();

        $identity = $this->administratorSession->terminate();

        self::assertSame(31, $identity?->userId());
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
        self::assertNotSame('', $authenticatedId);
    }

    public function testPersistedIdentityIsRevokedWhenTheAccountValidatorRejectsIt(): void
    {
        $originalId = session_id();
        $originalToken = $this->csrfTokens->token();
        $this->session->set('preserved', 'value');
        $this->session->set(
            'administrator_identity',
            (new AdministratorIdentity(44))->toSessionData(),
        );
        $validator = new class implements AdministratorIdentityValidator {
            public int $calls = 0;

            public function isValid(AdministratorIdentity $identity): bool
            {
                $this->calls++;

                return false;
            }
        };
        $administratorSession = new AdministratorSession(
            $this->session,
            $this->csrfTokens,
            $validator,
        );

        self::assertNull($administratorSession->identity());

        self::assertSame(1, $validator->calls);
        self::assertNotSame($originalId, session_id());
        self::assertNotSame($originalToken, $this->csrfTokens->token());
        self::assertFalse($this->session->has('administrator_identity'));
        self::assertSame('value', $this->session->get('preserved'));
        self::assertFalse($administratorSession->isAuthenticated());
        self::assertSame(1, $validator->calls);
    }

    public function testValidatorFailureRevokesThePersistedIdentityFailClosed(): void
    {
        $this->session->set(
            'administrator_identity',
            (new AdministratorIdentity(45))->toSessionData(),
        );
        $validator = new class implements AdministratorIdentityValidator {
            public function isValid(AdministratorIdentity $identity): bool
            {
                throw new \RuntimeException('test-only validator failure');
            }
        };
        $administratorSession = new AdministratorSession(
            $this->session,
            $this->csrfTokens,
            $validator,
        );

        self::assertNull($administratorSession->identity());
        self::assertFalse($this->session->has('administrator_identity'));
        self::assertFalse($administratorSession->isAuthenticated());
    }

    public function testMalformedIdentityIsRemovedAndTheAnonymousSessionIsHardened(): void
    {
        $originalId = session_id();
        $originalToken = $this->csrfTokens->token();
        $this->session->set('preserved', 'value');
        $this->session->set('administrator_identity', [
            'version' => 1,
            'user_id' => 12,
            'role' => 'administrator',
        ]);

        self::assertNull($this->administratorSession->identity());

        self::assertNotSame($originalId, session_id());
        self::assertNotSame($originalToken, $this->csrfTokens->token());
        self::assertFalse($this->session->has('administrator_identity'));
        self::assertSame('value', $this->session->get('preserved'));
        self::assertFalse($this->administratorSession->isAuthenticated());
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
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
