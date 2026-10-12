<?php

declare(strict_types=1);

namespace App\Model\Registration;

/**
 * What the fingerprint match said about a public registration that is not paired with a skautIS person.
 */
enum PairingHint: string
{
    case Possible = 'possible';

    public function label(): string
    {
        return 'možná shoda se členem';
    }
}
