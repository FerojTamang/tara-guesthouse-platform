<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($applicationName) ?> reply</title>
</head>
<body style="margin:0;background:#f5f1e8;color:#173f34;font-family:Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 12px;background:#f5f1e8;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dfe8df;">
                <tr>
                    <td style="padding:24px 28px;background:#0f4a3b;color:#ffffff;">
                        <strong style="font-size:20px;"><?= $e($applicationName) ?></strong>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px 28px;line-height:1.65;">
                        <p style="margin:0 0 18px;">Hello <?= $e($guestName) ?>,</p>
                        <p style="margin:0 0 18px;color:#52645d;">
                            Thank you for contacting us regarding
                            <strong><?= $e($originalSubject) ?></strong>.
                        </p>
                        <div style="padding:20px;border-radius:12px;background:#f7faf7;border-left:4px solid #2c7a5c;white-space:pre-line;"><?= $e($replyBody) ?></div>
                        <p style="margin:24px 0 0;">Kind regards,<br><strong><?= $e($applicationName) ?></strong></p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
