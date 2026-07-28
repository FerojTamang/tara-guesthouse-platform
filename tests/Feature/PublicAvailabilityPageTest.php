<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicAvailabilityController;
use App\Core\Request;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Models\Room;
use App\Models\RoomType;
use App\Repositories\AvailabilityRepository;
use App\Repositories\RoomCatalogRepository;
use App\Services\AvailabilityService;
use App\Services\PublicAvailabilityService;
use App\Services\PublicAvailabilityServiceProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicAvailabilityPageTest extends TestCase
{
    private PublicAvailabilityPageRepositoryFake $availability;

    private RoomCatalogRepository $rooms;

    private Router $router;

    protected function setUp(): void
    {
        $this->availability = new PublicAvailabilityPageRepositoryFake();
        $this->rooms = $this->createStub(RoomCatalogRepository::class);
        $views = new ViewRenderer(dirname(__DIR__, 2) . '/resources/views');
        $controller = new PublicAvailabilityController(
            new PublicAvailabilityServiceProvider(
                fn (): PublicAvailabilityService => new PublicAvailabilityService(
                    new AvailabilityService(
                        $this->availability,
                        static fn (): DateTimeImmutable => new DateTimeImmutable(
                            '2026-08-01 12:00:00 UTC',
                        ),
                    ),
                    $this->rooms,
                ),
            ),
            $views,
        );

        $this->router = new Router();
        $registrar = require dirname(__DIR__, 2) . '/routes/web.php';
        $registrar($this->router, $views, $controller);
    }

    public function testInitialPageRendersTheGetFormWithoutSearching(): void
    {
        $response = $this->router->dispatch(
            Request::create('GET', '/availability'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString(
            '<title>Check availability | Tara Guest House</title>',
            $response->body(),
        );
        self::assertStringContainsString('method="get" action="/availability"', $response->body());
        self::assertStringContainsString('name="check_in"', $response->body());
        self::assertStringContainsString('name="check_out"', $response->body());
        self::assertStringContainsString('name="adults"', $response->body());
        self::assertStringContainsString('name="children"', $response->body());
        self::assertStringContainsString('value="1"', $response->body());
        self::assertStringContainsString('value="0"', $response->body());
        self::assertStringNotContainsString('Available rooms', $response->body());
        self::assertSame(0, $this->availability->searchCalls);
    }

    public function testValidSearchRendersEscapedAvailableRoomsAndPreservesCriteria(): void
    {
        $this->availability->roomIds = [12];
        $this->rooms->method('findAllPublic')->willReturn([
            self::room('<script>Deluxe</script>', 'deluxe-room-12'),
        ]);

        $response = $this->router->dispatch(Request::create(
            'GET',
            '/availability',
            [
                'check_in' => '2026-08-10',
                'check_out' => '2026-08-12',
                'adults' => '2',
                'children' => '0',
            ],
        ));

        self::assertSame(200, $response->statusCode());
        self::assertSame(1, $this->availability->searchCalls);
        self::assertStringContainsString('Available rooms', $response->body());
        self::assertStringContainsString('1 room available.', $response->body());
        self::assertStringContainsString('&lt;script&gt;Deluxe&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Deluxe</script>', $response->body());
        self::assertStringContainsString('NPR 4500.00 per night', $response->body());
        self::assertStringContainsString('/rooms/deluxe-room-12', $response->body());
        self::assertStringContainsString('value="2026-08-10"', $response->body());
        self::assertStringContainsString('value="2026-08-12"', $response->body());
        self::assertStringContainsString('value="2"', $response->body());
        self::assertStringNotContainsString('room_number', $response->body());
        self::assertStringNotContainsString('operational_status', $response->body());
    }

    public function testValidSearchCanRenderAStableEmptyState(): void
    {
        $response = $this->router->dispatch(Request::create(
            'GET',
            '/availability',
            [
                'check_in' => '2026-08-10',
                'check_out' => '2026-08-12',
                'adults' => '1',
                'children' => '0',
            ],
        ));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString(
            'No rooms match the selected dates and occupancy.',
            $response->body(),
        );
    }

    public function testInvalidSearchReturns422PreservesEscapedInputAndDoesNotSearch(): void
    {
        $response = $this->router->dispatch(Request::create(
            'GET',
            '/availability',
            [
                'check_in' => '<script>bad</script>',
                'check_out' => '2026-08-10',
                'adults' => '0',
                'children' => '-1',
            ],
        ));

        self::assertSame(422, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(0, $this->availability->searchCalls);
        self::assertStringContainsString('role="alert"', $response->body());
        self::assertStringContainsString('Check-in date is invalid.', $response->body());
        self::assertStringContainsString(
            'value="&lt;script&gt;bad&lt;/script&gt;"',
            $response->body(),
        );
        self::assertStringNotContainsString('<script>bad</script>', $response->body());
        self::assertStringNotContainsString('Available rooms', $response->body());
    }

    public function testSystemFailureReturnsOnlyGeneric500(): void
    {
        $this->availability->failure = new RuntimeException(
            'SQLSTATE[HY000] private availability details',
        );

        $response = $this->router->dispatch(Request::create(
            'GET',
            '/availability',
            [
                'check_in' => '2026-08-10',
                'check_out' => '2026-08-12',
                'adults' => '1',
                'children' => '0',
            ],
        ));

        self::assertSame(500, $response->statusCode());
        self::assertSame('Internal Server Error', $response->body());
        self::assertStringNotContainsString('SQLSTATE', $response->body());
        self::assertStringNotContainsString('private', $response->body());
    }

    public function testAvailabilityPageIsReadOnly(): void
    {
        $response = $this->router->dispatch(
            Request::create('POST', '/availability'),
        );

        self::assertSame(405, $response->statusCode());
        self::assertSame('GET, HEAD', $response->headers()['Allow']);
        self::assertSame(0, $this->availability->searchCalls);
    }

    private static function room(string $name, string $slug): Room
    {
        $timestamp = new DateTimeImmutable('2026-07-01 10:00:00 UTC');
        $type = new RoomType(
            1,
            'Deluxe',
            'deluxe',
            null,
            '4500.00',
            2,
            1,
            true,
            $timestamp,
            $timestamp,
        );

        return new Room(
            12,
            $type,
            'D-12',
            $name,
            $slug,
            null,
            '4500.00',
            2,
            1,
            Room::STATUS_ACTIVE,
            true,
            $timestamp,
            $timestamp,
            null,
        );
    }
}

final class PublicAvailabilityPageRepositoryFake implements AvailabilityRepository
{
    /** @var list<int> */
    public array $roomIds = [];

    public ?RuntimeException $failure = null;

    public int $searchCalls = 0;

    public function findAvailableRoomIds(
        \App\Models\StayPeriod $stayPeriod,
        int $adults,
        int $children,
        DateTimeImmutable $asOf,
    ): array {
        $this->searchCalls++;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->roomIds;
    }

    public function hasBlockingOverlap(
        int $roomId,
        \App\Models\StayPeriod $stayPeriod,
        DateTimeImmutable $asOf,
    ): bool {
        return false;
    }
}
