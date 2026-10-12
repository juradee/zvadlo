<?php

declare(strict_types=1);

namespace App\Model\Web;

use App\Model\Event\Event;
use Doctrine\ORM\Mapping as ORM;

/**
 * An uploaded attachment kept outside the web root and served through the presenter with the web token.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event_content_file')]
class FileContent extends EventContent
{
    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    public function __construct(Event $event, string $title, int $position, StoredFile $file)
    {
        parent::__construct($event, $title, $position);
        $this->replaceFile($file);
    }

    public function getType(): ContentType
    {
        return ContentType::File;
    }

    public function replaceFile(StoredFile $file): void
    {
        $this->path = $file->path;
        $this->originalName = $file->originalName;
        $this->mimeType = $file->mimeType;
        $this->size = $file->size;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }
}
