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

final class PublicAvailabilityApiTest extends TestCase
{
    private PublicAvailabilityApiRepositoryFake $availability;

    private RoomCatalogRepository $rooms;

    private Router $router;

    protected function setUp(): void
    {
        $this->availability = new PublicAvailabilityApiRepositoryFake();
        $this->rooms = $this->createStub(RoomCatalogRepository::class);
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
        );

        $this->router = new Router();
        $registrar = require dirname(__DIR__, 2) . '/routes/api.php';
        $registrar(
            $this->router,
            new ViewRenderer(dirname(__DIR__, 2) . '/resources/views'),
            $controller,
        );
    }

    public function testSuccessfulAvailabilityReadUsesTheApprovedJsonContract(): void
    {
        $this->availability->roomIds = [12];
        $this->rooms->method('findAllPublic')->willReturn([self::room()]);

        $response = $this->router->dispatch(Request::create(
            'GET',
            '/api/v1/availability',
            [
                'check_in' => '2026-08-10',
                'check_out' => '2026-08-12',
                'adults' => '2',
                'children' => '0',
            ],
        ));

        self::assertSame(200, $response->statusCode());
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame([
            'success' => true,
            'data' => [[
                'room_id' => 12,
                'name' => 'Deluxe Room',
                'slug' => 'deluxe-room-12',
                'operational_status' => Room::STATUS_ACTIVE,
                'is_published' => true,
                'is_available' => true,
                'currency' => 'NPR',
                'price_per_night' => '4500.00',
            ]],
        ], json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('room_number', $response->body());
        self::assertStringNotContainsString('SQLSTATE', $response->body());
    }

    public function testValidationFailureReturns422BeforeRepositoryAccess(): void
    {
        $response = $this->router->dispatch(Request::create(
            'GET',
            '/api/v1/availability',
            [
                'check_in' => '2026-08-10',
                'check_out' => '2026-08-10',
                'adults' => '0',
                'children' => '-1',
            ],
        ));

        self::assertSame(422, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(0, $this->availability->searchCalls);
        $body = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($body['success']);
        self::assertSame('Validation failed.', $body['message']);
        self::assertArrayHasKey('availability', $body['errors']);
    }

    public function testSystemFailureReturnsGeneric500WithoutDisclosure(): void
    {
        $this->availability->failure = new RuntimeException(
            'SQLSTATE[HY000] private availability details',
        );

        $response = $this->router->dispatch(Request::create(
            'GET',
            '/api/v1/availability',
            [
                'check_in' => '2026-08-10',
                'check_out' => '2026-08-12',
                'adults' => '1',
                'children' => '0',
            ],
        ));

        self::assertSame(500, $response->statusCode());
        self::assertSame([
            'success' => false,
            'message' => 'Internal Server Error.',
        ], json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('SQLSTATE', $response->body());
        self::assertStringNotContainsString('private', $response->body());
    }

    public function testAvailabilityEndpointIsReadOnly(): void
    {
        $response = $this->router->dispatch(
            Request::create('POST', '/api/v1/availability'),
        );

        self::assertSame(405, $response->statusCode());
        self::assertSame('GET, HEAD', $response->headers()['Allow']);
        self::assertSame(0, $this->availability->searchCalls);
    }

    private static function room(): Room
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
            'Deluxe Room',
            'deluxe-room-12',
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

final class PublicAvailabilityApiRepositoryFake implements AvailabilityRepository
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
