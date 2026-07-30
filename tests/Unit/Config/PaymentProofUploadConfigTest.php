<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

final class PaymentProofUploadConfigTest extends TestCase
{
    public function testTrackedPolicyUsesOnlyApprovedPrivateProofSettings(): void
    {
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        $proofs = $configuration['payment_proofs'] ?? null;

        self::assertSame([
            'allowed_mime_extensions' => [
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'application/pdf' => ['pdf'],
            ],
            'maximum_file_size_bytes' => 5_242_880,
            'filesystem_root' => 'storage/private/payment-proofs',
            'database_path_prefix' => 'payment-proofs/',
        ], $proofs);
        self::assertStringNotContainsString('public', $proofs['filesystem_root']);
        self::assertDirectoryExists(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $proofs['filesystem_root']),
        );
    }
}
