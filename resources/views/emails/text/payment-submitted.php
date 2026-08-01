<?php
$plain = static fn (mixed $value): string => trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $value));
?>
Hello <?= $plain($guestName) ?>,

We received your payment submission for booking <?= $plain($bookingReference) ?>.

Amount submitted: <?= $plain($paymentCurrency) ?> <?= $plain($paymentAmount) ?>
Booking total: <?= $plain($currency) ?> <?= $plain($totalAmount) ?>

The payment is awaiting Administrator verification. This message does not confirm the booking.

<?= $plain($applicationName) ?>
<?= $plain($baseUrl) ?>
