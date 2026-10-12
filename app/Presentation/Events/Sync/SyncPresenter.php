<?php

declare(strict_types=1);

namespace App\Presentation\Events\Sync;

use App\Model\Auth\Authorizator;
use App\Model\Event\Event;
use App\Model\Event\EventRepository;
use App\Model\Skautis\SkautisException;
use App\Model\Skautis\SkautisLinks;
use App\Model\Sync\SkautisSync;
use App\Model\Sync\SyncNotAllowedException;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\SecuredPresenter;
use LogicException;
use Nette\Application\UI\Form;
use Skaut\Skautis\Wsdl\PermissionException;

/**
 * Propagating the event and its participants to skautIS: preview of the difference, then batches.
 */
final class SyncPresenter extends SecuredPresenter
{
    private ?Event $event = null;

    public function __construct(
        private readonly EventRepository $events,
        private readonly SkautisSync $sync,
        private readonly SkautisLinks $links,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(int $id): void
    {
        $event = $this->events->find($id);
        if ($event === null) {
            $this->error('Akce nebyla nalezena.');
        }
        if (! $this->authorizator->canEditUnit($event->getUnitId())) {
            $this->error('K této akci nemáte oprávnění.', 403);
        }

        $this->event = $event;
    }

    public function renderDefault(): void
    {
        $event = $this->requireEvent();
        $linked = $event->isCamp() ? $event->getSkautisCampId() !== null : $event->getSkautisEventId() !== null;

        $this->template->event = $event;
        $this->template->linked = $linked;
        $this->template->canCreate = ! $event->isCamp() && ! $linked && $this->authorizator->canInSkautis(Authorizator::TABLE_EVENT, null, Authorizator::ACTION_EVENT_INSERT);
        $this->template->canWrite = $linked && ($event->isCamp()
            ? $this->authorizator->canInSkautis(Authorizator::TABLE_CAMP, $event->getSkautisCampId(), Authorizator::ACTION_CAMP_PARTICIPANT_INSERT)
            : $this->authorizator->canInSkautis(Authorizator::TABLE_EVENT, $event->getSkautisEventId(), Authorizator::ACTION_PARTICIPANT_INSERT));
        $this->template->canDelete = $linked && ($event->isCamp()
            ? $this->authorizator->canInSkautis(Authorizator::TABLE_CAMP, $event->getSkautisCampId(), Authorizator::ACTION_CAMP_PARTICIPANT_DELETE)
            : $this->authorizator->canInSkautis(Authorizator::TABLE_EVENT, $event->getSkautisEventId(), Authorizator::ACTION_PARTICIPANT_DELETE));

        $diff = null;
        $error = null;
        $blocker = null;
        if ($linked) {
            try {
                $blocker = $this->sync->blocker($event);
                $diff = $this->sync->diff($event);
            } catch (SkautisException|PermissionException $exception) {
                $error = $exception->getMessage();
            }
        }
        $this->template->diff = $diff;
        $this->template->blocker = $blocker;
        $this->template->skautisError = $error;
        $this->template->skautisUrl = $this->links->event($event);
    }

    /**
     * AJAX: one batch of participants into skautIS.
     */
    public function handleSyncBatch(int $offset = 0): void
    {
        $event = $this->requireEvent();
        try {
            $this->sendJson($this->sync->syncBatch($event, $this->currentUser->getUser()->id, $offset));
        } catch (SyncNotAllowedException|SkautisException|PermissionException $exception) {
            $this->sendJson(['remaining' => 0, 'error' => $exception->getMessage()]);
        }
    }

    public function handleRemoveBatch(int $offset = 0): void
    {
        $event = $this->requireEvent();
        try {
            $this->sendJson($this->sync->removeBatch($event, $this->currentUser->getUser()->id, $offset));
        } catch (SkautisException|PermissionException $exception) {
            $this->sendJson(['remaining' => 0, 'error' => $exception->getMessage()]);
        }
    }

    protected function createComponentCreateForm(): Form
    {
        $form = $this->forms->create();
        $form->addSubmit('create', 'Založit akci ve skautISu')->setOption('class', 'btn btn-primary');
        $form->onSuccess[] = function (): void {
            $event = $this->requireEvent();
            if (! $this->authorizator->canInSkautis(Authorizator::TABLE_EVENT, null, Authorizator::ACTION_EVENT_INSERT)) {
                $this->error('skautIS vám nedovoluje zakládat akce.', 403);
            }
            try {
                $id = $this->sync->createEvent($event);
                $this->flashMessage(\sprintf('Akce byla založena ve skautISu (ID %d).', $id), 'success');
            } catch (SyncNotAllowedException|SkautisException|PermissionException $exception) {
                $this->flashMessage('Založení ve skautISu se nezdařilo: '.$exception->getMessage(), 'danger');
            }
            $this->redirect('this');
        };

        return $form;
    }

    private function requireEvent(): Event
    {
        return $this->event ?? throw new LogicException('The event is loaded in the action.');
    }
}
