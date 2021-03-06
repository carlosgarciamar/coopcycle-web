<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180312131809 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE pricing_rule ALTER expression TYPE TEXT');
        $this->addSql('ALTER TABLE pricing_rule ALTER expression DROP DEFAULT');
        $this->addSql('ALTER TABLE pricing_rule ALTER price TYPE TEXT');
        $this->addSql('ALTER TABLE pricing_rule ALTER price DROP DEFAULT');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE pricing_rule ALTER expression TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE pricing_rule ALTER expression DROP DEFAULT');
        $this->addSql('ALTER TABLE pricing_rule ALTER price TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE pricing_rule ALTER price DROP DEFAULT');
    }
}
