<?php
$plain = static fn (mixed $value): string => trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $value));
?>
Hello <?= $plain($guestName) ?>,

Your payment for booking <?= $plain($bookingReference) ?> has been <?= strtolower($plain($statusLabel)) ?>.

Amount: <?= $plain($paymentCurrency) ?> <?= $plain($paymentAmount) ?>
<?php if ($bookingConfirmed): ?>
Your booking is now confirmed.
<?php else: ?>
Your booking has not been confirmed. Please contact the guest house through the official website if you need assistance.
<?php endif; ?>

<?= $plain($applicationName) ?>
<?= $plain($baseUrl) ?>
