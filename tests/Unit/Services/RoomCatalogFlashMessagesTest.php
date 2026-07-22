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

    public function testSuccessMessageCanBePulledOnlyOnce(): void
    {
        $messages = new RoomCatalogFlashMessages($this->session);

        self::assertNull($messages->pullRoomTypeSuccess());

        $messages->roomTypeSuccess('Room type created successfully.');

        self::assertSame('Room type created successfully.', $messages->pullRoomTypeSuccess());
        self::assertNull($messages->pullRoomTypeSuccess());
    }

    public function testMalformedSessionValueIsDiscardedSafely(): void
    {
        $this->session->set('administrator_room_type_success', ['unexpected']);
        $messages = new RoomCatalogFlashMessages($this->session);

        self::assertNull($messages->pullRoomTypeSuccess());
        self::assertFalse($this->session->has('administrator_room_type_success'));
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
