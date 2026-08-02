<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicAvailabilityController;
use App\Core\Request;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Models\Amenity;
use App\Presentation\RoomImageUrl;
use App\Repositories\AvailabilityRepository;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Services\AvailabilityService;
use App\Services\PublicAvailabilityService;
use App\Services\PublicAvailabilityServiceProvider;
use App\Services\PublicRoomCatalogService;
use App\Services\PublicRoomCatalogServiceProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicAvailabilityPageTest extends TestCase
{
    private PublicAvailabilityPageRepositoryFake $availability;

    private RoomCatalogRepository $rooms;

    private RoomImageCatalogRepository $images;

    private Router $router;

    protected function setUp(): void
    {
        $this->availability = new PublicAvailabilityPageRepositoryFake();
        $this->rooms = $this->createStub(RoomCatalogRepository::class);
        $this->images = $this->createStub(RoomImageCatalogRepository::class);
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
                    new PublicRoomCatalogServiceProvider(
                        fn (): PublicRoomCatalogService => new PublicRoomCatalogService(
                            $this->rooms,
                            $this->images,
                            new RoomImageUrl(),
                        ),
                    ),
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

    public function testSelectedRoomSearchRestrictsResultsAndBuildsSafeBookingContinuation(): void
    {
        $this->availability->roomIds = [12, 99];
        $this->rooms->method('findPublicBySlug')->willReturnCallback(
            static fn (string $slug): ?Room => $slug === 'deluxe-room-12'
                ? self::room('Deluxe Room', 'deluxe-room-12')
                : null,
        );
        $this->rooms->method('findAllPublic')->willReturn([
            self::room('Deluxe Room', 'deluxe-room-12'),
        ]);

        $response = $this->router->dispatch(Request::create('GET', '/availability', [
            'room' => 'deluxe-room-12',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'adults' => '2',
            'children' => '0',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('name="room" value="deluxe-room-12"', $response->body());
        self::assertStringContainsString('Selected room', $response->body());
        self::assertStringContainsString('Continue Booking', $response->body());
        self::assertStringContainsString(
            '/booking?room_id=12&amp;check_in=2026-08-10&amp;check_out=2026-08-12&amp;adults=2&amp;children=0',
            $response->body(),
        );
        self::assertStringContainsString('/rooms/deluxe-room-12?', $response->body());
        self::assertStringNotContainsString('price_per_night=', $response->body());
        self::assertStringNotContainsString('room_number', $response->body());
    }

    public function testUnavailableSelectedRoomRetainsCriteriaAndOffersSafeAlternatives(): void
    {
        $this->rooms->method('findPublicBySlug')->willReturn(
            self::room('Deluxe Room', 'deluxe-room-12'),
        );
        $this->rooms->method('findAllPublic')->willReturn([
            self::room('Deluxe Room', 'deluxe-room-12'),
        ]);

        $response = $this->router->dispatch(Request::create('GET', '/availability', [
            'room' => 'deluxe-room-12',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'adults' => '2',
            'children' => '0',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('is unavailable for these dates', $response->body());
        self::assertStringContainsString('Change Dates', $response->body());
        self::assertStringContainsString('View Other Available Rooms', $response->body());
        self::assertStringNotContainsString('Continue Booking', $response->body());
        self::assertStringContainsString('check_in=2026-08-10', $response->body());
    }

    public function testMalformedAndIneligibleSelectedRoomsFailWithSameSafeResponse(): void
    {
        foreach (['Bad Room', 'maintenance-room', 'inactive-room', 'draft-room', 'deleted-room'] as $slug) {
            $response = $this->router->dispatch(Request::create('GET', '/availability', [
                'room' => $slug,
                'check_in' => '2026-08-10',
                'check_out' => '2026-08-12',
                'adults' => '1',
                'children' => '0',
            ]));

            self::assertSame(422, $response->statusCode());
            self::assertStringContainsString('The selected room is unavailable.', $response->body());
            self::assertStringNotContainsString('SQLSTATE', $response->body());
            self::assertStringNotContainsString('operational_status', $response->body());
        }

        self::assertSame(0, $this->availability->searchCalls);
    }

    public function testRichResultUsesPublicImageTypeCapacityAmenitiesAndServerDerivedNights(): void
    {
        $room = self::room('Garden Suite', 'garden-suite');
        $this->availability->roomIds = [12];
        $this->rooms->method('findAllPublic')->willReturn([$room]);
        $this->rooms->method('findActiveAmenitiesByRoomId')->willReturn([
            self::amenity('<em>Air conditioning</em>'),
        ]);
        $this->images->method('findPrimaryByRoomId')->willReturn(self::image());

        $response = $this->router->dispatch(Request::create('GET', '/availability', [
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-13',
            'adults' => '2',
            'children' => '1',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('/uploads/rooms/' . str_repeat('a', 64) . '.png', $response->body());
        self::assertStringContainsString('Deluxe', $response->body());
        self::assertStringContainsString('2 adults', $response->body());
        self::assertStringContainsString('1 children', $response->body());
        self::assertStringContainsString('3 nights', $response->body());
        self::assertStringContainsString('&lt;em&gt;Air conditioning&lt;/em&gt;', $response->body());
        self::assertStringNotContainsString('<em>Air conditioning</em>', $response->body());
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

    private static function amenity(string $name): Amenity
    {
        $timestamp = new DateTimeImmutable('2026-07-01 10:00:00 UTC');

        return new Amenity(1, $name, null, true, $timestamp, $timestamp);
    }

    private static function image(): RoomImage
    {
        return new RoomImage(
            1,
            12,
            'uploads/rooms/' . str_repeat('a', 64) . '.png',
            'Garden Suite',
            0,
            true,
            new DateTimeImmutable('2026-07-01 10:00:00 UTC'),
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

    public function findBlockingStayPeriods(
        int $roomId,
        \App\Models\StayPeriod $window,
        DateTimeImmutable $asOf,
    ): array {
        return [];
    }
}
