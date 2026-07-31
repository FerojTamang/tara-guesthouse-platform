<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use Closure;
use Throwable;

/** Lazily constructs the public CMS/homepage service. */
final class PublicCmsServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?PublicCmsService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): PublicCmsService
    {
        if ($this->service instanceof PublicCmsService) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof PublicCmsService) {
                throw CmsException::failure();
            }

            return $this->service = $service;
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }
}
