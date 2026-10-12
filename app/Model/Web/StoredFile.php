<?php

declare(strict_types=1);

namespace App\Model\Web;

final readonly class StoredFile
{
    public function __construct(
        public string $path,
        public string $originalName,
        public string $mimeType,
        public int $size,
        /** The name the leader gave the file, for the block title. */
        public string $displayName,
    ) {
    }
}
