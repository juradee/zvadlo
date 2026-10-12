<?php

declare(strict_types=1);

namespace App\Model\Task;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tasks:run', description: 'Processes queued tasks for up to 50 seconds (the scheduler runs this every minute)')]
final class TasksRunCommand extends Command
{
    public function __construct(private readonly TaskRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Seconds to run', (string) TaskRunner::TIME_LIMIT_SECONDS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = $input->getOption('time-limit');
        $batches = $this->runner->run(is_numeric($limit) ? (int) $limit : TaskRunner::TIME_LIMIT_SECONDS);
        $output->writeln(\sprintf('Processed %d batches.', $batches));

        return Command::SUCCESS;
    }
}
