<?php

declare(strict_types=1);

namespace App\Model\Registration;

use Nette\Http\Session;
use Nette\Http\SessionSection;

/**
 * Remembers in the browser session which registrations were unlocked with the right birth date.
 */
final class VerificationSession
{
    private const string EXPIRATION = '4 hours';

    private readonly SessionSection $section;

    public function __construct(Session $session)
    {
        $this->section = $session->getSection('registration.verified');
    }

    public function isVerified(Registration $registration): bool
    {
        return $this->section->get(self::key($registration)) === $registration->getTokenHash();
    }

    public function markVerified(Registration $registration): void
    {
        $this->section->set(self::key($registration), $registration->getTokenHash(), self::EXPIRATION);
    }

    public function forget(Registration $registration): void
    {
        $this->section->remove(self::key($registration));
    }

    private static function key(Registration $registration): string
    {
        return 'r'.$registration->getId();
    }
}
