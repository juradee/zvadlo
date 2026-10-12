<?php

declare(strict_types=1);

namespace App\Model\BugReport;

use InvalidArgumentException;
use Nette\Http\FileUpload;
use Nette\Utils\Random;
use Nette\Utils\Strings;

/**
 * Screenshots of bug reports live in uploads/bug-report-screenshots outside the web root.
 */
final class ScreenshotStorage
{
    public const int MAX_SIZE = 5 * 1024 * 1024;
    public const string DIRECTORY = 'bug-report-screenshots';

    /** Raster formats only: SVG is executable and would be served back to administrators. */
    public const array ACCEPTED_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public function __construct(private readonly string $uploadDir)
    {
    }

    /**
     * @return array{path: string, originalName: string, contentType: string, size: int}
     *
     * @throws InvalidArgumentException when the upload is not a valid image
     */
    public function store(FileUpload $upload): array
    {
        if (! $upload->isOk()) {
            throw new InvalidArgumentException('Screenshot se nepodařilo nahrát.');
        }
        if ($upload->getSize() > self::MAX_SIZE) {
            throw new InvalidArgumentException('Screenshot může mít nejvýše 5 MB.');
        }
        $contentType = $upload->getContentType() ?? '';
        if (! self::accepts($contentType)) {
            throw new InvalidArgumentException('Screenshot musí být obrázek PNG, JPEG, GIF nebo WebP.');
        }

        $extension = $upload->getSuggestedExtension() ?? 'bin';
        $relative = self::DIRECTORY.'/'.Random::generate(24, '0-9a-z').'.'.$extension;
        $upload->move($this->uploadDir.'/'.$relative);
        $name = Strings::webalize(pathinfo($upload->getUntrustedName(), \PATHINFO_FILENAME));

        return [
            'path' => $relative,
            'originalName' => ($name === '' ? 'screenshot' : $name).'.'.$extension,
            'contentType' => $contentType,
            'size' => $upload->getSize(),
        ];
    }

    public static function accepts(?string $contentType): bool
    {
        return \in_array($contentType, self::ACCEPTED_TYPES, true);
    }

    public function absolutePath(TechnicalErrorReport $report): ?string
    {
        $path = $report->getScreenshotPath();
        if ($path === null || ! is_file($this->uploadDir.'/'.$path)) {
            return null;
        }

        return $this->uploadDir.'/'.$path;
    }
}
