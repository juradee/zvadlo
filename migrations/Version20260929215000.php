<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929215000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Events with custom registration fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, unit_id INT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(100) NOT NULL, skautis_type_id VARCHAR(50) NOT NULL, skautis_scope_id VARCHAR(50) NOT NULL, is_camp TINYINT NOT NULL, date_from DATE NOT NULL, date_to DATE NOT NULL, place VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, registration_deadline DATE DEFAULT NULL, edit_lock_days SMALLINT NOT NULL, capacity INT DEFAULT NULL, price INT DEFAULT NULL, contact_name VARCHAR(255) NOT NULL, contact_email VARCHAR(255) NOT NULL, contact_phone VARCHAR(50) DEFAULT NULL, state VARCHAR(20) NOT NULL, skautis_event_id INT DEFAULT NULL, skautis_camp_id INT DEFAULT NULL, web_token VARCHAR(64) NOT NULL, created_by_user_id INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_event_unit (unit_id), UNIQUE INDEX uniq_event_unit_slug (unit_id, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE event_field (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, label VARCHAR(255) NOT NULL, help VARCHAR(500) DEFAULT NULL, required TINYINT NOT NULL, position INT NOT NULL, hidden TINYINT NOT NULL, event_id INT NOT NULL, INDEX IDX_1AEEF47471F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE event_field_option (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, position INT NOT NULL, field_id INT NOT NULL, UNIQUE INDEX uniq_field_option_position (field_id, position), INDEX IDX_9DD18641443707B0 (field_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('ALTER TABLE event_field ADD CONSTRAINT FK_1AEEF47471F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_field_option ADD CONSTRAINT FK_9DD18641443707B0 FOREIGN KEY (field_id) REFERENCES event_field (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_field DROP FOREIGN KEY FK_1AEEF47471F7E88B');
        $this->addSql('ALTER TABLE event_field_option DROP FOREIGN KEY FK_9DD18641443707B0');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_field');
        $this->addSql('DROP TABLE event_field_option');
    }
}
