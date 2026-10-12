<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Model\Event\Event;
use App\Model\Event\EventRepository;
use App\Model\Event\EventState;
use App\Model\Mailing\MarkdownRenderer;
use App\Model\Web\EventContentRepository;
use App\Model\Web\FileContent;
use App\Model\Web\FileStorage;
use App\Model\Web\TextContent;
use App\Presentation\BasePresenter;
use DateTimeImmutable;
use LogicException;
use Nette\Application\Attributes\Persistent;
use Nette\Application\Responses\FileResponse;

/**
 * The event's mini-web behind the shared web token; a wrong or missing token is a 404, not a 403.
 */
final class SitePresenter extends BasePresenter
{
    #[Persistent]
    public ?int $unitId = null;

    #[Persistent]
    public ?string $slug = null;

    #[Persistent]
    public ?string $t = null;

    private ?Event $event = null;

    public function __construct(
        private readonly EventRepository $events,
        private readonly EventContentRepository $contents,
        private readonly FileStorage $files,
        private readonly MarkdownRenderer $markdown,
    ) {
        parent::__construct();
    }

    public function actionDefault(): void
    {
        $this->load();
    }

    public function renderDefault(): void
    {
        $event = $this->requireEvent();
        $blocks = [];
        foreach ($this->contents->findByEvent($event) as $content) {
            $blocks[] = [
                'content' => $content,
                'html' => $content instanceof TextContent ? $this->markdown->toHtml($content->getBody()) : null,
            ];
        }

        $this->template->event = $event;
        $this->template->descriptionHtml = $event->getDescription() === null ? null : $this->markdown->toHtml($event->getDescription());
        $this->template->blocks = $blocks;
        $this->template->publicRegistrationOpen = $event->isPublicRegistrationOpen(new DateTimeImmutable());
        $this->template->skautisLogin = $event->acceptsSkautisLogin(new DateTimeImmutable());
    }

    public function actionFile(int $file): void
    {
        $event = $this->load();
        $content = $this->contents->find($file);
        if (! $content instanceof FileContent || $content->getEvent() !== $event || ! $this->files->exists($content->getPath())) {
            $this->error('Soubor nebyl nalezen.');
        }

        $this->sendResponse(new FileResponse($this->files->absolutePath($content->getPath()), $content->getOriginalName(), $content->getMimeType(), forceDownload: false));
    }

    private function load(): Event
    {
        if ($this->unitId === null || $this->slug === null || $this->t === null) {
            $this->error('Stránka nebyla nalezena.');
        }

        $event = $this->events->findBySlug($this->unitId, $this->slug);
        if ($event === null || $event->getState() === EventState::Draft || ! hash_equals($event->getWebToken(), $this->t)) {
            $this->error('Stránka nebyla nalezena.');
        }

        return $this->event = $event;
    }

    private function requireEvent(): Event
    {
        return $this->event ?? throw new LogicException('The event is loaded in the action.');
    }
}
