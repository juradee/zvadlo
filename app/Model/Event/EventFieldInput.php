<?php

declare(strict_types=1);

namespace App\Model\Event;

final readonly class EventFieldInput
{
    /**
     * @param list<string> $options
     */
    public function __construct(
        public EventFieldType $type,
        public string $label,
        public ?string $help,
        public bool $required,
        public array $options,
    ) {
    }
}
