<?php
$plain = static fn (mixed $value): string => trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $value));
?>
Hello <?= $plain($guestName) ?>,

Your booking at <?= $plain($applicationName) ?> has been <?= strtolower($plain($statusLabel)) ?>.

Booking reference: <?= $plain($bookingReference) ?>
Check-in: <?= $plain($checkIn) ?>
Check-out: <?= $plain($checkOut) ?>

Please contact the guest house through the official website if you need assistance.

<?= $plain($applicationName) ?>
<?= $plain($baseUrl) ?>
