<?php

declare(strict_types=1);

namespace HolidayBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Day shifts: a contract day moves to another day of the same week.
 */
final class Version20261030100000 extends AbstractMigration
{
    private const SHIFT = 'kimai2_ext_holiday_workday_shift';

    public function getDescription(): string
    {
        return 'Tagesverschiebung';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable(self::SHIFT)) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $table = $schema->createTable(self::SHIFT);
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('from_date', 'date_immutable', ['notnull' => true]);
        $table->addColumn('to_date', 'date_immutable', ['notnull' => true]);
        $table->addColumn('status', 'string', ['length' => 32, 'notnull' => true]);
        $table->addColumn('comment', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
        $table->addColumn('approved_by_id', 'integer', ['notnull' => false]);
        $table->addColumn('approved_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id', 'from_date'], 'idx_holiday_shift_user_from');
        $table->addIndex(['user_id', 'to_date'], 'idx_holiday_shift_user_to');
        $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_WSHIFT_USER');
        $table->addForeignKeyConstraint('kimai2_users', ['approved_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_HOLIDAY_WSHIFT_APPROVER');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable(self::SHIFT)) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $schema->dropTable(self::SHIFT);
    }
}
