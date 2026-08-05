<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\CmsException;
use App\Models\CmsPage;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Validates one exact CMS publication transition target. */
final readonly class CmsPageStatusRequest
{
    private function __construct(
        private string $status,
        private ?DateTimeImmutable $publishAt,
    ) {
    }

    public static function fromInput(
        mixed $status,
        mixed $publishAt = null,
        ?DateTimeZone $timezone = null,
    ): self {
        if (!is_string($status) || !in_array($status, CmsPage::PUBLICATION_STATES, true)) {
            throw CmsException::invalidInput('The page status is invalid.');
        }

        $date = null;
        if ($status === CmsPage::SCHEDULED) {
            if (
                !is_string($publishAt)
                || $publishAt === ''
                || strlen($publishAt) > 40
                || str_contains($publishAt, "\0")
            ) {
                throw CmsException::invalidInput('A scheduled page requires a valid publication date and time.');
            }

            try {
                $timezone ??= new DateTimeZone('UTC');
                $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $publishAt, $timezone);
                $errors = DateTimeImmutable::getLastErrors();
                if (
                    !$date instanceof DateTimeImmutable
                    || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
                    || $date->format('Y-m-d\\TH:i') !== $publishAt
                ) {
                    throw CmsException::invalidInput('The scheduled publication date and time is invalid.');
                }
                $date = $date->setTimezone(new DateTimeZone('UTC'));
            } catch (CmsException $exception) {
                throw $exception;
            } catch (Throwable) {
                throw CmsException::invalidInput('The scheduled publication date and time is invalid.');
            }
        } elseif ($publishAt !== null && $publishAt !== '') {
            throw CmsException::invalidInput('A publication date is accepted only for scheduled pages.');
        }

        return new self($status, $date);
    }

    public function status(): string { return $this->status; }
    public function publishAt(): ?DateTimeImmutable { return $this->publishAt; }
}
