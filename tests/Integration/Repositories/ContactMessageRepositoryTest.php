<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\ContactMessage;
use App\Repositories\PdoContactMessageRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ContactFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class ContactMessageRepositoryTest extends DatabaseTestCase
{
    private PdoContactMessageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->repository = new PdoContactMessageRepository($this->connection);
    }

    public function testCreateRequiresCallerOwnedTransactionAndStoresNullWorkflowState(): void
    {
        try {
            $this->repository->create(
                'Asha Guest',
                'asha@example.test',
                null,
                'Local tours',
                'Please share information about local guided tours.',
                new DateTimeImmutable('2026-07-31 12:00:00 UTC'),
            );
            self::fail('A contact write outside a transaction was accepted.');
        } catch (DatabaseException $exception) {
            self::assertSame('The contact message write failed.', $exception->getMessage());
        }

        self::assertTrue($this->connection->beginTransaction());
        $contact = $this->repository->create(
            'Asha Guest',
            'asha@example.test',
            null,
            'Local tours',
            'Please share information about local guided tours.',
            new DateTimeImmutable('2026-07-31 12:00:00 UTC'),
        );
        self::assertTrue($this->connection->commit());

        self::assertInstanceOf(ContactMessage::class, $contact);
        self::assertNull($contact->status());
        self::assertNull($contact->phone());
        self::assertSame('asha@example.test', $contact->email());
    }

    public function testListingIsNewestFirstSearchesApprovedFieldsAndRetainsOldMessages(): void
    {
        $oldId = ContactFixture::insert($this->connection, [
            'name' => 'Old Guest',
            'email' => 'old@example.test',
            'subject' => 'Historic inquiry',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]);
        $newId = ContactFixture::insert($this->connection, [
            'name' => 'New Guest',
            'email' => 'new@example.test',
            'subject' => 'Current airport question',
            'created_at' => '2026-07-31 12:00:00',
            'updated_at' => '2026-07-31 12:00:00',
        ]);

        self::assertSame([$newId, $oldId], array_map(
            static fn (ContactMessage $contact): int => $contact->id(),
            $this->repository->findAll(),
        ));
        self::assertSame([$newId], array_map(
            static fn (ContactMessage $contact): int => $contact->id(),
            $this->repository->findAll('airport'),
        ));
        self::assertSame([$oldId], array_map(
            static fn (ContactMessage $contact): int => $contact->id(),
            $this->repository->findAll('old@example.test'),
        ));
        self::assertNotNull($this->repository->findById($oldId));
        self::assertNull($this->repository->findById(0));
        self::assertNull($this->repository->findById(999999));
    }

    public function testLikeWildcardsAreEscapedAndFailuresAreSanitized(): void
    {
        ContactFixture::insert($this->connection, ['subject' => 'A literal 100% request']);
        self::assertCount(1, $this->repository->findAll('100%'));
        self::assertSame([], $this->repository->findAll('100_'));

        $this->connection->exec('DROP TABLE contact_messages');

        try {
            $this->repository->findAll();
            self::fail('Expected a sanitized repository failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The contact message listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }
}
