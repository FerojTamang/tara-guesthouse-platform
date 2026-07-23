<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\PublicRoomCatalogException;
use App\Services\PublicRoomCatalogService;
use App\Services\PublicRoomCatalogServiceProvider;

final readonly class PublicRoomController
{
    public function __construct(private ViewRenderer $views, private PublicRoomCatalogServiceProvider $services) {}
    public function index(Request $request, array $parameters): Response
    {
        try { return $this->page('public.rooms.index', ['title' => 'Rooms | Tara Guest House', 'rooms' => $this->services->service()->list()]); }
        catch (PublicRoomCatalogException) { return Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']); }
    }
    public function show(Request $request, array $parameters): Response
    {
        try { return $this->page('public.rooms.show', ['title' => 'Room | Tara Guest House', 'room' => $this->services->service()->get($parameters['slug'] ?? '')]); }
        catch (PublicRoomCatalogException $exception) {
            return $exception->isNotFound() ? Response::text('Not Found', 404, ['Cache-Control' => 'no-store']) : Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
        }
    }
    private function page(string $view, array $data): Response { return Response::html($this->views->render($view, $data, 'layouts.base'), 200, ['Cache-Control' => 'no-store']); }
}
