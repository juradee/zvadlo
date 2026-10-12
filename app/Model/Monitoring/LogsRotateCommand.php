<?php

declare(strict_types=1);

namespace App\Model\Monitoring;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'logs:rotate', description: 'Rotates big log files and deletes old exception dumps (the scheduler runs this daily)')]
final class LogsRotateCommand extends Command
{
    public function __construct(private readonly LogRotation $rotation)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->rotation->run(new DateTimeImmutable());
        $output->writeln(\sprintf('%d files rotated, %d deleted.', $result['rotated'], $result['deleted']));

        return Command::SUCCESS;
    }
}
