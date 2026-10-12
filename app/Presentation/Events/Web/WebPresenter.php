<?php

declare(strict_types=1);

namespace App\Presentation\Events\Web;

use App\Model\Event\Event;
use App\Model\Event\EventRepository;
use App\Model\Event\EventService;
use App\Model\Web\EventContent;
use App\Model\Web\EventContentRepository;
use App\Model\Web\FileContent;
use App\Model\Web\InvalidUploadException;
use App\Model\Web\LinkContent;
use App\Model\Web\TextContent;
use App\Model\Web\WebService;
use App\Presentation\Accessory\Form\FormFactory;
use App\Presentation\SecuredPresenter;
use LogicException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Forms\Controls\SubmitButton;
use Nette\Http\FileUpload;

/**
 * The leader builds the event's mini-web from blocks: text, files and links.
 */
final class WebPresenter extends SecuredPresenter
{
    private ?Event $event = null;

    private ?EventContent $edited = null;

    public function __construct(
        private readonly EventRepository $events,
        private readonly EventService $eventService,
        private readonly EventContentRepository $contents,
        private readonly WebService $service,
        private readonly FormFactory $forms,
    ) {
        parent::__construct();
    }

    public function actionDefault(int $id, ?int $blok = null): void
    {
        $event = $this->events->find($id);
        if ($event === null) {
            $this->error('Akce nebyla nalezena.');
        }
        if (! $this->authorizator->canEditUnit($event->getUnitId())) {
            $this->error('K této akci nemáte oprávnění.', 403);
        }

        $this->event = $event;
        $this->edited = $blok === null ? null : $this->findContent($blok);
    }

    public function renderDefault(): void
    {
        $event = $this->requireEvent();

        $this->template->event = $event;
        $this->template->contents = $this->contents->findByEvent($event);
        $this->template->edited = $this->edited;
        $this->template->webLink = $this->link('//:Web:Site:default', ['unitId' => $event->getUnitId(), 'slug' => $event->getSlug(), 't' => $event->getWebToken()]);
    }

    protected function createComponentTextForm(): Form
    {
        $edited = $this->edited instanceof TextContent ? $this->edited : null;
        $form = $this->forms->create();
        $form->addText('title', 'Nadpis')->setRequired('Zadejte nadpis.')->addRule($form::MaxLength, 'Nejvýše %d znaků.', 255);
        $form->addTextArea('body', 'Text (Markdown)', null, 8)->setRequired('Zadejte text.');
        $form->addSubmit('save', $edited === null ? 'Přidat text' : 'Uložit text');
        if ($edited !== null) {
            $form->setDefaults(['title' => $edited->getTitle(), 'body' => $edited->getBody()]);
        }

        $form->onSuccess[] = function (Form $form, array $values) use ($edited): void {
            if ($edited === null) {
                $this->service->addText($this->requireEvent(), (string) $values['title'], (string) $values['body']);
            } else {
                $this->service->updateText($edited, (string) $values['title'], (string) $values['body']);
            }
            $this->flashMessage('Blok byl uložen.', 'success');
            $this->redirect('default', ['id' => $this->requireEvent()->getId()]);
        };

        return $form;
    }

    protected function createComponentLinkForm(): Form
    {
        $edited = $this->edited instanceof LinkContent ? $this->edited : null;
        $form = $this->forms->create();
        $form->addText('title', 'Popisek')->setRequired('Zadejte popisek.')->addRule($form::MaxLength, 'Nejvýše %d znaků.', 255);
        $form->addText('url', 'Adresa (https://…)')
            ->setRequired('Zadejte adresu.')
            ->addRule($form::URL, 'Zadejte platnou adresu.')
            ->addRule($form::MaxLength, 'Nejvýše %d znaků.', 2000)
            ->setOption('description', 'Fotoalbum (Immich, Google Photos), mapa srazu a podobně. Data zůstávají tam, kde jsou.');
        $form->addSubmit('save', $edited === null ? 'Přidat odkaz' : 'Uložit odkaz');
        if ($edited !== null) {
            $form->setDefaults(['title' => $edited->getTitle(), 'url' => $edited->getUrl()]);
        }

        $form->onSuccess[] = function (Form $form, array $values) use ($edited): void {
            if ($edited === null) {
                $this->service->addLink($this->requireEvent(), (string) $values['title'], (string) $values['url']);
            } else {
                $this->service->updateLink($edited, (string) $values['title'], (string) $values['url']);
            }
            $this->flashMessage('Blok byl uložen.', 'success');
            $this->redirect('default', ['id' => $this->requireEvent()->getId()]);
        };

        return $form;
    }

