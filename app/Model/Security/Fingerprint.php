<?php

declare(strict_types=1);

namespace App\Model\Security;

use DateTimeImmutable;
use Nette\Utils\Strings;
use RuntimeException;

/**
 * Keyed fingerprints of personal data (HMAC-SHA256 with APP_SECRET) so that names, birth dates and e-mails
 * of people outside an event never have to be stored in the clear.
 */
final class Fingerprint
{
    public function __construct(private readonly string $appSecret)
    {
        if ($appSecret === '') {
            throw new RuntimeException('APP_SECRET must be set.');
        }
    }

    public function person(string $lastName, string $firstName, DateTimeImmutable $birthDate): string
    {
        return $this->hmac(self::normalize($lastName).'|'.self::normalize($firstName).'|'.$birthDate->format('Y-m-d'));
    }

    public function surname(string $lastName, DateTimeImmutable $birthDate): string
    {
        return $this->hmac(self::normalize($lastName).'|'.$birthDate->format('Y-m-d'));
    }

    public function email(string $email): string
    {
        return $this->hmac(mb_strtolower(trim($email)));
    }

    /**
     * Lower case without diacritics; a hyphen or apostrophe counts as a space ("Malá-Nová" = "Malá Nová").
     */
    public static function normalize(string $value): string
    {
        return Strings::trim(Strings::replace(Strings::lower(Strings::toAscii(Strings::replace($value, "~[-'’]~u", ' '))), '~\s+~', ' '));
    }

    private function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, $this->appSecret);
    }
}
