<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\CsrfTokenManager;
use App\Core\Session;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CsrfTokenManagerTest extends TestCase
{
    private string $savePath;

    private Session $session;

    private CsrfTokenManager $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-csrf-unit-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
        $this->tokens = new CsrfTokenManager($this->session);
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

    public function testTokenHasApprovedFormat(): void
    {
        $token = $this->tokens->token();

        self::assertSame(64, strlen($token));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $token);
    }

    public function testRepeatedRetrievalReturnsTheCurrentToken(): void
    {
        self::assertSame($this->tokens->token(), $this->tokens->token());
    }

    public function testMalformedStoredTokenIsReplacedSafely(): void
    {
        $_SESSION['__tgh']['csrf_token'] = 'malformed';

        $replacement = $this->tokens->token();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $replacement);
        self::assertNotSame('malformed', $replacement);
    }

    public function testRotationCreatesAUniqueReplacement(): void
    {
        $first = $this->tokens->token();
        $second = $this->tokens->rotate();

        self::assertNotSame($first, $second);
        self::assertSame($second, $this->tokens->token());
    }

    public function testValidTokenPassesValidation(): void
    {
        $token = $this->tokens->token();

        self::assertTrue($this->tokens->validate($token));
    }

    public function testInvalidCandidatesAreRejected(): void
    {
        $this->tokens->token();

        self::assertFalse($this->tokens->validate(null));
        self::assertFalse($this->tokens->validate(''));
        self::assertFalse($this->tokens->validate(str_repeat('g', 64)));
        self::assertFalse($this->tokens->validate(str_repeat('a', 63)));
        self::assertFalse($this->tokens->validate(str_repeat('a', 64)));
    }

    public function testValidationDoesNotGenerateMissingState(): void
    {
        self::assertFalse($this->tokens->validate(str_repeat('a', 64)));
        self::assertArrayNotHasKey('csrf_token', $_SESSION['__tgh']);
    }

    public function testValidationDoesNotMutateTheToken(): void
    {
        $token = $this->tokens->token();

        self::assertFalse($this->tokens->validate(str_repeat('a', 64)));
        self::assertSame($token, $this->tokens->token());
    }

    public function testClearRemovesTheToken(): void
    {
        $this->session->set('unrelated', 'preserved');
        $this->tokens->token();
        $this->tokens->clear();

        self::assertFalse($this->tokens->validate(str_repeat('a', 64)));
        self::assertArrayNotHasKey('csrf_token', $_SESSION['__tgh']);
        self::assertSame('preserved', $this->session->get('unrelated'));
    }

    public function testTokenExistsOnlyInsidePrivateSessionState(): void
    {
        $token = $this->tokens->token();

        self::assertSame($token, $_SESSION['__tgh']['csrf_token']);
        self::assertArrayNotHasKey('csrf_token', $_SESSION);
        self::assertNotContains($token, $_COOKIE);
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
