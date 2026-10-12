<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Skautis\Dto\Contact;
use App\Model\Skautis\Dto\ParentContact;

/**
 * Where an invitation e-mail address came from; the same types a leader picks from in h.skauting.
 */
enum RecipientSource: string
{
    case Mother = 'mother';
    case Father = 'father';
    case Main = 'main';
    case Other = 'other';
    case Manual = 'manual';

    public static function fromParent(ParentContact $parent): self
    {
        return match ($parent->parentTypeId) {
            ParentContact::MOTHER => self::Mother,
            ParentContact::FATHER => self::Father,
            default => self::Other,
        };
    }

    public static function fromContact(Contact $contact): self
    {
        return $contact->typeId === Contact::EMAIL ? self::Main : self::Other;
    }

    public function label(): string
    {
        return match ($this) {
            self::Mother => 'matka',
            self::Father => 'otec',
            self::Main => 'hlavní e-mail',
            self::Other => 'další e-mail',
            self::Manual => 'zadáno ručně',
        };
    }

    public function isParent(): bool
    {
        return $this === self::Mother || $this === self::Father;
    }

    /**
     * Types a leader can tick for every member at once on the invitation page, as in h.skauting.
     *
     * @return list<self>
     */
    public static function bulkSelectable(): array
    {
        return [self::Mother, self::Father, self::Main, self::Other];
    }
}
