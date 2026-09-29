<?php

declare(strict_types=1);

namespace Webwings\SkautisNette\Tests;

use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;
use Nette\Http\UrlScript;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Webwings\SkautisNette\SessionAdapter;

final class SessionAdapterTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testStoresValuesInTheNetteSession(): void
    {
        $session = new Session(new Request(new UrlScript('http://localhost/')), new Response());
        $adapter = new SessionAdapter($session);

        self::assertFalse($adapter->has('login'));
        self::assertNull($adapter->get('login'));

        $adapter->set('login', ['ID_Login' => 'token']);

        self::assertTrue($adapter->has('login'));
        self::assertSame(['ID_Login' => 'token'], $adapter->get('login'));
        self::assertSame(['ID_Login' => 'token'], (new SessionAdapter($session))->get('login'));
    }
}
