<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Payment status</title></head>
<body style="margin:0;background:#f4f5f7;color:#212529;font-family:Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f7;padding:24px 12px;"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#fff;border:1px solid #dee2e6;border-radius:8px;"><tr><td style="padding:28px;">
<p style="margin:0 0 8px;color:#6c757d;font-size:13px;text-transform:uppercase;letter-spacing:.08em;"><?= $e($applicationName) ?></p>
<h1 style="margin:0 0 20px;font-size:24px;">Payment <?= $e(strtolower((string) $statusLabel)) ?></h1>
<p>Hello <?= $e($guestName) ?>,</p>
<p>Your payment for booking <strong><?= $e($bookingReference) ?></strong> has been <strong><?= $e(strtolower((string) $statusLabel)) ?></strong>.</p>
<p><strong>Amount:</strong> <?= $e($paymentCurrency) ?> <?= $e($paymentAmount) ?></p>
<?php if ($bookingConfirmed): ?>
<div style="padding:12px 16px;background:#d1e7dd;border:1px solid #badbcc;border-radius:6px;">Your booking is now confirmed.</div>
<?php else: ?>
<div style="padding:12px 16px;background:#f8d7da;border:1px solid #f5c2c7;border-radius:6px;">Your booking has not been confirmed. Please contact the guest house if you need assistance.</div>
<?php endif; ?>
<p style="margin:24px 0 0;"><a href="<?= $e($baseUrl) ?>" style="color:#0d6efd;">Visit <?= $e($applicationName) ?></a></p>
</td></tr></table></td></tr></table>
</body></html>
