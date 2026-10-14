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
use DateTimeImmutable;
use DateTimeZone;
use Skaut\Skautis\Skautis;
use Skaut\Skautis\UnexpectedValueException;
use Skaut\Skautis\Wsdl\AuthenticationException;
use Skaut\Skautis\Wsdl\PermissionException;
use Skaut\Skautis\Wsdl\WebServiceName;
use Skaut\Skautis\Wsdl\WsdlException;
use stdClass;

final class SoapSkautisGateway implements SkautisGateway
{
    /** Extend the login when less than this many seconds remain. */
    private const int REFRESH_BEFORE_EXPIRY = 20 * 60;

    /** @var list<string> */
    private const array RETRYABLE_READ_METHODS = [
        'ActionVerify',
        'DomainAllPerson',
        'DomainAllUnit',
        'DomainUnitAll',
        'EventCampAll',
        'EventCampDetail',
        'EventGeneralScopeAll',
        'EventGeneralTypeAll',
        'ParticipantCampAll',
        'ParticipantGeneralAll',
        'PersonAll',
        'PersonContactAll',
        'PersonDetail',
        'PersonParentAll',
        'PersonParentAllPersonChildren',
        'UnitAll',
        'UnitDetail',
        'UserDetail',
        'UserRoleAll',
    ];

    public function __construct(
        private readonly Skautis $skautis,
        private readonly RetryingSkautisCall $retryingCall,
    ) {
    }

    public function getLoginUrl(string $backlink = ''): string
    {
        return $this->skautis->getLoginUrl($backlink);
    }

    public function getLogoutUrl(): string
    {
        return $this->skautis->getLogoutUrl();
    }

    public function login(array $post): void
    {
        try {
            $this->skautis->setLoginData($post);
        } catch (UnexpectedValueException $exception) {
            throw new SkautisException('skautIS did not send valid login data.', 0, $exception);
        }
    }

    public function isLoggedIn(): bool
    {
        $user = $this->skautis->getUser();

        try {
            if (! $this->retryingCall->run($user->isLoggedIn(...))) {
                return false;
            }

            $logoutDate = $user->getLogoutDate();
            if ($logoutDate !== null && $logoutDate->getTimestamp() - time() < self::REFRESH_BEFORE_EXPIRY) {
                $this->retryingCall->run($user->updateLogoutTime(...));
            }

            return true;
        } catch (AuthenticationException|UnexpectedValueException) {
            // UnexpectedValueException: LoginUpdateRefresh answered without DateLogout, so the login cannot be confirmed.
            return false;
        } catch (WsdlException $exception) {
            throw $this->wrap($exception);
        }
    }

    public function refreshLogin(): void
    {
        try {
            $this->retryingCall->run($this->skautis->getUser()->updateLogoutTime(...));
        } catch (AuthenticationException|UnexpectedValueException $exception) {
            throw new SkautisException('The skautIS login has expired.', 0, $exception);
        } catch (WsdlException $exception) {
            throw $this->wrap($exception);
        }
    }

    public function logout(): void
    {
        $this->skautis->getUser()->resetLoginData();
    }

    public function withTemporaryLogin(array $post, callable $reads): mixed
    {
        $user = $this->skautis->getUser();
        $saved = [$user->getLoginId(), $user->getRoleId(), $user->getUnitId(), $user->getLogoutDate()];
        $this->login($post);
        try {
            return $reads();
        } finally {
            if ($saved[0] === null) {
                $user->resetLoginData();
            } else {
                $user->setLoginData(...$saved);
            }
        }
    }

    public function getRoleId(): ?int
    {
        return $this->skautis->getUser()->getRoleId();
    }

    public function getUnitId(): ?int
    {
        return $this->skautis->getUser()->getUnitId();
    }

    public function getCurrentUser(): SkautisUser
    {
        $detail = $this->object($this->call(WebServiceName::USER_MANAGEMENT, 'UserDetail'));

        return new SkautisUser(
            self::int($detail, 'ID'),
            self::int($detail, 'ID_Person'),
            self::string($detail, 'UserName'),
            self::string($detail, 'Person'),
        );
    }

