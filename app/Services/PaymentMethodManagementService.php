<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PaymentMethodException;
use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Requests\PaymentMethodQrUploadRequest;
use App\Requests\PaymentMethodUpsertRequest;
use App\Storage\PaymentMethodQrStorage;
use Throwable;

/**
 * Coordinates Administrator management of configured manual payment methods.
 */
final readonly class PaymentMethodManagementService
{
    public function __construct(
        private PaymentMethodCatalogRepository $repository,
        private ?PaymentMethodQrStorage $qrStorage = null,
    ) {
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

    public function create(
        PaymentMethodUpsertRequest $request,
        ?PaymentMethodQrUploadRequest $qrUpload = null,
    ): PaymentMethod {
        return $this->execute(function () use ($request, $qrUpload): PaymentMethod {
            $qrImagePath = $qrUpload === null ? null : $this->qrStorage()->store($qrUpload);

            try {
                return $this->repository->create(
                    $request->name(),
                    $request->instructions(),
                    $request->accountReference(),
                    $qrImagePath,
                    $request->requiresProof(),
                    $request->isActive(),
                );
            } catch (Throwable $throwable) {
                $this->removeNewQrAfterFailure($qrImagePath);

                throw $throwable;
            }
        });
    }

    public function update(
        int $id,
        PaymentMethodUpsertRequest $request,
        ?PaymentMethodQrUploadRequest $qrUpload = null,
        bool $removeQr = false,
    ): PaymentMethod {
        return $this->execute(function () use ($id, $request, $qrUpload, $removeQr): PaymentMethod {
            $current = $this->repository->findById($id);

            if (!$current instanceof PaymentMethod) {
                throw PaymentMethodException::notFound();
            }

            $newQrImagePath = null;
            $targetQrImagePath = $current->qrImagePath();

            if ($qrUpload !== null) {
                $newQrImagePath = $this->qrStorage()->store($qrUpload);
                $targetQrImagePath = $newQrImagePath;
            } elseif ($removeQr) {
                $targetQrImagePath = null;
            }

            try {
                $method = $this->repository->update(
                    $id,
                    $request->name(),
                    $request->instructions(),
                    $request->accountReference(),
                    $targetQrImagePath,
                    $request->requiresProof(),
                    $request->isActive(),
                );
            } catch (Throwable $throwable) {
                $this->removeNewQrAfterFailure($newQrImagePath);

                throw $throwable;
            }

            if (!$method instanceof PaymentMethod) {
                $this->removeNewQrAfterFailure($newQrImagePath);

                throw PaymentMethodException::notFound();
            }

            if (
                $current->qrImagePath() !== null
                && $current->qrImagePath() !== $targetQrImagePath
            ) {
                try {
                    $this->qrStorage()->remove($current->qrImagePath());
                } catch (Throwable) {
                    // The database now points at the correct image. Old-file cleanup is best effort.
                }
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

    private function qrStorage(): PaymentMethodQrStorage
    {
        if (!$this->qrStorage instanceof PaymentMethodQrStorage) {
            throw PaymentMethodException::failure();
        }

        return $this->qrStorage;
    }

    private function removeNewQrAfterFailure(?string $relativePath): void
    {
        if ($relativePath === null || !$this->qrStorage instanceof PaymentMethodQrStorage) {
            return;
        }

        try {
            $this->qrStorage->remove($relativePath);
        } catch (Throwable) {
            // Preserve the original persistence failure.
        }
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
