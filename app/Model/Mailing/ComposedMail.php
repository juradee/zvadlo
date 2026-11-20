<?php

declare(strict_types=1);

namespace App\Model\Mailing;

/**
 * A rendered e-mail: subject, Markdown text and its HTML. The e-mail log gets the text with the personal link and
 * the edit code masked, so that the database never holds them in the clear.
 */
final readonly class ComposedMail
{
    public function __construct(
        public string $subject,
        public string $text,
        public string $html,
        public string $loggedText,
    ) {
    }
}
