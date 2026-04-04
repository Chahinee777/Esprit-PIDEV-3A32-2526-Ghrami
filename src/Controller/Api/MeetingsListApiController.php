<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\MeetingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/meetings/list')]
final class MeetingsListApiController extends AbstractController
{
    public function __construct(private readonly MeetingsService $meetingsService) {}

    /**
     * GET /api/meetings/list?filter=upcoming|past|scheduled|completed|cancelled|physical|virtual
     */
    #[Route('', name: 'api_meetings_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $filter = (string) $request->query->get('filter', 'upcoming');

            // BUG FIX: 'physical' and 'virtual' are now handled directly by
            // MeetingsService::listMeetings(), no need for the intermediate 'all' hack.
            $rows     = $this->meetingsService->listMeetings((int) $user->id, $filter);
            $meetings = array_values(array_map(
                fn(array $row) => $this->formatRow($row, (int) $user->id),
                $rows
            ));

            return $this->json(['ok' => true, 'meetings' => $meetings]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function formatRow(array $m, int $currentUserId): array
    {
        $type     = strtolower((string) ($m['meeting_type'] ?? 'virtual'));
        $location = $m['location'] ?? null;

        // BUG FIX (previous): connection_type is the *type* label (e.g. "skill"),
        // not a person name. Use peer_name (now provided by MeetingsService) instead.
        // Fall-back chain: peer_name -> organizer_full_name -> organizer_username
        $connectionName =
            $m['peer_name']           ??
            $m['peer_username']       ??
            $m['organizer_full_name'] ??
            $m['organizer_username']  ??
            'Meeting';

        // Only expose a Meet link when it is a real Google Meet URL
        $googleMeetLink = null;
        if (
            $type === 'virtual' &&
            is_string($location) &&
            str_starts_with($location, 'https://meet.google.com/') &&
            !str_contains($location, 'placeholder')
        ) {
            $googleMeetLink = $location;
        }

        return [
            'id'                => $m['meeting_id']       ?? null,
            'connection_id'     => $m['connection_id']    ?? null,
            'connection_name'   => $connectionName,
            'type'              => $type,
            'datetime'          => $m['scheduled_at']     ?? null,
            'duration'          => (int) ($m['duration']  ?? 0),
            'status'            => strtolower((string) ($m['status'] ?? 'scheduled')),
            'location'          => $location,
            'participant_count' => (int) ($m['participant_count'] ?? 0),
            'is_participant'    => (bool) ($m['is_participant']   ?? false),
            'google_meet_link'  => $googleMeetLink,
        ];
    }
}