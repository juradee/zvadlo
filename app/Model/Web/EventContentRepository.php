<?php

declare(strict_types=1);

namespace App\Model\Web;

use App\Model\Event\Event;
use Doctrine\ORM\EntityManagerInterface;

class EventContentRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function find(int $id): ?EventContent
    {
        return $this->entityManager->find(EventContent::class, $id);
    }

    /**
     * @return list<EventContent>
     */
    public function findByEvent(Event $event): array
    {
        /** @var list<EventContent> $contents */
        $contents = $this->entityManager->getRepository(EventContent::class)->findBy(['event' => $event], ['position' => 'ASC', 'id' => 'ASC']);

        return $contents;
    }

    public function add(EventContent $content): void
    {
        $this->entityManager->persist($content);
        $this->entityManager->flush();
    }

    public function remove(EventContent $content): void
    {
        $this->entityManager->remove($content);
        $this->entityManager->flush();
    }

    public function save(): void
    {
        $this->entityManager->flush();
    }
}
