<?php

declare(strict_types=1);

namespace App\Model\Registration;

final readonly class RetentionResult
{
    public function __construct(
        public int $eventsForgotten,
        public int $answersForgotten,
        public int $anonymizedSynced,
        public int $anonymizedUnsynced,
        public int $fingerprintUnits,
    ) {
    }

    public function describe(): string
    {
        return \sprintf(
            '%d events with %d registrations lost their answers, %d non-members with a skautIS person and %d without one were anonymised, fingerprints of %d units deleted.',
            $this->eventsForgotten,
            $this->answersForgotten,
            $this->anonymizedSynced,
            $this->anonymizedUnsynced,
            $this->fingerprintUnits,
        );
    }
}
