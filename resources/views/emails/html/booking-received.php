<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Booking received</title></head>
<body style="margin:0;background:#f4f5f7;color:#212529;font-family:Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f7;padding:24px 12px;"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #dee2e6;border-radius:8px;"><tr><td style="padding:28px;">
<p style="margin:0 0 8px;color:#6c757d;font-size:13px;text-transform:uppercase;letter-spacing:.08em;"><?= $e($applicationName) ?></p>
<h1 style="margin:0 0 20px;font-size:24px;">Booking received</h1>
<p>Hello <?= $e($guestName) ?>,</p>
<p>We received your booking request. It remains pending until a qualifying payment is verified.</p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse;background:#f8f9fa;border-radius:6px;">
<tr><td><strong>Booking reference</strong></td><td><?= $e($bookingReference) ?></td></tr>
<tr><td><strong>Check-in</strong></td><td><?= $e($checkIn) ?></td></tr>
<tr><td><strong>Check-out</strong></td><td><?= $e($checkOut) ?></td></tr>
<tr><td><strong>Total</strong></td><td><?= $e($currency) ?> <?= $e($totalAmount) ?></td></tr>
<tr><td><strong>Payment deadline</strong></td><td><?= $e($confirmationDeadline) ?></td></tr>
</table>
<p style="margin-top:20px;">Keep your booking reference private and use the payment instructions shown by the website.</p>
<p style="margin:24px 0 0;"><a href="<?= $e($baseUrl) ?>" style="color:#0d6efd;">Visit <?= $e($applicationName) ?></a></p>
</td></tr></table>
</td></tr></table>
</body>
</html>
