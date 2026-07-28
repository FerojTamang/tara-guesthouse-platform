<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PublicPaymentMethodException;
use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodCatalogRepository;
use Throwable;

/**
 * Exposes only active manual payment methods through the public JSON contract.
 */
final readonly class PublicPaymentMethodService
{
    public function __construct(
        private PaymentMethodCatalogRepository $repository,
    ) {
    }

    /**
     * @return list<array{
     *     payment_method_id: int,
     *     name: string,
     *     instructions: ?string,
     *     account_reference: ?string,
     *     qr_image_path: ?string,
     *     requires_proof: bool
     * }>
     */
    public function listActive(): array
    {
        try {
            $result = [];

            foreach ($this->repository->findAllActive() as $method) {
                if (!($method instanceof PaymentMethod) || !$method->isActive()) {
                    continue;
                }

                $result[] = [
                    'payment_method_id' => $method->id(),
                    'name' => $method->name(),
                    'instructions' => $method->instructions(),
                    'account_reference' => $method->accountReference(),
                    'qr_image_path' => $method->qrImagePath(),
                    'requires_proof' => $method->requiresProof(),
                ];
            }

            return $result;
        } catch (Throwable $throwable) {
            throw PublicPaymentMethodException::failure($throwable);
        }
    }
}
