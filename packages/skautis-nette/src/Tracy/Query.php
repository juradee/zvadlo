<?php

declare(strict_types=1);

namespace Webwings\SkautisNette\Tracy;

final readonly class Query
{
    /**
     * @param array<int|string, mixed>         $args
     * @param array<int, array<string, mixed>> $trace
     */
    public function __construct(
        public string $name,
        public array $args,
        public float $duration,
        public mixed $result,
        public ?string $error,
        public array $trace,
    ) {
    }
}
