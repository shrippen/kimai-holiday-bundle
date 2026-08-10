<?php

namespace KimaiPlugin\HolidayBundle\Service;

/**
 * Minimal ICS (iCalendar) parser for public-holiday VEVENT entries.
 */
class IcsHolidayParser
{
    /**
     * @param int|null $fromYear If set, keep events on/after 1 January of this year (future years included).
     *
     * @return list<array{date: string, name: string}>
     */
    public function parse(string $ics, ?int $fromYear = null): array
    {
        $ics = str_replace(["\r\n", "\r"], "\n", $ics);
        // Unfold continued lines (RFC 5545)
        $ics = preg_replace("/\n[ \t]/", '', $ics) ?? $ics;

        $events = [];
        if (!preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $blocks)) {
            return [];
        }

        foreach ($blocks[1] as $block) {
            $date = $this->extractDate($block);
            $name = $this->extractSummary($block);
            if ($date === null || $name === null || $name === '') {
                continue;
            }
            if ($fromYear !== null && (int) substr($date, 0, 4) < $fromYear) {
                continue;
            }
            $events[] = ['date' => $date, 'name' => $name];
        }

        // Dedupe by date+name
        $unique = [];
        foreach ($events as $event) {
            $key = $event['date'] . "\0" . $event['name'];
            $unique[$key] = $event;
        }
        $events = array_values($unique);
        usort($events, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $events;
    }

    private function extractDate(string $block): ?string
    {
        // DTSTART;VALUE=DATE:20260101  or  DTSTART:20260101T000000Z  or  DTSTART;TZID=...:20260101T000000
        if (!preg_match('/^DTSTART[^:]*:([0-9]{8})/mi', $block, $m)) {
            return null;
        }

        $raw = $m[1];

        return sprintf('%s-%s-%s', substr($raw, 0, 4), substr($raw, 4, 2), substr($raw, 6, 2));
    }

    private function extractSummary(string $block): ?string
    {
        if (!preg_match('/^SUMMARY[^:]*:(.*)$/mi', $block, $m)) {
            return null;
        }

        $name = trim($m[1]);
        // Unescape ICS text
        $name = str_replace(['\\\\', '\\;', '\\,', '\\n', '\\N'], ['\\', ';', ',', "\n", "\n"], $name);

        return trim($name);
    }
}
