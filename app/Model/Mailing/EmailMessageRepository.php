<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Event\Event;
use App\Model\Registration\Registration;
use App\Model\Task\Task;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class EmailMessageRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return list<EmailMessage>
     */
    public function findByRegistration(Registration $registration): array
    {
        /** @var list<EmailMessage> $messages */
        $messages = $this->entityManager->getRepository(EmailMessage::class)->findBy(['registration' => $registration], ['sentAt' => 'DESC', 'id' => 'DESC']);

        return $messages;
    }

    /**
     * @return list<EmailMessage>
     */
    public function findByEvent(Event $event, int $limit = 100): array
    {
        /** @var list<EmailMessage> $messages */
        $messages = $this->entityManager->getRepository(EmailMessage::class)->findBy(['event' => $event], ['sentAt' => 'DESC', 'id' => 'DESC'], $limit);

        return $messages;
    }

    /**
     * Registration ids a task already wrote a message for (sent or failed), so a batch never repeats.
     *
     * @return list<int>
     */
    public function findHandledRegistrationIds(Task $task): array
    {
        /** @var list<array{id: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(m.registration) AS id')
            ->from(EmailMessage::class, 'm')
            ->where('m.task = :task')
            ->andWhere('m.registration IS NOT NULL')
            ->setParameter('task', $task)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_unique(array_map(static fn (array $row): int => (int) $row['id'], $rows)));
    }

    public function hasSentToday(Registration $registration, EmailType $type, DateTimeImmutable $today): bool
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(EmailMessage::class, 'm')
            ->where('m.registration = :registration')
            ->andWhere('m.type = :type')
            ->andWhere('m.state = :sent')
            ->andWhere('m.sentAt >= :from AND m.sentAt < :to')
            ->setParameter('registration', $registration)
            ->setParameter('type', $type)
            ->setParameter('sent', EmailMessageState::Sent)
            ->setParameter('from', $today->setTime(0, 0))
            ->setParameter('to', $today->setTime(0, 0)->modify('+1 day'))
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function add(EmailMessage $message): void
    {
        $this->entityManager->persist($message);
        $this->entityManager->flush();
    }
}
