<?php

declare(strict_types=1);

namespace App\Storage;

use App\Requests\PaymentMethodQrUploadRequest;

/**
 * Stores and removes public payment-method QR images.
 */
interface PaymentMethodQrStorage
{
    public function store(PaymentMethodQrUploadRequest $request): string;

    public function remove(string $relativePath): void;
}
