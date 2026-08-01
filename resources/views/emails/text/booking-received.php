<?php
$plain = static fn (mixed $value): string => trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $value));
?>
Hello <?= $plain($guestName) ?>,

We received your booking request at <?= $plain($applicationName) ?>.

Booking reference: <?= $plain($bookingReference) ?>
Check-in: <?= $plain($checkIn) ?>
Check-out: <?= $plain($checkOut) ?>
Total: <?= $plain($currency) ?> <?= $plain($totalAmount) ?>
Payment deadline: <?= $plain($confirmationDeadline) ?>

Your booking remains pending until a qualifying payment is verified. Keep your booking reference private and use the payment instructions shown by the website.

<?= $plain($applicationName) ?>
<?= $plain($baseUrl) ?>
