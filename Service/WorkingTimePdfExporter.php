<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;

class WorkingTimePdfExporter
{
    /**
     * @param array<string, mixed> $yearData from WorkingTimeCalculator::calculateYear
     */
    public function renderHtml(User $user, array $yearData, int $month): string
    {
        $monthData = $yearData['months'][$month] ?? null;
        if ($monthData === null) {
            throw new \InvalidArgumentException('Invalid month');
        }

        $rows = '';
        foreach ($monthData['days'] as $day) {
            $marker = $day['marker'] ? ' *' : '';
            $rows .= sprintf(
                '<tr><td>%s%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($day['date']),
                $marker,
                $this->formatDuration($day['expected']),
                $this->formatDuration($day['actual']),
                $this->formatDuration($day['delta'], true)
            );
        }

        return sprintf(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Working times %s %d-%02d</title>
            <style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}table{border-collapse:collapse;width:100%%}
            th,td{border:1px solid #ccc;padding:4px;text-align:right}th:first-child,td:first-child{text-align:left}
            h1{font-size:18px}</style></head><body>
            <h1>Working times — %s — %d-%02d</h1>
            <p>Expected: %s | Actual: %s | Delta: %s</p>
            <table><thead><tr><th>Date</th><th>Expected</th><th>Actual</th><th>Delta</th></tr></thead>
            <tbody>%s</tbody></table>
            <p style="margin-top:16px;font-size:10px">* marks public holidays or absences. Generated %s.</p>
            </body></html>',
            htmlspecialchars($user->getDisplayName()),
            $yearData['year'],
            $month,
            htmlspecialchars($user->getDisplayName()),
            $yearData['year'],
            $month,
            $this->formatDuration($monthData['expected']),
            $this->formatDuration($monthData['actual']),
            $this->formatDuration($monthData['delta'], true),
            $rows,
            (new \DateTimeImmutable())->format('Y-m-d H:i')
        );
    }

    private function formatDuration(int $seconds, bool $signed = false): string
    {
        $sign = '';
        if ($signed && $seconds < 0) {
            $sign = '-';
            $seconds = abs($seconds);
        } elseif ($signed && $seconds > 0) {
            $sign = '+';
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return sprintf('%s%d:%02d', $sign, $h, $m);
    }
}