    public function getUserRoles(int $userId): array
    {
        $roles = [];
        foreach ($this->objects($this->call(WebServiceName::USER_MANAGEMENT, 'UserRoleAll', ['ID_User' => $userId, 'IsActive' => true])) as $role) {
            $roles[] = new SkautisRole(
                self::int($role, 'ID'),
                self::string($role, 'Key'),
                self::string($role, 'Role'),
                self::int($role, 'ID_Unit'),
                self::string($role, 'Unit'),
                self::string($role, 'DisplayName'),
            );
        }

        return $roles;
    }

    public function switchRole(int $userRoleId): int
    {
        $result = $this->object($this->call(WebServiceName::USER_MANAGEMENT, 'LoginUpdate', [
            'ID' => $this->skautis->getUser()->getLoginId(),
            'ID_UserRole' => $userRoleId,
        ]));
        $unitId = self::int($result, 'ID_Unit');

        $this->skautis->getUser()->updateLoginData(null, $userRoleId, $unitId);

        return $unitId;
    }

    public function getUnit(int $unitId): ?Unit
    {
        $unit = $this->call(WebServiceName::ORGANIZATION_UNIT, 'UnitDetail', ['ID' => $unitId]);

        return $unit instanceof stdClass ? $this->unit($unit) : null;
    }

    public function getChildUnits(int $unitId): array
    {
        $units = [];
        foreach ($this->objects($this->call(WebServiceName::ORGANIZATION_UNIT, 'UnitAll', ['ID_UnitParent' => $unitId])) as $unit) {
            $units[] = $this->unit($unit);
        }

        return $units;
    }

    public function getEventTypes(): array
    {
        return $this->codeList($this->call(WebServiceName::EVENTS, 'EventGeneralTypeAll'));
    }

    public function getEventScopes(): array
    {
        return $this->codeList($this->call(WebServiceName::EVENTS, 'EventGeneralScopeAll'));
    }

    public function getUnitMembers(int $unitId, bool $includeSubunits): array
    {
        $persons = [];
        foreach ($this->objects($this->call(WebServiceName::ORGANIZATION_UNIT, 'PersonAll', ['ID_Unit' => $unitId, 'OnlyDirectMember' => ! $includeSubunits])) as $person) {
            $unit = $person->ID_Unit ?? null;
            $sex = self::string($person, 'ID_Sex');
            $persons[] = new Person(
                self::int($person, 'ID'),
                self::string($person, 'FirstName'),
                self::string($person, 'LastName'),
                self::string($person, 'NickName'),
                self::date($person, 'Birthday'),
                $sex === '' ? null : $sex,
                is_numeric($unit) ? (int) $unit : null,
            );
        }

        return $persons;
    }

    public function getPersonContacts(int $personId): array
    {
        $contacts = [];
        foreach ($this->objects($this->call(WebServiceName::ORGANIZATION_UNIT, 'PersonContactAll', ['ID_Person' => $personId])) as $contact) {
            $contacts[] = new Contact(self::string($contact, 'ID_ContactType'), trim(self::string($contact, 'Value')));
        }

        return $contacts;
    }

    public function getPersonDetail(int $personId): ?PersonDetail
    {
        $detail = $this->call(WebServiceName::ORGANIZATION_UNIT, 'PersonDetail', ['ID' => $personId]);
        if (! $detail instanceof stdClass) {
            return null;
        }

        return $this->personDetail($detail, 'ID');
    }

    public function getUnitDomains(int $unitId): array
    {
        try {
            $rows = $this->objects($this->call(WebServiceName::GOOGLE_APPS, 'DomainAllUnit', ['ID_Unit' => $unitId]));
        } catch (PermissionException) {
            return [];
        }

        return self::domains($rows, 'DisplayName');
    }

