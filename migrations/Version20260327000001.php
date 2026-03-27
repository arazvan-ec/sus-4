<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create subscriptions and campaigns tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscriptions (
            id UUID NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            email VARCHAR(320) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('COMMENT ON COLUMN subscriptions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN subscriptions.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE UNIQUE INDEX uniq_subscription ON subscriptions (user_id, entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_entity_status ON subscriptions (entity_type, entity_id, status)');
        $this->addSql('CREATE INDEX idx_user_status ON subscriptions (user_id, status)');

        $this->addSql('CREATE TABLE campaigns (
            id UUID NOT NULL,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            audience_criteria JSONB NOT NULL,
            editorial_id VARCHAR(255) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('COMMENT ON COLUMN campaigns.scheduled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN campaigns.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN campaigns.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE INDEX idx_status_scheduled ON campaigns (status, scheduled_at)');
        $this->addSql('CREATE INDEX idx_editorial ON campaigns (editorial_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP TABLE campaigns');
    }
}
