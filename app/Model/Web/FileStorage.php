<?php

declare(strict_types=1);

namespace App\Model\Web;

use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Nette\Utils\Random;
use Nette\Utils\Strings;

/**
 * Attachments live in uploads/ outside the web root; only the presenter hands them out.
 */
final class FileStorage
{
    public const int MAX_SIZE = 10 * 1024 * 1024;

    public const array ALLOWED_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(private readonly string $uploadDir)
    {
    }

    /**
     * @throws InvalidUploadException
     */
    public function store(int $eventId, FileUpload $upload): StoredFile
    {
        if (! $upload->isOk()) {
            throw new InvalidUploadException('Soubor se nepodařilo nahrát.');
        }
        if ($upload->getSize() > self::MAX_SIZE) {
            throw new InvalidUploadException('Soubor je větší než 10 MB.');
        }
        $mimeType = (string) $upload->getContentType();
        $extension = self::ALLOWED_TYPES[$mimeType] ?? null;
        if ($extension === null) {
            throw new InvalidUploadException('Nahrát lze jen PDF, JPG nebo PNG.');
        }

        $relative = \sprintf('events/%d/%s.%s', $eventId, Random::generate(24, '0-9a-z'), $extension);
        $upload->move($this->uploadDir.'/'.$relative);

        $displayName = trim(pathinfo($upload->getUntrustedName(), \PATHINFO_FILENAME));
        $name = Strings::webalize($displayName);

        return new StoredFile($relative, ($name === '' ? 'priloha' : $name).'.'.$extension, $mimeType, $upload->getSize(), $displayName === '' ? 'Příloha' : $displayName);
    }

    public function delete(string $relativePath): void
    {
        FileSystem::delete($this->absolutePath($relativePath));
    }

    public function absolutePath(string $relativePath): string
    {
        return $this->uploadDir.'/'.$relativePath;
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->absolutePath($relativePath));
    }
}
