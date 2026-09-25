<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Enum\CalculationMode;

/**
 * Optionally creates compensatory timesheet entries for approved absences.
 *
 * Generated entries are tagged with the timesheet meta field META_KEY (value = absence id),
 * are never billable and are left untouched once they were exported.
 * Entries created by older plugin versions (no meta field) are recognised only by their exact
 * description "Absence (#N)" on the configured absence project/activity.
 */
class AbsenceTimesheetService
{
    public const META_KEY = 'holiday_absence_id';
    private const LEGACY_DESCRIPTION = '/^Absence \(#(\d+)\)$/';

    public function __construct(
        private readonly HolidayConfiguration $configuration,
        private readonly UserWorkContract $userWorkContract,
        private readonly ProjectRepository $projectRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AbsenceWorkdayHelper $workdayHelper,
    ) {
    }

    public function syncAbsence(Absence $absence): void
    {
        if ($this->configuration->getCalculationMode($absence->getType()) !== CalculationMode::COMPENSATE) {
            return;
        }

        if ($absence->getType() === AbsenceType::TIME_OFF) {
            return;
        }

        $projectId = $this->configuration->getAbsenceProjectId();
        $activityId = $this->configuration->getAbsenceActivityId();
        if ($projectId === null || $activityId === null) {
            return;
        }

        /** @var Project|null $project */
        $project = $this->projectRepository->find($projectId);
        /** @var Activity|null $activity */
        $activity = $this->activityRepository->find($activityId);
        $user = $absence->getUser();

        if ($project === null || $activity === null || $user === null || $absence->getId() === null) {
            return;
        }

        // Exported entries are kept; do not create a second entry for those days.
        $keptDays = $this->removeAbsenceTimesheets($absence);

        $cursor = $absence->getStartDate();
        $end = $absence->getEndDate();
        if ($cursor === null || $end === null) {
            return;
        }

        while ($cursor <= $end) {
            if (isset($keptDays[$cursor->format('Y-m-d')]) || !$this->workdayHelper->isAbsenceApplicableDay($user, $cursor)) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $expected = $this->userWorkContract->getExpectedSecondsForDate($user, $cursor);
            if ($expected <= 0) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $duration = $absence->isHalfDay() ? (int) floor($expected / 2) : $expected;
            if ($absence->getType() === AbsenceType::OTHER && $absence->getDuration() !== null) {
                $duration = $absence->getDuration();
            }

            if ($duration <= 0) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $begin = $cursor->setTime(9, 0);
            $endTime = $begin->modify(sprintf('+%d seconds', $duration));

            $timesheet = new Timesheet();
            $timesheet->setUser($user);
            $timesheet->setProject($project);
            $timesheet->setActivity($activity);
            $timesheet->setBegin(\DateTime::createFromImmutable($begin));
            $timesheet->setEnd(\DateTime::createFromImmutable($endTime));
            $timesheet->setDuration($duration);
            $timesheet->setDescription(sprintf('Absence (#%d)', $absence->getId()));
            $timesheet->setBillableMode(Timesheet::BILLABLE_NO);
            $timesheet->setBillable(false);

            $meta = new TimesheetMeta();
            $meta->setName(self::META_KEY);
            $meta->setValue((string) $absence->getId());
            $meta->setIsVisible(false);
            $timesheet->setMetaField($meta);

            $this->entityManager->persist($timesheet);
            $cursor = $cursor->modify('+1 day');
        }

        $this->entityManager->flush();
    }

    /**
     * Removes the generated timesheets of an absence. Exported entries are kept.
     *
     * @return array<string, true> days (Y-m-d) that still have a (kept, exported) entry
     */
    public function removeAbsenceTimesheets(Absence $absence): array
    {
        if ($absence->getId() === null || $absence->getUser() === null) {
            return [];
        }

        $kept = [];
        $removed = false;
        foreach ($this->findAbsenceTimesheets($absence->getUser(), null, null, $absence->getId()) as [$timesheet]) {
            if ($timesheet->isExported()) {
                $kept[$timesheet->getBegin()?->format('Y-m-d') ?? ''] = true;
                continue;
            }
            $this->entityManager->remove($timesheet);
            $removed = true;
        }

        if ($removed) {
            $this->entityManager->flush();
        }

        return $kept;
    }

    /**
     * Generated absence timesheet seconds per absence and day, so the calculators can skip
     * the absence credit where the timesheet already contains it.
     *
     * @return array<int, array<string, int>> absence id => [Y-m-d => seconds]
     */
    public function getAbsenceTimesheetDays(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $result = [];
        foreach ($this->findAbsenceTimesheets($user, $from, $to) as [$timesheet, $absenceId]) {
            $key = $timesheet->getBegin()?->format('Y-m-d');
            if ($key === null) {
                continue;
            }
            $result[$absenceId][$key] = ($result[$absenceId][$key] ?? 0) + (int) ($timesheet->getDuration() ?? 0);
        }

        return $result;
    }

    /**
     * @return list<array{0: Timesheet, 1: int}> timesheet + absence id
     */
    private function findAbsenceTimesheets(User $user, ?\DateTimeInterface $from, ?\DateTimeInterface $to, ?int $absenceId = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t', 'm.value AS absenceMeta')
            ->from(Timesheet::class, 't')
            ->leftJoin('t.meta', 'm', 'WITH', 'm.name = :metaName')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->setParameter('metaName', self::META_KEY);

        if ($from !== null) {
            $qb->andWhere('t.begin >= :from')->setParameter('from', \DateTimeImmutable::createFromInterface($from)->setTime(0, 0));
        }
        if ($to !== null) {
            $qb->andWhere('t.begin <= :to')->setParameter('to', \DateTimeImmutable::createFromInterface($to)->setTime(23, 59, 59));
        }

        $legacy = $qb->expr()->andX('m.id IS NULL', 't.description LIKE :legacyPrefix');
        $projectId = $this->configuration->getAbsenceProjectId();
        $activityId = $this->configuration->getAbsenceActivityId();
        $withLegacy = $projectId !== null && $activityId !== null;

        if ($withLegacy) {
            $legacy->add('IDENTITY(t.project) = :legacyProject');
            $legacy->add('IDENTITY(t.activity) = :legacyActivity');
            $qb->setParameter('legacyProject', $projectId)
                ->setParameter('legacyActivity', $activityId)
                ->setParameter('legacyPrefix', 'Absence (#%)');
        }

        if ($absenceId !== null) {
            $metaMatch = 'm.value = :absenceId';
            $qb->setParameter('absenceId', (string) $absenceId);
            if ($withLegacy) {
                $qb->andWhere($qb->expr()->orX($metaMatch, $legacy));
            } else {
                $qb->andWhere($metaMatch);
            }
        } elseif ($withLegacy) {
            $qb->andWhere($qb->expr()->orX('m.id IS NOT NULL', $legacy));
        } else {
            $qb->andWhere('m.id IS NOT NULL');
        }

        $result = [];
        foreach ($qb->getQuery()->getResult() as $row) {
            /** @var Timesheet $timesheet */
            $timesheet = $row[0];
            $metaValue = $row['absenceMeta'] ?? null;
            if ($metaValue !== null && $metaValue !== '') {
                $id = (int) $metaValue;
            } elseif (preg_match(self::LEGACY_DESCRIPTION, (string) $timesheet->getDescription(), $m)) {
                $id = (int) $m[1];
            } else {
                continue;
            }

            if ($absenceId !== null && $id !== $absenceId) {
                continue;
            }
            $result[] = [$timesheet, $id];
        }

        return $result;
    }
}
