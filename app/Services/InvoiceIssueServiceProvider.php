<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AdministratorInvoiceException;
use Closure;
use Throwable;

/**
 * Lazily constructs the transactional invoice-issue workflow.
 */
final class InvoiceIssueServiceProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?InvoiceIssueService $service = null;

    /** @param callable(): mixed $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function service(): InvoiceIssueService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        try {
            $service = ($this->factory)();

            if (!$service instanceof InvoiceIssueService) {
                throw AdministratorInvoiceException::failure();
            }

            return $this->service = $service;
        } catch (AdministratorInvoiceException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw AdministratorInvoiceException::failure($failure);
        }
    }
}
