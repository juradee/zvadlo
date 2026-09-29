<?php

declare(strict_types=1);

namespace Webwings\SkautisNette\Tests;

use PHPUnit\Framework\TestCase;
use Skaut\Skautis\InvalidArgumentException;
use stdClass;
use Webwings\SkautisNette\Fixture\FixtureNotFoundException;
use Webwings\SkautisNette\Fixture\FixtureWebServiceFactory;

final class FixtureWebServiceTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../resources/fixtures';

    public function testPlainAndVariantFixtures(): void
    {
        $factory = new FixtureWebServiceFactory(self::FIXTURES);
        $service = $factory->createWebService('https://test-is.skaut.cz/JunakWebservice/OrganizationUnit.asmx?WSDL', []);

        $unit = $service->call('UnitDetail', [['ID' => 1002]]);
        self::assertInstanceOf(stdClass::class, $unit);
        self::assertSame('Středisko Sluneční hodiny', $unit->DisplayName);

        self::assertNull($service->call('UnitDetail', [['ID' => 9999]]));

        $persons = $service->call('PersonAll', [['ID_Unit' => 1003, 'OnlyDirectMember' => false]]);
        self::assertIsArray($persons);
        self::assertCount(3, $persons);
    }

    public function testMagicCallAndCaseInsensitiveMethodName(): void
    {
        $service = (new FixtureWebServiceFactory(self::FIXTURES))
            ->createWebService('https://test-is.skaut.cz/JunakWebservice/UserManagement.asmx?WSDL', []);

        $detail = $service->userDetail();

        self::assertInstanceOf(stdClass::class, $detail);
        self::assertSame(100, $detail->ID_Person);
    }

    public function testMissingFixtureNamesTheFile(): void
    {
        $service = (new FixtureWebServiceFactory(self::FIXTURES))
            ->createWebService('https://test-is.skaut.cz/JunakWebservice/Events.asmx?WSDL', []);

        $this->expectException(FixtureNotFoundException::class);
        $this->expectExceptionMessage('Events/EventCampDetail.json');
        $service->call('EventCampDetail', [['ID' => 1]]);
    }

    public function testUnknownDirectoryIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FixtureWebServiceFactory(__DIR__.'/does-not-exist');
    }

    public function testServiceNameMustBeInTheUrl(): void
    {
        $factory = new FixtureWebServiceFactory(self::FIXTURES);

        $this->expectException(InvalidArgumentException::class);
        $factory->createWebService('https://example.com/nonsense', []);
    }
}
