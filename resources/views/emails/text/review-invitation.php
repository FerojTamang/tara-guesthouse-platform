<?php
$plain = static fn (mixed $value): string => trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $value));
?>
Hello <?= $plain($guestName) ?>,

Thank you for staying at <?= $plain($applicationName) ?>. You may submit one verified-stay review using the secure link below:

<?= $plain($reviewUrl) ?>

This link expires at <?= $plain($expiresAt) ?> and can be used once. Do not forward it.

<?= $plain($applicationName) ?>
<?= $plain($baseUrl) ?>
