<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AuditLogEntry;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuditLogEntryTest extends TestCase
{
    public function testValidEntryRetainsOnlyApprovedAppendOnlyFields(): void
    {
        $entry = new AuditLogEntry(7, 'payment_verified', 'payment', 11, 'Administrator verified a payment.', '{"from_status":"submitted","to_status":"verified"}', '203.0.113.7', 'Audit Test Agent', new DateTimeImmutable('2026-08-01 12:00:00 UTC'));
        self::assertSame(7, $entry->userId());
        self::assertSame('payment_verified', $entry->eventType());
        self::assertSame('payment', $entry->entityType());
        self::assertSame(11, $entry->entityId());
        self::assertSame('{"from_status":"submitted","to_status":"verified"}', $entry->metadataJson());
        self::assertSame('2026-08-01 12:00:00', $entry->createdAt()->format('Y-m-d H:i:s'));
    }

    #[DataProvider('invalidEntries')]
    public function testInvalidIdentifiersNamesMetadataAndNonUtcTimeAreRejected(
        ?int $userId, string $event, string $entity, ?int $entityId, ?string $metadata, DateTimeImmutable $createdAt,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        new AuditLogEntry($userId, $event, $entity, $entityId, 'Valid description.', $metadata, null, null, $createdAt);
    }

    /** @return iterable<string, array{?int,string,string,?int,?string,DateTimeImmutable}> */
    public static function invalidEntries(): iterable
    {
        $utc = new DateTimeImmutable('2026-08-01 12:00:00 UTC');
        yield 'invalid actor' => [0, 'payment_verified', 'payment', 1, null, $utc];
        yield 'invalid event' => [1, 'Payment Verified', 'payment', 1, null, $utc];
        yield 'invalid entity' => [1, 'payment_verified', 'Payment', 1, null, $utc];
        yield 'invalid subject' => [1, 'payment_verified', 'payment', 0, null, $utc];
        yield 'list metadata' => [1, 'payment_verified', 'payment', 1, '[1,2]', $utc];
        yield 'malformed metadata' => [1, 'payment_verified', 'payment', 1, '{invalid', $utc];
        yield 'non utc time' => [1, 'payment_verified', 'payment', 1, null, new DateTimeImmutable('2026-08-01 12:00:00 +05:45')];
    }
}
