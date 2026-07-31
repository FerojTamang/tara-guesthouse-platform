<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Exceptions\CmsException;
use App\Models\AuditLogEntry;
use App\Models\CmsPage;
use App\Models\SettingDefinition;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\PdoAuditLogRepository;
use App\Repositories\PdoPageRepository;
use App\Repositories\PdoSettingRepository;
use App\Requests\CmsPageContentRequest;
use App\Requests\CmsSettingsUpdateRequest;
use App\Services\AdministratorCmsService;
use App\Services\CmsPageService;
use App\Services\CmsSettingsService;
use App\Services\PdoCmsTransaction;
use App\Services\PlainTextCmsPageContentSanitizer;
use App\Services\SettingDefinitionRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AdministratorCmsServiceIntegrationTest extends DatabaseTestCase
{
    private int $administratorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->administratorId = $this->insertAdministrator();
    }

    public function testSettingsAndPageWorkflowPersistsWithActorAndAuditEvidence(): void
    {
        $service = $this->service(new PdoAuditLogRepository($this->connection));
        $settings = $service->updateSettings(
            CmsSettingsUpdateRequest::fromInput([
                'contact.email' => 'Stay@Example.Test',
                'homepage.rooms.limit' => '4',
            ]),
            $this->administratorId,
            '127.0.0.1',
            'CMS Integration Test',
        );
        self::assertCount(2, $settings);

        $page = $service->createPage(
            CmsPageContentRequest::fromInput('About Tara', 'about-tara', " Welcome  \r\nHome \r\n", 'About Tara', 'About Tara Guest House.'),
            $this->administratorId,
            '127.0.0.1',
            'CMS Integration Test',
        );
        self::assertSame(CmsPage::DRAFT, $page->status());
        self::assertSame("Welcome\nHome", $page->content());

        $page = $service->transitionPageStatus($page->id(), CmsPage::PUBLISHED, $this->administratorId, null, null);
        self::assertTrue($page->isPublished());
        self::assertNotNull($page->publishedAt());

        $events = $this->connection->query(
            "SELECT event_type FROM audit_logs WHERE entity_type IN ('setting', 'page') ORDER BY id",
        )->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame([
            'cms_setting_updated',
            'cms_setting_updated',
            'cms_page_created',
            'cms_page_status_changed',
        ], $events);

        $setting = $this->connection->query(
            "SELECT setting_value, value_type, is_public, updated_by FROM settings WHERE setting_key = 'contact.email'",
        )->fetch();
        self::assertIsArray($setting);
        self::assertSame('stay@example.test', $setting['setting_value']);
        self::assertSame('email', $setting['value_type']);
        self::assertSame('1', (string) $setting['is_public']);
        self::assertSame((string) $this->administratorId, (string) $setting['updated_by']);

        $metadata = (string) $this->connection->query(
            "SELECT metadata_json FROM audit_logs WHERE event_type = 'cms_setting_updated' ORDER BY id LIMIT 1",
        )->fetchColumn();
        self::assertStringContainsString('"setting_key":"contact.email"', $metadata);
        self::assertStringNotContainsString('stay@example.test', $metadata);
    }

    public function testAuditFailureRollsBackSettingAndPageWrites(): void
    {
        $service = $this->service(new AdministratorCmsFailingAuditRepository());

        foreach ([
            fn () => $service->updateSettings(
                CmsSettingsUpdateRequest::fromInput(['contact.email' => 'stay@example.test']),
                $this->administratorId,
                null,
                null,
            ),
            fn () => $service->createPage(
                CmsPageContentRequest::fromInput('About Tara', 'about-tara', 'Safe content', null, null),
                $this->administratorId,
                null,
                null,
            ),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected a safe audit failure.');
            } catch (CmsException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('CMS operation failed.', $exception->getMessage());
            }
        }

        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM settings')->fetchColumn());
        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM pages')->fetchColumn());
    }

    private function service(AuditLogWriteRepository $audit): AdministratorCmsService
    {
        $registry = new SettingDefinitionRegistry([
            new SettingDefinition('contact.email', 'email', true, 254),
            new SettingDefinition('homepage.rooms.limit', 'integer', false, 3),
        ]);

        return new AdministratorCmsService(
            new CmsSettingsService(new PdoSettingRepository($this->connection), $registry),
            new CmsPageService(new PdoPageRepository($this->connection)),
            new PlainTextCmsPageContentSanitizer(5000),
            $audit,
            new PdoCmsTransaction($this->connection),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-31 05:00:00 UTC'),
        );
    }

    private function insertAdministrator(): int
    {
        $this->connection->exec("INSERT INTO roles (role_name, description) VALUES ('Administrator', 'Test role')");
        $roleId = (int) $this->connection->lastInsertId();
        $statement = $this->connection->prepare(
            'INSERT INTO users (role_id, full_name, username, email, password, account_status) '
            . 'VALUES (:role_id, :full_name, :username, :email, :password, :account_status)',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'CMS Administrator',
            'username' => 'cms-admin',
            'email' => 'cms-admin@example.test',
            'password' => password_hash('Test-only-password-123!', PASSWORD_DEFAULT),
            'account_status' => 'active',
        ]);
        return (int) $this->connection->lastInsertId();
    }
}

final class AdministratorCmsFailingAuditRepository implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void
    {
        throw new RuntimeException('sensitive audit persistence failure');
    }
}
