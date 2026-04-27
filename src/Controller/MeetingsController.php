<?php

namespace App\Controller;

use App\Entity\Meeting;
use App\Entity\User;
use App\Service\GoogleAuthService;
use App\Service\GoogleMeetService;
use App\Service\MeetingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;

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
        GoogleAuthService $googleAuthService,
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

                // If access token expired, refresh and retry once.
                if ($meetLink === null) {
                    $refreshToken = (string) $session->get('google_refresh_token', '');
                    if ($refreshToken !== '') {
                        $refreshed = $googleAuthService->refreshAccessTokenWithRefreshToken($refreshToken);
                        if (is_array($refreshed) && !empty($refreshed['access_token'])) {
                            $session->set('google_access_token', $refreshed['access_token']);
                            if (!empty($refreshed['refresh_token'])) {
                                $session->set('google_refresh_token', $refreshed['refresh_token']);
                            }

                            $meetLink = $googleMeetService->createMeetLinkWithUserToken(
                                (string) $refreshed['access_token'],
                                $scheduledAt,
                                $duration,
                                'Ghrami Meeting',
                                'Scheduled from Ghrami platform'
                            );
                        }
                    }
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

    #[Route('/api/users', name: 'app_meetings_api_users', methods: ['GET'])]
    public function apiGetUsers(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->id === null) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $users = $em->createQuery(
            'SELECT u.id, u.username, u.fullName, u.profilePicture FROM App\Entity\User u WHERE u.id != :userId ORDER BY u.fullName ASC'
        )->setParameter('userId', $user->id)->getResult();

        return new JsonResponse(['ok' => true, 'users' => array_map(fn($u) => [
            'id' => (int)$u['id'],
            'username' => $u['username'] ?? '',
            'fullName' => $u['fullName'] ?? $u['username'],
            'profilePicture' => $u['profilePicture'] ?? null,
        ], $users)]);
    }

    #[Route('/api/google-meet-link', name: 'app_meetings_api_google_meet', methods: ['POST'])]
    public function apiGenerateGoogleMeetLink(
        Request $request,
        GoogleMeetService $googleMeetService,
        GoogleAuthService $googleAuthService,
        SessionInterface $session
    ): JsonResponse {
        try {
            $datetime = $request->request->get('datetime');
            $duration = (int)$request->request->get('duration', 60);

            if (!$datetime) {
                return new JsonResponse(['ok' => false, 'error' => 'Date and time required'], 400);
            }

            $meetLink = null;
            $errors = [];
            $hadUserToken = false;

            // Try user's personal Google credentials first
            $userAccessToken = $session->get('google_access_token');
            if ($userAccessToken !== null && $userAccessToken !== '') {
                $hadUserToken = true;
                $meetLink = $googleMeetService->createMeetLinkWithUserToken(
                    (string) $userAccessToken,
                    $datetime,
                    $duration,
                    'Ghrami Meeting',
                    'Scheduled from Ghrami platform'
                );
                if ($meetLink === null && $googleMeetService->getLastError() !== null) {
                    $errors[] = 'User Google token: ' . $googleMeetService->getLastError();
                }
            }

            // Retry once with refreshed token when available.
            if ($meetLink === null) {
                $refreshToken = (string) $session->get('google_refresh_token', '');
                if ($refreshToken !== '') {
                    $refreshed = $googleAuthService->refreshAccessTokenWithRefreshToken($refreshToken);
                    if (is_array($refreshed) && !empty($refreshed['access_token'])) {
                        $session->set('google_access_token', $refreshed['access_token']);
                        if (!empty($refreshed['refresh_token'])) {
                            $session->set('google_refresh_token', $refreshed['refresh_token']);
                        }

                        $hadUserToken = true;
                        $meetLink = $googleMeetService->createMeetLinkWithUserToken(
                            (string) $refreshed['access_token'],
                            $datetime,
                            $duration,
                            'Ghrami Meeting',
                            'Scheduled from Ghrami platform'
                        );
                        if ($meetLink === null && $googleMeetService->getLastError() !== null) {
                            $errors[] = 'Refreshed user token: ' . $googleMeetService->getLastError();
                        }
                    }
                }
            }

            // Fall back to service account
            if ($meetLink === null) {
                $meetLink = $googleMeetService->createMeetLink(
                    $datetime,
                    $duration,
                    'Ghrami Meeting',
                    'Scheduled from Ghrami platform'
                );
                if ($meetLink === null && $googleMeetService->getLastError() !== null) {
                    $errors[] = 'Service account: ' . $googleMeetService->getLastError();
                }
            }

            if (!$meetLink) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Real Google Meet generation failed.',
                    'details' => $errors,
                    'needs_auth' => !$hadUserToken,
                ], 400);
            }

            return new JsonResponse(['ok' => true, 'link' => $meetLink, 'fallback' => false]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    #[Route('/api/create', name: 'app_meetings_api_create', methods: ['POST'])]
    public function apiCreate(
        Request $request,
        MeetingsService $meetingsService,
        GoogleMeetService $googleMeetService,
        GoogleAuthService $googleAuthService,
        SessionInterface $session,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            $user = $this->getUser();
            if (!$user instanceof User || $user->id === null) {
                return new JsonResponse(['ok' => false, 'error' => 'Not authenticated'], 401);
            }

            $data = json_decode($request->getContent(), true);

            $connectionId = trim((string) ($data['connection_id'] ?? ''));
            $meetingType = (string) ($data['meeting_type'] ?? 'virtual');
            $location = trim((string) ($data['location'] ?? ''));
            $scheduledAt = trim((string) ($data['scheduled_at'] ?? ''));
            $duration = (int) ($data['duration'] ?? 60);
            $addToCalendar = (bool) ($data['add_to_calendar'] ?? false);

            if ($connectionId === '') {
                return new JsonResponse(['ok' => false, 'error' => 'Connection is required.'], 400);
            }

            $scheduledAtDate = date_create_immutable($scheduledAt);
            if (!$scheduledAtDate) {
                return new JsonResponse(['ok' => false, 'error' => 'Please provide a valid meeting date and time.'], 400);
            }

            $meetingValidation = new Meeting();
            $meetingValidation->meetingType = $meetingType;
            $meetingValidation->location = $location !== '' ? $location : null;
            $meetingValidation->scheduledAt = $scheduledAtDate;
            $meetingValidation->duration = $duration;
            $meetingValidation->status = 'scheduled';

            $violations = $validator->validate($meetingValidation);
            if (count($violations) > 0) {
                return new JsonResponse(['ok' => false, 'error' => $this->firstValidationMessage($violations)], 400);
            }

            if ($meetingType === 'physical' && $location === '') {
                return new JsonResponse(['ok' => false, 'error' => 'Location is required for in-person meetings.'], 400);
            }

            $autoMeetLink = null;
            $calendarSuccess = false;
            $calendarError = null;

            if ($meetingType === 'virtual' && $location === '') {
                // Try user's personal Google credentials first
                $userAccessToken = $session->get('google_access_token');
                if ($userAccessToken !== null && $userAccessToken !== '') {
                    $autoMeetLink = $googleMeetService->createMeetLinkWithUserToken(
                        (string) $userAccessToken,
                        $scheduledAt,
                        $duration,
                        'Ghrami Meeting: ' . ($user->fullName ?? $user->username),
                        'Scheduled from Ghrami platform'
                    );
                    if ($autoMeetLink) {
                        $calendarSuccess = true;
                        // Since we just created it in their personal calendar, we don't need to add it again later
                        $addToCalendar = false;
                    }
                }

                // Retry once with refreshed token when available.
                if ($autoMeetLink === null) {
                    $refreshToken = (string) $session->get('google_refresh_token', '');
                    if ($refreshToken !== '') {
                        $refreshed = $googleAuthService->refreshAccessTokenWithRefreshToken($refreshToken);
                        if (is_array($refreshed) && !empty($refreshed['access_token'])) {
                            $session->set('google_access_token', $refreshed['access_token']);
                            if (!empty($refreshed['refresh_token'])) {
                                $session->set('google_refresh_token', $refreshed['refresh_token']);
                            }

                            $autoMeetLink = $googleMeetService->createMeetLinkWithUserToken(
                                (string) $refreshed['access_token'],
                                $scheduledAt,
                                $duration,
                                'Ghrami Meeting: ' . ($user->fullName ?? $user->username),
                                'Scheduled from Ghrami platform'
                            );
                            if ($autoMeetLink) {
                                $calendarSuccess = true;
                                $addToCalendar = false;
                            }
                        }
                    }
                }

                // Fall back to service account if user credentials unavailable
                if ($autoMeetLink === null) {
                    $autoMeetLink = $googleMeetService->createMeetLink(
                        $scheduledAt,
                        $duration,
                        'Ghrami Meeting: ' . ($user->fullName ?? $user->username),
                        'Scheduled from Ghrami platform'
                    );
                }

                if ($autoMeetLink !== null) {
                    $location = $autoMeetLink;
                } else {
                    return new JsonResponse([
                        'ok' => false,
                        'error' => 'Could not generate a real Google Meet link. Connect Google Calendar and try again.',
                        'details' => [$googleMeetService->getLastError()],
                    ], 400);
                }
            } elseif ($meetingType === 'virtual' && $location !== '' && !str_starts_with($location, 'https://meet.google.com/')) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Virtual meetings require a real Google Meet link (https://meet.google.com/...).',
                ], 400);
            }

            $meetingsService->createMeeting(
                (int) $user->id,
                $connectionId,
                $meetingType,
                $location,
                $scheduledAt,
                $duration,
                true
            );

            if ($addToCalendar && $location !== '') {
                // This part is only reached if it wasn't already added (e.g. physical meeting or virtual with existing link)
                $userAccessToken = $session->get('google_access_token');

                if ($userAccessToken !== null && $userAccessToken !== '') {
                    $result = $googleMeetService->createMeetLinkWithUserToken(
                        (string) $userAccessToken,
                        $scheduledAt,
                        $duration,
                        'Ghrami Meeting: ' . ($user->fullName ?? $user->username),
                        'Scheduled from Ghrami platform. Location: ' . $location
                    );
                    if ($result !== null) {
                        $calendarSuccess = true;
                    } else {
                        $calendarError = $googleMeetService->getLastError();
                    }
                } else {
                    // Try refreshing the token if a refresh token exists
                    $refreshToken = (string) $session->get('google_refresh_token', '');
                    if ($refreshToken !== '') {
                        $refreshed = $googleAuthService->refreshAccessTokenWithRefreshToken($refreshToken);
                        if (is_array($refreshed) && !empty($refreshed['access_token'])) {
                            $session->set('google_access_token', $refreshed['access_token']);
                            if (!empty($refreshed['refresh_token'])) {
                                $session->set('google_refresh_token', $refreshed['refresh_token']);
                            }
                            $result = $googleMeetService->createMeetLinkWithUserToken(
                                (string) $refreshed['access_token'],
                                $scheduledAt,
                                $duration,
                                'Ghrami Meeting: ' . ($user->fullName ?? $user->username),
                                'Scheduled from Ghrami platform. Location: ' . $location
                            );
                            if ($result !== null) {
                                $calendarSuccess = true;
                            } else {
                                $calendarError = $googleMeetService->getLastError();
                            }
                        } else {
                            $calendarError = 'Could not refresh Google token.';
                        }
                    } else {
                        $calendarError = 'User not authenticated with Google.';
                    }
                }
            }

            return new JsonResponse([
                'ok' => true,
                'message' => 'Meeting scheduled successfully.',
                'meet_link' => $autoMeetLink,
                'calendar_added' => $calendarSuccess ?? null,
                'calendar_error' => $calendarError ?? null
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/calendar', name: 'app_meetings_calendar', methods: ['GET'])]
    public function calendar(Request $request, MeetingsService $meetingsService, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->id === null) {
            return $this->redirectToRoute('app_login');
        }

        $status = $request->query->get('status', '');

        // Get meetings from database
        $meetingRepo = $em->getRepository(Meeting::class);
        $query = $meetingRepo->createQueryBuilder('m')
            ->where('m.user1 = :user OR m.user2 = :user')
            ->setParameter('user', $user)
            ->orderBy('m.scheduledAt', 'ASC');

        // Filter by status if provided
        if ($status && in_array($status, ['scheduled', 'completed', 'cancelled'])) {
            $query->andWhere('m.status = :status')
                ->setParameter('status', $status);
        }

        $meetings = $query->getQuery()->getResult();

        // Format events for calendar
        $events = array_map(function(Meeting $meeting) {
            return [
                'id' => $meeting->id,
                'title' => $meeting->title ?? 'Meeting',
                'start' => $meeting->scheduledAt->format('Y-m-d H:i'),
                'end' => (clone $meeting->scheduledAt)
                    ->modify('+' . ($meeting->duration ?? 60) . ' minutes')
                    ->format('Y-m-d H:i'),
                'backgroundColor' => match($meeting->status) {
                    'scheduled' => '#4f46e5',
                    'completed' => '#059669',
                    'cancelled' => '#dc2626',
                    default => '#94a3b8'
                },
                'borderColor' => match($meeting->status) {
                    'scheduled' => '#4338ca',
                    'completed' => '#047857',
                    'cancelled' => '#991b1b',
                    default => '#64748b'
                },
                'textColor' => '#fff',
                'extendedProps' => [
                    'location' => $meeting->location,
                    'type' => $meeting->meetingType,
                    'status' => $meeting->status,
                    'duration' => $meeting->duration,
                    'meetingId' => $meeting->id,
                ]
            ];
        }, $meetings);

        return $this->render('meetings/calendar.html.twig', [
            'events' => $events,
            'meetings' => $meetings,
        ]);
    }

    #[Route('/api/details', name: 'api_meetings_details', methods: ['GET'])]
    public function getMeetingDetails(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User || $user->id === null) {
                return new JsonResponse(['ok' => false, 'error' => 'Not authenticated'], 401);
            }

            $meetingId = (int) $request->query->get('id', 0);
            if ($meetingId === 0) {
                return new JsonResponse(['ok' => false, 'error' => 'Meeting ID required'], 400);
            }

            $meetingRepo = $em->getRepository(Meeting::class);
            $meeting = $meetingRepo->find($meetingId);

            if (!$meeting) {
                return new JsonResponse(['ok' => false, 'error' => 'Meeting not found'], 404);
            }

            // Check access
            if ($meeting->user1?->id !== $user->id && $meeting->user2?->id !== $user->id) {
                return new JsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
            }

            return new JsonResponse([
                'ok' => true,
                'meeting' => [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'scheduledAt' => $meeting->scheduledAt->format('c'),
                    'duration' => $meeting->duration,
                    'location' => $meeting->location,
                    'type' => $meeting->meetingType,
                    'status' => $meeting->status,
                    'notes' => $meeting->notes,
                    'googleMeetLink' => $meeting->googleMeetLink,
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function firstValidationMessage(ConstraintViolationListInterface $violations): string
    {
        foreach ($violations as $violation) {
            return $violation->getMessage();
        }

        return 'Validation failed.';
    }
}
