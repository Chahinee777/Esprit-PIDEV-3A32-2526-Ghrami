<?php

namespace App\Controller;

use App\Entity\Meeting;
use App\Entity\User;
use App\Service\GoogleMeetService;
use App\Service\MeetingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/meetings')]
final class MeetingsController extends AbstractController
{
    #[Route('', name: 'app_meetings_index', methods: ['GET'])]
    public function index(Request $request, MeetingsService $meetingsService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->id === null) {
            return $this->redirectToRoute('app_login');
        }

        $filter = (string) $request->query->get('filter', 'upcoming');
        $selectedMeetingId = trim((string) $request->query->get('meeting', ''));

        return $this->render('meetings/index.html.twig', [
            'stats' => $meetingsService->getStats((int) $user->id),
            'connections' => $meetingsService->listAcceptedConnections((int) $user->id),
            'meetings' => $meetingsService->listMeetings((int) $user->id, $filter),
            'selectedMeetingId' => $selectedMeetingId,
            'participants' => $selectedMeetingId !== '' ? $meetingsService->listParticipants($selectedMeetingId) : [],
            'activeFilter' => $filter,
        ]);
    }

    #[Route('/create', name: 'app_meetings_create', methods: ['POST'])]
    public function create(
        Request $request,
        MeetingsService $meetingsService,
        GoogleMeetService $googleMeetService,
        SessionInterface $session,
        ValidatorInterface $validator
    ): Response {
        if (!$this->isCsrfTokenValid('meetings_create', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_meetings_index');
        }

        $user = $this->getUser();
        if (!$user instanceof User || $user->id === null) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $connectionId = trim((string) $request->request->get('connection_id', ''));
            $meetingType = (string) $request->request->get('meeting_type', 'virtual');
            $location = trim((string) $request->request->get('location', ''));
            $scheduledAt = trim((string) $request->request->get('scheduled_at', ''));
            $duration = (int) $request->request->get('duration', 60);

            if ($connectionId === '') {
                throw new \InvalidArgumentException('Connection is required.');
            }

            $scheduledAtDate = date_create_immutable($scheduledAt);
            if (!$scheduledAtDate) {
                throw new \InvalidArgumentException('Please provide a valid meeting date and time.');
            }

            $meetingValidation = new Meeting();
            $meetingValidation->meetingType = $meetingType;
            $meetingValidation->location = $location !== '' ? $location : null;
            $meetingValidation->scheduledAt = $scheduledAtDate;
            $meetingValidation->duration = $duration;
            $meetingValidation->status = 'scheduled';

            $violations = $validator->validate($meetingValidation);
            if (count($violations) > 0) {
                throw new \InvalidArgumentException($this->firstValidationMessage($violations));
            }

            if ($meetingType === 'physical' && $location === '') {
                throw new \InvalidArgumentException('Location is required for in-person meetings.');
            }

            if ($meetingType === 'virtual' && $location === '') {
                $meetLink = null;

                // Try user's personal Google credentials first (parity with Desktop model)
                $userAccessToken = $session->get('google_access_token');
                if ($userAccessToken !== null && $userAccessToken !== '') {
                    $meetLink = $googleMeetService->createMeetLinkWithUserToken(
                        (string) $userAccessToken,
                        $scheduledAt,
                        $duration,
                        'Ghrami Meeting',
                        'Scheduled from Ghrami platform'
                    );
                }

                // Fall back to service account if user credentials unavailable
                if ($meetLink === null) {
                    $meetLink = $googleMeetService->createMeetLink(
                        $scheduledAt,
                        $duration,
                        'Ghrami Meeting',
                        'Scheduled from Ghrami platform'
                    );
                }

                if ($meetLink !== null) {
                    $location = $meetLink;
                    $this->addFlash('success', 'Google Meet link created automatically.');
                } else {
                    $this->addFlash('info', 'Google Meet auto-link is unavailable. You can add a meeting link manually.');
                }
            }

            $meetingsService->createMeeting(
                (int) $user->id,
                $connectionId,
                $meetingType,
                $location,
                $scheduledAt,
                $duration,
                $request->request->getBoolean('invite_peer', true)
            );

            $this->addFlash('success', 'Meeting scheduled successfully.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_meetings_index');
    }

    #[Route('/join', name: 'app_meetings_join', methods: ['POST'])]
    public function join(Request $request, MeetingsService $meetingsService): Response
    {
        if (!$this->isCsrfTokenValid('meetings_join', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_meetings_index');
        }

        $user = $this->getUser();
        if (!$user instanceof User || $user->id === null) {
            return $this->redirectToRoute('app_login');
        }

        $meetingId = trim((string) $request->request->get('meeting_id', ''));
        if ($meetingId === '') {
            $this->addFlash('error', 'Meeting ID is required.');
            return $this->redirectToRoute('app_meetings_index');
        }

        try {
            $meetingsService->joinMeeting((int) $user->id, $meetingId);
            $this->addFlash('success', 'You joined the meeting.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_meetings_index', [
            'meeting' => $meetingId,
            'filter' => (string) $request->request->get('filter', 'upcoming'),
        ]);
    }

    #[Route('/leave', name: 'app_meetings_leave', methods: ['POST'])]
    public function leave(Request $request, MeetingsService $meetingsService): Response
    {
        if (!$this->isCsrfTokenValid('meetings_leave', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_meetings_index');
        }

        $user = $this->getUser();
        if (!$user instanceof User || $user->id === null) {
            return $this->redirectToRoute('app_login');
        }

        $meetingId = trim((string) $request->request->get('meeting_id', ''));
        if ($meetingId === '') {
            $this->addFlash('error', 'Meeting ID is required.');
            return $this->redirectToRoute('app_meetings_index');
        }

        try {
            $meetingsService->leaveMeeting((int) $user->id, $meetingId);
            $this->addFlash('success', 'You left the meeting.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_meetings_index', [
            'filter' => (string) $request->request->get('filter', 'upcoming'),
        ]);
    }

    #[Route('/status', name: 'app_meetings_status', methods: ['POST'])]
    public function updateStatus(Request $request, MeetingsService $meetingsService): Response
    {
        if (!$this->isCsrfTokenValid('meetings_status', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_meetings_index');
        }

        $user = $this->getUser();
        if (!$user instanceof User || $user->id === null) {
            return $this->redirectToRoute('app_login');
        }

        $meetingId = (string) $request->request->get('meeting_id');
        $status = trim((string) $request->request->get('status', ''));

        if (trim($meetingId) === '') {
            $this->addFlash('error', 'Meeting ID is required.');
            return $this->redirectToRoute('app_meetings_index');
        }

        if (!in_array($status, ['scheduled', 'completed', 'cancelled'], true)) {
            $this->addFlash('error', 'Invalid meeting status.');
            return $this->redirectToRoute('app_meetings_index');
        }

        try {
            $meetingsService->updateStatus((int) $user->id, $meetingId, $status);
            $this->addFlash('success', 'Meeting status updated.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_meetings_index', [
            'meeting' => $meetingId,
            'filter' => (string) $request->request->get('filter', 'upcoming'),
        ]);
    }

    private function firstValidationMessage(ConstraintViolationListInterface $violations): string
    {
        foreach ($violations as $violation) {
            return $violation->getMessage();
        }

        return 'Validation failed.';
    }
}
