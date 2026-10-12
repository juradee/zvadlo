<?php

declare(strict_types=1);

namespace App\Model\Registration;

enum RegistrationState: string
{
    case Invited = 'invited';
    case Seen = 'seen';
    case Registered = 'registered';
    case Declined = 'declined';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'pozván',
            self::Seen => 'zobrazeno',
            self::Registered => 'přihlášen',
            self::Declined => 'odmítl',
            self::Waitlisted => 'čekací listina',
            self::Cancelled => 'zrušeno',
            self::Rejected => 'zamítnuto',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Invited => 'text-bg-secondary',
            self::Seen => 'text-bg-info',
            self::Registered => 'text-bg-success',
            self::Declined => 'text-bg-dark',
            self::Waitlisted => 'text-bg-warning',
            self::Cancelled => 'text-bg-light border',
            self::Rejected => 'text-bg-danger',
        };
    }

    /** The person has not answered yet. */
    public function isPending(): bool
    {
        return $this === self::Invited || $this === self::Seen;
    }

    /** Counts as taking part (or wanting to). */
    public function isAttending(): bool
    {
        return $this === self::Registered || $this === self::Waitlisted;
    }

    /**
     * States shown in the default list; a rejected registration is kept only as a record.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $state): bool => $state !== self::Rejected));
    }
}
