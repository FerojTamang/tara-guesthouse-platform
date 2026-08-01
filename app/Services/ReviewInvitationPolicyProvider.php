<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Environment;
use App\Exceptions\ReviewException;
use App\Models\ReviewInvitationPolicy;
use Throwable;

/** Resolves the required environment-approved review invitation lifetime. */
final class ReviewInvitationPolicyProvider
{
    private ?ReviewInvitationPolicy $policy = null;

    public function __construct(
        private readonly string $basePath,
        private readonly ?Environment $environment = null,
    ) {
    }

    public function policy(): ReviewInvitationPolicy
    {
        if ($this->policy instanceof ReviewInvitationPolicy) {
            return $this->policy;
        }

        try {
            $descriptor = $this->descriptor();
            $environment = $this->environment ?? Environment::load($this->basePath);
            $raw = $environment->literalString($descriptor['invitation_lifetime_environment_key'], '');

            if ($raw === '' || $raw !== trim($raw) || preg_match('/^[1-9][0-9]*$/D', $raw) !== 1) {
                throw ReviewException::failure();
            }

            $seconds = filter_var($raw, FILTER_VALIDATE_INT);

            if (!is_int($seconds) || $seconds <= 0) {
                throw ReviewException::failure();
            }

            return $this->policy = new ReviewInvitationPolicy($seconds);
        } catch (ReviewException $exception) {
            throw $exception->isSystemFailure() ? $exception : ReviewException::failure($exception);
        } catch (Throwable $failure) {
            throw ReviewException::failure($failure);
        }
    }

    /** @return array{invitation_lifetime_environment_key: string, token_bytes: int, token_hash_algorithm: string} */
    private function descriptor(): array
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'reviews.php';

        if (!is_file($path)) {
            throw ReviewException::failure();
        }

        $configuration = require $path;

        if (
            !is_array($configuration)
            || array_keys($configuration) !== [
                'invitation_lifetime_environment_key',
                'token_bytes',
                'token_hash_algorithm',
            ]
            || $configuration['invitation_lifetime_environment_key'] !== 'REVIEW_INVITATION_LIFETIME_SECONDS'
            || $configuration['token_bytes'] !== 32
            || $configuration['token_hash_algorithm'] !== 'sha256'
        ) {
            throw ReviewException::failure();
        }

        return $configuration;
    }
}
