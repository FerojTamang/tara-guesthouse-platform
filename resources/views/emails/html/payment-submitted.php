<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Payment submitted</title></head>
<body style="margin:0;background:#f4f5f7;color:#212529;font-family:Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f7;padding:24px 12px;"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#fff;border:1px solid #dee2e6;border-radius:8px;"><tr><td style="padding:28px;">
<p style="margin:0 0 8px;color:#6c757d;font-size:13px;text-transform:uppercase;letter-spacing:.08em;"><?= $e($applicationName) ?></p>
<h1 style="margin:0 0 20px;font-size:24px;">Payment submitted</h1>
<p>Hello <?= $e($guestName) ?>,</p>
<p>We received your payment submission for booking <strong><?= $e($bookingReference) ?></strong>.</p>
<p><strong>Amount submitted:</strong> <?= $e($paymentCurrency) ?> <?= $e($paymentAmount) ?></p>
<div style="padding:12px 16px;background:#fff3cd;border:1px solid #ffecb5;border-radius:6px;">The payment is awaiting Administrator verification. This message does not confirm the booking.</div>
<p style="margin:24px 0 0;"><a href="<?= $e($baseUrl) ?>" style="color:#0d6efd;">Visit <?= $e($applicationName) ?></a></p>
</td></tr></table></td></tr></table>
</body></html>
