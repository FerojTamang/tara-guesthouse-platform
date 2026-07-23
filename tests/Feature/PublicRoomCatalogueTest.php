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
        $this->rooms->method('findActiveAmenitiesByRoomId')->willReturn([$this->amenity('<i>Wi-Fi</i>')]);
        $this->images->method('findPrimaryByRoomId')->willReturn($this->image());
        $response = $this->router->dispatch(Request::create('GET', '/rooms'));
        self::assertSame(200, $response->statusCode()); self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('&lt;script&gt;Garden&lt;/script&gt;', $response->body()); self::assertStringNotContainsString('101', $response->body());
        self::assertStringNotContainsString('operational_status', $response->body()); self::assertStringNotContainsString('is_published', $response->body());
        self::assertStringContainsString('/uploads/rooms/' . str_repeat('a', 64) . '.png', $response->body());
    }

    public function testListingUsesPlaceholderWithoutPrimaryImage(): void
    {
        $this->rooms->method('findAllPublic')->willReturn([$this->room('Garden', 'garden-room', null)]); $this->rooms->method('findActiveAmenitiesByRoomId')->willReturn([]); $this->images->method('findPrimaryByRoomId')->willReturn(null);
        self::assertStringContainsString('/assets/images/room-placeholder.svg', $this->router->dispatch(Request::create('GET', '/rooms'))->body());
    }

    public function testDetailAndAllIneligibleSlugsReturnSafeExpectedResponses(): void
    {
        $room = $this->room('Garden', 'garden-room', 'A room'); $this->rooms->method('findPublicBySlug')->willReturnCallback(fn (string $slug) => $slug === 'garden-room' ? $room : null); $this->rooms->method('findActiveAmenitiesByRoomId')->willReturn([$this->amenity('Wi-Fi')]); $this->images->method('findPrimaryByRoomId')->willReturn($this->image()); $this->images->method('findAllByRoomId')->willReturn([$this->image()]);
        $detail = $this->router->dispatch(Request::create('GET', '/rooms/garden-room'));
        self::assertSame(200, $detail->statusCode()); self::assertSame('no-store', $detail->headers()['Cache-Control']); self::assertStringContainsString('NPR 6200.00', $detail->body()); self::assertStringContainsString('Deluxe', $detail->body()); self::assertStringContainsString('Wi-Fi', $detail->body());
        foreach (['/rooms/Malformed', '/rooms/missing-room', '/rooms/draft-room', '/rooms/maintenance-room', '/rooms/inactive-room', '/rooms/deleted-room'] as $path) { $response = $this->router->dispatch(Request::create('GET', $path)); self::assertSame(404, $response->statusCode()); self::assertSame('Not Found', $response->body()); self::assertSame('no-store', $response->headers()['Cache-Control']); }
        self::assertSame(405, $this->router->dispatch(Request::create('POST', '/rooms'))->statusCode());
    }

    private function room(string $name, string $slug, ?string $description): Room { $t = new DateTimeImmutable('2026-07-23 UTC'); return new Room(1, new RoomType(1, 'Deluxe', 'deluxe', null, '5000.00', 2, 1, true, $t, $t), '101', $name, $slug, $description, '6200.00', 2, 1, Room::STATUS_ACTIVE, true, $t, $t, null); }
    private function amenity(string $name): Amenity { $t = new DateTimeImmutable('2026-07-23 UTC'); return new Amenity(1, $name, null, true, $t, $t); }
    private function image(): RoomImage { return new RoomImage(1, 1, 'uploads/rooms/' . str_repeat('a', 64) . '.png', null, 0, true, new DateTimeImmutable('2026-07-23 UTC')); }
}
