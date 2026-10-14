<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail;
use Nette\Mail\Mailer;
use Nette\Mail\Message;

/**
 * Sends through the Gmail API as the connected account (users.messages.send), like h.skauting.
 */
final class OAuthMailer implements Mailer
{
    private readonly Gmail $gmail;

    public function __construct(GoogleService $google, string $refreshToken)
    {
        $client = $google->createClient();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (isset($token['error'])) {
            $message = (string) $token['error'];
            if (isset($token['error_description'])) {
                $message .= ': '.$token['error_description'];
            }

            throw new InvalidOAuthException($message.' Je potřeba odebrat a znovu propojit e-mailový účet.');
        }

        $client->setAccessToken($token);
        $this->gmail = new Gmail($client);
    }

    public function send(Message $mail): void
    {
        $message = new Gmail\Message();
        $message->setRaw(rtrim(strtr(base64_encode($mail->generateMessage()), '+/', '-_'), '='));
        try {
            $this->gmail->users_messages->send('me', $message);
        } catch (GoogleServiceException $exception) {
            // The consent screen lets the leader untick "send e-mail"; such an account can never send.
            if ($exception->getCode() === 403 && \in_array('insufficientPermissions', array_column($exception->getErrors() ?? [], 'reason'), true)) {
                throw new InvalidOAuthException(GoogleService::MISSING_SEND_SCOPE, 0, $exception);
            }

            throw $exception;
        }
    }
}
