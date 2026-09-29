<?php

declare(strict_types=1);

namespace App\Console;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drops every table of the configured database; used to rebuild the test database from migrations.
 */
#[AsCommand(name: 'app:database:drop', description: 'Drops all tables of the database (never in production)')]
final class DatabaseDropCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $appEnv,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Really drop the tables');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->appEnv === 'prod') {
            $output->writeln('<error>Refusing to drop tables in production.</error>');

            return Command::FAILURE;
        }

        if ($input->getOption('force') !== true) {
            $output->writeln('Add --force to drop all tables of the database.');

            return Command::INVALID;
        }

        $tables = $this->connection->fetchFirstColumn('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
        $views = $this->connection->fetchFirstColumn('SHOW FULL TABLES WHERE Table_type = \'VIEW\'');

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($views as $view) {
            $this->connection->executeStatement('DROP VIEW '.$this->connection->quoteSingleIdentifier((string) $view));
        }
        foreach ($tables as $table) {
            $this->connection->executeStatement('DROP TABLE '.$this->connection->quoteSingleIdentifier((string) $table));
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $output->writeln(\sprintf('Dropped %d tables and %d views.', \count($tables), \count($views)));

        return Command::SUCCESS;
    }
}
