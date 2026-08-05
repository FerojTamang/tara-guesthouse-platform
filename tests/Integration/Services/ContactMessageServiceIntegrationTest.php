<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Exceptions\ContactException;
use App\Models\AuditLogEntry;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\PdoAuditLogRepository;
use App\Repositories\PdoContactMessageRepository;
use App\Requests\ContactMessageRequest;
use App\Services\ContactMessageService;
use App\Services\NullContactReplyMailSender;
use App\Services\PdoContactTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class ContactMessageServiceIntegrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
    }

    public function testSubmissionPersistsInquiryAndMinimalAuditEvidence(): void
    {
        $service = $this->service(new PdoAuditLogRepository($this->connection));
        $contact = $service->submit(
            ContactMessageRequest::fromInput(
                ' Asha Guest ',
                'ASHA@Example.Test',
                ' +977 9800000000 ',
                ' Airport transfer ',
                " Could you arrange an airport transfer?\r\nThank you. ",
            ),
            '203.0.113.41',
            ' Contact Integration Test ',
        );

        self::assertGreaterThan(0, $contact->id());
        self::assertSame('asha@example.test', $contact->email());
        self::assertSame('new', $contact->status());
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn());

        $audit = $this->connection->query(
            "SELECT user_id, event_type, entity_type, entity_id, metadata_json, ip_address, user_agent "
            . "FROM audit_logs WHERE event_type = 'contact_message_submitted'",
        )->fetch();
        self::assertIsArray($audit);
        self::assertNull($audit['user_id']);
        self::assertSame('contact_message', $audit['entity_type']);
        self::assertSame((string) $contact->id(), (string) $audit['entity_id']);
        self::assertNull($audit['metadata_json']);
        self::assertSame('203.0.113.41', $audit['ip_address']);
        self::assertSame('Contact Integration Test', $audit['user_agent']);
        self::assertStringNotContainsString('asha@example.test', json_encode($audit, JSON_THROW_ON_ERROR));
    }

    public function testAuditFailureRollsBackContactWrite(): void
    {
        $service = $this->service(new ContactFailingAuditRepository());

        try {
            $service->submit(
                ContactMessageRequest::fromInput(
                    'Asha Guest',
                    'asha@example.test',
                    null,
                    'Airport transfer',
                    'Could you arrange an airport transfer for me?',
                ),
                null,
                null,
            );
            self::fail('Expected contact submission rollback.');
        } catch (ContactException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Contact workflow operation failed.', $exception->getMessage());
        }

        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn());
        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn());
    }

    private function service(AuditLogWriteRepository $audit): ContactMessageService
    {
        return new ContactMessageService(
            new PdoContactMessageRepository($this->connection),
            $audit,
            new PdoContactTransaction($this->connection),
            new NullContactReplyMailSender(),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-31 12:00:00 UTC'),
        );
    }
}

final class ContactFailingAuditRepository implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void
    {
        throw new RuntimeException('sensitive audit persistence failure');
    }
}
