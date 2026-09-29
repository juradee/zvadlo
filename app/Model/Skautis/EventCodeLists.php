<?php

declare(strict_types=1);

namespace App\Model\Skautis;

use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * Types and scopes of general events from skautIS, cached for a day.
 */
final class EventCodeLists
{
    private readonly Cache $cache;

    public function __construct(
        private readonly SkautisGateway $gateway,
        Storage $storage,
    ) {
        $this->cache = new Cache($storage, 'skautis.codelists');
    }

    /**
     * @return array<string, string> id => name
     */
    public function getEventTypes(): array
    {
        return $this->cached('event-types', fn (): array => $this->gateway->getEventTypes());
    }

    /**
     * @return array<string, string> id => name
     */
    public function getEventScopes(): array
    {
        return $this->cached('event-scopes', fn (): array => $this->gateway->getEventScopes());
    }

    /**
     * @param callable(): array<string, string> $loader
     *
     * @return array<string, string>
     */
    private function cached(string $key, callable $loader): array
    {
        /** @var array<string, string> $items */
        $items = $this->cache->load($key, static function (?array &$dependencies) use ($loader): array {
            $dependencies = [Cache::Expire => '1 day'];

            return $loader();
        });

        return $items;
    }
}
