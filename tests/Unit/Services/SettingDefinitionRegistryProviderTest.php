<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Environment;
use App\Exceptions\CmsException;
use App\Services\SettingDefinitionRegistryProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SettingDefinitionRegistryProviderTest extends TestCase
{
    public function testItResolvesExactApprovedDefinitionsAndCachesRegistry(): void
    {
        $environment = Environment::fromArray([
            'CMS_SETTING_DEFINITIONS_JSON' => json_encode([
                'contact.email' => [
                    'value_type' => 'email',
                    'is_public' => true,
                    'maximum_length' => 254,
                ],
                'homepage.rooms.limit' => [
                    'value_type' => 'integer',
                    'is_public' => false,
                    'maximum_length' => 3,
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
        $provider = new SettingDefinitionRegistryProvider(dirname(__DIR__, 3), $environment);

        $registry = $provider->registry();

        self::assertSame($registry, $provider->registry());
        self::assertSame('email', $registry->get('contact.email')->valueType());
        self::assertTrue($registry->get('contact.email')->isPublic());
        self::assertFalse($registry->get('homepage.rooms.limit')->isPublic());
        self::assertSame(
            ['contact.email', 'homepage.rooms.limit'],
            array_map(static fn ($definition): string => $definition->key(), $registry->all()),
        );
    }

    /** @return iterable<string, array{array<string, mixed>} */
    public static function invalidEnvironments(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty' => [['CMS_SETTING_DEFINITIONS_JSON' => '']];
        yield 'list' => [['CMS_SETTING_DEFINITIONS_JSON' => '[]']];
        yield 'malformed' => [['CMS_SETTING_DEFINITIONS_JSON' => '{']];
        yield 'unknown field' => [['CMS_SETTING_DEFINITIONS_JSON' => '{"contact.email":{"value_type":"email","is_public":true,"maximum_length":254,"extra":1}}']];
        yield 'unsupported type' => [['CMS_SETTING_DEFINITIONS_JSON' => '{"contact.email":{"value_type":"secret","is_public":true,"maximum_length":254}}']];
    }

    #[DataProvider('invalidEnvironments')]
    public function testMalformedOrMissingDefinitionsFailClosed(array $values): void
    {
        $provider = new SettingDefinitionRegistryProvider(dirname(__DIR__, 3), Environment::fromArray($values));

        try {
            $provider->registry();
            self::fail('Expected the CMS setting policy to fail closed.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('CMS operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('CMS_SETTING_DEFINITIONS_JSON', $exception->getMessage());
        }
    }
}
