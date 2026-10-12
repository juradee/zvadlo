<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Event\Event;
use App\Model\Registration\Registration;
use App\Model\Task\Task;
use DateTimeImmutable;
use Throwable;

/**
 * Sends one composed e-mail to its addresses through the event's account and logs every copy.
 */
final class EventMailer
{
    public function __construct(
        private readonly MailComposer $composer,
        private readonly MailerFactory $mailers,
        private readonly EmailMessageRepository $messages,
    ) {
    }

    /**
     * @param list<array{email: string, name: string|null}> $to
     *
     * @return list<EmailMessage>
     *
     * @throws InvalidOAuthException when the account itself is broken (nothing was sent)
     */
    public function send(Event $event, EmailType $type, ?Registration $registration, ?Task $task, array $to, ComposedMail $mail): array
    {
        $mailer = $this->mailers->forEvent($event);
        $logged = [];

        foreach ($to as $recipient) {
            $error = null;
            try {
                $mailer->send($this->composer->message($event, $mail, [$recipient]));
            } catch (InvalidOAuthException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            $message = new EmailMessage($task, $event, $registration, $type, $recipient['email'], $recipient['name'], $mail->subject, $mail->text, $event->getMailAccount(), $error, new DateTimeImmutable());
            $this->messages->add($message);
            $logged[] = $message;
        }

        return $logged;
    }

    /**
     * Composes from the templates and sends to the registration's addresses.
     *
     * @param array<string, string> $extra
     *
     * @return list<EmailMessage>
     */
    public function sendToRegistration(Registration $registration, EmailType $type, ?Task $task, array $extra = []): array
    {
        $event = $registration->getEvent();
        $to = self::addresses($registration, $type);
        if ($to === []) {
            return [];
        }

        return $this->send($event, $type, $registration, $task, $to, $this->composer->compose($event, $type, $registration, $extra));
    }

    /**
     * Invitations and reminders go to the selected invitation addresses; everything else to the contact e-mail.
     *
     * @return list<array{email: string, name: string|null}>
     */
    public static function addresses(Registration $registration, EmailType $type): array
    {
        if ($type === EmailType::Invitation || $type === EmailType::Reminder) {
            return array_map(
                static fn (\App\Model\Registration\RegistrationRecipient $recipient): array => ['email' => $recipient->getEmail(), 'name' => $recipient->getName()],
                $registration->getSelectedRecipients(),
            );
        }

        return array_map(static fn (string $email): array => ['email' => $email, 'name' => $registration->hasName() ? $registration->getDisplayName() : null], $registration->getMailAddresses());
    }
}
