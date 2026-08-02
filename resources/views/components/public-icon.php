<?php

declare(strict_types=1);

$paths = match ($name) {
    'bed' => '<path d="M3 18v-7.5a2.5 2.5 0 0 1 5 0V12h9a4 4 0 0 1 4 4v2M3 14h18M5 18v3M19 18v3"/>',
    'calendar' => '<path d="M7 3v3M17 3v3M4 8h16M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="m9 15 2 2 4-5"/>',
    'compass' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>',
    'heart' => '<path d="M20.8 5.7a5.4 5.4 0 0 0-7.6 0L12 6.9l-1.2-1.2a5.4 5.4 0 0 0-7.6 7.6L12 22l8.8-8.7a5.4 5.4 0 0 0 0-7.6Z"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
    'leaf' => '<path d="M20.8 3.2C13 3.4 6.5 6.4 4.5 11.5c-1.6 4 .2 7.4 3.8 7.8 6.1.7 10.8-5.7 12.5-16.1Z"/><path d="M4 21c2.2-5.2 6.2-8.8 12-11"/>',
    'pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
    'arrow' => '<path d="M5 12h14M14 7l5 5-5 5"/>',
    'check' => '<path d="m5 12 4 4L19 6"/>',
    'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
    default => '<circle cx="12" cy="12" r="9"/>',
};
?>
<svg
    class="tg-icon<?= isset($className) ? ' ' . $e($className) : '' ?>"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
><?= $paths ?></svg>
