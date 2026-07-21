<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testItDispatchesStaticRoutes(): void
    {
        $router = new Router();
        $router->get('/status', static fn (Request $request, array $parameters): Response => Response::text('OK'));

        $response = $router->dispatch(Request::create('GET', '/status'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('OK', $response->body());
    }

    public function testItDispatchesOneSegmentParametersAfterDecoding(): void
    {
        $router = new Router();
        $router->get('/items/{slug}', static fn (Request $request, array $parameters): Response => Response::text($parameters['slug']));

        $response = $router->dispatch(Request::create('GET', '/items/river%20view'));

        self::assertSame('river view', $response->body());
    }

    public function testItRejectsDuplicateRoutes(): void
    {
        $router = new Router();
        $handler = static fn (Request $request, array $parameters): Response => Response::text('OK');
        $router->get('/status', $handler);

        $this->expectException(LogicException::class);
        $router->get('/status', $handler);
    }

    public function testItRejectsDuplicateParameterNames(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->get('/items/{id}/{id}', static fn (Request $request, array $parameters): Response => Response::text('No'));
    }

    public function testItReturnsNotFound(): void
    {
        $response = (new Router())->dispatch(Request::create('GET', '/missing'));

        self::assertSame(404, $response->statusCode());
        self::assertSame('Not Found', $response->body());
    }

    public function testItReturnsMethodNotAllowedWithAllowHeader(): void
    {
        $router = new Router();
        $router->get('/', static fn (Request $request, array $parameters): Response => Response::text('OK'));

        $response = $router->dispatch(Request::create('POST', '/'));

        self::assertSame(405, $response->statusCode());
        self::assertSame('GET, HEAD', $response->headers()['Allow']);
    }

    public function testHeadUsesGetRouteWithoutResponseBody(): void
    {
        $router = new Router();
        $router->get('/', static fn (Request $request, array $parameters): Response => Response::text('Body'));

        $response = $router->dispatch(Request::create('HEAD', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('', $response->body());
    }
}
