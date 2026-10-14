<?php

declare(strict_types=1);

namespace App\Model\Skautis;

use App\Model\Skautis\Dto\Camp;
use App\Model\Skautis\Dto\Contact;
use App\Model\Skautis\Dto\DomainAssignment;
use App\Model\Skautis\Dto\NewEvent;
use App\Model\Skautis\Dto\NewPerson;
use App\Model\Skautis\Dto\ParentContact;
use App\Model\Skautis\Dto\Participant;
use App\Model\Skautis\Dto\Person;
use App\Model\Skautis\Dto\PersonDetail;
use App\Model\Skautis\Dto\SkautisRole;
use App\Model\Skautis\Dto\SkautisUser;
use App\Model\Skautis\Dto\Unit;

/**
 * The only place the application talks to skautIS. One method per purpose, typed DTOs in and out.
 */
interface SkautisGateway
{
    public function getLoginUrl(string $backlink = ''): string;

    public function getLogoutUrl(): string;

    /**
     * Stores the token skautIS posted back after login.
     *
     * @param array<string, mixed> $post
     *
     * @throws SkautisException when the post does not contain a token
     */
    public function login(array $post): void;

    /**
     * Whether the stored token is still valid; extends the login when it is about to expire.
     */
    public function isLoggedIn(): bool;

    /**
     * Extends the skautIS login by another 30 minutes (UserManagement.LoginUpdateRefresh).
     *
     * @throws SkautisException when skautIS refuses
     */
    public function refreshLogin(): void;

    public function logout(): void;

    /**
     * Runs reads with a login skautIS just posted back and then restores the login stored before
     * (a leader's, or none), so a participant's sign-up never replaces or drops it.
     *
     * @template T
     *
     * @param array<string, mixed> $post
     * @param callable(): T        $reads
     *
     * @return T
     *
     * @throws SkautisException when the posted login data are invalid
     */
    public function withTemporaryLogin(array $post, callable $reads): mixed;

    public function getRoleId(): ?int;

    public function getUnitId(): ?int;

    public function getCurrentUser(): SkautisUser;

    /**
     * @return list<SkautisRole>
     */
    public function getUserRoles(int $userId): array;

    /**
     * Switches the active role; returns the unit of the new role.
     */
    public function switchRole(int $userRoleId): int;

    public function getUnit(int $unitId): ?Unit;

    /**
     * @return list<Unit>
     */
    public function getChildUnits(int $unitId): array;

    /**
     * Types of general events (Events.EventGeneralTypeAll).
     *
     * @return array<string, string> id => name
     */
    public function getEventTypes(): array;

    /**
     * Scopes of general events (Events.EventGeneralScopeAll).
     *
     * @return array<string, string> id => name
     */
    public function getEventScopes(): array;

    /**
     * Members of a unit, optionally with the members of its sub-units (OrganizationUnit.PersonAll).
     *
     * @return list<Person>
     */
    public function getUnitMembers(int $unitId, bool $includeSubunits): array;

    /**
     * @return list<Contact>
     */
    public function getPersonContacts(int $personId): array;

    /**
     * E-mails of the parents; empty when the application lacks the permission.
     *
     * @return list<ParentContact>
     */
    public function getPersonParents(int $personId): array;

    /**
     * The person behind a skautIS account, for the sign-up through skautIS (4.6); null when unknown.
     */
    public function getPersonDetail(int $personId): ?PersonDetail;

    /**
     * Children the person is the legal guardian of; empty when there are none or the role may not see them.
     *
     * @return list<PersonDetail>
     */
    public function getPersonChildren(int $personId): array;

    /**
     * Camps of the given year visible to the active role (Events.EventCampAll).
     *
     * @return list<Camp>
     */
    public function getCamps(int $year): array;

    /**
     * State id of a camp (Events.EventCampDetail → ID_EventCampState), null when not readable.
     */
    public function getCampState(int $campId): ?string;

    /**
     * Domains of the scout Google Workspace the unit owns (GoogleApps.DomainAllUnit); empty when the role may not see them.
     *
     * @return list<string>
     */
    public function getUnitDomains(int $unitId): array;

    /**
     * Domains assigned to the unit (GoogleApps.DomainUnitAll); empty when the role may not see them.
     *
     * @return list<DomainAssignment>
     */
    public function getAssignedDomains(int $unitId): array;

    /**
     * Domains the person may have a Google account in (GoogleApps.DomainAllPerson); empty when not readable.
     *
     * @return list<string>
     */
    public function getPersonDomains(int $personId): array;

    /**
     * Actions the active role may perform on a skautIS record (UserManagement.ActionVerify).
     *
     * @return list<string> action ids such as EV_ParticipantGeneral_INSERT_EventGeneral
     */
    public function getAllowedActions(string $table, ?int $id): array;

    /**
     * Creates a general event and returns its skautIS id (Events.EventGeneralInsert).
     */
    public function createEvent(NewEvent $event): int;

    /**
     * @return list<Participant>
     */
    public function getEventParticipants(int $eventId): array;

    /**
     * Adds a skautIS person to a general event; null when skautIS says the person already takes part.
     */
    public function addEventParticipant(int $eventId, int $personId): ?int;

    /**
     * Creates a person for a non-member and adds it to the event; returns participant id and person id.
     *
     * @return array{participantId: int, personId: int, updateError: string|null} updateError: the person exists but PersonUpdateBasic failed
     */
    public function createEventParticipant(int $eventId, NewPerson $person): array;

    public function removeEventParticipant(int $participantId): void;

    /**
     * @return list<Participant>
     */
    public function getCampParticipants(int $campId): array;

    public function addCampParticipant(int $campId, int $personId): ?int;

    /**
     * @return array{participantId: int, personId: int, updateError: string|null} updateError: the person exists but PersonUpdateBasic failed
     */
    public function createCampParticipant(int $campId, NewPerson $person): array;

    public function removeCampParticipant(int $participantId): void;
}
