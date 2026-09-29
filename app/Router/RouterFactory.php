<?php

declare(strict_types=1);

namespace App\Router;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList();

        // Login callbacks must stay at these paths: they are the return URLs registered with the skautIS AppID.
        $router->addRoute('sign/<action>[/back-<backlink>]', 'Auth:in');
        $router->addRoute('prehled', 'Dashboard:default');
        $router->addRoute('akce', 'Events:Overview:default');
        $router->addRoute('akce/nova', 'Events:Event:create');
        $router->addRoute('akce/<id \d+>', 'Events:Event:default');
        $router->addRoute('akce/<id \d+>/upravit', 'Events:Event:edit');
        $router->addRoute('akce/<id \d+>/pole', 'Events:Field:default');
        $router->addRoute('', 'Home:default');
        $router->addRoute('<presenter>/<action>[/<id>]', 'Home:default');

        return $router;
    }
}
