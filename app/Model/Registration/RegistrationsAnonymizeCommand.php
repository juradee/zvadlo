<?php

declare(strict_types=1);

namespace App\Model\Registration;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'registrations:anonymize', description: 'Applies the retention rules: deletes answers of finished events and anonymises non-members (the scheduler runs this daily)')]
final class RegistrationsAnonymizeCommand extends Command
{
    public function __construct(private readonly RetentionService $retention)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only count what would change');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $result = $this->retention->run(new DateTimeImmutable(), $dryRun);
        $output->writeln(($dryRun ? 'Dry run: ' : '').$result->describe());

        return Command::SUCCESS;
    }
}
