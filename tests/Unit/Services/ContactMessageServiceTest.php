<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ContactException;
use App\Models\AuditLogEntry;
use App\Models\ContactMessage;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContactMessageRepository;
use App\Requests\ContactMessageRequest;
use App\Services\ContactMessageService;
use App\Services\ContactTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContactMessageServiceTest extends TestCase
{
    private ContactServiceRepositoryFake $contacts;
    private ContactServiceAuditFake $audit;
    private ContactServiceTransactionFake $transaction;
    private ContactMessageService $service;

    protected function setUp(): void
    {
        $this->contacts = new ContactServiceRepositoryFake();
        $this->audit = new ContactServiceAuditFake();
        $this->transaction = new ContactServiceTransactionFake();
        $this->service = new ContactMessageService(
            $this->contacts,
            $this->audit,
            $this->transaction,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-31 12:00:00 UTC'),
        );
    }

    public function testSubmissionIsTransactionalAndAuditedWithoutGuestPiiMetadata(): void
    {
        $contact = $this->service->submit(
            self::request(),
            '203.0.113.10',
            'Example Browser',
        );

        self::assertSame(1, $contact->id());
        self::assertNull($contact->status());
        self::assertSame(1, $this->transaction->beginCalls);
        self::assertSame(1, $this->transaction->commitCalls);
        self::assertSame(0, $this->transaction->rollbackCalls);
        self::assertCount(1, $this->audit->entries);

        $entry = $this->audit->entries[0];
        self::assertNull($entry->userId());
        self::assertSame('contact_message_submitted', $entry->eventType());
        self::assertSame('contact_message', $entry->entityType());
        self::assertSame(1, $entry->entityId());
        self::assertNull($entry->metadataJson());
        self::assertSame('203.0.113.10', $entry->ipAddress());
        self::assertStringNotContainsString('asha@example.test', $entry->description());
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

    public function testReadsNormalizeSearchAndRetainOldMessages(): void
    {
        $this->contacts->messages[8] = self::contact(8, '2020-01-01 00:00:00 UTC');

        $listed = $this->service->list('  Asha  ');
        $loaded = $this->service->get(8);

        self::assertSame('Asha', $this->contacts->lastSearch);
        self::assertCount(1, $listed);
        self::assertSame(8, $loaded->id());
        self::assertSame('2020-01-01', $loaded->createdAt()->format('Y-m-d'));
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

    private static function contact(int $id, string $time): ContactMessage
    {
        $timestamp = new DateTimeImmutable($time);
        return new ContactMessage(
            $id,
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            'General inquiry',
            'Please send more information.',
            null,
            $timestamp,
            $timestamp,
        );
    }
}

final class ContactServiceRepositoryFake implements ContactMessageRepository
{
    /** @var array<int, ContactMessage> */
    public array $messages = [];
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
            null,
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

    public function findById(int $id): ?ContactMessage
    {
        return $this->messages[$id] ?? null;
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

    public function begin(): void
    {
        $this->beginCalls++;
        $this->active = true;
    }

    public function commit(): void
    {
        $this->commitCalls++;
        $this->active = false;
    }

    public function rollBack(): void
    {
        $this->rollbackCalls++;
        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}
