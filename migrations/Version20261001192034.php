<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261001192034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_message (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, to_email VARCHAR(255) NOT NULL, to_name VARCHAR(255) DEFAULT NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, state VARCHAR(20) NOT NULL, sent_at DATETIME NOT NULL, error VARCHAR(1000) DEFAULT NULL, task_id INT DEFAULT NULL, event_id INT DEFAULT NULL, registration_id INT DEFAULT NULL, oauth_id VARCHAR(36) DEFAULT NULL, INDEX idx_email_message_registration (registration_id, type), INDEX IDX_B7D58B08DB60186 (task_id), INDEX IDX_B7D58B071F7E88B (event_id), INDEX IDX_B7D58B0833D8F43 (registration_id), INDEX IDX_B7D58B07107339C (oauth_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, unit_id INT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(100) NOT NULL, skautis_type_id VARCHAR(50) DEFAULT NULL, skautis_scope_id VARCHAR(50) DEFAULT NULL, date_from DATE NOT NULL, date_to DATE NOT NULL, place VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, registration_deadline DATE DEFAULT NULL, waitlist_enabled TINYINT DEFAULT 1 NOT NULL, lock_notice_days SMALLINT NOT NULL, answers_forgotten_at DATETIME DEFAULT NULL, capacity INT DEFAULT NULL, price INT DEFAULT NULL, contact_name VARCHAR(255) NOT NULL, contact_email VARCHAR(255) NOT NULL, contact_phone VARCHAR(50) DEFAULT NULL, state VARCHAR(20) NOT NULL, public_registration VARCHAR(20) NOT NULL, skautis_event_id INT DEFAULT NULL, skautis_camp_id INT DEFAULT NULL, hskauting_pushed_at DATETIME DEFAULT NULL, web_token VARCHAR(64) NOT NULL, created_by_user_id INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, oauth_id VARCHAR(36) DEFAULT NULL, INDEX idx_event_unit (unit_id), UNIQUE INDEX uniq_event_unit_slug (unit_id, slug), INDEX IDX_3BAE0AA77107339C (oauth_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE event_content (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, position INT NOT NULL, event_id INT NOT NULL, type VARCHAR(10) NOT NULL, INDEX idx_event_content_event (event_id, position), INDEX IDX_7B92990671F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE event_content_file (path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE event_content_link (url VARCHAR(2000) NOT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE event_content_text (body LONGTEXT NOT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE event_email (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, enabled TINYINT NOT NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, event_id INT NOT NULL, UNIQUE INDEX uniq_event_email_type (event_id, type), INDEX IDX_A689CD5871F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE event_field (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, label VARCHAR(255) NOT NULL, help VARCHAR(500) DEFAULT NULL, required TINYINT NOT NULL, position INT NOT NULL, hidden TINYINT NOT NULL, event_id INT NOT NULL, INDEX IDX_1AEEF47471F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE event_field_option (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, position INT NOT NULL, field_id INT NOT NULL, UNIQUE INDEX uniq_field_option_position (field_id, position), INDEX IDX_9DD18641443707B0 (field_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE google_oauth (id VARCHAR(36) NOT NULL, unit_id INT NOT NULL, email VARCHAR(255) NOT NULL, refresh_token_encrypted VARCHAR(1000) NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_google_oauth_unit_email (unit_id, email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE registration (id INT AUTO_INCREMENT NOT NULL, origin VARCHAR(20) NOT NULL, skautis_person_id INT DEFAULT NULL, pairing_hint VARCHAR(20) DEFAULT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, nick_name VARCHAR(100) DEFAULT NULL, sex VARCHAR(10) DEFAULT NULL, birth_date DATE DEFAULT NULL, street VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, postcode VARCHAR(20) DEFAULT NULL, contact_email VARCHAR(255) DEFAULT NULL, email_hash VARCHAR(64) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, note LONGTEXT DEFAULT NULL, state VARCHAR(20) NOT NULL, decline_reason LONGTEXT DEFAULT NULL, token_hash VARCHAR(64) NOT NULL, token_encrypted VARCHAR(255) NOT NULL, invited_at DATETIME DEFAULT NULL, seen_at DATETIME DEFAULT NULL, responded_at DATETIME DEFAULT NULL, waitlisted_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, rejected_at DATETIME DEFAULT NULL, failed_verifications SMALLINT NOT NULL, blocked_until DATETIME DEFAULT NULL, edit_code_hash VARCHAR(64) DEFAULT NULL, edit_code_expires_at DATETIME DEFAULT NULL, skautis_participant_id INT DEFAULT NULL, skautis_error VARCHAR(500) DEFAULT NULL, anonymized_at DATETIME DEFAULT NULL, skautis_person_created_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, event_id INT NOT NULL, INDEX idx_registration_event_state (event_id, state), INDEX idx_registration_event_email_hash (event_id, email_hash), UNIQUE INDEX uniq_registration_token (token_hash), UNIQUE INDEX uniq_registration_event_person (event_id, skautis_person_id), INDEX IDX_62A8A7A771F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE registration_boolean_value (value TINYINT NOT NULL, registration_id INT NOT NULL, field_id INT NOT NULL, INDEX IDX_58F4E2E2833D8F43 (registration_id), INDEX IDX_58F4E2E2443707B0 (field_id), PRIMARY KEY (registration_id, field_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE registration_log (id INT AUTO_INCREMENT NOT NULL, at DATETIME NOT NULL, actor VARCHAR(20) NOT NULL, actor_user_id INT DEFAULT NULL, message VARCHAR(255) NOT NULL, changes JSON DEFAULT NULL, registration_id INT NOT NULL, INDEX IDX_BBD2DF3833D8F43 (registration_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE registration_option_value (registration_id INT NOT NULL, option_id INT NOT NULL, INDEX IDX_82623D98833D8F43 (registration_id), INDEX IDX_82623D98A7C41D6F (option_id), PRIMARY KEY (registration_id, option_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE registration_recipient (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, source VARCHAR(20) NOT NULL, selected TINYINT NOT NULL, registration_id INT NOT NULL, UNIQUE INDEX uniq_recipient_registration_email (registration_id, email), INDEX IDX_8B58052C833D8F43 (registration_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE registration_text_value (value LONGTEXT NOT NULL, registration_id INT NOT NULL, field_id INT NOT NULL, INDEX IDX_76F82B40833D8F43 (registration_id), INDEX IDX_76F82B40443707B0 (field_id), PRIMARY KEY (registration_id, field_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE system_user_role (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, role VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_system_user_role (user_id, role), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE task (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, state VARCHAR(20) NOT NULL, payload JSON NOT NULL, attempts SMALLINT NOT NULL, progress_done INT NOT NULL, progress_total INT NOT NULL, error VARCHAR(1000) DEFAULT NULL, created_by_user_id INT DEFAULT NULL, created_at DATETIME NOT NULL, started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, heartbeat_at DATETIME DEFAULT NULL, event_id INT DEFAULT NULL, INDEX idx_task_state (state, id), INDEX IDX_527EDB2571F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE task_registration (task_id INT NOT NULL, registration_id INT NOT NULL, INDEX IDX_AA39838A8DB60186 (task_id), INDEX IDX_AA39838A833D8F43 (registration_id), PRIMARY KEY (task_id, registration_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE technical_error_report (id INT AUTO_INCREMENT NOT NULL, description LONGTEXT NOT NULL, reported_url VARCHAR(2048) DEFAULT NULL, reporter_user_id INT NOT NULL, reporter_display_name VARCHAR(255) NOT NULL, reporter_email VARCHAR(255) DEFAULT NULL, role_id INT DEFAULT NULL, role_name VARCHAR(255) DEFAULT NULL, unit_id INT DEFAULT NULL, unit_name VARCHAR(255) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, screenshot_path VARCHAR(255) DEFAULT NULL, screenshot_original_name VARCHAR(255) DEFAULT NULL, screenshot_content_type VARCHAR(100) DEFAULT NULL, screenshot_size INT DEFAULT NULL, app_release VARCHAR(255) NOT NULL, diagnostics JSON NOT NULL, created_at DATETIME NOT NULL, notification_sent_at DATETIME DEFAULT NULL, notification_error LONGTEXT DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, resolution_state VARCHAR(20) DEFAULT NULL, resolution_message LONGTEXT DEFAULT NULL, resolution_notification_sent_at DATETIME DEFAULT NULL, resolution_notification_error LONGTEXT DEFAULT NULL, reply_error LONGTEXT DEFAULT NULL, github_issue_number INT DEFAULT NULL, github_issue_url VARCHAR(2048) DEFAULT NULL, github_issue_created_at DATETIME DEFAULT NULL, github_sync_error LONGTEXT DEFAULT NULL, INDEX idx_error_report_created (created_at), INDEX idx_error_report_resolved (resolved_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE technical_error_report_reply (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, sent_at DATETIME NOT NULL, github_comment_url VARCHAR(2048) DEFAULT NULL, github_comment_error LONGTEXT DEFAULT NULL, report_id INT NOT NULL, INDEX IDX_7DDE9F214BD2A4C0 (report_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE unit_email_template (id INT AUTO_INCREMENT NOT NULL, unit_id INT NOT NULL, type VARCHAR(20) NOT NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, UNIQUE INDEX uniq_unit_email_template (unit_id, type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE unit_member_fingerprint (unit_id INT NOT NULL, person_id INT NOT NULL, fingerprint_full VARCHAR(64) NOT NULL, fingerprint_surname VARCHAR(64) NOT NULL, refreshed_at DATETIME NOT NULL, INDEX idx_fingerprint_full (unit_id, fingerprint_full), INDEX idx_fingerprint_surname (unit_id, fingerprint_surname), PRIMARY KEY (unit_id, person_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE unit_settings (unit_id INT NOT NULL, agreement_text LONGTEXT DEFAULT NULL, default_oauth_id VARCHAR(36) DEFAULT NULL, INDEX IDX_907B4A57774303D2 (default_oauth_id), PRIMARY KEY (unit_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE user_preference (user_id INT NOT NULL, extend_login TINYINT NOT NULL, remember_role TINYINT NOT NULL, remembered_role_id INT DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('ALTER TABLE email_message ADD CONSTRAINT FK_B7D58B08DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_message ADD CONSTRAINT FK_B7D58B071F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_message ADD CONSTRAINT FK_B7D58B0833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_message ADD CONSTRAINT FK_B7D58B07107339C FOREIGN KEY (oauth_id) REFERENCES google_oauth (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA77107339C FOREIGN KEY (oauth_id) REFERENCES google_oauth (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE event_content ADD CONSTRAINT FK_7B92990671F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_content_file ADD CONSTRAINT FK_ACEED126BF396750 FOREIGN KEY (id) REFERENCES event_content (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_content_link ADD CONSTRAINT FK_16DD7EC7BF396750 FOREIGN KEY (id) REFERENCES event_content (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_content_text ADD CONSTRAINT FK_1BFA40F1BF396750 FOREIGN KEY (id) REFERENCES event_content (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_email ADD CONSTRAINT FK_A689CD5871F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_field ADD CONSTRAINT FK_1AEEF47471F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_field_option ADD CONSTRAINT FK_9DD18641443707B0 FOREIGN KEY (field_id) REFERENCES event_field (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A771F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration_boolean_value ADD CONSTRAINT FK_58F4E2E2833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration_boolean_value ADD CONSTRAINT FK_58F4E2E2443707B0 FOREIGN KEY (field_id) REFERENCES event_field (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration_log ADD CONSTRAINT FK_BBD2DF3833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration_option_value ADD CONSTRAINT FK_82623D98833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration_option_value ADD CONSTRAINT FK_82623D98A7C41D6F FOREIGN KEY (option_id) REFERENCES event_field_option (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration_recipient ADD CONSTRAINT FK_8B58052C833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration_text_value ADD CONSTRAINT FK_76F82B40833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration_text_value ADD CONSTRAINT FK_76F82B40443707B0 FOREIGN KEY (field_id) REFERENCES event_field (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2571F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_registration ADD CONSTRAINT FK_AA39838A8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_registration ADD CONSTRAINT FK_AA39838A833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE technical_error_report_reply ADD CONSTRAINT FK_7DDE9F214BD2A4C0 FOREIGN KEY (report_id) REFERENCES technical_error_report (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE unit_settings ADD CONSTRAINT FK_907B4A57774303D2 FOREIGN KEY (default_oauth_id) REFERENCES google_oauth (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_message DROP FOREIGN KEY FK_B7D58B08DB60186');
        $this->addSql('ALTER TABLE email_message DROP FOREIGN KEY FK_B7D58B071F7E88B');
        $this->addSql('ALTER TABLE email_message DROP FOREIGN KEY FK_B7D58B0833D8F43');
        $this->addSql('ALTER TABLE email_message DROP FOREIGN KEY FK_B7D58B07107339C');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA77107339C');
        $this->addSql('ALTER TABLE event_content DROP FOREIGN KEY FK_7B92990671F7E88B');
        $this->addSql('ALTER TABLE event_content_file DROP FOREIGN KEY FK_ACEED126BF396750');
        $this->addSql('ALTER TABLE event_content_link DROP FOREIGN KEY FK_16DD7EC7BF396750');
        $this->addSql('ALTER TABLE event_content_text DROP FOREIGN KEY FK_1BFA40F1BF396750');
        $this->addSql('ALTER TABLE event_email DROP FOREIGN KEY FK_A689CD5871F7E88B');
        $this->addSql('ALTER TABLE event_field DROP FOREIGN KEY FK_1AEEF47471F7E88B');
        $this->addSql('ALTER TABLE event_field_option DROP FOREIGN KEY FK_9DD18641443707B0');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A771F7E88B');
        $this->addSql('ALTER TABLE registration_boolean_value DROP FOREIGN KEY FK_58F4E2E2833D8F43');
        $this->addSql('ALTER TABLE registration_boolean_value DROP FOREIGN KEY FK_58F4E2E2443707B0');
        $this->addSql('ALTER TABLE registration_log DROP FOREIGN KEY FK_BBD2DF3833D8F43');
        $this->addSql('ALTER TABLE registration_option_value DROP FOREIGN KEY FK_82623D98833D8F43');
        $this->addSql('ALTER TABLE registration_option_value DROP FOREIGN KEY FK_82623D98A7C41D6F');
        $this->addSql('ALTER TABLE registration_recipient DROP FOREIGN KEY FK_8B58052C833D8F43');
        $this->addSql('ALTER TABLE registration_text_value DROP FOREIGN KEY FK_76F82B40833D8F43');
        $this->addSql('ALTER TABLE registration_text_value DROP FOREIGN KEY FK_76F82B40443707B0');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB2571F7E88B');
        $this->addSql('ALTER TABLE task_registration DROP FOREIGN KEY FK_AA39838A8DB60186');
        $this->addSql('ALTER TABLE task_registration DROP FOREIGN KEY FK_AA39838A833D8F43');
        $this->addSql('ALTER TABLE technical_error_report_reply DROP FOREIGN KEY FK_7DDE9F214BD2A4C0');
        $this->addSql('ALTER TABLE unit_settings DROP FOREIGN KEY FK_907B4A57774303D2');
        $this->addSql('DROP TABLE email_message');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_content');
        $this->addSql('DROP TABLE event_content_file');
        $this->addSql('DROP TABLE event_content_link');
        $this->addSql('DROP TABLE event_content_text');
        $this->addSql('DROP TABLE event_email');
        $this->addSql('DROP TABLE event_field');
        $this->addSql('DROP TABLE event_field_option');
        $this->addSql('DROP TABLE google_oauth');
        $this->addSql('DROP TABLE registration');
        $this->addSql('DROP TABLE registration_boolean_value');
        $this->addSql('DROP TABLE registration_log');
        $this->addSql('DROP TABLE registration_option_value');
        $this->addSql('DROP TABLE registration_recipient');
        $this->addSql('DROP TABLE registration_text_value');
        $this->addSql('DROP TABLE system_user_role');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_registration');
        $this->addSql('DROP TABLE technical_error_report');
        $this->addSql('DROP TABLE technical_error_report_reply');
        $this->addSql('DROP TABLE unit_email_template');
        $this->addSql('DROP TABLE unit_member_fingerprint');
        $this->addSql('DROP TABLE unit_settings');
        $this->addSql('DROP TABLE user_preference');
    }
}
