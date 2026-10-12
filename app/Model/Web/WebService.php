<?php

declare(strict_types=1);

namespace App\Model\Web;

use App\Model\Event\Event;
use Nette\Http\FileUpload;

/**
 * The leader's edits of the mini-web: blocks of text, files and links in an order.
 */
final class WebService
{
    public function __construct(
        private readonly EventContentRepository $contents,
        private readonly FileStorage $files,
    ) {
    }

    public function addText(Event $event, string $title, string $body): TextContent
    {
        $content = new TextContent($event, $title, $this->nextPosition($event), $body);
        $this->contents->add($content);

        return $content;
    }

    public function addLink(Event $event, string $title, string $url): LinkContent
    {
        $content = new LinkContent($event, $title, $this->nextPosition($event), $url);
        $this->contents->add($content);

        return $content;
    }

    /**
     * @throws InvalidUploadException
     */
    public function addFile(Event $event, string $title, FileUpload $upload): FileContent
    {
        $stored = $this->files->store($event->getId(), $upload);
        $content = new FileContent($event, $title === '' ? $stored->displayName : $title, $this->nextPosition($event), $stored);
        $this->contents->add($content);

        return $content;
    }

    public function updateText(TextContent $content, string $title, string $body): void
    {
        $content->edit($title, $body);
        $this->contents->save();
    }

    public function updateLink(LinkContent $content, string $title, string $url): void
    {
        $content->edit($title, $url);
        $this->contents->save();
    }

    /**
     * @throws InvalidUploadException
     */
    public function updateFile(FileContent $content, string $title, ?FileUpload $upload): void
    {
        $content->rename($title);
        if ($upload !== null && $upload->hasFile()) {
            $old = $content->getPath();
            $content->replaceFile($this->files->store($content->getEvent()->getId(), $upload));
            $this->files->delete($old);
        }
        $this->contents->save();
    }

    /**
     * Moves the block one step up (-1) or down (+1) by swapping positions with its neighbour.
     */
    public function move(EventContent $content, int $direction): void
    {
        $all = $this->contents->findByEvent($content->getEvent());
        $index = array_search($content, $all, true);
        $neighbour = $index === false ? null : ($all[$index + $direction] ?? null);
        if ($neighbour === null) {
            return;
        }

        $position = $content->getPosition();
        $content->setPosition($neighbour->getPosition());
        $neighbour->setPosition($position);
        $this->contents->save();
    }

    public function remove(EventContent $content): void
    {
        $this->contents->remove($content);
        if ($content instanceof FileContent) {
            $this->files->delete($content->getPath());
        }
    }

    private function nextPosition(Event $event): int
    {
        $positions = array_map(static fn (EventContent $content): int => $content->getPosition(), $this->contents->findByEvent($event));

        return $positions === [] ? 1 : max($positions) + 1;
    }
}
