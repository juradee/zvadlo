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
        public int $membersForgotten,
        public int $emailsErased,
        public int $tasksDeleted,
        public int $fingerprintUnits,
    ) {
    }

    public function describe(): string
    {
        return \sprintf(
            '%d events with %d registrations lost their answers, %d non-members with a skautIS person and %d without one were anonymised, %d members kept only name and skautIS ID, %d logged e-mails lost address and text, %d finished tasks deleted, fingerprints of %d units deleted.',
            $this->eventsForgotten,
            $this->answersForgotten,
            $this->anonymizedSynced,
            $this->anonymizedUnsynced,
            $this->membersForgotten,
            $this->emailsErased,
            $this->tasksDeleted,
            $this->fingerprintUnits,
        );
    }
}
