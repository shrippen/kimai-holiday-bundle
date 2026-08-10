<?php

declare(strict_types=1);

namespace HolidayBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Remove unused plugin work-contract table (data lives in Kimai user preferences).
 */
final class Version20260810140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unused kimai2_ext_holiday_contract table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('kimai2_ext_holiday_contract')) {
            $schema->dropTable('kimai2_ext_holiday_contract');
        } else {
            $this->preventEmptyMigrationWarning();
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('kimai2_ext_holiday_contract')) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $table = $schema->createTable('kimai2_ext_holiday_contract');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('monday', 'integer', ['notnull' => true, 'default' => 28800]);
        $table->addColumn('tuesday', 'integer', ['notnull' => true, 'default' => 28800]);
        $table->addColumn('wednesday', 'integer', ['notnull' => true, 'default' => 28800]);
        $table->addColumn('thursday', 'integer', ['notnull' => true, 'default' => 28800]);
        $table->addColumn('friday', 'integer', ['notnull' => true, 'default' => 28800]);
        $table->addColumn('saturday', 'integer', ['notnull' => true, 'default' => 0]);
        $table->addColumn('sunday', 'integer', ['notnull' => true, 'default' => 0]);
        $table->addColumn('vacation_days_per_year', 'float', ['notnull' => true, 'default' => 30]);
        $table->addColumn('start_date', 'date_immutable', ['notnull' => false]);
        $table->addColumn('end_date', 'date_immutable', ['notnull' => false]);
        $table->addColumn('public_holiday_group_id', 'integer', ['notnull' => false]);
        $table->addColumn('active', 'boolean', ['notnull' => true, 'default' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['user_id'], 'uniq_holiday_contract_user');
    }
}
