<?php

declare(strict_types=1);

namespace App\Model\User;

use App\Model\Skautis\Dto\Contact;
use App\Model\Skautis\SkautisGateway;
use Throwable;

/**
 * The logged-in leader's main e-mail from skautIS (one call, inside the leader's request).
 */
final class CurrentUserEmail
{
    private bool $loaded = false;

    private ?string $email = null;

    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly SkautisGateway $skautis,
    ) {
    }

    /**
     * @return string|null null when the person has none or skautIS does not answer
     */
    public function main(): ?string
    {
        if (! $this->loaded) {
            $this->loaded = true;
            try {
                foreach ($this->skautis->getPersonContacts($this->currentUser->getUser()->personId) as $contact) {
                    if ($contact->typeId === Contact::EMAIL && $contact->value !== '') {
                        $this->email = $contact->value;
                        break;
                    }
                }
            } catch (Throwable) {
                $this->email = null;
            }
        }

        return $this->email;
    }
}