    public function getAssignedDomains(int $unitId): array
    {
        try {
            $rows = $this->objects($this->call(WebServiceName::GOOGLE_APPS, 'DomainUnitAll', ['ID_Unit' => $unitId]));
        } catch (PermissionException) {
            return [];
        }

        $assignments = [];
        foreach ($rows as $row) {
            $domain = mb_strtolower(trim(self::string($row, 'Domain')));
            if ($domain !== '') {
                $assignments[] = new DomainAssignment($domain, ($row->IncludeChildUnit ?? false) === true);
            }
        }

        return $assignments;
    }

    public function getPersonDomains(int $personId): array
    {
        try {
            $rows = $this->objects($this->call(WebServiceName::GOOGLE_APPS, 'DomainAllPerson', ['ID_Person' => $personId, 'CanGoogleAccount' => true]));
        } catch (PermissionException) {
            return [];
        }

        return self::domains($rows, 'DisplayName');
    }

    public function getPersonChildren(int $personId): array
    {
        try {
            $rows = $this->objects($this->call(WebServiceName::ORGANIZATION_UNIT, 'PersonParentAllPersonChildren', ['ID_Person' => $personId]));
        } catch (PermissionException) {
            return [];
        }

        return array_map(fn (stdClass $child): PersonDetail => $this->personDetail($child, 'ID_Person'), $rows);
    }

    private function personDetail(stdClass $person, string $idField): PersonDetail
    {
        $sex = self::string($person, 'ID_Sex');
        $email = trim(self::string($person, 'Email'));
        $phone = trim(self::string($person, 'Phone'));

        return new PersonDetail(
            self::int($person, $idField),
            self::string($person, 'FirstName'),
            self::string($person, 'LastName'),
            self::string($person, 'NickName'),
            self::date($person, 'Birthday'),
            $sex === '' ? null : $sex,
            $email === '' ? null : $email,
            $phone === '' ? null : $phone,
        );
    }

    public function getPersonParents(int $personId): array
    {
        try {
            $rows = $this->objects($this->call(WebServiceName::ORGANIZATION_UNIT, 'PersonParentAll', ['ID_Person' => $personId]));
        } catch (PermissionException) {
            return [];
        }

        $parents = [];
        foreach ($rows as $parent) {
            $email = trim(self::string($parent, 'Email'));
            if ($email !== '') {
                $parents[] = new ParentContact(self::string($parent, 'ID_ParentType'), self::string($parent, 'ParentType'), $email);
            }
        }

        return $parents;
    }

    public function getCamps(int $year): array
    {
        $camps = [];
        foreach ($this->objects($this->call(WebServiceName::EVENTS, 'EventCampAll', ['Year' => $year])) as $camp) {
            $dateFrom = self::date($camp, 'StartDate');
            $dateTo = self::date($camp, 'EndDate');
            if ($dateFrom === null || $dateTo === null) {
                continue;
            }
            $camps[] = new Camp(
                self::int($camp, 'ID'),
                self::string($camp, 'DisplayName'),
                self::int($camp, 'ID_Unit'),
                self::string($camp, 'Unit'),
                $dateFrom,
                $dateTo,
                self::string($camp, 'Location'),
                self::string($camp, 'ID_EventCampState'),
                self::string($camp, 'EventCampState'),
            );
        }

        return $camps;
    }

    public function getCampState(int $campId): ?string
    {
        $camp = $this->call(WebServiceName::EVENTS, 'EventCampDetail', ['ID' => $campId]);

        return $camp instanceof stdClass ? (self::string($camp, 'ID_EventCampState') ?: null) : null;
    }

