<?php

/*
 * Demo data of the Studio Weber world (shrippen demo): the Hamburg public
 * holiday group, absences in every status and a locked previous month.
 * Needs the core data first (shrippen.github.io/demo/kimai/seed-core.php);
 * demo/start.sh runs both.
 */

use App\Entity\User;
use App\Kernel;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Entity\MonthLock;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;

require '/opt/kimai/vendor/autoload.php';
require __DIR__ . '/DemoWorld.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/opt/kimai/.env');

$kernel = new Kernel('prod', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();

$world = new DemoWorld(getenv('DEMO_LANG') ?: 'de', null, 'today');
$w = $world->data;
$users = [];
foreach ($w['people'] as $person) {
    $users[$person['id']] = $em->getRepository(User::class)->findOneBy(['email' => $person['email']])
        ?? throw new RuntimeException('Core demo data missing (seed-core.php first).');
}
if ($em->getRepository(PublicHolidayGroup::class)->findOneBy(['name' => 'Hamburg']) !== null) {
    echo "Already seeded.\n";
    exit(0);
}

$group = new PublicHolidayGroup();
$group->setName('Hamburg');
$group->setCountry('DE');
$group->setRegion('HH');
$em->persist($group);
foreach ($w['public_holidays']['dates'] as $date => $name) {
    $holiday = new PublicHoliday();
    $holiday->setHolidayGroup($group);
    $holiday->setDate(new DateTimeImmutable($date));
    $holiday->setName($world->t($name));
    $em->persist($holiday);
}
$em->flush();
foreach ($users as $user) {
    $user->setPublicHolidayGroup((string) $group->getId());
}

$types = ['holiday' => AbsenceType::VACATION, 'sickness' => AbsenceType::SICKNESS, 'time_off' => AbsenceType::TIME_OFF];
$states = ['approved' => AbsenceStatus::APPROVED, 'pending' => AbsenceStatus::REQUESTED, 'rejected' => AbsenceStatus::REJECTED];
foreach ($w['absences'] as $a) {
    $absence = new Absence();
    $absence->setUser($users[$a['user']]);
    $absence->setType($types[$a['type']]);
    $absence->setStatus($states[$a['status']]);
    $absence->setStartDate($world->date($a['from']));
    $absence->setEndDate($world->date($a['to']));
    $absence->setComment($world->t($a['comment']) ?: null);
    if ($a['status'] !== 'pending') {
        $absence->setApprovedBy($users['lena']);
        $absence->setApprovedAt($world->date(min($a['from'], 0) - 7, '10:00'));
    }
    $em->persist($absence);
}

// The previous month is closed for everyone.
$previous = $world->today->modify('first day of previous month');
foreach ($users as $user) {
    $lock = new MonthLock();
    $lock->setUser($user);
    $lock->setYear((int) $previous->format('Y'));
    $lock->setMonth((int) $previous->format('n'));
    $lock->setLockedBy($users['lena']);
    $lock->setLockedAt($previous->modify('last day of this month')->setTime(18, 0));
    $em->persist($lock);
}
$em->flush();

echo 'Seeded ' . count($w['absences']) . " absences, public holidays Hamburg, month lock {$previous->format('Y-m')}.\n";
