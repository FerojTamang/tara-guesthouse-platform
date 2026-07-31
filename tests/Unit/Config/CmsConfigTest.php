<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Models\SiteSetting;
use PHPUnit\Framework\TestCase;

final class CmsConfigTest extends TestCase
{
    public function testCmsSettingDefinitionsAreEnvironmentBackedAndFailClosedByDefault(): void
    {
        $configuration = require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'cms.php';

        self::assertSame(
            ['definitions_environment_key', 'supported_value_types'],
            array_keys($configuration['settings']),
        );
        self::assertSame('CMS_SETTING_DEFINITIONS_JSON', $configuration['settings']['definitions_environment_key']);
        self::assertSame(SiteSetting::VALUE_TYPES, $configuration['settings']['supported_value_types']);
        self::assertStringContainsString('CMS_SETTING_DEFINITIONS_JSON=', (string) file_get_contents(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env.example',
        ));
    }
}
