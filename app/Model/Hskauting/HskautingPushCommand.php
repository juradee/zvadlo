<?php

declare(strict_types=1);

namespace App\Model\Hskauting;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'hskauting:push', description: 'Pushes changed events to h.skauting (the scheduler runs this every minute); --all resends every linked event')]
final class HskautingPushCommand extends Command
{
    public function __construct(private readonly HskautingNotifier $notifier)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Resend every event written to skautIS, ignoring changes and backoff');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->notifier->push(new DateTimeImmutable(), (bool) $input->getOption('all'));
        $output->writeln($result->describe());

        return $result->error === null ? Command::SUCCESS : Command::FAILURE;
    }
}
