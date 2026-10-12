<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationRepository;
use App\Model\Task\Task;
use App\Model\Task\TaskHandler;
use App\Model\Task\TaskType;
use DateTimeImmutable;
use RuntimeException;

/**
 * Sends the task's e-mail to every registration that has none logged for this task yet.
 */
final class SendEmailsHandler implements TaskHandler
{
    public function __construct(
        private readonly EventMailer $mailer,
        private readonly MailComposer $composer,
        private readonly EmailMessageRepository $messages,
        private readonly RegistrationRepository $registrations,
    ) {
    }

    public function supports(TaskType $type): bool
    {
        return \in_array($type, TaskType::cases(), true);
    }

    public function handleBatch(Task $task): bool
    {
        $event = $task->getEvent() ?? throw new RuntimeException('A mail task needs an event.');
        $type = EmailType::from($task->getPayloadString('type'));
        $handled = $this->messages->findHandledRegistrationIds($task);
        $pending = array_values(array_filter($task->getRegistrations(), static fn (Registration $registration): bool => ! \in_array($registration->getId(), $handled, true)));

        foreach (\array_slice($pending, 0, $task->getType()->batchSize()) as $registration) {
            $this->sendOne($task, $type, $registration);
            $handled[] = $registration->getId();
            $task->advance(\count($handled));
        }

        return \count($pending) <= $task->getType()->batchSize();
    }

    private function sendOne(Task $task, EmailType $type, Registration $registration): void
    {
        $event = $registration->getEvent();
        $now = new DateTimeImmutable();

        // A rejection was rendered before the data were removed; the payload carries the text and the addresses.
        if ($task->getPayload()['rendered'] ?? false) {
            $to = [];
            foreach ((array) ($task->getPayload()['to'] ?? []) as $email) {
                if (\is_string($email)) {
                    $to[] = ['email' => $email, 'name' => null];
                }
            }
            $mail = new ComposedMail($task->getPayloadString('subject'), $task->getPayloadString('body'), $task->getPayloadString('html'));
            $messages = $this->mailer->send($event, $type, $registration, $task, $to, $mail);
        } else {
            $messages = $this->mailer->sendToRegistration($registration, $type, $task, ['poznamka' => $task->getPayloadString('note')]);
        }

        if ($messages === []) {
            // Nothing to send to: log it so the task does not loop on this registration.
            $this->messages->add(new EmailMessage($task, $event, $registration, $type, '-', null, $this->composer->compose($event, $type, $registration)->subject, '', $event->getMailAccount(), 'Přihláška nemá žádnou adresu.', $now));

            return;
        }

        if ($type === EmailType::Invitation && $registration->getInvitedAt() === null) {
            $registration->markInvited($now);
            $this->registrations->save();
        }
    }
}
