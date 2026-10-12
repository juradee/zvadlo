<?php

declare(strict_types=1);

namespace App\Model\Task;

/**
 * Work that may take longer than a request: user-started bulk mail, automatic single mails, skautIS sync.
 */
enum TaskType: string
{
    case SendInvitations = 'send_invitations';
    case SendReminder = 'send_reminder';
    case SendInfo = 'send_info';
    case SendEmail = 'send_email';
    case SendLockNotice = 'send_lock_notice';

    public function label(): string
    {
        return match ($this) {
            self::SendInvitations => 'Rozeslání pozvánek',
            self::SendReminder => 'Připomínka pozvánky',
            self::SendInfo => 'Zpráva účastníkům',
            self::SendEmail => 'Automatický e-mail',
            self::SendLockNotice => 'Upozornění na zámek úprav',
        };
    }

    /** How many items one run of the worker handles before saving progress. */
    public function batchSize(): int
    {
        return $this === self::SendEmail ? 1 : 25;
    }

    /** Only one such task per event may be queued or running at a time. */
    public function isExclusivePerEvent(): bool
    {
        return $this !== self::SendEmail;
    }
}
