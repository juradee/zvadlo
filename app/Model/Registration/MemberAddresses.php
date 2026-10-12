<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Skautis\Dto\Contact;
use App\Model\Skautis\SkautisGateway;
use DateTimeImmutable;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * Every e-mail skautIS knows for a member (parents of a child, own addresses), offered on the invitation page,
 * with the default choice: parents for a child (their own main e-mail when no parent has one), the main
 * e-mail for an adult. Cached for an hour like the member list, because the page loads it in AJAX batches.
 */
final class MemberAddresses
{
    public const int ADULT_AGE = 18;

    private readonly Cache $cache;

    public function __construct(
        private readonly SkautisGateway $gateway,
        Storage $storage,
    ) {
        $this->cache = new Cache($storage, 'skautis.contacts');
    }

    public function find(int $personId, ?DateTimeImmutable $birthDate, DateTimeImmutable $now): MemberContacts
    {
        $isChild = $birthDate !== null && $birthDate->diff($now)->y < self::ADULT_AGE;

        /** @var MemberContacts $contacts */
        $contacts = $this->cache->load('person-'.$personId.($isChild ? '-child' : ''), function (?array &$dependencies) use ($personId, $isChild): MemberContacts {
            $dependencies = [Cache::Expire => '1 hour'];

            return $this->load($personId, $isChild);
        });

        return $contacts;
    }

    private function load(int $personId, bool $isChild): MemberContacts
    {
        $addresses = [];
        $seen = [];
        $add = static function (string $email, ?string $name, RecipientSource $source, bool $selected) use (&$addresses, &$seen): void {
            $email = mb_strtolower(trim($email));
            if ($email !== '' && ! isset($seen[$email])) {
                $seen[$email] = true;
                $addresses[] = new FoundAddress($email, $name, $source, $selected);
            }
        };

        // An adult gets mail on their own address; the parents are not asked for at all.
        $parentsFound = false;
        if ($isChild) {
            foreach ($this->gateway->getPersonParents($personId) as $parent) {
                if (trim($parent->email) !== '') {
                    $add($parent->email, $parent->parentType, RecipientSource::fromParent($parent), true);
                    $parentsFound = true;
                }
            }
        }

        $phone = null;
        foreach ($this->gateway->getPersonContacts($personId) as $contact) {
            if (str_starts_with($contact->typeId, 'email')) {
                $source = RecipientSource::fromContact($contact);
                $add($contact->value, null, $source, $source === RecipientSource::Main && ! $parentsFound);
            } elseif ($contact->typeId === Contact::PHONE) {
                $phone ??= $contact->value;
            }
        }

        return new MemberContacts($addresses, $phone);
    }
}
