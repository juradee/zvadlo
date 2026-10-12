<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationState;
use App\Model\Task\TaskQueue;
use App\Model\Task\TaskType;

/**
 * Queues the e-mails the system sends on its own after something happens to a registration.
 */
final class AutomaticMails
{
    public function __construct(
        private readonly TaskQueue $queue,
        private readonly MailComposer $composer,
    ) {
    }

    public function afterAnswer(Registration $registration, RegistrationState $previous): void
    {
        $state = $registration->getState();
        $type = match (true) {
            $state === RegistrationState::Declined => EmailType::Declined,
            $previous->isAttending() => EmailType::Changed,
            $state->isAttending() => EmailType::Confirmation,
            default => null,
        };

        if ($type !== null) {
            $this->queueIfEnabled($registration, $type);
        }
    }

    /**
     * Moved from the waiting list to the participants (a freed place or the leader's decision).
     */
    public function afterPromotion(Registration $registration): void
    {
        $this->queueIfEnabled($registration, EmailType::Promoted);
    }

    public function afterChangeByLeader(Registration $registration): void
    {
        $this->queueIfEnabled($registration, EmailType::Changed);
    }

    /**
     * Called before the personal data are removed: the text and the addresses travel in the task itself.
     */
    public function beforeReject(Registration $registration, ?string $reason): void
    {
        $to = $registration->getMailAddresses();
        if ($to === []) {
            return;
        }

        $mail = $this->composer->compose($registration->getEvent(), EmailType::Rejected, $registration, ['poznamka' => $reason ?? '']);
        $this->queue->enqueue(TaskType::SendEmail, $registration->getEvent(), [
            'type' => EmailType::Rejected->value,
            'rendered' => true,
            'subject' => $mail->subject,
            'body' => $mail->text,
            'html' => $mail->html,
            'to' => $to,
        ], [$registration], null);
    }

    private function queueIfEnabled(Registration $registration, EmailType $type): void
    {
        $event = $registration->getEvent();
        if (! $event->isEmailEnabled($type) || $registration->getMailAddresses() === []) {
            return;
        }

        $this->queue->enqueue(TaskType::SendEmail, $event, ['type' => $type->value], [$registration], null);
    }
}
