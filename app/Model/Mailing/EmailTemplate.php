<?php

declare(strict_types=1);

namespace App\Model\Mailing;

/**
 * Subject and body of one kind of e-mail, before symbols are filled in.
 */
final readonly class EmailTemplate
{
    public function __construct(
        public EmailType $type,
        public string $subject,
        public string $body,
    ) {
    }

    public static function default(EmailType $type): self
    {
        return new self($type, $type->defaultSubject(), $type->defaultBody());
    }
}
