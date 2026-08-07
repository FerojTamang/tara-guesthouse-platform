<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicRoomController;
use App\Core\Request;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Presentation\RoomImageUrl;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Services\PublicRoomCatalogService;
use App\Services\PublicRoomCatalogServiceProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PublicRoomCatalogueTest extends TestCase
{
    private RoomCatalogRepository $rooms;
    private RoomImageCatalogRepository $images;
    private Router $router;

    protected function setUp(): void
    {
        $this->rooms = $this->createStub(RoomCatalogRepository::class);
        $this->images = $this->createStub(RoomImageCatalogRepository::class);
        $controller = new PublicRoomController(new ViewRenderer(dirname(__DIR__, 2) . '/resources/views'), new PublicRoomCatalogServiceProvider(fn (): PublicRoomCatalogService => new PublicRoomCatalogService($this->rooms, $this->images, new RoomImageUrl())));
        $this->router = new Router();
        $registrar = require dirname(__DIR__, 2) . '/routes/web.php';
        $registrar($this->router, new ViewRenderer(dirname(__DIR__, 2) . '/resources/views'), $controller);
    }

    public function testListingRendersOnlyEligibleEscapedPublicDataAndNoStore(): void
    {
        $room = $this->room('<script>Garden</script>', 'garden-room', 'Description <b>x</b>');
        $this->rooms->method('findAllPublic')->willReturn([$room]);
        $this->rooms->method('findActiveAmenitiesByRoomIds')->willReturn([1 => [$this->amenity('<i>Wi-Fi</i>')]]);
        $this->images->method('findPrimaryByRoomIds')->willReturn([1 => $this->image()]);
        $response = $this->router->dispatch(Request::create('GET', '/rooms'));
        self::assertSame(200, $response->statusCode()); self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('&lt;script&gt;Garden&lt;/script&gt;', $response->body()); self::assertStringNotContainsString('101', $response->body());
        self::assertStringContainsString('Description &lt;b&gt;x&lt;/b&gt;', $response->body());
        self::assertStringContainsString('&lt;i&gt;Wi-Fi&lt;/i&gt;', $response->body());
        self::assertStringNotContainsString('Description <b>x</b>', $response->body());
        self::assertStringNotContainsString('<i>Wi-Fi</i>', $response->body());
        self::assertStringNotContainsString('operational_status', $response->body()); self::assertStringNotContainsString('is_published', $response->body());
        self::assertStringContainsString('/uploads/rooms/' . str_repeat('a', 64) . '.png', $response->body());
        self::assertStringContainsString('/assets/css/pages/rooms.css', $response->body());
        self::assertStringContainsString('<body class="tg-page-rooms">', $response->body());
        self::assertStringContainsString('class="tg-public-header tg-public-header--overlay"', $response->body());
        self::assertSame(1, substr_count($response->body(), '<h1'));
        self::assertStringContainsString('method="get" action="/availability"', $response->body());
        foreach (['check_in', 'check_out', 'adults', 'children'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $response->body());
        }
        self::assertStringNotContainsString('name="sort"', $response->body());
        self::assertStringNotContainsString('name="filter"', $response->body());
        self::assertStringContainsString('<footer class="tg-public-footer">', $response->body());
    }

    public function testListingUsesPlaceholderWithoutPrimaryImage(): void
    {
        $this->rooms->method('findAllPublic')->willReturn([$this->room('Garden', 'garden-room', null)]); $this->rooms->method('findActiveAmenitiesByRoomIds')->willReturn([1 => []]); $this->images->method('findPrimaryByRoomIds')->willReturn([]);
        $body = $this->router->dispatch(Request::create('GET', '/rooms'))->body();
        self::assertStringContainsString('/assets/images/room-placeholder.svg', $body);
        self::assertStringContainsString('/assets/images/home/home-hero-sauraha-jungle-lodge.webp', $body);
        self::assertStringContainsString('/assets/images/home/home-hero-sauraha-jungle-lodge.png', $body);
    }

    public function testListingUsesFirstRoomAsLeadAndRendersEveryRemainingRoomOnce(): void
    {
        $this->rooms->method('findAllPublic')->willReturn([
            $this->room('First Room', 'first-room', 'First description', 1),
            $this->room('Second Room', 'second-room', 'Second description', 2),
            $this->room('Third Room', 'third-room', 'Third description', 3),
        ]);
        $this->rooms->method('findActiveAmenitiesByRoomIds')->willReturn([1 => [], 2 => [], 3 => []]);
        $this->images->method('findPrimaryByRoomIds')->willReturnCallback(
            fn (array $roomIds): array => array_combine(
                $roomIds,
                array_map(fn (int $roomId): RoomImage => $this->image($roomId, dechex($roomId)), $roomIds),
            ),
        );

        $body = $this->router->dispatch(Request::create('GET', '/rooms'))->body();

        self::assertStringContainsString('3 rooms', $body);
        self::assertStringContainsString('Featured room', $body);
        self::assertStringContainsString('Explore all rooms', $body);
        self::assertSame(1, substr_count($body, 'class="tg-card tg-rooms-lead"'));
        self::assertSame(2, substr_count($body, 'class="tg-card tg-room-card tg-room-card--listing"'));
        self::assertSame(3, substr_count($body, 'href="/availability?room='));
        foreach (['first-room', 'second-room', 'third-room'] as $slug) {
            self::assertSame(1, substr_count($body, 'data-room-slug="' . $slug . '"'));
            self::assertStringContainsString('href="/availability?room=' . $slug . '"', $body);
            self::assertStringContainsString('href="/rooms/' . $slug . '"', $body);
        }
        self::assertStringContainsString('First description', $body);
        self::assertStringContainsString('Second description', $body);
        self::assertStringContainsString('Third description', $body);
        self::assertStringContainsString('<img src="/uploads/rooms/' . str_repeat('1', 64) . '.png" alt="" decoding="async">', $body);
    }

    public function testEmptyListingRetainsSearchAndRendersPolishedFallbackWithoutFakeCards(): void
    {
        $this->rooms->method('findAllPublic')->willReturn([]);

        $body = $this->router->dispatch(Request::create('GET', '/rooms'))->body();

        self::assertStringContainsString('0 rooms', $body);
        self::assertStringContainsString('No rooms are currently available to display.', $body);
        self::assertStringContainsString('method="get" action="/availability"', $body);
        self::assertStringContainsString('href="/contact"', $body);
        self::assertStringNotContainsString('data-room-slug=', $body);
    }

    public function testHeroUsesFirstValidPrimaryImageInExistingRoomOrder(): void
    {
        $this->rooms->method('findAllPublic')->willReturn([
            $this->room('Room Without Image', 'room-without-image', null, 1),
            $this->room('Room With Image', 'room-with-image', null, 2),
        ]);
        $this->rooms->method('findActiveAmenitiesByRoomIds')->willReturn([1 => [], 2 => []]);
        $this->images->method('findPrimaryByRoomIds')->willReturn([2 => $this->image(2, 'b')]);

        $body = $this->router->dispatch(Request::create('GET', '/rooms'))->body();

        self::assertStringContainsString('<img src="/uploads/rooms/' . str_repeat('b', 64) . '.png" alt="" decoding="async">', $body);
        self::assertStringContainsString('<img src="/assets/images/room-placeholder.svg" alt="Room Without Image"', $body);
    }

    public function testRoomsDesignStylesheetExists(): void
    {
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/css/pages/rooms.css');
    }

    public function testDetailAndAllIneligibleSlugsReturnSafeExpectedResponses(): void
    {
        $room = $this->room('Garden', 'garden-room', 'A room'); $this->rooms->method('findPublicBySlug')->willReturnCallback(fn (string $slug) => $slug === 'garden-room' ? $room : null); $this->rooms->method('findActiveAmenitiesByRoomId')->willReturn([$this->amenity('Wi-Fi')]); $this->images->method('findPrimaryByRoomId')->willReturn($this->image()); $this->images->method('findAllByRoomId')->willReturn([$this->image()]);
        $detail = $this->router->dispatch(Request::create('GET', '/rooms/garden-room?check_in=2026-08-10&check_out=2026-08-12&adults=2&children=0'));
        self::assertSame(200, $detail->statusCode()); self::assertSame('no-store', $detail->headers()['Cache-Control']); self::assertStringContainsString('NPR 6200.00', $detail->body()); self::assertStringContainsString('Deluxe', $detail->body()); self::assertStringContainsString('Wi-Fi', $detail->body());
        self::assertStringContainsString('method="get" action="/availability"', $detail->body());
        self::assertStringContainsString('type="hidden" name="room" value="garden-room"', $detail->body());
        self::assertStringContainsString('value="2026-08-10"', $detail->body());
        self::assertStringContainsString('value="2026-08-12"', $detail->body());
        self::assertStringContainsString('Check This Room’s Availability', $detail->body());
        self::assertStringContainsString('class="tg-reservation-card"', $detail->body());
        self::assertStringContainsString('class="tg-room-capacity-summary"', $detail->body());
        self::assertStringContainsString('class="tg-room-amenities-section"', $detail->body());
        self::assertStringContainsString('Sleeps up to 2 adults and 1 child', preg_replace('/\s+/', ' ', $detail->body()) ?? $detail->body());
        self::assertStringContainsString('/assets/css/pages/room-detail.css', $detail->body());
        self::assertSame(1, substr_count($detail->body(), '<h1'));
        self::assertStringNotContainsString('101', $detail->body());
        self::assertStringNotContainsString('operational_status', $detail->body());
        self::assertStringNotContainsString('is_published', $detail->body());
        foreach (['/rooms/Malformed', '/rooms/missing-room', '/rooms/draft-room', '/rooms/maintenance-room', '/rooms/inactive-room', '/rooms/deleted-room'] as $path) { $response = $this->router->dispatch(Request::create('GET', $path)); self::assertSame(404, $response->statusCode()); self::assertSame('Not Found', $response->body()); self::assertSame('no-store', $response->headers()['Cache-Control']); }
        self::assertSame(405, $this->router->dispatch(Request::create('POST', '/rooms'))->statusCode());
    }

    public function testDetailRendersFiveImageGalleryAndAccessibleViewerInBackendOrder(): void
    {
        $room = $this->room('Garden', 'garden-room', 'A room');
        $this->rooms->method('findPublicBySlug')->willReturn($room);
        $this->rooms->method('findActiveAmenitiesByRoomId')->willReturn([]);
        $images = [];

        foreach (range('a', 'f') as $index => $character) {
            $images[] = new RoomImage(
                $index + 1,
                1,
                'uploads/rooms/' . str_repeat($character, 64) . '.png',
                'Photo ' . ($index + 1),
                $index,
                $index === 0,
                new DateTimeImmutable('2026-07-23 UTC'),
            );
        }

        $this->images->method('findPrimaryByRoomId')->willReturn($images[0]);
        $this->images->method('findAllByRoomId')->willReturn($images);

        $body = $this->router->dispatch(Request::create('GET', '/rooms/garden-room'))->body();

        self::assertSame(5, substr_count($body, 'class="tg-room-gallery__item'));
        self::assertStringContainsString('tg-room-gallery--count-5', $body);
        self::assertStringContainsString('tg-room-detail__intro-facts', $body);
        self::assertStringContainsString('6 photos', $body);
        self::assertStringContainsString('View all photos', $body);
        self::assertStringContainsString('role="dialog" aria-modal="true"', $body);
        self::assertStringContainsString('data-photo-previous', $body);
        self::assertStringContainsString('data-photo-next', $body);
        self::assertStringContainsString('/assets/js/pages/room-detail.js', $body);
        foreach (range('a', 'f') as $character) {
            self::assertStringContainsString('/uploads/rooms/' . str_repeat($character, 64) . '.png', $body);
        }
        self::assertStringNotContainsString('storage/', $body);
        self::assertStringNotContainsString('C:\\', $body);
    }

    private function room(string $name, string $slug, ?string $description, int $id = 1): Room { $t = new DateTimeImmutable('2026-07-23 UTC'); return new Room($id, new RoomType(1, 'Deluxe', 'deluxe', null, '5000.00', 2, 1, true, $t, $t), '101', $name, $slug, $description, '6200.00', 2, 1, Room::STATUS_ACTIVE, true, $t, $t, null); }
    private function amenity(string $name): Amenity { $t = new DateTimeImmutable('2026-07-23 UTC'); return new Amenity(1, $name, null, true, $t, $t); }
    private function image(int $roomId = 1, string $hashCharacter = 'a'): RoomImage { return new RoomImage($roomId, $roomId, 'uploads/rooms/' . str_repeat($hashCharacter, 64) . '.png', null, 0, true, new DateTimeImmutable('2026-07-23 UTC')); }
}
