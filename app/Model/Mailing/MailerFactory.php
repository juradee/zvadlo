<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Security\Crypto;
use Nette\Mail\Mailer;
use Nette\Mail\SendmailMailer;

/**
 * Picks the transport: with SEND_EMAIL=false everything goes to the configured debug mailer (Mailpit);
 * otherwise event mail leaves through the unit's Gmail account and system mail through sendmail.
 */
final class MailerFactory
{
    public function __construct(
        private readonly Mailer $debugMailer,
        private readonly bool $sendEmail,
        private readonly GoogleService $google,
        private readonly Crypto $crypto,
    ) {
    }

    /**
     * @throws InvalidOAuthException
     */
    public function forAccount(GoogleOAuth $account): Mailer
    {
        if (! $this->sendEmail) {
            return $this->debugMailer;
        }

        return new OAuthMailer($this->google, $this->crypto->decrypt($account->getRefreshTokenEncrypted()));
    }

    /**
     * Without a real transport (dev, ci) nothing needs a Gmail account; everything lands in Mailpit.
     */
    public function requiresAccount(): bool
    {
        return $this->sendEmail;
    }

    public function forEvent(\App\Model\Event\Event $event): Mailer
    {
        $account = $event->getMailAccount();
        if (! $this->sendEmail) {
            return $this->debugMailer;
        }
        if ($account === null) {
            throw new InvalidOAuthException('Akce nemá vybraný odesílací e-mailový účet.');
        }

        return $this->forAccount($account);
    }

    public function system(): Mailer
    {
        return $this->sendEmail ? new SendmailMailer() : $this->debugMailer;
    }
}
