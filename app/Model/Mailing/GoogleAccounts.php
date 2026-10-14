<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Security\Crypto;
use DateTimeImmutable;
use Google\Service\Oauth2;
use RuntimeException;

/**
 * Connecting and removing a unit's Gmail accounts (OAuth consent, refresh token stored encrypted).
 */
final class GoogleAccounts
{
    public function __construct(
        private readonly GoogleService $google,
        private readonly GoogleOAuthRepository $accounts,
        private readonly Crypto $crypto,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->google->isConfigured();
    }

    /**
     * @param string $state random value kept in the session and compared when Google calls back (CSRF)
     */
    public function consentUrl(string $state): string
    {
        $client = $this->google->createClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * Exchanges the code Google returned for tokens and stores the account under the unit. An account outside
     * the allowed domains is not stored and its consent is revoked at Google.
     *
     * @param list<string> $allowedDomains see SenderDomains::allowedFor()
     */
    public function connect(int $unitId, string $code, array $allowedDomains): GoogleOAuth
    {
        $client = $this->google->createClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error']) || ! isset($token['refresh_token']) || ! \is_string($token['refresh_token'])) {
            throw new RuntimeException('Google nevrátil trvalý přístup k účtu: '.(string) ($token['error_description'] ?? $token['error'] ?? 'chybí refresh token').'.');
        }
        $client->setAccessToken($token);
        $email = (new Oauth2($client))->userinfo->get()->getEmail();
        if (! \is_string($email) || $email === '') {
            throw new RuntimeException('Google nevrátil e-mail účtu.');
        }
        if (! SenderDomains::isAllowed($email, $allowedDomains)) {
            $client->revokeToken($token['refresh_token']);
            throw new RuntimeException(\sprintf('Účet %s nelze propojit, jednotka smí posílat jen z domén %s.', $email, implode(', ', $allowedDomains)));
        }

        $now = new DateTimeImmutable();
        $encrypted = $this->crypto->encrypt($token['refresh_token']);
        $account = $this->accounts->findByUnitAndEmail($unitId, $email);
        if ($account === null) {
            $account = new GoogleOAuth($unitId, $email, $encrypted, $now);
            $this->accounts->add($account);
        } else {
            $account->replaceToken($encrypted, $now);
            $this->accounts->save();
        }

        return $account;
    }

    public function allowForSubunits(GoogleOAuth $account): void
    {
        $account->allowForSubunits();
        $this->accounts->save();
    }

    public function disallowForSubunits(GoogleOAuth $account): void
    {
        $account->disallowForSubunits();
        $this->accounts->detachFromOtherUnits($account);
        $this->accounts->save();
    }

    public function remove(GoogleOAuth $account): void
    {
        $this->accounts->remove($account);
    }
}
