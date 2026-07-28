<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use Closure;
use Throwable;

/**
 * Lazily constructs the public booking-submission workflow.
 */
final class BookingSubmissionWorkflowProvider
{
    /** @var Closure(): mixed */
    private readonly Closure $factory;

    private ?BookingSubmissionWorkflow $workflow = null;

    /**
     * @param callable(): mixed $factory
     */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function workflow(): BookingSubmissionWorkflow
    {
        if ($this->workflow !== null) {
            return $this->workflow;
        }

        try {
            $workflow = ($this->factory)();

            if (!$workflow instanceof BookingSubmissionWorkflow) {
                throw BookingException::failure();
            }

            return $this->workflow = $workflow;
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }
}
