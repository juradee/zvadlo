<?php

declare(strict_types=1);

namespace App\Tests\Support\Helper;

use App\Bootstrap;
use Codeception\Module;
use Codeception\TestInterface;
use Nette\DI\Container;

/**
 * Builds the application container for integration tests, a fresh one per test.
 */
final class NetteContainer extends Module
{
    private ?Container $container = null;

    public function _before(TestInterface $test): void
    {
        $this->container = null;
    }

    public function getContainer(): Container
    {
        return $this->container ??= Bootstrap::boot()->createContainer();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    public function grabService(string $type): object
    {
        return $this->getContainer()->getByType($type);
    }
}
