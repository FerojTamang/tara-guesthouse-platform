<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ContactException;
use App\Models\AuditLogEntry;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContactMessageRepository;
use App\Requests\AdministratorContactReplyRequest;
use App\Requests\AdministratorContactStatusRequest;
use App\Requests\ContactMessageRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Owns public inquiry submission and controlled Administrator replies. */
final readonly class ContactMessageService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private ContactMessageRepository $contacts,
        private AuditLogWriteRepository $auditLogs,
        private ContactTransaction $transaction,
        private ContactReplyMailSender $mail,
        ?callable $clock = null,
        private ?AdministratorEventNotifier $notifications = null,
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
        $contact = $this->transact(function () use ($request, $ipAddress, $userAgent): ContactMessage {
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

        $this->deliverAdministratorNotification($contact);

        return $contact;
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

    /** @return list<ContactMessageReply> */
    public function replies(int $contactMessageId): array
    {
        if ($contactMessageId <= 0) {
            throw ContactException::notFound();
        }

        try {
            return $this->contacts->findRepliesByContactMessageId($contactMessageId);
        } catch (Throwable $failure) {
            throw ContactException::failure($failure);
        }
    }

    public function reply(
        int $contactMessageId,
        AdministratorContactReplyRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): ContactMessageReply {
        if ($contactMessageId <= 0 || $administratorId <= 0) {
            throw ContactException::notFound();
        }

        /** @var array{contact: ContactMessage, reply: ContactMessageReply} $queued */
        $queued = $this->transact(function () use (
            $contactMessageId,
            $request,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): array {
            $contact = $this->contacts->findByIdForUpdate($contactMessageId);

            if (!$contact instanceof ContactMessage) {
                throw ContactException::notFound();
            }

            if ($contact->isClosed()) {
                throw ContactException::conflict(
                    'Reopen this contact message before sending another reply.',
                );
            }

            $now = $this->now();
            $reply = $this->contacts->createReply(
                $contact->id(),
                $request->replyBody(),
                $administratorId,
                $now,
            );
            $contact = $this->contacts->updateStatus(
                $contact->id(),
                ContactMessage::STATUS_IN_PROGRESS,
                $now,
            );

            $this->auditLogs->append(new AuditLogEntry(
                $administratorId,
                'contact_reply_queued',
                'contact_message',
                $contact->id(),
                'Administrator queued a contact-message email reply.',
                sprintf('{"reply_id":%d}', $reply->id()),
                $ipAddress,
                $this->normalizeUserAgent($userAgent),
                $now,
            ));

            return ['contact' => $contact, 'reply' => $reply];
        });

        $delivered = $this->mail->send(
            $queued['contact'],
            $request->replyBody(),
        );

        $reply = $this->transact(function () use (
            $queued,
            $delivered,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): ContactMessageReply {
            $contact = $this->contacts->findByIdForUpdate($queued['contact']->id());

            if (!$contact instanceof ContactMessage) {
                throw ContactException::notFound();
            }

            $now = $this->now();

            if ($delivered) {
                $reply = $this->contacts->markReplySent($queued['reply']->id(), $now);

                if (!$contact->isClosed()) {
                    $this->contacts->updateStatus(
                        $contact->id(),
                        ContactMessage::STATUS_REPLIED,
                        $now,
                    );
                }

                $event = 'contact_reply_sent';
                $description = 'Administrator sent a contact-message email reply.';
            } else {
                $reply = $this->contacts->markReplyFailed(
                    $queued['reply']->id(),
                    'mail_delivery_failed',
                    $now,
                );
                $event = 'contact_reply_failed';
                $description = 'Contact-message email reply delivery failed.';
            }

            $this->auditLogs->append(new AuditLogEntry(
                $administratorId,
                $event,
                'contact_message',
                $contact->id(),
                $description,
                sprintf('{"reply_id":%d}', $reply->id()),
                $ipAddress,
                $this->normalizeUserAgent($userAgent),
                $now,
            ));

            return $reply;
        });

        if (!$delivered) {
            throw ContactException::delivery();
        }

        return $reply;
    }

    public function transitionStatus(
        int $contactMessageId,
        AdministratorContactStatusRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): ContactMessage {
        if ($contactMessageId <= 0 || $administratorId <= 0) {
            throw ContactException::notFound();
        }

        return $this->transact(function () use (
            $contactMessageId,
            $request,
            $administratorId,
            $ipAddress,
            $userAgent,
        ): ContactMessage {
            $contact = $this->contacts->findByIdForUpdate($contactMessageId);

            if (!$contact instanceof ContactMessage) {
                throw ContactException::notFound();
            }

            $target = $request->targetStatus();
            $allowed = $target === ContactMessage::STATUS_CLOSED
                ? !$contact->isClosed()
                : $contact->isClosed();

            if (!$allowed) {
                throw ContactException::conflict(
                    'This contact message can no longer use that workflow action.',
                );
            }

            $now = $this->now();
            $updated = $this->contacts->updateStatus($contact->id(), $target, $now);
            $this->auditLogs->append(new AuditLogEntry(
                $administratorId,
                $target === ContactMessage::STATUS_CLOSED
                    ? 'contact_message_closed'
                    : 'contact_message_reopened',
                'contact_message',
                $contact->id(),
                $target === ContactMessage::STATUS_CLOSED
                    ? 'Administrator closed a contact message.'
                    : 'Administrator reopened a contact message.',
                null,
                $ipAddress,
                $this->normalizeUserAgent($userAgent),
                $now,
            ));

            return $updated;
        });
    }

    private function deliverAdministratorNotification(ContactMessage $contact): void
    {
        if (!$this->notifications instanceof AdministratorEventNotifier) {
            return;
        }

        try {
            $this->notifications->contactMessageReceived($contact);
        } catch (Throwable) {
            error_log('Contact post-commit Administrator notification delivery failed.');
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

        return $value->setTime(
            (int) $value->format('H'),
            (int) $value->format('i'),
            (int) $value->format('s'),
        );
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
