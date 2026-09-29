<?php

declare(strict_types=1);

namespace Webwings\SkautisNette\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Skaut\Skautis\Config;
use Skaut\Skautis\Skautis;
use Skaut\Skautis\User;
use Skaut\Skautis\Wsdl\WebService;
use Skaut\Skautis\Wsdl\WebServiceFactory;
use Skaut\Skautis\Wsdl\WebServiceFactoryInterface;
use Skaut\Skautis\Wsdl\WsdlManager;
use stdClass;
use Tracy\Bar;
use Webwings\SkautisNette\EventDispatcher;
use Webwings\SkautisNette\Fixture\FixtureWebServiceFactory;
use Webwings\SkautisNette\SessionAdapter;
use Webwings\SkautisNette\Tracy\Panel;
use Webwings\SkautisNette\Tracy\QueryLog;

/**
 * Registers the skaut/skautis services. Configuration keys match skautis/nette, plus `fixtures`.
 */
final class SkautisExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'applicationId' => Expect::string()->required(),
            'testMode' => Expect::bool(true),
            'cache' => Expect::bool(true),
            'compression' => Expect::bool(true),
            'profiler' => Expect::bool()->nullable(),
            'fixtures' => Expect::string()->nullable(),
        ]);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        /** @var stdClass $config */
        $config = $this->getConfig();

        $builder->addDefinition($this->prefix('config'))
            ->setFactory(Config::class, [$config->applicationId, $config->testMode, $config->cache, $config->compression]);

        $dispatcher = $builder->addDefinition($this->prefix('eventDispatcher'))
            ->setFactory(EventDispatcher::class);

        $factory = $builder->addDefinition($this->prefix('webServiceFactory'))
            ->setType(WebServiceFactoryInterface::class);
        if ($config->fixtures !== null) {
            $factory->setFactory(FixtureWebServiceFactory::class, [$config->fixtures]);
        } else {
            $factory->setFactory(WebServiceFactory::class, [WebService::class, $this->prefix('@eventDispatcher')]);
        }

        $builder->addDefinition($this->prefix('wsdlManager'))
            ->setFactory(WsdlManager::class);

        $builder->addDefinition($this->prefix('session'))
            ->setFactory(SessionAdapter::class);

        $builder->addDefinition($this->prefix('user'))
            ->setFactory(User::class);

        $builder->addDefinition($this->prefix('skautis'))
            ->setFactory(Skautis::class);

        $profiler = $config->profiler ?? ($builder->parameters['debugMode'] ?? false) === true;
        if ($profiler && class_exists(Bar::class)) {
            $builder->addDefinition($this->prefix('queryLog'))
                ->setFactory(QueryLog::class)
                ->setAutowired(false);
            $builder->addDefinition($this->prefix('panel'))
                ->setFactory(Panel::class, [$this->prefix('@queryLog')])
                ->setAutowired(false);
            $dispatcher->addSetup('addListener', [$this->prefix('@queryLog')]);
        }
    }

    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();
        if (! $builder->hasDefinition($this->prefix('panel'))) {
            return;
        }

        $bar = $builder->getByType(Bar::class);
        $definition = $bar === null ? null : $builder->getDefinition($bar);
        if ($definition instanceof ServiceDefinition) {
            $definition->addSetup('addPanel', [$this->prefix('@panel')]);
        }
    }
}
