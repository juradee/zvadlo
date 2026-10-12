<?php

declare(strict_types=1);

namespace App\Model\Task;

use App\Model\Registration\Registration;
use Doctrine\ORM\Mapping as ORM;

/**
 * Which registrations a task works through.
 */
#[ORM\Entity]
#[ORM\Table(name: 'task_registration')]
class TaskRegistration
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'registrations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $task;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Registration::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Registration $registration;

    public function __construct(Task $task, Registration $registration)
    {
        $this->task = $task;
        $this->registration = $registration;
    }

    public function getRegistration(): Registration
    {
        return $this->registration;
    }
}
