<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ContactException;
use App\Models\AuditLogEntry;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContactMessageRepository;
use App\Requests\AdministratorContactReplyRequest;
use App\Requests\AdministratorContactStatusRequest;
use App\Requests\ContactMessageRequest;
use App\Services\ContactMessageService;
use App\Services\ContactTransaction;
use App\Services\ContactReplyMailSender;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContactMessageServiceTest extends TestCase
{
    private ContactServiceRepositoryFake $contacts;
    private ContactServiceAuditFake $audit;
    private ContactServiceTransactionFake $transaction;
    private ContactServiceMailFake $mail;
    private ContactMessageService $service;

    protected function setUp(): void
    {
        $this->contacts = new ContactServiceRepositoryFake();
        $this->audit = new ContactServiceAuditFake();
        $this->transaction = new ContactServiceTransactionFake();
        $this->mail = new ContactServiceMailFake();
        $this->service = new ContactMessageService(
            $this->contacts,
            $this->audit,
            $this->transaction,
            $this->mail,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-04 00:00:00.321 UTC'),
        );
    }

    public function testSubmissionIsTransactionalAndStartsAsNew(): void
    {
        $contact = $this->service->submit(
            self::request(),
            '203.0.113.10',
            'Example Browser',
        );

        self::assertSame(ContactMessage::STATUS_NEW, $contact->status());
        self::assertSame(1, $this->transaction->commitCalls);
        self::assertCount(1, $this->audit->entries);
        self::assertSame('contact_message_submitted', $this->audit->entries[0]->eventType());
        self::assertSame('00:00:00', $contact->createdAt()->format('H:i:s'));
    }

    public function testSuccessfulReplyIsSentThenRetainedAndMarksInquiryReplied(): void
    {
        $contact = $this->service->submit(self::request(), null, null);
        $reply = $this->service->reply(
            $contact->id(),
            AdministratorContactReplyRequest::fromInput(' We can arrange that for you. '),
            91,
            '203.0.113.11',
            'Administrator Browser',
        );

        self::assertTrue($reply->isSent());
        self::assertSame('asha@example.test', $this->mail->recipient);
        self::assertSame('We can arrange that for you.', $this->mail->body);
        self::assertSame(ContactMessage::STATUS_REPLIED, $this->contacts->messages[$contact->id()]->status());
        self::assertSame(3, $this->transaction->commitCalls);
        self::assertSame(
            ['contact_message_submitted', 'contact_reply_queued', 'contact_reply_sent'],
            array_map(static fn (AuditLogEntry $entry): string => $entry->eventType(), $this->audit->entries),
        );
    }

    public function testFailedDeliveryIsRetainedAndDoesNotMarkInquiryReplied(): void
    {
        $contact = $this->service->submit(self::request(), null, null);
        $this->mail->deliver = false;

        try {
            $this->service->reply(
                $contact->id(),
                AdministratorContactReplyRequest::fromInput('Please try this information.'),
                91,
                null,
                null,
            );
            self::fail('Expected mail delivery failure.');
        } catch (ContactException $exception) {
            self::assertTrue($exception->isDeliveryFailure());
        }

        self::assertSame(ContactMessage::STATUS_IN_PROGRESS, $this->contacts->messages[$contact->id()]->status());
        self::assertSame(ContactMessageReply::STATUS_FAILED, $this->contacts->replies[1]->deliveryStatus());
        self::assertSame('mail_delivery_failed', $this->contacts->replies[1]->failureCode());
    }

    public function testClosedInquiryMustBeReopenedBeforeReply(): void
    {
        $contact = $this->service->submit(self::request(), null, null);
        $closed = $this->service->transitionStatus(
            $contact->id(),
            AdministratorContactStatusRequest::fromInput(ContactMessage::STATUS_CLOSED),
            91,
            null,
            null,
        );
        self::assertTrue($closed->isClosed());

        try {
            $this->service->reply(
                $contact->id(),
                AdministratorContactReplyRequest::fromInput('This should not send.'),
                91,
                null,
                null,
            );
            self::fail('Closed inquiry accepted a reply.');
        } catch (ContactException $exception) {
            self::assertTrue($exception->isConflict());
        }

        self::assertNull($this->mail->recipient);
    }

    public function testInvalidSearchAndMissingRecordFailSafely(): void
    {
        try {
            $this->service->list(str_repeat('x', 101));
            self::fail('Expected invalid search failure.');
        } catch (ContactException $exception) {
            self::assertTrue($exception->isValidationFailure());
        }

        try {
            $this->service->get(999);
            self::fail('Expected missing contact failure.');
        } catch (ContactException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testAuditFailureRollsBackAndIsSanitized(): void
    {
        $this->audit->failure = true;

        try {
            $this->service->submit(self::request(), null, null);
            self::fail('Expected contact submission failure.');
        } catch (ContactException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        self::assertSame(1, $this->transaction->rollbackCalls);
        self::assertSame(0, $this->transaction->commitCalls);
    }

    private static function request(): ContactMessageRequest
    {
        return ContactMessageRequest::fromInput(
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            'General inquiry',
            'Please send more information.',
        );
    }
}

final class ContactServiceRepositoryFake implements ContactMessageRepository
{
    /** @var array<int, ContactMessage> */
    public array $messages = [];
    /** @var array<int, ContactMessageReply> */
    public array $replies = [];
    public ?string $lastSearch = null;

    public function create(
        string $name,
        string $email,
        ?string $phone,
        string $subject,
        string $message,
        DateTimeImmutable $createdAt,
    ): ContactMessage {
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
        $this->lastSearch = $search;
        return array_values($this->messages);
    }

    public function findById(int $id): ?ContactMessage { return $this->messages[$id] ?? null; }
    public function findByIdForUpdate(int $id): ?ContactMessage { return $this->findById($id); }

    public function updateStatus(int $id, string $status, DateTimeImmutable $updatedAt): ContactMessage
    {
        $current = $this->messages[$id];
        return $this->messages[$id] = new ContactMessage(
            $current->id(), $current->name(), $current->email(), $current->phone(),
            $current->subject(), $current->message(), $status, $current->createdAt(), $updatedAt,
        );
    }

    public function createReply(int $contactMessageId, string $replyBody, int $sentBy, DateTimeImmutable $createdAt): ContactMessageReply
    {
        $id = count($this->replies) + 1;
        return $this->replies[$id] = new ContactMessageReply(
            $id, $contactMessageId, $replyBody, $sentBy,
            ContactMessageReply::STATUS_PENDING, null, null, $createdAt, $createdAt,
        );
    }

    public function markReplySent(int $replyId, DateTimeImmutable $sentAt): ContactMessageReply
    {
        $current = $this->replies[$replyId];
        return $this->replies[$replyId] = new ContactMessageReply(
            $current->id(), $current->contactMessageId(), $current->replyBody(), $current->sentBy(),
            ContactMessageReply::STATUS_SENT, $sentAt, null, $current->createdAt(), $sentAt,
        );
    }

    public function markReplyFailed(int $replyId, string $failureCode, DateTimeImmutable $updatedAt): ContactMessageReply
    {
        $current = $this->replies[$replyId];
        return $this->replies[$replyId] = new ContactMessageReply(
            $current->id(), $current->contactMessageId(), $current->replyBody(), $current->sentBy(),
            ContactMessageReply::STATUS_FAILED, null, $failureCode, $current->createdAt(), $updatedAt,
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

final class ContactServiceAuditFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];
    public bool $failure = false;

    public function append(AuditLogEntry $entry): void
    {
        if ($this->failure) {
            throw new RuntimeException('SQLSTATE private audit failure');
        }
        $this->entries[] = $entry;
    }
}

final class ContactServiceTransactionFake implements ContactTransaction
{
    public bool $active = false;
    public int $beginCalls = 0;
    public int $commitCalls = 0;
    public int $rollbackCalls = 0;

    public function begin(): void { $this->beginCalls++; $this->active = true; }
    public function commit(): void { $this->commitCalls++; $this->active = false; }
    public function rollBack(): void { $this->rollbackCalls++; $this->active = false; }
    public function inProgress(): bool { return $this->active; }
}

final class ContactServiceMailFake implements ContactReplyMailSender
{
    public bool $deliver = true;
    public ?string $recipient = null;
    public ?string $body = null;

    public function send(ContactMessage $contact, string $replyBody): bool
    {
        $this->recipient = $contact->email();
        $this->body = $replyBody;
        return $this->deliver;
    }
}