    public function getAllowedActions(string $table, ?int $id): array
    {
        $arguments = ['ID_Table' => $table];
        if ($id !== null) {
            $arguments['ID'] = $id;
        }

        try {
            $rows = $this->objects($this->call(WebServiceName::USER_MANAGEMENT, 'ActionVerify', $arguments));
        } catch (PermissionException) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (stdClass $row): string => self::string($row, 'ID'), $rows), static fn (string $id): bool => $id !== ''));
    }

    public function createEvent(NewEvent $event): int
    {
        // The same payload h.skauting sends; ID and Note must not be empty for skautIS to accept the record.
        $result = $this->object($this->call(WebServiceName::EVENTS, 'EventGeneralInsert', [
            'ID' => 1,
            'Location' => $event->location ?? ' ',
            'Note' => ' ',
            'ID_EventGeneralScope' => $event->scopeId,
            'ID_EventGeneralType' => $event->typeId,
            'ID_Unit' => $event->unitId,
            'DisplayName' => $event->name,
            'StartDate' => $event->dateFrom->format('Y-m-d'),
            'EndDate' => $event->dateTo->format('Y-m-d'),
            'IsStatisticAutoComputed' => false,
        ], 'eventGeneral'));

        return self::int($result, 'ID');
    }

    public function getEventParticipants(int $eventId): array
    {
        return $this->participants($this->call(WebServiceName::EVENTS, 'ParticipantGeneralAll', ['ID_EventGeneral' => $eventId]));
    }

    public function addEventParticipant(int $eventId, int $personId): ?int
    {
        return $this->addParticipant('ParticipantGeneralInsert', ['ID_EventGeneral' => $eventId, 'ID_Person' => $personId]);
    }

    public function createEventParticipant(int $eventId, NewPerson $person): array
    {
        return $this->createParticipant('ParticipantGeneralInsert', ['ID_EventGeneral' => $eventId], $person);
    }

    public function removeEventParticipant(int $participantId): void
    {
        $this->call(WebServiceName::EVENTS, 'ParticipantGeneralDelete', ['ID' => $participantId, 'DeletePerson' => false]);
    }

    public function getCampParticipants(int $campId): array
    {
        return $this->participants($this->call(WebServiceName::EVENTS, 'ParticipantCampAll', ['ID_EventCamp' => $campId]));
    }

    public function addCampParticipant(int $campId, int $personId): ?int
    {
        return $this->addParticipant('ParticipantCampInsert', ['ID_EventCamp' => $campId, 'ID_Person' => $personId]);
    }

    public function createCampParticipant(int $campId, NewPerson $person): array
    {
        return $this->createParticipant('ParticipantCampInsert', ['ID_EventCamp' => $campId], $person);
    }

    public function removeCampParticipant(int $participantId): void
    {
        $this->call(WebServiceName::EVENTS, 'ParticipantCampDelete', ['ID' => $participantId, 'DeletePerson' => false]);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function addParticipant(string $method, array $arguments): ?int
    {
        try {
            $result = $this->object($this->call(WebServiceName::EVENTS, $method, $arguments));
        } catch (SkautisException $exception) {
            if (str_contains($exception->getMessage(), 'PersonIsAllreadyParticipant')) {
                return null;
            }

            throw $exception;
        }

        return self::int($result, 'ID');
    }

    /**
     * Person data go in two steps like in h.skauting: the participation creates the person with the name,
     * PersonUpdateBasic fills in the rest. Only this freshly created person is ever updated.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array{participantId: int, personId: int, updateError: string|null}
     */
    private function createParticipant(string $method, array $arguments, NewPerson $person): array
    {
        $result = $this->object($this->call(WebServiceName::EVENTS, $method, $arguments + [
            'Person' => [
                'FirstName' => $person->firstName,
                'LastName' => $person->lastName,
                'NickName' => $person->nickName ?? '',
                'Note' => '',
            ],
        ]));
        $participantId = self::int($result, 'ID');
        $personId = self::int($result, 'ID_Person');

        // The participation and the person exist now: a failure below must not lose their ids, otherwise
        // the next run would create the same person again.
        try {
            $this->call(WebServiceName::ORGANIZATION_UNIT, 'PersonUpdateBasic', [
                'ID' => $personId,
                'FirstName' => $person->firstName,
                'LastName' => $person->lastName,
                'NickName' => $person->nickName ?? '',
                'ID_Sex' => $person->sexId ?? '',
                'Birthday' => $person->birthday?->format('Y-m-d\TH:i:s'),
                'Street' => $person->street ?? '',
                'City' => $person->city ?? '',
                'Postcode' => $person->postcode ?? '',
                'State' => 'Česká republika',
            ]);
            $updateError = null;
        } catch (SkautisException|PermissionException $exception) {
            $updateError = $exception->getMessage();
        }

        return ['participantId' => $participantId, 'personId' => $personId, 'updateError' => $updateError];
    }

    /**
     * @return list<Participant>
     */
    private function participants(mixed $result): array
    {
        $participants = [];
        foreach ($this->objects($result) as $row) {
            $personId = $row->ID_Person ?? null;
            $unitId = $row->ID_Unit ?? null;
            $participants[] = new Participant(
                self::int($row, 'ID'),
                is_numeric($personId) ? (int) $personId : null,
                self::string($row, 'Person'),
                self::date($row, 'Birthday'),
                is_numeric($unitId) ? (int) $unitId : null,
                self::string($row, 'Unit'),
            );
        }

        return $participants;
    }

    /**
     * @return array<string, string>
     */
    private function codeList(mixed $result): array
    {
        $items = [];
        foreach ($this->objects($result) as $item) {
            $items[self::string($item, 'ID')] = self::string($item, 'DisplayName');
        }

        return $items;
    }

    private function unit(stdClass $unit): Unit
    {
        $parentId = $unit->ID_UnitParent ?? null;

        return new Unit(
            self::int($unit, 'ID'),
            self::string($unit, 'DisplayName'),
            self::string($unit, 'RegistrationNumber'),
            self::string($unit, 'ID_UnitType'),
            is_numeric($parentId) ? (int) $parentId : null,
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @param string|null          $wrapper   custom input element name when skautIS does not use "<method>Input"
     */
    private function call(string $service, string $method, array $arguments = [], ?string $wrapper = null): mixed
    {
        try {
            $call = fn (): mixed => $this->skautis->getWebService($service)->call($method, $wrapper === null ? [$arguments] : [$arguments, $wrapper]);

            return \in_array($method, self::RETRYABLE_READ_METHODS, true) ? $this->retryingCall->run($call) : $call();
        } catch (AuthenticationException|PermissionException $exception) {
            throw $exception;
        } catch (WsdlException $exception) {
            throw $this->wrap($exception);
        }
    }

    private function wrap(WsdlException $exception): SkautisException
    {
        return new SkautisException('skautIS call failed: '.$exception->getMessage(), 0, $exception);
    }

    private function object(mixed $result): stdClass
    {
        if (! $result instanceof stdClass) {
            throw new SkautisException('skautIS returned no record where one was expected.');
        }

        return $result;
    }

    /**
     * @return list<stdClass>
     */
    private function objects(mixed $result): array
    {
        if (! \is_array($result)) {
            return [];
        }

        return array_values(array_filter($result, static fn (mixed $item): bool => $item instanceof stdClass));
    }

    private static function int(stdClass $object, string $field): int
    {
        $value = $object->$field ?? null;
        if (! is_numeric($value)) {
            throw new SkautisException("skautIS response is missing the numeric field $field.");
        }

        return (int) $value;
    }

    private static function date(stdClass $object, string $field): ?DateTimeImmutable
    {
        $value = $object->$field ?? null;
        if (! \is_string($value) || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', substr($value, 0, 19), new DateTimeZone('Europe/Prague'));

        return $date === false ? null : $date->setTime(0, 0);
    }

    /**
     * @param list<stdClass> $rows
     *
     * @return list<string>
     */
    private static function domains(array $rows, string $field): array
    {
        $domains = array_map(static fn (stdClass $row): string => mb_strtolower(trim(self::string($row, $field))), $rows);

        return array_values(array_unique(array_filter($domains, static fn (string $domain): bool => $domain !== '')));
    }

    private static function string(stdClass $object, string $field): string
    {
        $value = $object->$field ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }
}
