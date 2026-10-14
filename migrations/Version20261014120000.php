<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gmail accounts shared with subordinate units only when their unit allows it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE google_oauth ADD allowed_for_subunits TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE google_oauth DROP allowed_for_subunits');
    }
}
