<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use Closure;
use Throwable;

/** Defers fail-closed page-content policy resolution until page editing needs it. */
final class LazyCmsPageContentSanitizer implements CmsPageContentSanitizer
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;
    private ?CmsPageContentSanitizer $sanitizer = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function format(): string
    {
        return $this->resolved()->format();
    }

    public function maximumLength(): int
    {
        return $this->resolved()->maximumLength();
    }

    public function sanitize(string $content): string
    {
        return $this->resolved()->sanitize($content);
    }

    private function resolved(): CmsPageContentSanitizer
    {
        if ($this->sanitizer instanceof CmsPageContentSanitizer) {
            return $this->sanitizer;
        }

        try {
            $sanitizer = ($this->factory)();

            if (!$sanitizer instanceof CmsPageContentSanitizer) {
                throw CmsException::failure();
            }

            return $this->sanitizer = $sanitizer;
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }
}
