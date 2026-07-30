<?php

declare(strict_types=1);

namespace App\Storage;

use App\Core\UploadedFile;

/**
 * Stores validated confidential payment-proof bytes outside the public tree.
 */
interface PaymentProofStorage
{
    public function store(UploadedFile $file, string $detectedMediaType): StoredPaymentProof;

    public function remove(string $relativePath): void;
}
