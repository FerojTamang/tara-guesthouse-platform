<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicContactController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PublicContactRateLimitMiddleware;
use App\Models\AuditLogEntry;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContactMessageRepository;
use App\Services\ContactMessageService;
use App\Services\ContactMessageServiceProvider;
use App\Services\NullContactReplyMailSender;
use App\Services\ContactSubmissionFlash;
use App\Services\ContactTransaction;
use App\Services\PublicContactRateLimiter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PublicContactExperienceTest extends TestCase
{
    private string $sessionPath;
    private string $rateLimitPath;
    private Session $session;
    private CsrfTokenManager $tokens;
    private Router $router;
    private PublicContactRepositoryFake $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = bin2hex(random_bytes(8));
        $this->sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-contact-session-' . $suffix;
        $this->rateLimitPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-contact-limit-' . $suffix;
        self::assertTrue(mkdir($this->sessionPath, 0700));
        $this->session = Session::start($this->sessionConfiguration());
        $this->tokens = new CsrfTokenManager($this->session);
        $this->repository = new PublicContactRepositoryFake();
        $service = new ContactMessageService(
            $this->repository,
            new PublicContactAuditFake(),
            new PublicContactTransactionFake(),
            new NullContactReplyMailSender(),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-31 12:00:00 UTC'),
        );
        $renderer = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
        $controller = new PublicContactController(
            $renderer,
            new ContactMessageServiceProvider(static fn (): ContactMessageService => $service),
            new ContactSubmissionFlash($this->session),
            $this->tokens,
        );
        $csrf = new CsrfMiddleware($this->tokens);
        $rateLimit = new PublicContactRateLimitMiddleware(new PublicContactRateLimiter($this->rateLimitPath, [
            'max_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ]));
        $this->router = new Router();
        $web = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $web($this->router, $renderer, $csrf, $controller, $rateLimit);
        $api = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
        $api($this->router, $renderer, $csrf, $controller, $rateLimit);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_id('');
        }
        foreach ([$this->sessionPath, $this->rateLimitPath] as $directory) {
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($directory);
        }
        parent::tearDown();
    }

    public function testContactPageRendersPrivateFormAndCsrfToken(): void
    {
        $response = $this->router->dispatch(Request::create('GET', '/contact'));
        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('<title>Contact | Tara Guest House</title>', $response->body());
        self::assertStringContainsString('action="/contact"', $response->body());
        self::assertStringContainsString('href="/contact">Contact</a>', $response->body());
        self::assertStringContainsString('value="' . $this->tokens->token() . '"', $response->body());
        self::assertStringContainsString('Do not include passwords', $response->body());
    }

    public function testWebSubmissionRequiresCsrfThenUsesPostRedirectGetFlash(): void
    {
        $withoutCsrf = $this->router->dispatch(Request::create(
            'POST',
            '/contact',
            input: self::input(),
            remoteAddress: '203.0.113.20',
        ));
        self::assertSame(403, $withoutCsrf->statusCode());
        self::assertSame([], $this->repository->messages);
        self::assertSame([], glob($this->rateLimitPath . DIRECTORY_SEPARATOR . '*.json') ?: []);

        $input = self::input();
        $input['_csrf'] = $this->tokens->token();
        $submitted = $this->router->dispatch(Request::create(
            'POST',
            '/contact',
            input: $input,
            remoteAddress: '203.0.113.20',
        ));
        self::assertSame(303, $submitted->statusCode());
        self::assertSame('/contact', $submitted->headers()['Location']);
        self::assertSame('4', $submitted->headers()['X-RateLimit-Remaining']);
        self::assertCount(1, $this->repository->messages);

        $success = $this->router->dispatch(Request::create('GET', '/contact'));
        self::assertStringContainsString('Your message has been received.', $success->body());
        $refresh = $this->router->dispatch(Request::create('GET', '/contact'));
        self::assertStringNotContainsString('Your message has been received.', $refresh->body());
    }

    public function testJsonApiReturnsOnlyReceiptIdentifierAndRejectsUnsupportedPayload(): void
    {
        $payload = self::input();
        $response = $this->router->dispatch(Request::create(
            'POST',
            '/api/v1/contact',
            headers: [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->tokens->token(),
                'User-Agent' => 'Public Contact Feature Test',
            ],
            body: json_encode($payload, JSON_THROW_ON_ERROR),
            remoteAddress: '203.0.113.21',
        ));
        self::assertSame(201, $response->statusCode());
        $decoded = json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR);
        self::assertSame(['contact_message_id' => 1], $decoded['data']);
        self::assertStringNotContainsString('asha@example.test', $response->body());
        self::assertStringNotContainsString($payload['message'], $response->body());

        $payload['status'] = 'new';
        $invalid = $this->router->dispatch(Request::create(
            'POST',
            '/api/v1/contact',
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $this->tokens->token()],
            body: json_encode($payload, JSON_THROW_ON_ERROR),
            remoteAddress: '203.0.113.22',
        ));
        self::assertSame(422, $invalid->statusCode());
        self::assertStringNotContainsString('status', $invalid->body());
    }

    public function testSixthSubmissionFromOneAddressIsRateLimited(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $input = self::input();
            $input['_csrf'] = $this->tokens->token();
            $response = $this->router->dispatch(Request::create(
                'POST',
                '/contact',
                input: $input,
                remoteAddress: '203.0.113.23',
            ));
            self::assertSame($attempt <= 5 ? 303 : 429, $response->statusCode());
        }
        self::assertCount(5, $this->repository->messages);
    }

    /** @return array<string, string> */
    private static function input(): array
    {
        return [
            'name' => 'Asha Guest',
            'email' => 'asha@example.test',
            'phone' => '+977 9800000000',
            'subject' => 'Airport transfer',
            'message' => 'Could you please arrange an airport transfer?',
        ];
    }

    /** @return array<string, mixed> */
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
            'save_path' => realpath($this->sessionPath),
            'gc_maxlifetime' => 43200,
        ];
    }
}

