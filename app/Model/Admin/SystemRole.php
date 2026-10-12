<?php

declare(strict_types=1);

namespace App\Model\Admin;

/**
 * Roles of the application itself (not skautIS): admin sees everything in the administration,
 * support only the bug reports. Same as in h.skauting.
 */
enum SystemRole: string
{
    case Admin = 'admin';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'admin (celá administrace)',
            self::Support => 'support (jen hlášení chyb)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }
}
