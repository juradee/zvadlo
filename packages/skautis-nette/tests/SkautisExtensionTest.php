<?php

declare(strict_types=1);

namespace Webwings\SkautisNette\Tests;

use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Skaut\Skautis\Skautis;
use Skaut\Skautis\Wsdl\Event\RequestPostEvent;
use Skaut\Skautis\Wsdl\WebServiceFactory;
use stdClass;
use Tracy\Bar;
use Webwings\SkautisNette\DI\SkautisExtension;
use Webwings\SkautisNette\EventDispatcher;
use Webwings\SkautisNette\Fixture\FixtureWebServiceFactory;
use Webwings\SkautisNette\Tracy\Panel;
use Webwings\SkautisNette\Tracy\QueryLog;

final class SkautisExtensionTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testFixturesAnswerTheWholeLoginFlow(): void
    {
        $container = $this->createContainer(fixtures: __DIR__.'/../resources/fixtures', debugMode: false);

        self::assertInstanceOf(FixtureWebServiceFactory::class, $container->getService('skautis.webServiceFactory'));
        self::assertFalse($container->hasService('skautis.panel'));

        $skautis = $container->getByType(Skautis::class);
        $skautis->setLoginData([
            'skautIS_Token' => '00000000-0000-4000-8000-000000000001',
            'skautIS_IDRole' => '10',
            'skautIS_IDUnit' => '1003',
            'skautIS_DateLogout' => '31. 12. 2099 23:59:59',
        ]);

        self::assertTrue($skautis->getUser()->isLoggedIn(true));
        self::assertSame(1003, $skautis->getUser()->getUnitId());

        $detail = $skautis->getWebService('user')->call('UserDetail');
        self::assertInstanceOf(stdClass::class, $detail);
        self::assertSame('jan.novak', $detail->UserName);
    }

    #[RunInSeparateProcess]
    public function testRealFactoryWithPanelInDebugMode(): void
    {
        $container = $this->createContainer(fixtures: null, debugMode: true);

        self::assertInstanceOf(WebServiceFactory::class, $container->getService('skautis.webServiceFactory'));
        self::assertTrue($container->hasService('skautis.panel'));

        $container->getByType(EventDispatcher::class)
            ->dispatch(new RequestPostEvent('UnitDetail', [['ID' => 1]], null, 0.25, []));

        $log = $container->getService('skautis.queryLog');
        self::assertInstanceOf(QueryLog::class, $log);
        self::assertCount(1, $log->getQueries());

        $panel = $container->getService('skautis.panel');
        self::assertInstanceOf(Panel::class, $panel);
        self::assertStringContainsString('1 skautIS', $panel->getTab());
        self::assertStringContainsString('UnitDetail', $panel->getPanel());
        self::assertSame($panel, $container->getByType(Bar::class)->getPanel(Panel::class));
    }

    private function createContainer(?string $fixtures, bool $debugMode): Container
    {
        $tempDir = sys_get_temp_dir().'/skautis-nette-'.getmypid();
        @mkdir($tempDir, 0777, true);

        $configurator = new Configurator();
        $configurator->setTempDirectory($tempDir);
        $configurator->setDebugMode($debugMode);
        $configurator->addConfig([
            'extensions' => ['skautis' => SkautisExtension::class],
            'skautis' => ['applicationId' => 'test-app-id', 'fixtures' => $fixtures],
        ]);

        return $configurator->createContainer();
    }
}
