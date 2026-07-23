<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Session;
use App\Services\RoomCatalogFlashMessages;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class RoomCatalogFlashMessagesTest extends TestCase
{
    private string $savePath;

    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-room-catalog-flash-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_id('');
        }

        foreach (glob($this->savePath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->savePath)) {
            rmdir($this->savePath);
        }

        parent::tearDown();
    }

    public function testRoomSuccessMessageCanBePulledOnlyOnce(): void
    {
        $messages = new RoomCatalogFlashMessages($this->session);

        self::assertNull($messages->pullRoomSuccess());

        $messages->roomSuccess('Room created successfully.');

        self::assertSame('Room created successfully.', $messages->pullRoomSuccess());
        self::assertNull($messages->pullRoomSuccess());
    }

    public function testSuccessMessageCanBePulledOnlyOnce(): void
    {
        $messages = new RoomCatalogFlashMessages($this->session);

        self::assertNull($messages->pullRoomTypeSuccess());

        $messages->roomTypeSuccess('Room type created successfully.');

        self::assertSame('Room type created successfully.', $messages->pullRoomTypeSuccess());
        self::assertNull($messages->pullRoomTypeSuccess());
    }

    public function testAmenitySuccessMessageCanBePulledOnlyOnce(): void
    {
        $messages = new RoomCatalogFlashMessages($this->session);

        self::assertNull($messages->pullAmenitySuccess());

        $messages->amenitySuccess('Amenity created successfully.');

        self::assertSame('Amenity created successfully.', $messages->pullAmenitySuccess());
        self::assertNull($messages->pullAmenitySuccess());
    }

    public function testMalformedSessionValueIsDiscardedSafely(): void
    {
        $this->session->set('administrator_room_success', ['unexpected']);
        $messages = new RoomCatalogFlashMessages($this->session);

        self::assertNull($messages->pullRoomSuccess());
        self::assertFalse($this->session->has('administrator_room_success'));

        $this->session->set('administrator_room_type_success', ['unexpected']);

        self::assertNull($messages->pullRoomTypeSuccess());
        self::assertFalse($this->session->has('administrator_room_type_success'));

        $this->session->set('administrator_amenity_success', ['unexpected']);

        self::assertNull($messages->pullAmenitySuccess());
        self::assertFalse($this->session->has('administrator_amenity_success'));
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return [
            'name' => 'tgh_' . bin2hex(random_bytes(8)),
            'inactivity_timeout' => 1800,
            'absolute_lifetime' => 43200,
            'cookie' => [
                'lifetime' => 0,
                'secure' => false,
                'http_only' => true,
                'same_site' => 'Lax',
                'path' => '/',
                'domain' => null,
            ],
            'strict_mode' => true,
            'use_cookies' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
            'save_handler' => 'files',
            'save_path' => realpath($this->savePath),
            'gc_maxlifetime' => 43200,
        ];
    }
}
