<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\CmsException;
use App\Models\NavigationItem;
use App\Requests\NavigationItemRequest;
use PHPUnit\Framework\TestCase;

final class NavigationItemRequestTest extends TestCase
{
    public function testValidRoutePageAndGroupPayloadsAreNormalized(): void
    {
        $route = NavigationItemRequest::fromInput('main', 'Rooms', 'route', 'rooms', null, null);
        self::assertSame(NavigationItem::MAIN, $route->location());
        self::assertSame('rooms', $route->routeKey());
        self::assertNull($route->pageId());
        self::assertSame('news', NavigationItemRequest::fromInput('main', 'News', 'route', 'news', null, null)->routeKey());
        self::assertSame('notices', NavigationItemRequest::fromInput('main', 'Notices', 'route', 'notices', null, null)->routeKey());

        $page = NavigationItemRequest::fromInput('main', ' About us ', 'page', null, 12, 3);
        self::assertSame('About us', $page->label());
        self::assertSame(12, $page->pageId());
        self::assertSame(3, $page->parentId());

        $group = NavigationItemRequest::fromInput('main', 'Explore', 'group', null, null, null);
        self::assertSame(NavigationItem::GROUP, $group->linkType());
        self::assertNull($group->routeKey());
        self::assertNull($group->pageId());
    }

    public function testInvalidTargetsScopesAndTypesAreRejected(): void
    {
        foreach ([
            ['footer', 'Explore', 'group', null, null, null],
            ['main', 'Rooms', 'route', 'unknown', null, null],
            ['main', 'About', 'page', null, null, null],
            ['footer', 'About', 'page', null, 4, 2],
            ['main', '', 'route', 'home', null, null],
            ['main', 'About', 'page', null, '4', null],
        ] as $payload) {
            try {
                NavigationItemRequest::fromInput(...$payload);
                self::fail('Expected invalid navigation payload rejection.');
            } catch (CmsException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }
}
