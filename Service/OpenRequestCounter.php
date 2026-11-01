<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;

/** Open (requested) absences and workday shifts an approver is responsible for: menu badge and request page. */
class OpenRequestCounter
{
    public function __construct(
        private readonly ApproverResolver $resolver,
        private readonly AbsenceRepository $absenceRepository,
        private readonly WorkdayShiftRepository $shiftRepository,
    ) {
    }

    public function countFor(User $approver): int
    {
        $absences = $this->absenceRepository->findRequestedForUsers($this->resolver->subjectsFor($approver, ApproverAccess::PERMISSION));
        $shifts = $this->shiftRepository->findRequestedForUsers($this->resolver->subjectsFor($approver, WorkdayShiftService::PERMISSION));

        return \count($absences) + \count($shifts);
    }
}
