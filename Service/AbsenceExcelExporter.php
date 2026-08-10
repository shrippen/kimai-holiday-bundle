<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;

class AbsenceExcelExporter
{
    /**
     * @param Absence[] $absences
     */
    public function exportCsv(User $user, array $absences): string
    {
        $lines = [];
        $lines[] = $this->csvLine(['User', 'Type', 'Status', 'Start', 'End', 'Half day', 'Duration (s)', 'Comment']);

        foreach ($absences as $absence) {
            $lines[] = $this->csvLine([
                $user->getDisplayName(),
                $absence->getType()->value,
                $absence->getStatus()->value,
                $absence->getStartDate()?->format('Y-m-d') ?? '',
                $absence->getEndDate()?->format('Y-m-d') ?? '',
                $absence->isHalfDay() ? '1' : '0',
                (string) ($absence->getDuration() ?? ''),
                $absence->getComment() ?? '',
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $fields
     */
    private function csvLine(array $fields): string
    {
        return implode(',', array_map(static function (string $field): string {
            $escaped = str_replace('"', '""', $field);

            return '"' . $escaped . '"';
        }, $fields));
    }
}
