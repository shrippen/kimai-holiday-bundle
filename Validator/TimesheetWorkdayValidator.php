<?php

namespace KimaiPlugin\HolidayBundle\Validator;

use App\Entity\Timesheet;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class TimesheetWorkdayValidator extends ConstraintValidator
{
    public function __construct(
        private readonly HolidayConfiguration $configuration,
        private readonly UserWorkContract $userWorkContract,
        private readonly AuthorizationCheckerInterface $security,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!($constraint instanceof TimesheetWorkday)) {
            throw new UnexpectedTypeException($constraint, TimesheetWorkday::class);
        }

        if (!($value instanceof Timesheet)) {
            throw new UnexpectedTypeException($value, Timesheet::class);
        }

        if (!$this->configuration->restrictTimesheetsToWorkdays()) {
            return;
        }

        if ($this->security->isGranted('workdays_override_timesheet')) {
            return;
        }

        $user = $value->getUser();
        $begin = $value->getBegin();
        if ($user === null || $begin === null) {
            return;
        }

        if (!$this->userWorkContract->hasWorkingTimeConfigured($user)) {
            return;
        }

        if ($this->userWorkContract->getExpectedSecondsForDate($user, $begin) <= 0) {
            $this->context->buildViolation($constraint->message)
                ->atPath('begin_date')
                ->setTranslationDomain('validators')
                ->setCode(TimesheetWorkday::NON_WORKDAY_ERROR)
                ->addViolation();
        }
    }
}