final class PublicContactRepositoryFake implements ContactMessageRepository
{
    /** @var array<int, ContactMessage> */
    public array $messages = [];
    /** @var array<int, ContactMessageReply> */
    public array $replies = [];

    public function create(string $name, string $email, ?string $phone, string $subject, string $message, DateTimeImmutable $createdAt): ContactMessage
    {
        $contact = new ContactMessage(
            count($this->messages) + 1,
            $name,
            $email,
            $phone,
            $subject,
            $message,
            ContactMessage::STATUS_NEW,
            $createdAt,
            $createdAt,
        );
        $this->messages[$contact->id()] = $contact;
        return $contact;
    }

    public function findAll(?string $search = null): array
    {
        return array_values($this->messages);
    }

    public function findById(int $id): ?ContactMessage
    {
        return $this->messages[$id] ?? null;
    }

    public function findByIdForUpdate(int $id): ?ContactMessage
    {
        return $this->findById($id);
    }

    public function updateStatus(int $id, string $status, DateTimeImmutable $updatedAt): ContactMessage
    {
        $current = $this->messages[$id];
        return $this->messages[$id] = new ContactMessage(
            $current->id(),
            $current->name(),
            $current->email(),
            $current->phone(),
            $current->subject(),
            $current->message(),
            $status,
            $current->createdAt(),
            $updatedAt,
        );
    }

    public function createReply(int $contactMessageId, string $replyBody, int $sentBy, DateTimeImmutable $createdAt): ContactMessageReply
    {
        $id = count($this->replies) + 1;
        return $this->replies[$id] = new ContactMessageReply(
            $id,
            $contactMessageId,
            $replyBody,
            $sentBy,
            ContactMessageReply::STATUS_PENDING,
            null,
            null,
            $createdAt,
            $createdAt,
        );
    }

    public function markReplySent(int $replyId, DateTimeImmutable $sentAt): ContactMessageReply
    {
        $current = $this->replies[$replyId];
        return $this->replies[$replyId] = new ContactMessageReply(
            $current->id(),
            $current->contactMessageId(),
            $current->replyBody(),
            $current->sentBy(),
            ContactMessageReply::STATUS_SENT,
            $sentAt,
            null,
            $current->createdAt(),
            $sentAt,
        );
    }

    public function markReplyFailed(int $replyId, string $failureCode, DateTimeImmutable $updatedAt): ContactMessageReply
    {
        $current = $this->replies[$replyId];
        return $this->replies[$replyId] = new ContactMessageReply(
            $current->id(),
            $current->contactMessageId(),
            $current->replyBody(),
            $current->sentBy(),
            ContactMessageReply::STATUS_FAILED,
            null,
            $failureCode,
            $current->createdAt(),
            $updatedAt,
        );
    }

    public function findRepliesByContactMessageId(int $contactMessageId): array
    {
        return array_values(array_filter(
            $this->replies,
            static fn (ContactMessageReply $reply): bool => $reply->contactMessageId() === $contactMessageId,
        ));
    }
}

final class PublicContactAuditFake implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void
    {
    }
}

final class PublicContactTransactionFake implements ContactTransaction
{
    private bool $active = false;
    public function begin(): void { $this->active = true; }
    public function commit(): void { $this->active = false; }
    public function rollBack(): void { $this->active = false; }
    public function inProgress(): bool { return $this->active; }
}
