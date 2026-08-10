<?php

declare(strict_types=1);

namespace HolidayBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260810000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'HolidayBundle initial schema: public holidays, absences, bookings, month locks';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_ext_holiday_ph_group')) {
            $table = $schema->createTable('kimai2_ext_holiday_ph_group');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('country', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('region', 'string', ['length' => 64, 'notnull' => false]);
            $table->setPrimaryKey(['id']);
        }

        if (!$schema->hasTable('kimai2_ext_holiday_public')) {
            $table = $schema->createTable('kimai2_ext_holiday_public');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('holiday_group_id', 'integer', ['notnull' => true]);
            $table->addColumn('holiday_date', 'date', ['notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('half_day', 'boolean', ['notnull' => true, 'default' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['holiday_date'], 'idx_holiday_public_date');
            $table->addForeignKeyConstraint('kimai2_ext_holiday_ph_group', ['holiday_group_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_PUBLIC_GROUP');
        }

        if (!$schema->hasTable('kimai2_ext_holiday_absence')) {
            $table = $schema->createTable('kimai2_ext_holiday_absence');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', 'integer', ['notnull' => true]);
            $table->addColumn('type', 'string', ['length' => 32, 'notnull' => true]);
            $table->addColumn('status', 'string', ['length' => 32, 'notnull' => true]);
            $table->addColumn('start_date', 'date', ['notnull' => true]);
            $table->addColumn('end_date', 'date', ['notnull' => true]);
            $table->addColumn('half_day', 'boolean', ['notnull' => true, 'default' => false]);
            $table->addColumn('duration', 'integer', ['notnull' => false]);
            $table->addColumn('comment', 'text', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
            $table->addColumn('approved_by_id', 'integer', ['notnull' => false]);
            $table->addColumn('approved_at', 'datetime_immutable', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['start_date', 'end_date'], 'idx_holiday_absence_dates');
            $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_ABSENCE_USER');
            $table->addForeignKeyConstraint('kimai2_users', ['approved_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_HOLIDAY_ABSENCE_APPROVER');
        }

        if (!$schema->hasTable('kimai2_ext_holiday_booking')) {
            $table = $schema->createTable('kimai2_ext_holiday_booking');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', 'integer', ['notnull' => true]);
            $table->addColumn('kind', 'string', ['length' => 16, 'notnull' => true]);
            $table->addColumn('amount_seconds', 'integer', ['notnull' => true, 'default' => 0]);
            $table->addColumn('amount_days', 'float', ['notnull' => true, 'default' => 0]);
            $table->addColumn('booking_date', 'date', ['notnull' => true]);
            $table->addColumn('comment', 'text', ['notnull' => true]);
            $table->addColumn('created_by_id', 'integer', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_BOOKING_USER');
            $table->addForeignKeyConstraint('kimai2_users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_HOLIDAY_BOOKING_CREATOR');
        }

        if (!$schema->hasTable('kimai2_ext_holiday_month_lock')) {
            $table = $schema->createTable('kimai2_ext_holiday_month_lock');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', 'integer', ['notnull' => true]);
            $table->addColumn('year', 'smallint', ['notnull' => true]);
            $table->addColumn('month', 'smallint', ['notnull' => true]);
            $table->addColumn('locked_by_id', 'integer', ['notnull' => false]);
            $table->addColumn('locked_at', 'datetime_immutable', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id', 'year', 'month'], 'uniq_holiday_month_lock');
            $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_LOCK_USER');
            $table->addForeignKeyConstraint('kimai2_users', ['locked_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_HOLIDAY_LOCK_BY');
        }
    }

    public function down(Schema $schema): void
    {
        foreach ([
            'kimai2_ext_holiday_month_lock',
            'kimai2_ext_holiday_booking',
            'kimai2_ext_holiday_absence',
            'kimai2_ext_holiday_contract',
            'kimai2_ext_holiday_public',
            'kimai2_ext_holiday_ph_group',
        ] as $table) {
            if ($schema->hasTable($table)) {
                $schema->dropTable($table);
            }
        }
    }
}
