<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ContactException;
use App\Models\AuditLogEntry;
use App\Models\ContactMessage;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContactMessageRepository;
use App\Requests\ContactMessageRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Owns public inquiry submission and read-only Administrator access. */
final readonly class ContactMessageService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private ContactMessageRepository $contacts,
        private AuditLogWriteRepository $auditLogs,
        private ContactTransaction $transaction,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            ),
        );
    }

    public function submit(
        ContactMessageRequest $request,
        ?string $ipAddress,
        ?string $userAgent,
    ): ContactMessage {
        return $this->transact(function () use ($request, $ipAddress, $userAgent): ContactMessage {
            $contact = $this->contacts->create(
                $request->name(),
                $request->email(),
                $request->phone(),
                $request->subject(),
                $request->message(),
                $this->now(),
            );

            $this->auditLogs->append(new AuditLogEntry(
                null,
                'contact_message_submitted',
                'contact_message',
                $contact->id(),
                'Guest submitted a contact message.',
                null,
                $ipAddress,
                $this->normalizeUserAgent($userAgent),
                $this->now(),
            ));

            return $contact;
        });
    }

    /** @return list<ContactMessage> */
    public function list(?string $search = null): array
    {
        try {
            return $this->contacts->findAll($this->normalizeSearch($search));
        } catch (ContactException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContactException::failure($failure);
        }
    }

    public function get(int $id): ContactMessage
    {
        if ($id <= 0) {
            throw ContactException::notFound();
        }

        try {
            $contact = $this->contacts->findById($id);

            if (!$contact instanceof ContactMessage) {
                throw ContactException::notFound();
            }

            return $contact;
        } catch (ContactException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContactException::failure($failure);
        }
    }

    private function normalizeSearch(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }

        $normalized = trim($search);

        if ($normalized === '') {
            return null;
        }

        if (
            !mb_check_encoding($normalized, 'UTF-8')
            || mb_strlen($normalized, 'UTF-8') > 100
            || preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw ContactException::invalidInput('Contact message search is invalid.');
        }

        return $normalized;
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();

        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw ContactException::failure();
        }

        return $value;
    }

    private function normalizeUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        $normalized = trim($userAgent);

        if (
            $normalized === ''
            || !mb_check_encoding($normalized, 'UTF-8')
            || strlen($normalized) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1
        ) {
            return null;
        }

        return $normalized;
    }

    /** @template T @param callable(): T $operation @return T */
    private function transact(callable $operation): mixed
    {
        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $result = $operation();
            $commitAttempted = true;
            $this->transaction->commit();

            return $result;
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof ContactException
                ? $failure
                : ContactException::failure($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw ContactException::failure($stateFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw ContactException::failure($rollbackFailure);
                }
            }

            if ($commitAttempted) {
                throw ContactException::failure($safeFailure);
            }

            throw $safeFailure;
        }
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw ContactException::failure();
            }
        } catch (ContactException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContactException::failure($failure);
        }
    }
}
