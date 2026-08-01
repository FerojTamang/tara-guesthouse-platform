<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Review invitation</title></head>
<body style="margin:0;background:#f4f5f7;color:#212529;font-family:Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f7;padding:24px 12px;"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#fff;border:1px solid #dee2e6;border-radius:8px;"><tr><td style="padding:28px;">
<p style="margin:0 0 8px;color:#6c757d;font-size:13px;text-transform:uppercase;letter-spacing:.08em;"><?= $e($applicationName) ?></p>
<h1 style="margin:0 0 20px;font-size:24px;">Share your stay experience</h1>
<p>Hello <?= $e($guestName) ?>,</p>
<p>Thank you for staying with us. You may submit one verified-stay review using the secure link below.</p>
<p style="margin:24px 0;"><a href="<?= $e($reviewUrl) ?>" style="display:inline-block;padding:12px 18px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:6px;">Write your review</a></p>
<p style="font-size:14px;color:#6c757d;">This link expires at <?= $e($expiresAt) ?> and can be used once. Do not forward it.</p>
</td></tr></table></td></tr></table>
</body></html>
