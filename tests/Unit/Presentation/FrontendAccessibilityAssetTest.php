<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation;

use PHPUnit\Framework\TestCase;

final class FrontendAccessibilityAssetTest extends TestCase
{
    public function testRoomDetailCalendarKeepsAccessibleStatesAcrossResponsiveBreakpoints(): void
    {
        $script = $this->asset('public/assets/js/pages/room-detail.js');

        self::assertStringContainsString(
            "['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']",
            $script,
        );
        self::assertStringContainsString("accessibleLabel.push('unavailable')", $script);
        self::assertStringContainsString("accessibleLabel.push('selected check-in')", $script);
        self::assertStringContainsString("button.setAttribute('aria-pressed', 'true')", $script);
        self::assertStringContainsString(
            "const monthBreakpoint = window.matchMedia('(max-width: 991.98px)')",
            $script,
        );
        self::assertStringContainsString("monthBreakpoint.addEventListener('change', reloadForBreakpoint)", $script);
        self::assertStringContainsString("monthBreakpoint.addListener(reloadForBreakpoint)", $script);
    }

    public function testCmsBlockInsertionRespectsReducedMotionPreference(): void
    {
        $script = $this->asset('public/assets/js/admin-cms.js');

        self::assertStringContainsString(
            "window.matchMedia('(prefers-reduced-motion: reduce)').matches",
            $script,
        );
        self::assertStringContainsString("behavior: reduceMotion ? 'auto' : 'smooth'", $script);
    }

    private function asset(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $contents = file_get_contents($path);

        self::assertIsString($contents);

        return $contents;
    }
}