    protected function createComponentFileForm(): Form
    {
        $edited = $this->edited instanceof FileContent ? $this->edited : null;
        $form = $this->forms->create();
        $form->addText('title', 'Popisek')->setNullable()->addRule($form::MaxLength, 'Nejvýše %d znaků.', 255)
            ->setOption('description', 'Prázdné = název souboru.');
        $upload = $form->addUpload('file', 'Soubor (PDF, JPG, PNG do 10 MB)')
            ->addRule($form::MaxFileSize, 'Soubor může mít nejvýše 10 MB.', 10 * 1024 * 1024);
        if ($edited === null) {
            $upload->setRequired('Vyberte soubor.');
        } else {
            $upload->setOption('description', 'Prázdné = soubor zůstane.');
            $form->setDefaults(['title' => $edited->getTitle()]);
        }
        $form->addSubmit('save', $edited === null ? 'Nahrát soubor' : 'Uložit soubor');

        $form->onSuccess[] = function (Form $form, array $values) use ($edited): void {
            $file = $values['file'];
            \assert($file instanceof FileUpload);
            $title = $values['title'] === null ? '' : (string) $values['title'];
            try {
                if ($edited === null) {
                    $this->service->addFile($this->requireEvent(), $title, $file);
                } else {
                    $this->service->updateFile($edited, $title === '' ? $edited->getTitle() : $title, $file);
                }
                $this->flashMessage('Soubor byl uložen.', 'success');
            } catch (InvalidUploadException $exception) {
                $this->flashMessage($exception->getMessage(), 'danger');
            }
            $this->redirect('default', ['id' => $this->requireEvent()->getId()]);
        };

        return $form;
    }

    /**
     * @return Multiplier<Form>
     */
    protected function createComponentBlockActions(): Multiplier
    {
        return new Multiplier(function (string $contentId): Form {
            $content = $this->findContent((int) $contentId);
            $form = $this->forms->create();
            $form->addSubmit('up', '↑')->setOption('class', 'btn btn-sm btn-outline-secondary');
            $form->addSubmit('down', '↓')->setOption('class', 'btn btn-sm btn-outline-secondary');
            $form->addSubmit('remove', 'Smazat')->setOption('class', 'btn btn-sm btn-outline-danger')
                ->setHtmlAttribute('data-confirm', 'Smazat blok z miniwebu?');
            $form->onSuccess[] = function (Form $form) use ($content): void {
                $button = $form->isSubmitted();
                $action = $button instanceof SubmitButton ? (string) $button->getName() : '';
                match ($action) {
                    'up' => $this->service->move($content, -1),
                    'down' => $this->service->move($content, 1),
                    'remove' => $this->service->remove($content),
                    default => null,
                };
                $this->redirect('default', ['id' => $this->requireEvent()->getId()]);
            };

            return $form;
        });
    }

    /**
     * A new web token: every shared link stops working, so it is a deliberate action with a confirmation.
     */
    protected function createComponentTokenForm(): Form
    {
        $form = $this->forms->create();
        $form->addSubmit('regenerate', 'Obnovit odkaz miniwebu')
            ->setOption('class', 'btn btn-outline-danger btn-sm')
            ->setHtmlAttribute('data-confirm', 'Všechny rozeslané odkazy na miniweb i veřejnou přihlášku přestanou platit. Pokračovat?');
        $form->onSuccess[] = function (): void {
            $this->eventService->regenerateWebToken($this->requireEvent());
            $this->flashMessage('Odkaz miniwebu byl obnoven. Starý přestal platit, pošlete účastníkům nový.', 'success');
            $this->redirect('default', ['id' => $this->requireEvent()->getId()]);
        };

        return $form;
    }

    private function findContent(int $id): EventContent
    {
        $content = $this->contents->find($id);
        if ($content === null || $content->getEvent() !== $this->requireEvent()) {
            $this->error('Blok nebyl nalezen.');
        }

        return $content;
    }

    private function requireEvent(): Event
    {
        return $this->event ?? throw new LogicException('The event is loaded in the action.');
    }
}
