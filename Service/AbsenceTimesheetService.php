<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Enum\CalculationMode;

/**
 * Optionally creates compensatory timesheet entries for approved absences.
 */
class AbsenceTimesheetService
{
    private const META_KEY = 'holiday_absence_id';

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

        if ($project === null || $activity === null || $user === null) {
            return;
        }

        $this->removeAbsenceTimesheets($absence);

        $cursor = $absence->getStartDate();
        $end = $absence->getEndDate();
        if ($cursor === null || $end === null) {
            return;
        }

        while ($cursor <= $end) {
            if (!$this->workdayHelper->isAbsenceApplicableDay($user, $cursor)) {
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
            $timesheet->setDescription(sprintf('Absence (#%d)', $absence->getId() ?? 0));

            $this->entityManager->persist($timesheet);
            $cursor = $cursor->modify('+1 day');
        }

        $this->entityManager->flush();
    }

    public function removeAbsenceTimesheets(Absence $absence): void
    {
        if ($absence->getId() === null || $absence->getUser() === null) {
            return;
        }

        $needle = sprintf('(#%d)', $absence->getId());
        $timesheets = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Timesheet::class, 't')
            ->andWhere('t.user = :user')
            ->andWhere('t.description LIKE :needle')
            ->setParameter('user', $absence->getUser())
            ->setParameter('needle', '%' . $needle . '%')
            ->getQuery()
            ->getResult();

        foreach ($timesheets as $timesheet) {
            $this->entityManager->remove($timesheet);
        }

        $this->entityManager->flush();
    }
}
