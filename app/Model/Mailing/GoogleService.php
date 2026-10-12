<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Oauth2;

/**
 * Builds the Google client the same way h.skauting does: offline access, Gmail send and the account e-mail.
 */
final class GoogleService
{
    public function __construct(
        private readonly string $credentialsFile,
        private readonly string $redirectUri,
    ) {
    }

    public function isConfigured(): bool
    {
        return is_file($this->credentialsFile);
    }

    public function createClient(): Client
    {
        $client = new Client();
        $client->setApplicationName('Zvadlo');
        $client->setScopes([Gmail::GMAIL_SEND, Oauth2::USERINFO_EMAIL]);
        $client->setAuthConfig($this->credentialsFile);
        $client->setRedirectUri($this->redirectUri);
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->setPrompt('select_account consent');

        return $client;
    }
}
