<?php

namespace KimaiPlugin\HolidayBundle\Controller;

use App\Controller\AbstractController;
use App\Repository\UserRepository;
use App\Utils\PageSetup;
use KimaiPlugin\HolidayBundle\Entity\ManualBooking;
use KimaiPlugin\HolidayBundle\Enum\ManualBookingKind;
use KimaiPlugin\HolidayBundle\Form\ManualBookingType;
use KimaiPlugin\HolidayBundle\Repository\ManualBookingRepository;
use KimaiPlugin\HolidayBundle\Service\MonthLockService;
use KimaiPlugin\HolidayBundle\Service\WorkingTimeCalculator;
use KimaiPlugin\HolidayBundle\Service\WorkingTimePdfExporter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/holiday')]
class ContractController extends AbstractController
{
    use TargetUserTrait;

    public function __construct(
        private readonly WorkingTimeCalculator $calculator,
        private readonly MonthLockService $monthLockService,
        private readonly ManualBookingRepository $bookingRepository,
        private readonly WorkingTimePdfExporter $pdfExporter,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route(path: '/working-times/{year}', name: 'holiday_working_times', defaults: ['year' => null], methods: ['GET', 'POST'])]
    #[IsGranted('hours_own_profile')]
    public function workingTimes(Request $request, ?int $year = null): Response
    {
        // Use Kimai's built-in Arbeitszeiten page (extended by this plugin's boxes/events).
        return $this->redirectToRoute('user_contract', array_filter([
            'user' => $request->query->get('user'),
            'date' => $year !== null ? sprintf('%d-01-01', $year) : null,
        ], static fn ($v) => $v !== null && $v !== ''));
    }

    #[Route(path: '/contract/edit', name: 'holiday_contract_edit', methods: ['GET', 'POST'])]
    #[IsGranted('hours_own_profile')]
    public function editContract(Request $request): Response
    {
        $user = $this->getTargetUser($request, $this->userRepository);
        if ($user !== $this->getUser() && !$this->isGranted('contract_other_profile')) {
            throw $this->createAccessDeniedException();
        }

        // Use Kimai's built-in profile → Arbeitsvertrag (extended by this plugin).
        return $this->redirectToRoute('user_profile_contract', [
            'username' => $user->getUserIdentifier(),
        ]);
    }

    #[Route(path: '/working-times/{year}/{month}/lock', name: 'holiday_month_lock', methods: ['POST'], requirements: ['month' => '\d+'])]
    #[IsGranted('approve_times_contract')]
    public function lockMonth(Request $request, int $year, int $month): Response
    {
        $user = $this->getTargetUser($request, $this->userRepository);
        $this->monthLockService->lock($user, $year, $month, $this->getUser());
        $this->flashSuccess('action.update.success');

        return $this->redirectToRoute('holiday_working_times', ['year' => $year, 'user' => $user->getId()]);
    }

    #[Route(path: '/working-times/{year}/{month}/unlock', name: 'holiday_month_unlock', methods: ['POST'], requirements: ['month' => '\d+'])]
    #[IsGranted('unlock_times_contract')]
    public function unlockMonth(Request $request, int $year, int $month): Response
    {
        $user = $this->getTargetUser($request, $this->userRepository);
        $this->monthLockService->unlock($user, $year, $month);
        $this->flashSuccess('action.update.success');

        return $this->redirectToRoute('holiday_working_times', ['year' => $year, 'user' => $user->getId()]);
    }

    #[Route(path: '/working-times/{year}/{month}/pdf', name: 'holiday_month_pdf', methods: ['GET'], requirements: ['month' => '\d+'])]
    #[IsGranted('view_booking_contract')]
    public function monthPdf(Request $request, int $year, int $month): Response
    {
        $user = $this->getTargetUser($request, $this->userRepository);
        $yearData = $this->calculator->calculateYear($user, $year);
        $html = $this->pdfExporter->renderHtml($user, $yearData, $month);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => sprintf('inline; filename="working-times-%d-%02d.html"', $year, $month),
        ]);
    }

    #[Route(path: '/booking/create', name: 'holiday_booking_create', methods: ['GET', 'POST'])]
    #[IsGranted('create_booking_contract')]
    public function createBooking(Request $request): Response
    {
        $user = $this->getTargetUser($request, $this->userRepository);
        $form = $this->createForm(ManualBookingType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ManualBookingKind $kind */
            $kind = $form->get('kind')->getData();
            $amount = (float) $form->get('amount')->getData();
            /** @var \DateTimeImmutable $date */
            $date = $form->get('bookingDate')->getData();
            $comment = (string) $form->get('comment')->getData();

            $booking = new ManualBooking();
            $booking->setUser($user);
            $booking->setKind($kind);
            $booking->setBookingDate($date);
            $booking->setComment($comment);
            $booking->setCreatedBy($this->getUser());

            if ($kind === ManualBookingKind::TIME) {
                $booking->setAmountSeconds((int) round($amount * 3600));
            } else {
                $booking->setAmountDays($amount);
            }

            $this->bookingRepository->save($booking);
            $this->flashSuccess('action.update.success');

            return $this->redirectToRoute('holiday_working_times', [
                'year' => (int) $date->format('Y'),
                'user' => $user->getId(),
            ]);
        }

        $page = new PageSetup('booking.create');

        return $this->render('@Holiday/contract/booking.html.twig', [
            'page_setup' => $page,
            'form' => $form->createView(),
            'target_user' => $user,
        ]);
    }
}
