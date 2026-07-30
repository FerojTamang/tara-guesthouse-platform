<?php

declare(strict_types=1);

namespace App\Storage;

/**
 * Reads one confidential proof from approved private storage.
 */
interface PaymentProofReader
{
    public function read(string $relativePath): ?RetrievedPaymentProof;
}
