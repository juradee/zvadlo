<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * A unit's default text for one kind of e-mail; new events start from it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'unit_email_template')]
#[ORM\UniqueConstraint(name: 'uniq_unit_email_template', columns: ['unit_id', 'type'])]
class UnitEmailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $unitId;

    #[ORM\Column(length: 20, enumType: EmailType::class)]
    private EmailType $type;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    public function __construct(int $unitId, EmailType $type, string $subject, string $body)
    {
        $this->unitId = $unitId;
        $this->type = $type;
        $this->subject = $subject;
        $this->body = $body;
    }

    public function edit(string $subject, string $body): void
    {
        $this->subject = $subject;
        $this->body = $body;
    }

    public function getId(): int
    {
        return $this->id ?? throw new LogicException('The template has not been persisted yet.');
    }

    public function getUnitId(): int
    {
        return $this->unitId;
    }

    public function getType(): EmailType
    {
        return $this->type;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
