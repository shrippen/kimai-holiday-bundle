<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds an ICS calendar with the user's public holidays and approved absences.
 * Absence events skip weekends and other non-working days (split into contiguous ranges).
 * Event titles use the calendar owner's language preference.
 */
class UserIcsCalendarBuilder
{
    public function __construct(
        private readonly AbsenceRepository $absenceRepository,
        private readonly PublicHolidayRepository $publicHolidayRepository,
        private readonly UserWorkContract $userWorkContract,
        private readonly TranslatorInterface $translator,
        private readonly AbsenceWorkdayHelper $workdayHelper,
    ) {
    }

    public function build(User $user, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): string
    {
        $now = new \DateTimeImmutable('now');
        $from ??= $now->modify('-1 year')->modify('first day of January')->setTime(0, 0);
        $to ??= $now->modify('+2 years')->modify('last day of December')->setTime(0, 0);
        $locale = $this->resolveLocale($user);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kimai HolidayBundle//' . strtoupper(substr($locale, 0, 2)),
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            $this->fold('X-WR-CALNAME:' . $this->escapeText(sprintf(
                '%s — %s',
                $this->t('absence.ics.calendar_name', $locale),
                $user->getDisplayName()
            ))),
        ];

        $group = $this->userWorkContract->getPublicHolidayGroup($user);
        if ($group !== null) {
            foreach ($this->publicHolidayRepository->findByGroupBetween($group, $from, $to) as $holiday) {
                $lines = array_merge($lines, $this->publicHolidayEvent($holiday, $locale));
            }
        }

        foreach ($this->absenceRepository->findApprovedBetween($user, $from, $to) as $absence) {
            $lines = array_merge($lines, $this->absenceEvents($absence, $locale));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function resolveLocale(User $user): string
    {
        if (method_exists($user, 'getLocale')) {
            $locale = $user->getLocale();
            if (\is_string($locale) && $locale !== '') {
                return $locale;
            }
        }

        if (method_exists($user, 'getLanguage')) {
            $language = $user->getLanguage();
            if (\is_string($language) && $language !== '') {
                return $language;
            }
        }

        return 'en';
    }

    private function t(string $id, string $locale): string
    {
        return $this->translator->trans($id, [], 'messages', $locale);
    }

    /**
     * @return list<string>
     */
    private function publicHolidayEvent(PublicHoliday $holiday, string $locale): array
    {
        $date = $holiday->getDate();
        if ($date === null || $this->workdayHelper->isWeekend($date)) {
            return [];
        }

        $start = $date->format('Ymd');
        $end = $date->modify('+1 day')->format('Ymd');
        $summary = $holiday->getName() ?: $this->t('menu.public_holidays', $locale);
        if ($holiday->isHalfDay()) {
            $summary .= ' (' . $this->t('public_holiday.half_day', $locale) . ')';
        }

        return $this->vevent(
            'ph-' . ($holiday->getId() ?? '0') . '@holiday-bundle',
            $start,
            $end,
            $summary
        );
    }

    /**
     * @return list<string>
     */
    private function absenceEvents(Absence $absence, string $locale): array
    {
        $summary = $this->t($absence->getType()->label(), $locale);
        if ($absence->isHalfDay()) {
            $summary .= ' (' . $this->t('absence.half_day', $locale) . ')';
        }
        $description = $absence->getComment();
        $id = $absence->getId() ?? 0;

        $lines = [];
        $part = 0;
        foreach ($this->workdayHelper->applicableRanges($absence) as [$rangeStart, $rangeEnd]) {
            ++$part;
            $lines = array_merge($lines, $this->vevent(
                sprintf('absence-%d-%d@holiday-bundle', $id, $part),
                $rangeStart->format('Ymd'),
                $rangeEnd->modify('+1 day')->format('Ymd'),
                $summary,
                $description
            ));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function vevent(string $uid, string $dtStart, string $dtEnd, string $summary, ?string $description = null): array
    {
        $stamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $stamp,
            'DTSTART;VALUE=DATE:' . $dtStart,
            'DTEND;VALUE=DATE:' . $dtEnd,
            $this->fold('SUMMARY:' . $this->escapeText($summary)),
        ];

        if ($description !== null && trim($description) !== '') {
            $lines[] = $this->fold('DESCRIPTION:' . $this->escapeText($description));
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function escapeText(string $value): string
    {
        return str_replace(
            ["\\", ';', ',', "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', ''],
            $value
        );
    }

    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = substr($line, 0, 75);
        $rest = substr($line, 75);
        while ($rest !== '' && $rest !== false) {
            $out .= "\r\n " . substr($rest, 0, 74);
            $rest = substr($rest, 74);
        }

        return $out;
    }
}
