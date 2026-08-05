<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorContactController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Models\AuditLogEntry;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContactMessageRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\ContactMessageService;
use App\Services\ContactMessageServiceProvider;
use App\Services\ContactTransaction;
use App\Services\ContactReplyMailSender;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorContactReadTest extends TestCase
{
    private string $sessionPath;
    private Session $session;
    private AdministratorSession $administratorSession;
    private CsrfTokenManager $tokens;
    private Router $router;
    private AdministratorContactRepositoryFake $repository;
    private AdministratorContactMailFake $mail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-admin-contact-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->sessionPath, 0700));
        $this->session = Session::start($this->sessionConfiguration());
        $this->tokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->tokens);
        $renderer = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
        $logger = new NullLogger();
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new AdministratorContactAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->tokens,
            $logger,
            self::loginPolicy(),
        );
        $this->repository = new AdministratorContactRepositoryFake();
        $this->mail = new AdministratorContactMailFake();
        $service = new ContactMessageService(
            $this->repository,
            new AdministratorContactAuditFake(),
            new AdministratorContactTransactionFake(),
            $this->mail,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-04 00:00:00 UTC'),
        );
        $contactController = new AdministratorContactController(
            $renderer,
            new ContactMessageServiceProvider(static fn (): ContactMessageService => $service),
            $this->administratorSession,
            $this->tokens,
            new DateTimeZone('Asia/Kathmandu'),
        );
        $guestOnly = new GuestOnlyMiddleware($this->administratorSession);
        $requireAdministrator = new RequireAdministratorMiddleware($this->administratorSession);
        $csrf = new CsrfMiddleware($this->tokens);
        $this->router = new Router();
        $admin = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'admin.php';
        $admin(
            $this->router,
            $renderer,
            $authenticationController,
            $guestOnly,
            $requireAdministrator,
            $csrf,
            contactController: $contactController,
        );
        $api = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
        $api(
            $this->router,
            $renderer,
            $authenticationController,
            $guestOnly,
            $requireAdministrator,
            $csrf,
            $contactController,
        );
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_id('');
        }
        foreach (glob($this->sessionPath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->sessionPath);
        parent::tearDown();
    }

    public function testGuestCannotReadOrReplyToContactMessages(): void
    {
        $page = $this->router->dispatch(Request::create('GET', '/admin/contact-messages'));
        self::assertSame(302, $page->statusCode());
        self::assertSame('/admin/login', $page->headers()['Location']);

        $api = $this->router->dispatch(Request::create(
            'POST',
            '/api/v1/admin/contact-messages/7/reply',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode(['reply_body' => 'Private reply'], JSON_THROW_ON_ERROR),
        ));
        self::assertSame(401, $api->statusCode());
        self::assertStringContainsString('Authentication required.', $api->body());
    }

    public function testAuthenticatedListAndDetailEscapeGuestContentAndShowReplyWorkflow(): void
    {
        $this->repository->messages[7] = self::contact();
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $list = $this->router->dispatch(Request::create('GET', '/admin/contact-messages?q=airport'));
        self::assertSame(200, $list->statusCode());
        self::assertSame('airport', $this->repository->lastSearch);
        self::assertStringContainsString('&lt;script&gt;Asha&lt;/script&gt;', $list->body());
        self::assertStringNotContainsString('<script>Asha</script>', $list->body());
        self::assertStringContainsString('New', $list->body());

        $detail = $this->router->dispatch(Request::create('GET', '/admin/contact-messages/7'));
        self::assertSame(200, $detail->statusCode());
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $detail->body());
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $detail->body());
        self::assertStringContainsString('Send email reply', $detail->body());
        self::assertStringContainsString('/api/v1/admin/contact-messages/7/reply', $detail->body());
        self::assertStringContainsString('admin-contact-messages.js', $detail->body());
    }

    public function testAdministratorCanSendReplyAndHistoryBecomesReplied(): void
    {
        $this->repository->messages[7] = self::contact();
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $body = 'Yes, we can arrange the airport transfer for your arrival.';

        $response = $this->router->dispatch(Request::create(
            'POST',
            '/api/v1/admin/contact-messages/7/reply',
            headers: [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->tokens->token(),
                'User-Agent' => 'Contact Feature Test',
            ],
            body: json_encode(['reply_body' => $body], JSON_THROW_ON_ERROR),
            remoteAddress: '203.0.113.50',
        ));

        self::assertSame(201, $response->statusCode(), $response->body());
        self::assertStringContainsString('Reply sent to the Guest.', $response->body());
        self::assertSame('asha@example.test', $this->mail->recipient);
        self::assertSame($body, $this->mail->body);
        self::assertSame(ContactMessage::STATUS_REPLIED, $this->repository->messages[7]->status());
        self::assertCount(1, $this->repository->replies);
        self::assertSame(ContactMessageReply::STATUS_SENT, $this->repository->replies[1]->deliveryStatus());

        $detail = $this->router->dispatch(Request::create('GET', '/admin/contact-messages/7'));
        self::assertStringContainsString($body, $detail->body());
        self::assertStringContainsString('Replied', $detail->body());
    }

    public function testInvalidSearchAndIdentifierFailSafely(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $invalidSearch = $this->router->dispatch(Request::create(
            'GET',
            '/admin/contact-messages?q=' . str_repeat('x', 101),
        ));
        self::assertSame(422, $invalidSearch->statusCode());
        self::assertStringContainsString('Contact message search is invalid.', $invalidSearch->body());
        self::assertSame(404, $this->router->dispatch(
            Request::create('GET', '/admin/contact-messages/not-a-number'),
        )->statusCode());
    }

    private static function contact(string $status = ContactMessage::STATUS_NEW): ContactMessage
    {
        return new ContactMessage(
            7,
            '<script>Asha</script>',
            'asha@example.test',
            null,
            'Airport transfer',
            '<img src=x onerror=alert(1)>\nPlease reply.',
            $status,
            new DateTimeImmutable('2026-07-31 12:00:00 UTC'),
            new DateTimeImmutable('2026-07-31 12:00:00 UTC'),
        );
    }

    /** @return array<string, int> */
    private static function loginPolicy(): array
    {
        return [
            'username_min_characters' => 3,
            'username_max_characters' => 50,
            'identifier_max_bytes' => 150,
            'password_max_bytes' => 72,
            'max_failed_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
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

final class AdministratorContactRepositoryFake implements ContactMessageRepository
{
    /** @var array<int, ContactMessage> */
    public array $messages = [];
    /** @var array<int, ContactMessageReply> */
    public array $replies = [];
    public ?string $lastSearch = null;

    public function create(string $name, string $email, ?string $phone, string $subject, string $message, DateTimeImmutable $createdAt): ContactMessage
    {
        throw new \LogicException('Not used.');
    }

    public function findAll(?string $search = null): array
    {
        $this->lastSearch = $search;
        return array_values($this->messages);
    }

    public function findById(int $id): ?ContactMessage { return $this->messages[$id] ?? null; }
    public function findByIdForUpdate(int $id): ?ContactMessage { return $this->findById($id); }

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

final class AdministratorContactAuditFake implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void
    {
    }
}

final class AdministratorContactTransactionFake implements ContactTransaction
{
    private bool $active = false;
    public function begin(): void { $this->active = true; }
    public function commit(): void { $this->active = false; }
    public function rollBack(): void { $this->active = false; }
    public function inProgress(): bool { return $this->active; }
}

final class AdministratorContactMailFake implements ContactReplyMailSender
{
    public ?string $recipient = null;
    public ?string $body = null;

    public function send(ContactMessage $contact, string $replyBody): bool
    {
        $this->recipient = $contact->email();
        $this->body = $replyBody;
        return true;
    }
}

final class AdministratorContactAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(AdministratorLoginRequest $request, string $remoteAddress, string $context = 'http'): AdministratorIdentity
    {
        return new AdministratorIdentity(91);
    }
}
