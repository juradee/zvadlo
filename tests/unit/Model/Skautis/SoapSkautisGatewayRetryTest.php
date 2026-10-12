<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Skautis;

use App\Model\Skautis\RetryingSkautisCall;
use App\Model\Skautis\SkautisException;
use App\Model\Skautis\SoapSkautisGateway;
use Codeception\Test\Unit;
use Mockery;
use Mockery\MockInterface;
use Skaut\Skautis\Skautis;
use Skaut\Skautis\Wsdl\WebServiceInterface;
use Skaut\Skautis\Wsdl\WebServiceName;
use Skaut\Skautis\Wsdl\WsdlException;

final class SoapSkautisGatewayRetryTest extends Unit
{
    protected function _after(): void
    {
        Mockery::close();
    }

    public function testRetriesReadCalls(): void
    {
        $service = Mockery::mock(WebServiceInterface::class);
        $attempts = 0;
        $service->shouldReceive('call')
            ->twice()
            ->with('EventGeneralTypeAll', [[]])
            ->andReturnUsing(static function () use (&$attempts): array {
                ++$attempts;
                if ($attempts === 1) {
                    throw new WsdlException('Could not connect to host');
                }

                return [];
            });

        $skautis = $this->skautis($service);
        $gateway = new SoapSkautisGateway($skautis, new RetryingSkautisCall());

        self::assertSame([], $gateway->getEventTypes());
    }

    public function testDoesNotRetryWriteCalls(): void
    {
        $service = Mockery::mock(WebServiceInterface::class);
        $service->shouldReceive('call')
            ->once()
            ->with('ParticipantGeneralDelete', [['ID' => 42, 'DeletePerson' => false]])
            ->andThrow(new WsdlException('Could not connect to host'));

        $skautis = $this->skautis($service);
        $gateway = new SoapSkautisGateway($skautis, new RetryingSkautisCall());

        $this->expectException(SkautisException::class);
        $gateway->removeEventParticipant(42);
    }

    /** @return Skautis&MockInterface */
    private function skautis(WebServiceInterface $service): Skautis
    {
        /** @var Skautis&MockInterface $skautis */
        $skautis = Mockery::mock(Skautis::class);
        $skautis->shouldReceive('getWebService')
            ->with(WebServiceName::EVENTS)
            ->andReturn($service);

        return $skautis;
    }
}
