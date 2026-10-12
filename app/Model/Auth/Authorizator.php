<?php

declare(strict_types=1);

namespace App\Model\Auth;

use App\Model\Skautis\SkautisGateway;
use App\Model\User\CurrentUser;

/**
 * Every permission check of the application goes through here; presenters never ask skautIS themselves.
 * Unit access comes from the role key (spec 7.2); anything that writes to skautIS is verified there
 * with ActionVerify (spec 7.3), cached for the request.
 */
final class Authorizator
{
    public const string TABLE_EVENT = 'EV_EventGeneral';
    public const string TABLE_CAMP = 'EV_EventCamp';

    public const string ACTION_EVENT_INSERT = 'EV_EventGeneral_INSERT';
    public const string ACTION_PARTICIPANT_INSERT = 'EV_ParticipantGeneral_INSERT_EventGeneral';
    public const string ACTION_PARTICIPANT_DELETE = 'EV_ParticipantGeneral_DELETE_EventGeneral';
    public const string ACTION_CAMP_PARTICIPANT_INSERT = 'EV_ParticipantCamp_INSERT_EventCamp';
    public const string ACTION_CAMP_PARTICIPANT_DELETE = 'EV_ParticipantCamp_DELETE';

    /** @var array<string, list<string>> */
    private array $allowedActions = [];

    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly SkautisGateway $skautis,
    ) {
    }

    public function canReadUnit(int $unitId): bool
    {
        return $this->currentUser->isLoggedIn() && $this->currentUser->getAccess()->canRead($unitId);
    }

    public function canEditUnit(int $unitId): bool
    {
        return $this->currentUser->isLoggedIn() && $this->currentUser->getAccess()->canEdit($unitId);
    }

    /**
     * Whether skautIS lets the active role perform the action on the record (or on the table when $id is null).
     */
    public function canInSkautis(string $table, ?int $id, string $action): bool
    {
        if (! $this->currentUser->isLoggedIn()) {
            return false;
        }

        $key = $table.'/'.($id ?? '');
        $this->allowedActions[$key] ??= $this->skautis->getAllowedActions($table, $id);

        return \in_array($action, $this->allowedActions[$key], true);
    }
}
