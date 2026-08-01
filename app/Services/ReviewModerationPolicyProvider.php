<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Environment;
use App\Exceptions\ReviewException;
use App\Models\ReviewModerationPolicy;
use Throwable;

/** Resolves the required environment-approved Administrator review moderation policy. */
final class ReviewModerationPolicyProvider
{
    private ?ReviewModerationPolicy $policy = null;

    public function __construct(
        private readonly string $basePath,
        private readonly ?Environment $environment = null,
    ) {
    }

    public function policy(): ReviewModerationPolicy
    {
        if ($this->policy instanceof ReviewModerationPolicy) {
            return $this->policy;
        }

        try {
            $descriptor = $this->descriptor();
            $environment = $this->environment ?? Environment::load($this->basePath);
            $raw = $environment->literalString($descriptor['note_maximum_environment_key'], '');

            if (
                $raw === ''
                || $raw !== trim($raw)
                || preg_match('/^[1-9][0-9]*$/D', $raw) !== 1
            ) {
                throw ReviewException::failure();
            }

            $maximum = filter_var($raw, FILTER_VALIDATE_INT);

            if (!is_int($maximum) || $maximum <= 0 || $maximum > 65535) {
                throw ReviewException::failure();
            }

            return $this->policy = new ReviewModerationPolicy(
                $maximum,
                $descriptor['rejection_note_required'],
            );
        } catch (ReviewException $exception) {
            throw $exception->isSystemFailure()
                ? $exception
                : ReviewException::failure();
        } catch (Throwable) {
            throw ReviewException::failure();
        }
    }

    /** @return array{note_maximum_environment_key: string, rejection_note_required: bool} */
    private function descriptor(): array
    {
        $path = $this->basePath
            . DIRECTORY_SEPARATOR
            . 'config'
            . DIRECTORY_SEPARATOR
            . 'review_moderation.php';

        if (!is_file($path)) {
            throw ReviewException::failure();
        }

        $configuration = require $path;

        if (
            !is_array($configuration)
            || array_keys($configuration) !== [
                'note_maximum_environment_key',
                'rejection_note_required',
            ]
            || $configuration['note_maximum_environment_key']
                !== 'REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS'
            || $configuration['rejection_note_required'] !== true
        ) {
            throw ReviewException::failure();
        }

        return $configuration;
    }
}
