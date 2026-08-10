<?php

declare(strict_types=1);

namespace HolidayBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260810100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen public holiday group country/region columns';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_ext_holiday_ph_group')) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $this->addSql('ALTER TABLE kimai2_ext_holiday_ph_group CHANGE country country VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE kimai2_ext_holiday_ph_group CHANGE region region VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_ext_holiday_ph_group')) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $this->addSql('ALTER TABLE kimai2_ext_holiday_ph_group CHANGE country country VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE kimai2_ext_holiday_ph_group CHANGE region region VARCHAR(50) DEFAULT NULL');
    }
}
