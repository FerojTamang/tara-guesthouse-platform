<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\Middleware;
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


    public function testItRegistersStateChangingRouteHelpers(): void
    {
        $router = new Router();
        $handler = static fn (Request $request, array $parameters): Response => Response::text($request->method());

        $router
            ->post('/post', $handler)
            ->put('/put', $handler)
            ->patch('/patch', $handler)
            ->delete('/delete', $handler);

        self::assertSame('POST', $router->dispatch(Request::create('POST', '/post'))->body());
        self::assertSame('PUT', $router->dispatch(Request::create('PUT', '/put'))->body());
        self::assertSame('PATCH', $router->dispatch(Request::create('PATCH', '/patch'))->body());
        self::assertSame('DELETE', $router->dispatch(Request::create('DELETE', '/delete'))->body());
    }

    public function testRouteMiddlewareRunsInRegistrationOrderAroundTheHandler(): void
    {
        $events = [];
        $first = new RecordingMiddleware('first', $events);
        $second = new RecordingMiddleware('second', $events);
        $router = new Router();
        $router->get('/protected', static function (Request $request, array $parameters) use (&$events): Response {
            $events[] = 'handler';

            return Response::text('OK');
        }, [$first, $second]);

        $response = $router->dispatch(Request::create('GET', '/protected'));

        self::assertSame(200, $response->statusCode());
        self::assertSame(
            ['first:before', 'second:before', 'handler', 'second:after', 'first:after'],
            $events,
        );
    }

    public function testRouteMiddlewareCanShortCircuitTheHandler(): void
    {
        $handlerCalled = false;
        $router = new Router();
        $router->get('/protected', static function (Request $request, array $parameters) use (&$handlerCalled): Response {
            $handlerCalled = true;

            return Response::text('Should not run');
        }, [new ShortCircuitMiddleware()]);

        $response = $router->dispatch(Request::create('GET', '/protected'));

        self::assertSame(403, $response->statusCode());
        self::assertSame('Forbidden', $response->body());
        self::assertFalse($handlerCalled);
    }

    public function testItRejectsMiddlewareThatDoesNotImplementTheInterface(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->get(
            '/protected',
            static fn (Request $request, array $parameters): Response => Response::text('No'),
            [static fn (Request $request, callable $next): Response => $next($request)],
        );
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


final class RecordingMiddleware implements Middleware
{
    /** @var list<string> */
    private array $events;

    /**
     * @param list<string> $events
     */
    public function __construct(
        private readonly string $name,
        array &$events,
    ) {
        $this->events =& $events;
    }

    public function process(Request $request, callable $next): Response
    {
        $this->events[] = $this->name . ':before';
        $response = $next($request);
        $this->events[] = $this->name . ':after';

        return $response;
    }
}

final class ShortCircuitMiddleware implements Middleware
{
    public function process(Request $request, callable $next): Response
    {
        return Response::text('Forbidden', 403);
    }
}
