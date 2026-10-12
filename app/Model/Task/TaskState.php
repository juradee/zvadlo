<?php

declare(strict_types=1);

namespace App\Model\Task;

enum TaskState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'čeká',
            self::Running => 'běží',
            self::Done => 'hotovo',
            self::Failed => 'selhalo',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
