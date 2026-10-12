<?php

declare(strict_types=1);

namespace App\Model\Registration;

/**
 * What skautIS knows to reach a member: the e-mails to choose from and the main phone.
 */
final readonly class MemberContacts
{
    /**
     * @param list<FoundAddress> $addresses
     */
    public function __construct(
        public array $addresses,
        public ?string $phone,
    ) {
    }

    public function find(string $email): ?FoundAddress
    {
        $email = mb_strtolower(trim($email));
        foreach ($this->addresses as $address) {
            if ($address->email === $email) {
                return $address;
            }
        }

        return null;
    }
}
