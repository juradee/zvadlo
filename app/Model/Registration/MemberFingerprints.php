<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Security\Fingerprint;
use App\Model\Skautis\Dto\Person;
use App\Model\Skautis\MemberDirectory;
use App\Model\Skautis\SkautisException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Skaut\Skautis\Wsdl\PermissionException;
use Tracy\ILogger;

/**
 * Keeps fingerprints of unit members up to date while a leader is around (skautIS is reachable only then)
 * and matches public sign-ups against them when nobody is logged in.
 */
final class MemberFingerprints
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MemberDirectory $members,
        private readonly Fingerprint $fingerprint,
        private readonly ILogger $logger,
    ) {
    }

    /**
     * Refresh after creating an event or switching public sign-up on: a skautIS failure keeps the old fingerprints
     * and the leader's page works.
     *
     * @return bool whether the fingerprints were refreshed
     */
    public function refreshQuietly(int $unitId): bool
    {
        try {
            $this->refresh($unitId);

            return true;
        } catch (SkautisException|PermissionException $exception) {
            $this->logger->log($exception, ILogger::WARNING);

            return false;
        }
    }

    /**
     * Replaces the fingerprints of the unit tree with the current member list.
     */
    public function refresh(int $unitId): void
    {
        $now = new DateTimeImmutable();
        // skautIS first: when it fails, the old fingerprints stay and public pairing keeps working.
        $members = $this->members->getMembers($unitId);

        $this->entityManager->wrapInTransaction(function () use ($unitId, $members, $now): void {
            $this->entityManager->createQuery('DELETE FROM '.UnitMemberFingerprint::class.' f WHERE f.unitId = :unit')
                ->setParameter('unit', $unitId)
                ->execute();

            foreach ($members as $person) {
                if ($person->birthday === null) {
                    continue;
                }
                $this->entityManager->persist(new UnitMemberFingerprint(
                    $unitId,
                    $person->id,
                    $this->fingerprint->person($person->lastName, $person->firstName, $person->birthday),
                    $this->fingerprint->surname($person->lastName, $person->birthday),
                    $now,
                ));
            }
        });
    }

    public function forget(int $unitId): void
    {
        $this->entityManager->createQuery('DELETE FROM '.UnitMemberFingerprint::class.' f WHERE f.unitId = :unit')
            ->setParameter('unit', $unitId)
            ->execute();
    }

    public function hasFingerprints(int $unitId): bool
    {
        return $this->getRefreshedAt($unitId) !== null;
    }

    /**
     * When the unit's fingerprints were last built; null when there are none.
     */
    public function getRefreshedAt(int $unitId): ?DateTimeImmutable
    {
        $value = $this->entityManager->createQueryBuilder()
            ->select('MAX(f.refreshedAt)')
            ->from(UnitMemberFingerprint::class, 'f')
            ->where('f.unitId = :unit')
            ->setParameter('unit', $unitId)
            ->getQuery()
            ->getSingleScalarResult();

        return \is_string($value) ? new DateTimeImmutable($value) : null;
    }

    public function countFingerprints(int $unitId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(f.personId)')
            ->from(UnitMemberFingerprint::class, 'f')
            ->where('f.unitId = :unit')
            ->setParameter('unit', $unitId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Person ids whose name and birth date match exactly; both name orders are tried.
     *
     * @return list<int>
     */
    public function matchExact(int $unitId, string $firstName, string $lastName, DateTimeImmutable $birthDate): array
    {
        return $this->personIds($unitId, 'fingerprintFull', [
            $this->fingerprint->person($lastName, $firstName, $birthDate),
            $this->fingerprint->person($firstName, $lastName, $birthDate),
        ]);
    }

    /**
     * Person ids matching only the surname and birth date (a typo or a nickname in the first name).
     *
     * @return list<int>
     */
    public function matchSurname(int $unitId, string $firstName, string $lastName, DateTimeImmutable $birthDate): array
    {
        return $this->personIds($unitId, 'fingerprintSurname', [
            $this->fingerprint->surname($lastName, $birthDate),
            $this->fingerprint->surname($firstName, $birthDate),
        ]);
    }

    /**
     * The member behind a matched fingerprint, for the public page that has no skautIS token: the cached
     * member list when a leader loaded it recently, otherwise the person as the participant typed them
     * (the fingerprint already proved name and birth date; the leader's pages refresh the rest from skautIS).
     */
    public function findPerson(int $unitId, int $personId, string $firstName, string $lastName, DateTimeImmutable $birthDate): Person
    {
        foreach ($this->members->getMembersIfCached($unitId) ?? [] as $person) {
            if ($person->id === $personId) {
                return $person;
            }
        }

        return new Person($personId, self::typedName($firstName), self::typedName($lastName), '', $birthDate, null, $unitId);
    }

    private static function typedName(string $name): string
    {
        $name = trim($name);

        // "anna" and "MALÁ" become "Anna" and "Malá"; mixed case such as "McDonald" is kept as typed.
        return $name === mb_strtolower($name) || $name === mb_strtoupper($name) ? mb_convert_case($name, \MB_CASE_TITLE, 'UTF-8') : $name;
    }

    /**
     * @param list<string> $fingerprints
     *
     * @return list<int>
     */
    private function personIds(int $unitId, string $column, array $fingerprints): array
    {
        /** @var list<array{personId: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('f.personId')
            ->from(UnitMemberFingerprint::class, 'f')
            ->where('f.unitId = :unit')
            ->andWhere('f.'.$column.' IN (:fingerprints)')
            ->setParameter('unit', $unitId)
            ->setParameter('fingerprints', $fingerprints)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_unique(array_map(static fn (array $row): int => $row['personId'], $rows)));
    }
}
