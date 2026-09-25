<?php

declare(strict_types=1);

namespace HolidayBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * The ICS feed token moves from the user preference "holiday_ics_token" (which Kimai returns in /api/users/me and
 * /api/users/{id} and hands to invoice/export templates) into its own table. Token values are moved unchanged,
 * so existing feed URLs keep working.
 */
final class Version20260925120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ICS feed token in its own table instead of the user preferences';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('kimai2_ext_holiday_ics_token')) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $table = $schema->createTable('kimai2_ext_holiday_ics_token');
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('token', 'string', ['length' => 48, 'notnull' => true]);
        $table->setPrimaryKey(['user_id']);
        $table->addUniqueIndex(['token'], 'uniq_holiday_ics_token');
        $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_ICS_TOKEN_USER');
    }

    /**
     * Runs after the table exists: move the tokens, then delete the old preference rows.
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement(
            "INSERT INTO kimai2_ext_holiday_ics_token (user_id, token)
             SELECT p.user_id, p.value FROM kimai2_user_preferences p
             WHERE p.name = 'holiday_ics_token' AND p.value IS NOT NULL AND CHAR_LENGTH(p.value) = 48
               AND NOT EXISTS (SELECT 1 FROM kimai2_ext_holiday_ics_token t WHERE t.user_id = p.user_id OR t.token = p.value)"
        );
        $this->connection->executeStatement("DELETE FROM kimai2_user_preferences WHERE name = 'holiday_ics_token'");
    }

    public function preDown(Schema $schema): void
    {
        parent::preDown($schema);

        if (!$schema->hasTable('kimai2_ext_holiday_ics_token')) {
            return;
        }

        $this->connection->executeStatement(
            "INSERT INTO kimai2_user_preferences (user_id, name, value)
             SELECT t.user_id, 'holiday_ics_token', t.token FROM kimai2_ext_holiday_ics_token t
             WHERE NOT EXISTS (SELECT 1 FROM kimai2_user_preferences p WHERE p.user_id = t.user_id AND p.name = 'holiday_ics_token')"
        );
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('kimai2_ext_holiday_ics_token')) {
            $schema->dropTable('kimai2_ext_holiday_ics_token');
        } else {
            $this->preventEmptyMigrationWarning();
        }
    }
}
