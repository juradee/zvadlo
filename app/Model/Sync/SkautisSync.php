<?php

declare(strict_types=1);

namespace App\Model\Sync;

use App\Model\Event\Event;
use App\Model\Event\EventRepository;
use App\Model\Registration\LogActor;
use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationRepository;
use App\Model\Registration\RegistrationState;
use App\Model\Skautis\Dto\NewEvent;
use App\Model\Skautis\Dto\Participant;
use App\Model\Skautis\SkautisException;
use App\Model\Skautis\SkautisGateway;
use DateTimeImmutable;
use Skaut\Skautis\Wsdl\PermissionException;

/**
 * Propagates the event and its attending participants to skautIS. Runs inside the leader's request in
 * batches (the leader's skautIS token is the only one there is), the page keeps calling until done.
 */
final class SkautisSync
{
    public const int BATCH_SIZE = 10;

    /** Camps accept participants once they left the draft state (submitted for approval). */
    public const string CAMP_DRAFT_STATE = 'draft';

    public function __construct(
        private readonly SkautisGateway $skautis,
        private readonly EventRepository $events,
        private readonly RegistrationRepository $registrations,
    ) {
    }

    /**
     * Creates the general event in skautIS and remembers its id.
     *
     * @throws SyncNotAllowedException
     */
    public function createEvent(Event $event): int
    {
        if ($event->isCamp()) {
            throw new SyncNotAllowedException('Tábor už ve skautISu je, zakládá se tam.');
        }
        if ($event->getSkautisEventId() !== null) {
            throw new SyncNotAllowedException('Akce už je ve skautISu propsaná.');
        }
        if ($event->getSkautisTypeId() === null || $event->getSkautisScopeId() === null) {
            throw new SyncNotAllowedException('Akce nemá typ a rozsah pro skautIS.');
        }

        $id = $this->skautis->createEvent(new NewEvent(
            $event->getUnitId(),
            $event->getName(),
            $event->getDateFrom(),
            $event->getDateTo(),
            $event->getPlace(),
            $event->getSkautisTypeId(),
            $event->getSkautisScopeId(),
        ));
        $event->linkSkautisEvent($id, new DateTimeImmutable());
        $this->events->save();

        return $id;
    }

    /**
     * Whether participants can be written now; the reason otherwise.
     */
    public function blocker(Event $event): ?string
    {
        if ($event->isCamp()) {
            $state = $this->skautis->getCampState((int) $event->getSkautisCampId());
            if ($state === null) {
                return 'Tábor ve skautISu nejde načíst.';
            }
            if ($state === self::CAMP_DRAFT_STATE) {
                return 'Tábor je ve skautISu rozpracovaný. Účastníky lze propsat, až bude odevzdaný ke schválení.';
            }

            return null;
        }

        return $event->getSkautisEventId() === null ? 'Akce ještě není ve skautISu založená.' : null;
    }

    public function diff(Event $event): SyncDiff
    {
        $inSkautis = $this->participants($event);
        $byPerson = [];
        $byId = [];
        foreach ($inSkautis as $participant) {
            $byId[$participant->id] = $participant;
            if ($participant->personId !== null) {
                $byPerson[$participant->personId] = $participant;
            }
        }

        // A registration counts as present when Zvadlo wrote it (participant id) or skautIS lists the person.
        $missing = $synced = $toRemove = $failed = [];
        $known = [];
        $all = [...$this->registrations->findByEvent($event), ...$this->registrations->findByEvent($event, RegistrationState::Rejected)];
        foreach ($all as $registration) {
            $participantId = $registration->getSkautisParticipantId();
            $personId = $registration->getSkautisPersonId();
            if ($participantId !== null) {
                $known[$participantId] = true;
            }
            if ($personId !== null && isset($byPerson[$personId])) {
                $known[$byPerson[$personId]->id] = true;
            }
            $present = $participantId !== null || ($personId !== null && isset($byPerson[$personId]));

            if ($registration->getState() === RegistrationState::Registered) {
                if ($present) {
                    $synced[] = $registration;
                } else {
                    $missing[] = $registration;
                    if ($registration->getSkautisError() !== null) {
                        $failed[] = $registration;
                    }
                }
            } elseif ($participantId !== null) {
                $toRemove[] = $registration;
            }
        }

        $foreign = array_values(array_filter($inSkautis, static fn (Participant $participant): bool => ! isset($known[$participant->id])));

        return new SyncDiff($missing, $synced, $toRemove, $foreign, $failed);
    }

