<?php

declare(strict_types=1);

namespace App\Model\Mailing;

/**
 * Templates a new event starts with: the unit's defaults where set, otherwise the system texts.
 */
final class EmailTemplateProvider
{
    public function __construct(private readonly UnitEmailTemplateRepository $templates)
    {
    }

    /**
     * @return list<EmailTemplate>
     */
    public function forUnit(int $unitId): array
    {
        $own = $this->templates->findByUnit($unitId);
        $result = [];
        foreach (EmailType::eventTemplates() as $type) {
            $template = $own[$type->value] ?? null;
            $result[] = $template === null ? EmailTemplate::default($type) : new EmailTemplate($type, $template->getSubject(), $template->getBody());
        }

        return $result;
    }

    /**
     * The unit-wide template (rejection) or the system default.
     */
    public function unitTemplate(int $unitId, EmailType $type): EmailTemplate
    {
        $template = $this->templates->findByUnit($unitId)[$type->value] ?? null;

        return $template === null ? EmailTemplate::default($type) : new EmailTemplate($type, $template->getSubject(), $template->getBody());
    }
}
