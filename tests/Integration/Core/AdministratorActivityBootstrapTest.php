<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Authentication\AdministratorIdentity;
use App\Core\Bootstrap;
use App\Core\MigrationRunner;
use App\Core\Request;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Support\ActivityFixture;
use Tests\Support\CmsFixture;
use Tests\Support\DatabaseTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorActivityBootstrapTest extends DatabaseTestCase
{
    private bool $handlersRegistered = false;

    private ?string $sessionFile = null;

    protected function tearDown(): void
    {
        if ($this->handlersRegistered) {
            restore_error_handler();
            restore_exception_handler();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        session_id('');

        if ($this->sessionFile !== null && is_file($this->sessionFile)) {
            unlink($this->sessionFile);
        }

        parent::tearDown();
    }

    public function testAuthenticatedActivityListingBuildsThroughBootstrapWithNullableLegacyFields(): void
    {
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $administratorId = CmsFixture::insertAdministrator(
            $this->connection,
            'activity-bootstrap',
        );
        ActivityFixture::insert($this->connection, [
            'title' => 'Legacy Activity',
            'slug' => 'legacy-activity',
            'summary' => 'Legacy activity summary.',
            'featured_image_path' => null,
            'price' => null,
            'price_unit' => null,
            'duration_label' => null,
            'location' => null,
            'is_homepage_featured' => 0,
            'homepage_sort_order' => 0,
        ]);

        $application = Bootstrap::boot(dirname(__DIR__, 3));
        $this->handlersRegistered = true;
        $sessionId = session_id();
        $this->sessionFile = rtrim(session_save_path(), '\\/') . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
        $_SESSION['administrator_identity'] = (new AdministratorIdentity($administratorId))->toSessionData();

        $response = $application->handle(Request::create('GET', '/admin/activities'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('Legacy Activity', $response->body());
        self::assertStringContainsString('Not published', $response->body());
        self::assertStringNotContainsString('Internal Server Error', $response->body());
    }

    public function testBootstrapRevokesAnAuthenticatedSessionWhenTheAdministratorBecomesInactive(): void
    {
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $administratorId = CmsFixture::insertAdministrator(
            $this->connection,
            'session-revocation',
        );

        $application = Bootstrap::boot(dirname(__DIR__, 3));
        $this->handlersRegistered = true;
        $sessionId = session_id();
        $this->sessionFile = rtrim(session_save_path(), '\\/') . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
        $_SESSION['administrator_identity'] = (new AdministratorIdentity($administratorId))->toSessionData();

        $statement = $this->connection->prepare(
            'UPDATE users SET account_status = :status WHERE id = :id',
        );
        $statement->execute([
            'status' => 'inactive',
            'id' => $administratorId,
        ]);

        $response = $application->handle(Request::create('GET', '/admin/activities'));

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/login', $response->headers()['Location']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertArrayNotHasKey('administrator_identity', $_SESSION);
    }
}
