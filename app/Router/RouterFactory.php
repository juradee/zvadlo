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
        $router->addRoute('session/keep-alive', 'Session:keepAlive');
        $router->addRoute('nahlasit-problem', 'BugReport:default');
        $router->addRoute('napoveda', 'Help:default');
        $router->addRoute('health', 'Health:default');
        // PWA for leaders: the worker must live at the root so its scope covers the whole application.
        $router->addRoute('manifest.webmanifest', 'Pwa:manifest');
        $router->addRoute('sw.js', 'Pwa:serviceWorker');
        $router->addRoute('offline', 'Pwa:offline');
        $router->addRoute('admin', 'Admin:Dashboard:default');
        $router->addRoute('admin/uzivatele', 'Admin:Users:default');
        $router->addRoute('admin/hlaseni-chyb', 'Admin:BugReports:default');
        $router->addRoute('admin/hlaseni-chyb/<id \d+>', 'Admin:BugReports:detail');
        $router->addRoute('admin/hlaseni-chyb/<id \d+>/screenshot', 'Admin:BugReports:screenshot');
        $router->addRoute('admin/statistiky', 'Admin:Statistics:default');
        $router->addRoute('admin/jednotky', 'Admin:Units:default');
        $router->addRoute('jednotka', 'Unit:Settings:default');
        // Google OAuth redirect URI registered with the client: /google/token
        $router->addRoute('google/<action oauth|token>', 'Google:oauth');
        $router->addRoute('akce', 'Events:Overview:default');
        $router->addRoute('akce/nova', 'Events:Event:create');
        $router->addRoute('akce/novy-tabor[/<tabor \d+>]', 'Events:Event:createCamp');
        $router->addRoute('akce/<id \d+>', 'Events:Event:default');
        $router->addRoute('akce/<id \d+>/upravit', 'Events:Event:edit');
        $router->addRoute('akce/<id \d+>/pole', 'Events:Field:default');
        $router->addRoute('akce/<id \d+>/pozvani', 'Events:Invite:default');
        $router->addRoute('akce/<id \d+>/emaily', 'Events:Email:default');
        $router->addRoute('akce/<id \d+>/web', 'Events:Web:default');
        $router->addRoute('akce/<id \d+>/skautis', 'Events:Sync:default');
        $router->addRoute('akce/<id \d+>/prihlasky', 'Events:Registration:default');
        $router->addRoute('akce/<id \d+>/prihlasky/export', 'Events:Registration:export');
        $router->addRoute('akce/<id \d+>/prihlasky/<rid \d+>', 'Events:Registration:detail');
        // Public pages: the unit id and slug identify the event, the token the person.
        $router->addRoute('<unitId \d+>/<slug [a-z0-9-]+>/prihlaska/<token [A-Za-z0-9_-]+>[/<action>]', 'Web:Registration:default');
        $router->addRoute('<unitId \d+>/<slug [a-z0-9-]+>/prihlasit[/<action>]', 'Web:Public:default');
        $router->addRoute('<unitId \d+>/<slug [a-z0-9-]+>/soubor/<file \d+>', 'Web:Site:file');
        $router->addRoute('<unitId \d+>/<slug [a-z0-9-]+>', 'Web:Site:default');
        $router->addRoute('', 'Home:default');
        $router->addRoute('<presenter>/<action>[/<id>]', 'Home:default');

        return $router;
    }
}
