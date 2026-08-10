<?php

namespace KimaiPlugin\HolidayBundle\Service;

/**
 * Curated public holiday ICS feeds.
 * German regional calendars: https://ics.tools/ (auto-updated subscription feeds).
 * Other countries: Google Calendar public holiday calendars.
 */
class HolidayIcsCatalog
{
    /**
     * @return list<array{id: string, label: string, country: string, region: ?string, url: string}>
     */
    public function all(): array
    {
        $feeds = [];
        foreach ($this->definitions() as $def) {
            $feeds[] = [
                'id' => $def['id'],
                'label' => $def['label'],
                'country' => $def['country'],
                'region' => $def['region'] ?? null,
                'url' => $def['url'] ?? $this->googleHolidayUrl($def['calendar']),
            ];
        }

        return $feeds;
    }

    /**
     * @return array{id: string, label: string, country: string, region: ?string, url: string}|null
     */
    public function get(string $id): ?array
    {
        foreach ($this->all() as $feed) {
            if ($feed['id'] === $id) {
                return $feed;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> label translation key => id
     */
    public function getChoices(): array
    {
        $choices = [];
        foreach ($this->all() as $feed) {
            $choices[$feed['label']] = $feed['id'];
        }

        return $choices;
    }

    private function googleHolidayUrl(string $calendarId): string
    {
        return 'https://calendar.google.com/calendar/ical/' . rawurlencode($calendarId) . '/public/basic.ics';
    }

    private function icsToolsFeiertage(string $slug): string
    {
        // Path segments may contain umlauts (e.g. baden-württemberg); encode per segment.
        return 'https://ics.tools/Feiertage/' . implode('/', array_map('rawurlencode', explode('/', $slug))) . '.ics';
    }

    /**
     * @return list<array{id: string, label: string, country: string, region?: string, calendar?: string, url?: string}>
     */
    private function definitions(): array
    {
        $deStates = [
            ['id' => 'de-bw', 'label' => 'public_holiday.ics.de_bw', 'region' => 'BW', 'slug' => 'baden-württemberg'],
            ['id' => 'de-by', 'label' => 'public_holiday.ics.de_by', 'region' => 'BY', 'slug' => 'bayern'],
            ['id' => 'de-be', 'label' => 'public_holiday.ics.de_be', 'region' => 'BE', 'slug' => 'berlin'],
            ['id' => 'de-bb', 'label' => 'public_holiday.ics.de_bb', 'region' => 'BB', 'slug' => 'brandenburg'],
            ['id' => 'de-hb', 'label' => 'public_holiday.ics.de_hb', 'region' => 'HB', 'slug' => 'bremen'],
            ['id' => 'de-hh', 'label' => 'public_holiday.ics.de_hh', 'region' => 'HH', 'slug' => 'hamburg'],
            ['id' => 'de-he', 'label' => 'public_holiday.ics.de_he', 'region' => 'HE', 'slug' => 'hessen'],
            ['id' => 'de-mv', 'label' => 'public_holiday.ics.de_mv', 'region' => 'MV', 'slug' => 'mecklenburg-vorpommern'],
            ['id' => 'de-ni', 'label' => 'public_holiday.ics.de_ni', 'region' => 'NI', 'slug' => 'niedersachsen'],
            ['id' => 'de-nw', 'label' => 'public_holiday.ics.de_nw', 'region' => 'NW', 'slug' => 'nordrhein-westfalen'],
            ['id' => 'de-rp', 'label' => 'public_holiday.ics.de_rp', 'region' => 'RP', 'slug' => 'rheinland-pfalz'],
            ['id' => 'de-sl', 'label' => 'public_holiday.ics.de_sl', 'region' => 'SL', 'slug' => 'saarland'],
            ['id' => 'de-sn', 'label' => 'public_holiday.ics.de_sn', 'region' => 'SN', 'slug' => 'sachsen'],
            ['id' => 'de-st', 'label' => 'public_holiday.ics.de_st', 'region' => 'ST', 'slug' => 'sachsen-anhalt'],
            ['id' => 'de-sh', 'label' => 'public_holiday.ics.de_sh', 'region' => 'SH', 'slug' => 'schleswig-holstein'],
            ['id' => 'de-th', 'label' => 'public_holiday.ics.de_th', 'region' => 'TH', 'slug' => 'thüringen'],
        ];

        $feeds = [
            // Germany — ics.tools (rolling multi-year feeds, auto-updated)
            [
                'id' => 'de-bundesweit',
                'label' => 'public_holiday.ics.de_bundesweit',
                'country' => 'DE',
                'url' => $this->icsToolsFeiertage('bundesweit'),
            ],
            [
                'id' => 'de-alle',
                'label' => 'public_holiday.ics.de_alle',
                'country' => 'DE',
                'url' => $this->icsToolsFeiertage('alle'),
            ],
        ];

        foreach ($deStates as $state) {
            $feeds[] = [
                'id' => $state['id'],
                'label' => $state['label'],
                'country' => 'DE',
                'region' => $state['region'],
                'url' => $this->icsToolsFeiertage($state['slug']),
            ];
        }

        return array_merge($feeds, [
            // Other countries — Google Calendar holiday ICS
            ['id' => 'at', 'label' => 'public_holiday.ics.at', 'country' => 'AT', 'calendar' => 'en.austrian#holiday@group.v.calendar.google.com'],
            ['id' => 'at-de', 'label' => 'public_holiday.ics.at_de', 'country' => 'AT', 'calendar' => 'de.austrian#holiday@group.v.calendar.google.com'],
            ['id' => 'ch', 'label' => 'public_holiday.ics.ch', 'country' => 'CH', 'calendar' => 'en.ch#holiday@group.v.calendar.google.com'],
            ['id' => 'ch-de', 'label' => 'public_holiday.ics.ch_de', 'country' => 'CH', 'calendar' => 'de.ch#holiday@group.v.calendar.google.com'],
            ['id' => 'fr', 'label' => 'public_holiday.ics.fr', 'country' => 'FR', 'calendar' => 'en.french#holiday@group.v.calendar.google.com'],
            ['id' => 'nl', 'label' => 'public_holiday.ics.nl', 'country' => 'NL', 'calendar' => 'en.dutch#holiday@group.v.calendar.google.com'],
            ['id' => 'be', 'label' => 'public_holiday.ics.be', 'country' => 'BE', 'calendar' => 'en.be#holiday@group.v.calendar.google.com'],
            ['id' => 'lu', 'label' => 'public_holiday.ics.lu', 'country' => 'LU', 'calendar' => 'en.lu#holiday@group.v.calendar.google.com'],
            ['id' => 'gb', 'label' => 'public_holiday.ics.gb', 'country' => 'GB', 'calendar' => 'en.uk#holiday@group.v.calendar.google.com'],
            ['id' => 'ie', 'label' => 'public_holiday.ics.ie', 'country' => 'IE', 'calendar' => 'en.irish#holiday@group.v.calendar.google.com'],
            ['id' => 'it', 'label' => 'public_holiday.ics.it', 'country' => 'IT', 'calendar' => 'en.italian#holiday@group.v.calendar.google.com'],
            ['id' => 'es', 'label' => 'public_holiday.ics.es', 'country' => 'ES', 'calendar' => 'en.spain#holiday@group.v.calendar.google.com'],
            ['id' => 'pt', 'label' => 'public_holiday.ics.pt', 'country' => 'PT', 'calendar' => 'en.portuguese#holiday@group.v.calendar.google.com'],
            ['id' => 'pl', 'label' => 'public_holiday.ics.pl', 'country' => 'PL', 'calendar' => 'en.polish#holiday@group.v.calendar.google.com'],
            ['id' => 'cz', 'label' => 'public_holiday.ics.cz', 'country' => 'CZ', 'calendar' => 'en.czech#holiday@group.v.calendar.google.com'],
            ['id' => 'hu', 'label' => 'public_holiday.ics.hu', 'country' => 'HU', 'calendar' => 'en.hungarian#holiday@group.v.calendar.google.com'],
            ['id' => 'ro', 'label' => 'public_holiday.ics.ro', 'country' => 'RO', 'calendar' => 'en.romanian#holiday@group.v.calendar.google.com'],
            ['id' => 'gr', 'label' => 'public_holiday.ics.gr', 'country' => 'GR', 'calendar' => 'en.greek#holiday@group.v.calendar.google.com'],
            ['id' => 'tr', 'label' => 'public_holiday.ics.tr', 'country' => 'TR', 'calendar' => 'en.turkish#holiday@group.v.calendar.google.com'],
            ['id' => 'se', 'label' => 'public_holiday.ics.se', 'country' => 'SE', 'calendar' => 'en.swedish#holiday@group.v.calendar.google.com'],
            ['id' => 'no', 'label' => 'public_holiday.ics.no', 'country' => 'NO', 'calendar' => 'en.norwegian#holiday@group.v.calendar.google.com'],
            ['id' => 'dk', 'label' => 'public_holiday.ics.dk', 'country' => 'DK', 'calendar' => 'en.danish#holiday@group.v.calendar.google.com'],
            ['id' => 'fi', 'label' => 'public_holiday.ics.fi', 'country' => 'FI', 'calendar' => 'en.finnish#holiday@group.v.calendar.google.com'],
            ['id' => 'us', 'label' => 'public_holiday.ics.us', 'country' => 'US', 'calendar' => 'en.usa#holiday@group.v.calendar.google.com'],
            ['id' => 'ca', 'label' => 'public_holiday.ics.ca', 'country' => 'CA', 'calendar' => 'en.canadian#holiday@group.v.calendar.google.com'],
            ['id' => 'mx', 'label' => 'public_holiday.ics.mx', 'country' => 'MX', 'calendar' => 'en.mexican#holiday@group.v.calendar.google.com'],
            ['id' => 'br', 'label' => 'public_holiday.ics.br', 'country' => 'BR', 'calendar' => 'en.brazilian#holiday@group.v.calendar.google.com'],
            ['id' => 'au', 'label' => 'public_holiday.ics.au', 'country' => 'AU', 'calendar' => 'en.australian#holiday@group.v.calendar.google.com'],
            ['id' => 'nz', 'label' => 'public_holiday.ics.nz', 'country' => 'NZ', 'calendar' => 'en.new_zealand#holiday@group.v.calendar.google.com'],
            ['id' => 'jp', 'label' => 'public_holiday.ics.jp', 'country' => 'JP', 'calendar' => 'en.japanese#holiday@group.v.calendar.google.com'],
            ['id' => 'kr', 'label' => 'public_holiday.ics.kr', 'country' => 'KR', 'calendar' => 'en.south_korea#holiday@group.v.calendar.google.com'],
            ['id' => 'cn', 'label' => 'public_holiday.ics.cn', 'country' => 'CN', 'calendar' => 'en.china#holiday@group.v.calendar.google.com'],
            ['id' => 'in', 'label' => 'public_holiday.ics.in', 'country' => 'IN', 'calendar' => 'en.indian#holiday@group.v.calendar.google.com'],
            ['id' => 'il', 'label' => 'public_holiday.ics.il', 'country' => 'IL', 'calendar' => 'en.jewish#holiday@group.v.calendar.google.com'],
            ['id' => 'za', 'label' => 'public_holiday.ics.za', 'country' => 'ZA', 'calendar' => 'en.sa#holiday@group.v.calendar.google.com'],
        ]);
    }
}
