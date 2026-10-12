<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Skautis\Dto\PersonDetail;
use App\Model\Skautis\SkautisException;
use App\Model\Skautis\SkautisGateway;
use App\Model\User\LoginFailedException;
use DateTimeImmutable;
use Nette\Http\Session;
use Nette\Http\SessionSection;

/**
 * A participant (or parent) logs into skautIS from the public event page (4.6). The token is used for three
 * calls inside one request and dropped again: no leader identity is created, the participant only gets
 * a short session with the persons they may sign up (themselves and their children).
 */
final class SkautisParticipantLogin
{
    private const string EXPIRATION = '1 hour';

    private readonly SessionSection $section;

    public function __construct(
        private readonly SkautisGateway $gateway,
        Session $session,
    ) {
        $this->section = $session->getSection('registration.skautis');
    }

    /**
     * What skautIS gets as ReturnUrl and hands back after the login: enough to find the event page again.
     */
    public static function backlink(int $unitId, string $slug, string $webToken): string
    {
        return \sprintf('p:%d:%s:%s', $unitId, $slug, $webToken);
    }

    /**
     * @return array{unitId: int, slug: string, t: string}|null
     */
    public static function parseBacklink(?string $value): ?array
    {
        if ($value === null || preg_match('~^p:(\d{1,10}):([a-z0-9-]{1,120}):([A-Za-z0-9_-]{8,128})$~', $value, $match) !== 1) {
            return null;
        }

        return ['unitId' => (int) $match[1], 'slug' => $match[2], 't' => $match[3]];
    }

    /**
     * @param array<string, mixed> $post what skautIS posted back
     *
     * @throws LoginFailedException
     */
    public function loginFromSkautis(array $post): void
    {
        // The member's token must never outlive this request (spec 4.6, 7.1), and a leader logged in in the
        // same browser keeps their own login.
        try {
            $candidates = $this->gateway->withTemporaryLogin($post, function (): array {
                if (! $this->gateway->isLoggedIn()) {
                    throw new LoginFailedException('skautIS did not confirm the login.');
                }
                $account = $this->gateway->getCurrentUser();
                $self = $this->gateway->getPersonDetail($account->personId);
                $candidates = $self === null ? [] : [$self];
                foreach ($this->gateway->getPersonChildren($account->personId) as $child) {
                    $candidates[] = $child;
                }

                return $candidates;
            });
        } catch (SkautisException $exception) {
            throw new LoginFailedException($exception->getMessage(), 0, $exception);
        }

        if ($candidates === []) {
            throw new LoginFailedException('skautIS did not return the person of the account.');
        }

        $this->section->set('candidates', array_map(static fn (PersonDetail $person): array => [
            'id' => $person->id,
            'firstName' => $person->firstName,
            'lastName' => $person->lastName,
            'nickName' => $person->nickName,
            'birthday' => $person->birthday?->format('Y-m-d'),
            'sex' => $person->sex,
            'email' => $person->email,
            'phone' => $person->phone,
        ], $candidates), self::EXPIRATION);
    }

    /**
     * @return list<PersonDetail> the account's person first, then the children
     */
    public function getCandidates(): array
    {
        $stored = $this->section->get('candidates');
        if (! \is_array($stored)) {
            return [];
        }

        $candidates = [];
        foreach ($stored as $row) {
            if (! \is_array($row)) {
                continue;
            }
            $candidates[] = new PersonDetail(
                (int) $row['id'],
                (string) $row['firstName'],
                (string) $row['lastName'],
                (string) $row['nickName'],
                \is_string($row['birthday']) ? new DateTimeImmutable($row['birthday']) : null,
                \is_string($row['sex']) ? $row['sex'] : null,
                \is_string($row['email']) ? $row['email'] : null,
                \is_string($row['phone']) ? $row['phone'] : null,
            );
        }

        return $candidates;
    }

    public function findCandidate(int $personId): ?PersonDetail
    {
        foreach ($this->getCandidates() as $candidate) {
            if ($candidate->id === $personId) {
                return $candidate;
            }
        }

        return null;
    }

    public function forget(): void
    {
        $this->section->remove('candidates');
    }
}
