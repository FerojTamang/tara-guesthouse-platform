<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PaymentMethodException;
use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Requests\PaymentMethodUpsertRequest;
use Throwable;

/**
 * Coordinates Administrator management of configured manual payment methods.
 */
final readonly class PaymentMethodManagementService
{
    public function __construct(private PaymentMethodCatalogRepository $repository)
    {
    }

    /** @return list<PaymentMethod> */
    public function listAll(): array
    {
        return $this->execute(fn (): array => $this->repository->findAll());
    }

    public function get(int $id): PaymentMethod
    {
        return $this->execute(function () use ($id): PaymentMethod {
            $method = $this->repository->findById($id);

            if (!$method instanceof PaymentMethod) {
                throw PaymentMethodException::notFound();
            }

            return $method;
        });
    }

    public function create(PaymentMethodUpsertRequest $request): PaymentMethod
    {
        return $this->execute(fn (): PaymentMethod => $this->repository->create(
            $request->name(),
            $request->instructions(),
            $request->accountReference(),
            null,
            $request->requiresProof(),
            $request->isActive(),
        ));
    }

    public function update(int $id, PaymentMethodUpsertRequest $request): PaymentMethod
    {
        return $this->execute(function () use ($id, $request): PaymentMethod {
            $current = $this->repository->findById($id);

            if (!$current instanceof PaymentMethod) {
                throw PaymentMethodException::notFound();
            }

            $method = $this->repository->update(
                $id,
                $request->name(),
                $request->instructions(),
                $request->accountReference(),
                $current->qrImagePath(),
                $request->requiresProof(),
                $request->isActive(),
            );

            if (!$method instanceof PaymentMethod) {
                throw PaymentMethodException::notFound();
            }

            return $method;
        });
    }

    public function activate(int $id): PaymentMethod
    {
        return $this->setActive($id, true);
    }

    public function deactivate(int $id): PaymentMethod
    {
        return $this->setActive($id, false);
    }

    private function setActive(int $id, bool $active): PaymentMethod
    {
        return $this->execute(function () use ($id, $active): PaymentMethod {
            $method = $this->repository->setActive($id, $active);

            if (!$method instanceof PaymentMethod) {
                throw PaymentMethodException::notFound();
            }

            return $method;
        });
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (PaymentMethodException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw PaymentMethodException::failure($throwable);
        }
    }
}
