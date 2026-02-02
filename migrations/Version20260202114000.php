<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260202114000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add building tags to actions for recommendation filtering.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE action ADD building_tags JSON NOT NULL DEFAULT '[]'");
        $this->addSql("UPDATE action SET building_tags = '[\"bureau\",\"erp\",\"logement\"]' WHERE title IN ('Verifications HVAC et filtration', 'Protections solaires sur vitrages', 'Suivi des temperatures interieures', 'Optimiser l''inertie des locaux')");
        $this->addSql("UPDATE action SET building_tags = '[\"entrepot\"]' WHERE title = 'Rehausse des stocks sensibles'");
        $this->addSql("UPDATE action SET building_tags = '[\"entrepot\",\"erp\"]' WHERE title IN ('Pose de batardeaux', 'Stock de materiel d''urgence')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE action DROP building_tags');
    }
}
