<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContactMessage;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Throwable;

/** Sends a contact reply while logging only non-sensitive failure metadata. */
final readonly class LoggingContactReplyMailSender implements ContactReplyMailSender
{
    public function __construct(
        private TransactionalMailServiceProvider $services,
        private LoggerInterface $logger,
    ) {
    }

    public function send(
        ContactMessage $contact,
        #[SensitiveParameter] string $replyBody,
    ): bool {
        try {
            $this->services->service()->sendContactReply($contact, $replyBody);
            return true;
        } catch (Throwable) {
            try {
                $this->logger->warning('transactional_mail_delivery_failed', [
                    'mail_event' => 'contact_reply',
                    'contact_message_id' => $contact->id(),
                ]);
            } catch (Throwable) {
                error_log('Transactional mail delivery logging failed.');
            }

            return false;
        }
    }
}
