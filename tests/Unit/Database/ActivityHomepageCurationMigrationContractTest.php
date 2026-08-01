<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Core\Migration;
use PHPUnit\Framework\TestCase;

final class ActivityHomepageCurationMigrationContractTest extends TestCase
{
    private string $source;
    private string $path;

    protected function setUp(): void
    {
        $this->path = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'database'
            . DIRECTORY_SEPARATOR . 'migrations'
            . DIRECTORY_SEPARATOR . '20260802000100_add_activity_catalogue_homepage_curation.php';
        $source = file_get_contents($this->path);
        self::assertIsString($source);
        $this->source = $source;
    }

    public function testMigrationAddsOnlyTheApprovedFieldsAndOrderedIndexes(): void
    {
        preg_match_all('/ADD COLUMN\s+([a-z_]+)/i', $this->source, $columns);
        self::assertSame([
            'summary', 'featured_image_path', 'price_unit', 'duration_label', 'location',
            'is_homepage_featured', 'homepage_sort_order',
            'is_homepage_featured', 'homepage_sort_order',
        ], $columns[1]);

        preg_match_all('/ADD INDEX\s+([a-z_]+)\s*\(([^)]+)\)/is', $this->source, $matches, PREG_SET_ORDER);
        $indexes = [];
        foreach ($matches as $match) {
            $indexes[$match[1]] = array_map('trim', explode(',', $match[2]));
        }

        self::assertSame([
            'idx_activities_homepage_order' => [
                'is_active', 'is_published', 'is_homepage_featured',
                'homepage_sort_order', 'sort_order', 'id',
            ],
            'idx_gallery_homepage_order' => [
                'is_active', 'is_published', 'is_homepage_featured',
                'homepage_sort_order', 'sort_order', 'id',
            ],
        ], $indexes);
    }

    public function testMigrationBackfillsWithoutDeletingRecordsAndHasAnExactRollback(): void
    {
        self::assertSame(3, preg_match_all('/\bUPDATE\s+(?:activities|gallery)\b/i', $this->source));
        self::assertDoesNotMatchRegularExpression('/\b(?:DELETE|TRUNCATE|DROP TABLE)\b/i', $this->source);

        preg_match_all('/DROP COLUMN\s+([a-z_]+)/i', $this->source, $columns);
        self::assertSame([
            'homepage_sort_order', 'is_homepage_featured',
            'homepage_sort_order', 'is_homepage_featured', 'location', 'duration_label',
            'price_unit', 'featured_image_path', 'summary',
        ], $columns[1]);

        self::assertInstanceOf(Migration::class, require $this->path);
    }
}
