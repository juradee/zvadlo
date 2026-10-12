<?php

declare(strict_types=1);

namespace App\Model\Mailing;

/**
 * A rendered e-mail: subject, Markdown text and its HTML.
 */
final readonly class ComposedMail
{
    public function __construct(
        public string $subject,
        public string $text,
        public string $html,
    ) {
    }
}
