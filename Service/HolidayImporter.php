<?php

namespace KimaiPlugin\HolidayBundle\Service;

use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Imports public holidays from ICS feeds (catalog or custom URL).
 * The ICS URL is stored on the group so future syncs pick up newly published years.
 */
class HolidayImporter
{
    public function __construct(
        private readonly PublicHolidayRepository $publicHolidayRepository,
        private readonly PublicHolidayGroupRepository $groupRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly HolidayIcsCatalog $catalog,
        private readonly IcsHolidayParser $parser,
    ) {
    }

    public function getCatalog(): HolidayIcsCatalog
    {
        return $this->catalog;
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    public function previewFromUrl(string $url, int $fromYear): array
    {
        $ics = $this->fetchIcs($url);

        return $this->parser->parse($ics, $fromYear);
    }

    /**
     * Import from a catalog entry or a custom ICS URL.
     * Stores the feed URL on the group for later syncs.
     *
     * @return int number of newly added holidays
     */
    public function import(
        PublicHolidayGroup $group,
        int $fromYear,
        ?string $catalogId = null,
        ?string $customUrl = null,
    ): int {
        $url = null;
        $country = $group->getCountry();
        $region = $group->getRegion();

        if ($customUrl !== null && trim($customUrl) !== '') {
            $url = trim($customUrl);
            if (!preg_match('#^https?://#i', $url)) {
                throw new \InvalidArgumentException('holiday.error.ics_invalid_url');
            }
        } elseif ($catalogId !== null && $catalogId !== '' && $catalogId !== 'custom') {
            $feed = $this->catalog->get($catalogId);
            if ($feed === null) {
                throw new \InvalidArgumentException('holiday.error.ics_unknown_catalog');
            }
            $url = $feed['url'];
            $country = $feed['country'];
            $region = $feed['region'];
        } else {
            throw new \InvalidArgumentException('holiday.error.ics_source_required');
        }

        $group->setIcsUrl($url);
        $group->setIcsFromYear($fromYear);
        if ($country !== null && $country !== '') {
            $group->setCountry(strtoupper($country));
        }
        $group->setRegion($region);

        return $this->applyHolidays($group, $this->loadHolidays($url, $fromYear));
    }

    /**
     * Re-fetch the group's stored ICS URL and add any new dates (from icsFromYear onward).
     *
     * @return int number of newly added holidays
     */
    public function sync(PublicHolidayGroup $group): int
    {
        $url = $group->getIcsUrl();
        if ($url === null || $url === '') {
            throw new \InvalidArgumentException('holiday.error.ics_no_subscription');
        }

        $fromYear = $group->getIcsFromYear() ?? (int) date('Y');

        return $this->applyHolidays($group, $this->loadHolidays($url, $fromYear));
    }

    /**
     * Sync all groups that have an ICS subscription configured.
     *
     * @return array{groups: int, holidays: int}
     */
    public function syncAll(): array
    {
        $groups = 0;
        $holidays = 0;

        foreach ($this->groupRepository->findAllOrdered() as $group) {
            if ($group->getIcsUrl() === null || $group->getIcsUrl() === '') {
                continue;
            }
            $holidays += $this->sync($group);
            ++$groups;
        }

        return ['groups' => $groups, 'holidays' => $holidays];
    }

    /**
     * @param list<array{date: string, name: string}> $holidays
     */
    private function applyHolidays(PublicHolidayGroup $group, array $holidays): int
    {
        $count = 0;

        foreach ($holidays as $item) {
            $date = new \DateTimeImmutable($item['date']);
            $existing = $this->publicHolidayRepository->findOneByGroupAndDate($group, $date);
            if ($existing !== null) {
                continue;
            }

            $holiday = new PublicHoliday();
            $holiday->setHolidayGroup($group);
            $holiday->setDate($date);
            $holiday->setName($item['name']);
            $this->publicHolidayRepository->save($holiday, false);
            ++$count;
        }

        // Persists ICS URL / from-year on the group and flushes the unit of work.
        $this->groupRepository->save($group);

        return $count;
    }

    /**
     * @return list<array{date: string, name: string}>
     */
    private function loadHolidays(string $url, int $fromYear): array
    {
        try {
            return $this->previewFromUrl($url, $fromYear);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->warning('HolidayBundle: ICS import failed: ' . $e->getMessage());
            throw new \RuntimeException('holiday.error.ics_fetch_failed', 0, $e);
        }
    }

    private function fetchIcs(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 30,
            'max_redirects' => 5,
            'headers' => [
                'Accept' => 'text/calendar, text/plain, */*',
                'User-Agent' => 'KimaiHolidayBundle/1.0',
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf('ICS HTTP %d', $status));
        }

        $body = $response->getContent();
        if (!str_contains($body, 'BEGIN:VCALENDAR') && !str_contains($body, 'BEGIN:VEVENT')) {
            throw new \RuntimeException('Response is not a valid ICS calendar');
        }

        return $body;
    }
}
