<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\MeetingsService;
use App\Service\GoogleMeetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/meetings')]
final class MeetingsApiController extends AbstractController
{
    public function __construct(
        private readonly MeetingsService  $meetingsService,
        private readonly GoogleMeetService $googleMeetService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // BOOKED DATES (for calendar date picker)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/booked-dates', name: 'api_meetings_booked_dates', methods: ['GET'])]
    public function getBookedDates(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'ok'           => true,
            'booked_dates' => $this->meetingsService->getBookedDates((int) $user->id),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE MEETING
    //
    // BUG FIX 1: 'in_person' normalised to 'physical' (DB stores 'physical').
    // BUG FIX 2: Removed fake placeholder Google Meet links. If Meet creation
    //            fails the meeting is stored with location = null and the
    //            frontend receives meet_link_created: false so it can warn the user.
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/create', name: 'api_meetings_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $connectionId = trim((string) ($data['connection_id'] ?? ''));
        $meetingType  = strtolower(trim((string) ($data['meeting_type'] ?? 'virtual')));
        $location     = isset($data['location']) && $data['location'] !== '' ? (string) $data['location'] : null;
        $scheduledAt  = trim((string) ($data['scheduled_at'] ?? ''));
        $duration     = (int) ($data['duration'] ?? 60);
        $title        = trim((string) ($data['title']  ?? 'Rendez-vous Ghrami'));

        // Normalise legacy 'in_person' value from older frontend code
        if ($meetingType === 'in_person') {
            $meetingType = 'physical';
        }

        if ($connectionId === '' || $scheduledAt === '') {
            return $this->json(['ok' => false, 'error' => 'Missing required fields: connection_id and scheduled_at.'], Response::HTTP_BAD_REQUEST);
        }


        try {
            $dt = new \DateTimeImmutable($scheduledAt);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'error' => 'Invalid scheduled_at format.'], Response::HTTP_BAD_REQUEST);
        }

        if ($dt <= new \DateTimeImmutable()) {
            return $this->json(['ok' => false, 'error' => 'Meeting date and time must be in the future.'], Response::HTTP_BAD_REQUEST);
        }

        $meetLinkCreated = false;

        try {

            if ($meetingType === 'virtual' && $location === null) {
                $googleToken = $data['google_access_token'] ?? null;
                if ($googleToken) {
                    $location = $this->googleMeetService->createMeetLinkWithUserToken(
                        (string) $googleToken, $scheduledAt, $duration, $title
                    );
                }



                if ($location === null) {
                    // Try service-account fallback
                    $location = $this->googleMeetService->createMeetLink(
                        $scheduledAt, $duration, $title, 'Scheduled from Ghrami platform'
                    );
                }

                $meetLinkCreated = ($location !== null);

            }

            $this->meetingsService->createMeeting(
                (int) $user->id,
                $connectionId,
                $meetingType,
                $location,
                $scheduledAt,
                $duration,
                true   // always invite the peer
            );

            return $this->json([
                'ok'                => true,
                'meet_link'         => $location,
                'meet_link_created' => $meetLinkCreated,
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{meetingId}/update', name: 'api_meetings_update', methods: ['POST'])]
    public function update(string $meetingId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        $data        = json_decode($request->getContent(), true) ?? [];
        $meetingType = strtolower(trim((string) ($data['meeting_type'] ?? 'virtual')));
        $location    = isset($data['location']) && $data['location'] !== '' ? (string) $data['location'] : null;
        $scheduledAt = trim((string) ($data['scheduled_at'] ?? ''));
        $duration    = (int) ($data['duration'] ?? 60);

        if ($meetingType === 'in_person') {
            $meetingType = 'physical';
        }

        try {
            $dt = new \DateTimeImmutable($scheduledAt);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'error' => 'Invalid scheduled_at format.'], Response::HTTP_BAD_REQUEST);
        }

        if ($dt <= new \DateTimeImmutable()) {
            return $this->json(['ok' => false, 'error' => 'Meeting date and time must be in the future.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->meetingsService->updateMeeting(
                (int) $user->id,
                $meetingId,
                $meetingType,
                $meetingType === 'virtual' ? ($location) : $location,
                $scheduledAt,
                $duration
            );

            return $this->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MARK COMPLETE – returns structured error when meeting hasn't happened yet
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{meetingId}/mark-complete', name: 'api_meetings_mark_complete', methods: ['POST'])]
    public function markComplete(string $meetingId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $validation = $this->meetingsService->validateBeforeMarkingComplete($meetingId);
            if (!$validation['valid']) {
                return $this->json([
                    'ok'           => false,
                    'error'        => $validation['error'],
                    'remaining'    => $validation['remaining']    ?? null,
                    'scheduled_at' => $validation['scheduledAt']  ?? null,
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->meetingsService->updateStatus((int) $user->id, $meetingId, 'completed');
            return $this->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CANCEL
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{meetingId}/cancel', name: 'api_meetings_cancel', methods: ['POST'])]
    public function cancel(string $meetingId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->meetingsService->updateStatus((int) $user->id, $meetingId, 'cancelled');
            return $this->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{meetingId}/delete', name: 'api_meetings_delete', methods: ['POST'])]
    public function delete(string $meetingId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->meetingsService->deleteMeeting((int) $user->id, $meetingId);
            return $this->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
