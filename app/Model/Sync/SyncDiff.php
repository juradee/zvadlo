<?php

declare(strict_types=1);

namespace App\Model\Sync;

use App\Model\Registration\Registration;
use App\Model\Skautis\Dto\Participant;

/**
 * What differs between Zvadlo and skautIS for one event.
 */
final readonly class SyncDiff
{
    /**
     * @param list<Registration> $missing  attending here, not yet in skautIS
     * @param list<Registration> $synced   attending here and present in skautIS
     * @param list<Registration> $toRemove no longer attending here, still in skautIS (added by Zvadlo)
     * @param list<Participant>  $foreign  in skautIS only, unknown to Zvadlo (added there by hand)
     * @param list<Registration> $failed   the last attempt ended with an error
     */
    public function __construct(
        public array $missing,
        public array $synced,
        public array $toRemove,
        public array $foreign,
        public array $failed,
    ) {
    }

    public function isInSync(): bool
    {
        return $this->missing === [] && $this->toRemove === [];
    }
}
