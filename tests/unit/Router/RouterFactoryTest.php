<?php

declare(strict_types=1);

namespace App\Tests\Unit\Router;

use App\Router\RouterFactory;
use Codeception\Test\Unit;
use Nette\Http\Request;
use Nette\Http\UrlScript;

final class RouterFactoryTest extends Unit
{
    public function testSkautisLoginCallbacksMatchTheAuthenticationAction(): void
    {
        $router = RouterFactory::createRouter();

        foreach (['/login', '/login/', '/sign/skaut-i-s'] as $path) {
            $route = $router->match(new Request(new UrlScript('https://zvadlo.skauting.cz'.$path, '/')));

            self::assertIsArray($route);
            self::assertSame('Auth', $route['presenter']);
            self::assertSame('skautIS', $route['action']);
        }
    }

    public function testSkautisLogoutCallbacksMatchTheLogoutAction(): void
    {
        $router = RouterFactory::createRouter();

        foreach (['/logout', '/logout/', '/sign/skautis-logout'] as $path) {
            $route = $router->match(new Request(new UrlScript('https://zvadlo.skauting.cz'.$path, '/')));

            self::assertIsArray($route);
            self::assertSame('Auth', $route['presenter']);
            self::assertSame('skautisLogout', $route['action']);
        }
    }

    public function testAuthenticationLinksKeepTheCanonicalSignPath(): void
    {
        $url = RouterFactory::createRouter()->constructUrl(
            ['presenter' => 'Auth', 'action' => 'in'],
            new UrlScript('https://zvadlo.skauting.cz/', '/'),
        );

        self::assertSame('https://zvadlo.skauting.cz/sign/', $url);
    }
}
