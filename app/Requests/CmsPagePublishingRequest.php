<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\CmsException;
use App\Models\PageContentProfile;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Validates optional professional publishing and social metadata for one CMS page. */
final readonly class CmsPagePublishingRequest
{
    private function __construct(
        private ?string $noticeStyle,
        private ?DateTimeImmutable $publishAt,
        private ?DateTimeImmutable $expiresAt,
        private bool $featured,
        private bool $pinned,
        private ?int $featuredMediaId,
        private ?string $socialTitle,
        private ?string $socialDescription,
        private ?int $socialMediaId,
        private bool $seoNoindex,
    ) {
    }

    public static function fromInput(
        mixed $noticeStyle,
        mixed $publishAt,
        mixed $expiresAt,
        mixed $featured,
        mixed $pinned,
        mixed $featuredMediaId,
        mixed $socialTitle,
        mixed $socialDescription,
        mixed $socialMediaId,
        mixed $seoNoindex,
        DateTimeZone $timezone,
    ): self {
        $noticeStyle = self::nullableText($noticeStyle, 20, 'The notice style is invalid.');
        if ($noticeStyle !== null && !in_array($noticeStyle, PageContentProfile::NOTICE_STYLES, true)) {
            throw CmsException::invalidInput('The notice style is invalid.');
        }

        $publishAt = self::dateTime($publishAt, $timezone, 'The publication date and time is invalid.');
        $expiresAt = self::dateTime($expiresAt, $timezone, 'The expiry date and time is invalid.');
        if ($expiresAt instanceof DateTimeImmutable && !$publishAt instanceof DateTimeImmutable) {
            throw CmsException::invalidInput('An expiry date requires a publication date.');
        }
        if ($publishAt instanceof DateTimeImmutable && $expiresAt instanceof DateTimeImmutable && $expiresAt <= $publishAt) {
            throw CmsException::invalidInput('The expiry date must be later than the publication date.');
        }

        if (!is_bool($featured) || !is_bool($pinned) || !is_bool($seoNoindex)) {
            throw CmsException::invalidInput('The publishing options are invalid.');
        }

        $featuredMediaId = self::nullableIdentifier($featuredMediaId, 'The featured image is invalid.');
        $socialMediaId = self::nullableIdentifier($socialMediaId, 'The social image is invalid.');
        $socialTitle = self::nullableText($socialTitle, 255, 'The social title is invalid.');
        $socialDescription = self::nullableText($socialDescription, 500, 'The social description is invalid.');

        return new self(
            $noticeStyle,
            $publishAt,
            $expiresAt,
            $featured,
            $pinned,
            $featuredMediaId,
            $socialTitle,
            $socialDescription,
            $socialMediaId,
            $seoNoindex,
        );
    }

    public function noticeStyle(): ?string { return $this->noticeStyle; }
    public function publishAt(): ?DateTimeImmutable { return $this->publishAt; }
    public function expiresAt(): ?DateTimeImmutable { return $this->expiresAt; }
    public function isFeatured(): bool { return $this->featured; }
    public function isPinned(): bool { return $this->pinned; }
    public function featuredMediaId(): ?int { return $this->featuredMediaId; }
    public function socialTitle(): ?string { return $this->socialTitle; }
    public function socialDescription(): ?string { return $this->socialDescription; }
    public function socialMediaId(): ?int { return $this->socialMediaId; }
    public function seoNoindex(): bool { return $this->seoNoindex; }

    private static function nullableText(mixed $value, int $maximumLength, string $message): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw CmsException::invalidInput($message);
        }
        $value = trim($value);
        if (
            $value === ''
            || !mb_check_encoding($value, 'UTF-8')
            || str_contains($value, "\0")
            || mb_strlen($value, 'UTF-8') > $maximumLength
        ) {
            throw CmsException::invalidInput($message);
        }
        return $value;
    }

    private static function nullableIdentifier(mixed $value, string $message): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_int($value) || $value <= 0) {
            throw CmsException::invalidInput($message);
        }
        return $value;
    }

    private static function dateTime(mixed $value, DateTimeZone $timezone, string $message): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || strlen($value) > 40 || str_contains($value, "\0")) {
            throw CmsException::invalidInput($message);
        }

        try {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, $timezone);
            $errors = DateTimeImmutable::getLastErrors();
            if (
                !$date instanceof DateTimeImmutable
                || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
                || $date->format('Y-m-d\\TH:i') !== $value
            ) {
                throw CmsException::invalidInput($message);
            }

            return $date->setTimezone(new DateTimeZone('UTC'));
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::invalidInput($message);
        }
    }
}
