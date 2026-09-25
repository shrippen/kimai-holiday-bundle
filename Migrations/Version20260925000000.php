<?php

declare(strict_types=1);

namespace HolidayBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Tag absence timesheets created by older plugin versions with the meta field
 * "holiday_absence_id" and make them non-billable.
 *
 * Only entries with the exact generated description "Absence (#N)" (N = an absence of the same user)
 * on the configured absence project/activity are touched.
 */
final class Version20260925000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tag legacy absence timesheets with the holiday_absence_id meta field, mark them non-billable';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_ext_holiday_absence') || !$schema->hasTable('kimai2_timesheet_meta')) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $legacy = "
            FROM kimai2_timesheet t
            INNER JOIN kimai2_ext_holiday_absence a ON a.user_id = t.user AND t.description = CONCAT('Absence (#', a.id, ')')
            INNER JOIN kimai2_configuration cp ON cp.name = 'holiday.absence_project_id' AND cp.value = CAST(t.project_id AS CHAR)
            INNER JOIN kimai2_configuration ca ON ca.name = 'holiday.absence_activity_id' AND ca.value = CAST(t.activity_id AS CHAR)
            LEFT JOIN kimai2_timesheet_meta m ON m.timesheet_id = t.id AND m.name = 'holiday_absence_id'
            WHERE m.id IS NULL";

        // Non-billable first (needs the "not yet tagged" condition), exported entries stay as they are.
        $this->addSql('UPDATE kimai2_timesheet SET billable = 0 WHERE exported = 0 AND id IN (SELECT id FROM (SELECT t.id ' . $legacy . ') x)');
        $this->addSql("INSERT INTO kimai2_timesheet_meta (timesheet_id, name, value, visible) SELECT t.id, 'holiday_absence_id', CAST(a.id AS CHAR), 0 " . $legacy);
    }

    public function down(Schema $schema): void
    {
        $this->preventEmptyMigrationWarning();
    }
}
