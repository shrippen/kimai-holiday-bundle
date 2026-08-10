<?php

declare(strict_types=1);

namespace HolidayBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260810120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store ICS subscription URL and from-year on public holiday groups';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_ext_holiday_ph_group')) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $table = $schema->getTable('kimai2_ext_holiday_ph_group');
        if (!$table->hasColumn('ics_url')) {
            $this->addSql('ALTER TABLE kimai2_ext_holiday_ph_group ADD ics_url VARCHAR(512) DEFAULT NULL');
        }
        if (!$table->hasColumn('ics_from_year')) {
            $this->addSql('ALTER TABLE kimai2_ext_holiday_ph_group ADD ics_from_year SMALLINT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_ext_holiday_ph_group')) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $table = $schema->getTable('kimai2_ext_holiday_ph_group');
        if ($table->hasColumn('ics_from_year')) {
            $this->addSql('ALTER TABLE kimai2_ext_holiday_ph_group DROP ics_from_year');
        }
        if ($table->hasColumn('ics_url')) {
            $this->addSql('ALTER TABLE kimai2_ext_holiday_ph_group DROP ics_url');
        }
    }
}
