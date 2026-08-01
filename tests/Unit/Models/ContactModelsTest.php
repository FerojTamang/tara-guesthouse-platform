<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ContactMessage;
use App\Models\ContactRateLimitState;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ContactModelsTest extends TestCase
{
    public function testContactMessageRetainsNullableWorkflowStatus(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-31 12:00:00 UTC');
        $contact = new ContactMessage(
            4,
            'Asha Sharma',
            'asha@example.test',
            null,
            'General inquiry',
            'Please send more information.',
            null,
            $timestamp,
            $timestamp,
        );

        self::assertSame(4, $contact->id());
        self::assertSame('Asha Sharma', $contact->name());
        self::assertNull($contact->phone());
        self::assertNull($contact->status());
        self::assertSame($timestamp, $contact->createdAt());
    }

    public function testApprovedFutureStatusShapeCanBeReadWithoutDefiningVocabulary(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-31 12:00:00 UTC');
        $contact = new ContactMessage(
            5,
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            'General inquiry',
            'Please send more information.',
            'future_approved_status',
            $timestamp,
            $timestamp,
        );

        self::assertSame('future_approved_status', $contact->status());
    }

    public function testInvalidContactAndRateLimitStateAreRejected(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-31 12:00:00 UTC');

        try {
            new ContactMessage(
                0,
                'Asha Sharma',
                'asha@example.test',
                null,
                'General inquiry',
                'Please send more information.',
                null,
                $timestamp,
                $timestamp,
            );
            self::fail('Expected invalid contact data to fail.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        new ContactRateLimitState(true, 1, 900);
    }

    public function testRateLimitStateExposesApprovedValues(): void
    {
        $allowed = new ContactRateLimitState(false, 4, 0);
        $blocked = new ContactRateLimitState(true, 0, 900);

        self::assertFalse($allowed->isBlocked());
        self::assertSame(4, $allowed->remainingAttempts());
        self::assertTrue($blocked->isBlocked());
        self::assertSame(900, $blocked->retryAfterSeconds());
    }
}
