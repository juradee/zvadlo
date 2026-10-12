<?php

declare(strict_types=1);

namespace App\Model\Registration;

use App\Model\Event\EventFieldOption;
use Doctrine\ORM\Mapping as ORM;

/**
 * A selected option of a choice field; a multi-choice field has one row per selected option.
 */
#[ORM\Entity]
#[ORM\Table(name: 'registration_option_value')]
class RegistrationOptionValue
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Registration::class, inversedBy: 'optionValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Registration $registration;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: EventFieldOption::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EventFieldOption $option;

    public function __construct(Registration $registration, EventFieldOption $option)
    {
        $this->registration = $registration;
        $this->option = $option;
    }

    public function getOption(): EventFieldOption
    {
        return $this->option;
    }
}
