<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use Doctrine\ORM\EntityManagerInterface;

class UnitEmailTemplateRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array<string, UnitEmailTemplate> keyed by type value
     */
    public function findByUnit(int $unitId): array
    {
        $templates = [];
        foreach ($this->entityManager->getRepository(UnitEmailTemplate::class)->findBy(['unitId' => $unitId]) as $template) {
            $templates[$template->getType()->value] = $template;
        }

        return $templates;
    }

    /**
     * A text equal to the system default is not kept as the unit's own, so the unit follows later changes of it.
     */
    public function save(int $unitId, EmailType $type, string $subject, string $body): void
    {
        $default = EmailTemplate::default($type);
        if (trim($subject) === trim($default->subject) && self::normalize($body) === self::normalize($default->body)) {
            $this->reset($unitId, $type);

            return;
        }

        $template = $this->findByUnit($unitId)[$type->value] ?? null;
        if ($template === null) {
            $this->entityManager->persist(new UnitEmailTemplate($unitId, $type, $subject, $body));
        } else {
            $template->edit($subject, $body);
        }
        $this->entityManager->flush();
    }

    /**
     * Back to the system default text.
     */
    public function reset(int $unitId, EmailType $type): void
    {
        $template = $this->findByUnit($unitId)[$type->value] ?? null;
        if ($template !== null) {
            $this->entityManager->remove($template);
            $this->entityManager->flush();
        }
    }

    private static function normalize(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }
}
