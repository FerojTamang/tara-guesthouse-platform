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
        try { return $this->page('public.rooms.index', ['title' => 'Rooms | Tara Guest House', 'rooms' => $this->services->service()->list(), 'isRooms' => true]); }
        catch (PublicRoomCatalogException) { return Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']); }
    }
    public function show(Request $request, array $parameters): Response
    {
        try { return $this->page('public.rooms.show', [
            'title' => 'Room | Tara Guest House',
            'room' => $this->services->service()->get($parameters['slug'] ?? ''),
            'availabilityValues' => [
                'checkIn' => $this->display($request->query('check_in')),
                'checkOut' => $this->display($request->query('check_out')),
                'adults' => $this->display($request->query('adults', '1')),
                'children' => $this->display($request->query('children', '0')),
            ],
            'availabilityResult' => $this->availabilityResult(
                $request->query('availability'),
            ),
            'isRooms' => true,
            'isRoomDetail' => true,
        ]); }
        catch (PublicRoomCatalogException $exception) {
            return $exception->isNotFound() ? Response::text('Not Found', 404, ['Cache-Control' => 'no-store']) : Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
        }
    }
    private function display(mixed $value): string
    {
        return is_string($value) ? trim($value) : (is_int($value) ? (string) $value : '');
    }
    private function availabilityResult(mixed $value): ?string
    {
        return is_string($value) && in_array($value, ['available', 'unavailable'], true)
            ? $value
            : null;
    }
    private function page(string $view, array $data): Response { return Response::html($this->views->render($view, $data, 'layouts.base'), 200, ['Cache-Control' => 'no-store']); }
}