    /**
     * Writes one batch of missing participants. Rows that fail stay missing, so the caller passes back
     * `next` as the offset and every row is tried once per run; `remaining` always decreases.
     *
     * @return array{remaining: int, next: int}
     *
     * @throws SyncNotAllowedException
     */
    public function syncBatch(Event $event, int $userId, int $offset = 0, int $batchSize = self::BATCH_SIZE): array
    {
        $blocker = $this->blocker($event);
        if ($blocker !== null) {
            throw new SyncNotAllowedException($blocker);
        }

        $diff = $this->diff($event);
        $now = new DateTimeImmutable();
        $byPerson = [];
        foreach ($this->participants($event) as $participant) {
            if ($participant->personId !== null) {
                $byPerson[$participant->personId] = $participant;
            }
        }

        $batch = \array_slice($diff->missing, max(0, $offset), $batchSize);
        $failed = 0;
        foreach ($batch as $registration) {
            try {
                $this->syncOne($event, $registration, $byPerson, $userId, $now);
            } catch (SkautisException|PermissionException $exception) {
                $registration->recordSkautisError($exception->getMessage(), $now);
            }
            if ($registration->getSkautisParticipantId() === null) {
                ++$failed;
            }
        }
        $this->registrations->save();

        return self::progress(\count($diff->missing), $offset, \count($batch), $failed);
    }

    /**
     * Removes participants who no longer take part (only those Zvadlo added); the offset works as in syncBatch().
     *
     * @return array{remaining: int, next: int}
     */
    public function removeBatch(Event $event, int $userId, int $offset = 0, int $batchSize = self::BATCH_SIZE): array
    {
        $diff = $this->diff($event);
        $now = new DateTimeImmutable();
        $batch = \array_slice($diff->toRemove, max(0, $offset), $batchSize);
        $failed = 0;

        foreach ($batch as $registration) {
            $participantId = (int) $registration->getSkautisParticipantId();
            try {
                if ($event->isCamp()) {
                    $this->skautis->removeCampParticipant($participantId);
                } else {
                    $this->skautis->removeEventParticipant($participantId);
                }
                $registration->clearSkautisParticipant(LogActor::User, $userId, $now);
            } catch (SkautisException|PermissionException $exception) {
                $registration->recordSkautisError($exception->getMessage(), $now);
                ++$failed;
            }
        }
        $this->registrations->save();

        return self::progress(\count($diff->toRemove), $offset, \count($batch), $failed);
    }

    /**
     * The rows before the offset failed in this run and stay in the list; the next batch starts after them
     * and the newly failed ones.
     *
     * @return array{remaining: int, next: int}
     */
    private static function progress(int $pending, int $offset, int $processed, int $failed): array
    {
        $offset = max(0, $offset);

        return ['remaining' => max(0, $pending - $offset - $processed), 'next' => $offset + $failed];
    }

    /**
     * @param array<int, Participant> $byPerson
     */
    private function syncOne(Event $event, Registration $registration, array $byPerson, int $userId, DateTimeImmutable $now): void
    {
        $personId = $registration->getSkautisPersonId();
        if ($personId !== null && isset($byPerson[$personId])) {
            // Added in skautIS by hand before: adopt the existing participation instead of a duplicate.
            $registration->recordSkautisParticipant($byPerson[$personId]->id, null, LogActor::User, $userId, $now);

            return;
        }

        if ($personId !== null) {
            $participantId = $event->isCamp()
                ? $this->skautis->addCampParticipant((int) $event->getSkautisCampId(), $personId)
                : $this->skautis->addEventParticipant((int) $event->getSkautisEventId(), $personId);
            if ($participantId === null) {
                $registration->recordSkautisError('skautIS hlásí, že osoba už je účastníkem, ale v seznamu účastníků není.', $now);

                return;
            }
            $registration->recordSkautisParticipant($participantId, null, LogActor::User, $userId, $now);

            return;
        }

        $created = $event->isCamp()
            ? $this->skautis->createCampParticipant((int) $event->getSkautisCampId(), $registration->toNewPerson())
            : $this->skautis->createEventParticipant((int) $event->getSkautisEventId(), $registration->toNewPerson());
        $registration->recordSkautisParticipant($created['participantId'], $created['personId'], LogActor::User, $userId, $now);
        if ($created['updateError'] !== null) {
            $registration->recordSkautisError('Osoba je ve skautISu založená, ale pohlaví, datum narození a adresu se nepodařilo doplnit: '.$created['updateError'], $now);
        }
    }

    /**
     * @return list<Participant>
     */
    private function participants(Event $event): array
    {
        if ($event->isCamp()) {
            return $event->getSkautisCampId() === null ? [] : $this->skautis->getCampParticipants($event->getSkautisCampId());
        }

        return $event->getSkautisEventId() === null ? [] : $this->skautis->getEventParticipants($event->getSkautisEventId());
    }
}
