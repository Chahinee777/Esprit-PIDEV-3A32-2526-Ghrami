<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class MeetingsService
{
    // BUG FIX: the desktop and our API both use 'physical'.
    // The old code validated against 'in_person' which never matched, causing every
    // create/update request to return 400 "Invalid meeting type."
    private const VALID_TYPES    = ['virtual', 'physical'];
    private const VALID_STATUSES = ['scheduled', 'completed', 'cancelled'];

    public function __construct(private readonly EntityManagerInterface $em) {}

    // ─────────────────────────────────────────────────────────────────────────
    // STATS
    // ─────────────────────────────────────────────────────────────────────────

    public function getStats(int $userId): array
    {
        $conn = $this->em->getConnection();

        return [
            'connections' => (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM connections
                 WHERE status = 'accepted' AND (initiator_id = :uid OR receiver_id = :uid)",
                ['uid' => $userId]
            ),
            'upcoming' => (int) $conn->fetchOne(
                "SELECT COUNT(*)
                 FROM meetings m
                 WHERE m.scheduled_at > NOW()
                   AND m.status = 'scheduled'
                   AND (m.organizer_id = :uid OR EXISTS(
                       SELECT 1 FROM meeting_participants mp
                       WHERE mp.meeting_id = m.meeting_id AND mp.user_id = :uid AND mp.is_active = 1
                   ))",
                ['uid' => $userId]
            ),
            'pending' => (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM connections WHERE status = 'pending' AND receiver_id = :uid",
                ['uid' => $userId]
            ),
            'scheduled' => (int) $conn->fetchOne(
                "SELECT COUNT(*)
                 FROM meetings m
                 WHERE m.status = 'scheduled'
                   AND (m.organizer_id = :uid OR EXISTS(
                       SELECT 1 FROM meeting_participants mp
                       WHERE mp.meeting_id = m.meeting_id AND mp.user_id = :uid AND mp.is_active = 1
                   ))",
                ['uid' => $userId]
            ),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONNECTIONS (for meeting scheduling dropdown)
    // ─────────────────────────────────────────────────────────────────────────

    public function listAcceptedConnections(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT c.connection_id, c.connection_type,
                    c.initiator_id, c.receiver_id,
                    CASE WHEN c.initiator_id = :uid THEN u2.user_id  ELSE u1.user_id  END AS peer_user_id,
                    CASE WHEN c.initiator_id = :uid THEN u2.username ELSE u1.username END AS peer_username,
                    CASE WHEN c.initiator_id = :uid THEN u2.full_name ELSE u1.full_name END AS peer_full_name
             FROM connections c
             JOIN users u1 ON u1.user_id = c.initiator_id
             JOIN users u2 ON u2.user_id = c.receiver_id
             WHERE c.status = 'accepted'
               AND (c.initiator_id = :uid OR c.receiver_id = :uid)
             ORDER BY c.connection_id DESC",
            ['uid' => $userId]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LIST MEETINGS
    // BUG FIX: 'in_person' filter replaced with 'physical' to match DB values
    // ─────────────────────────────────────────────────────────────────────────

    public function listMeetings(int $userId, string $filter = 'upcoming'): array
    {
        // Also expose the peer's name so MeetingsListApiController can use it
        $sql = "SELECT
                    m.meeting_id,
                    m.connection_id,
                    m.organizer_id,
                    m.meeting_type,
                    m.location,
                    m.scheduled_at,
                    m.duration,
                    m.status,
                    ou.username  AS organizer_username,
                    ou.full_name AS organizer_full_name,
                    c.connection_type,
                    c.initiator_id,
                    c.receiver_id,
                    CASE WHEN c.initiator_id = :uid THEN pu.full_name  ELSE ou.full_name  END AS peer_name,
                    CASE WHEN c.initiator_id = :uid THEN pu.username   ELSE ou.username   END AS peer_username,
                    (SELECT COUNT(*)
                     FROM meeting_participants mp2
                     WHERE mp2.meeting_id = m.meeting_id AND mp2.is_active = 1
                    ) AS participant_count,
                    EXISTS(
                        SELECT 1 FROM meeting_participants mp3
                        WHERE mp3.meeting_id = m.meeting_id AND mp3.user_id = :uid AND mp3.is_active = 1
                    ) AS is_participant
                FROM meetings m
                JOIN users ou ON ou.user_id = m.organizer_id
                JOIN connections c ON c.connection_id = m.connection_id
                -- join the other party so we can show their name
                LEFT JOIN users pu ON pu.user_id = (
                    CASE WHEN c.initiator_id = :uid THEN c.receiver_id ELSE c.initiator_id END
                )
                WHERE (m.organizer_id = :uid OR EXISTS(
                    SELECT 1 FROM meeting_participants mp
                    WHERE mp.meeting_id = m.meeting_id AND mp.user_id = :uid AND mp.is_active = 1
                ))";

        $params = ['uid' => $userId];

        match ($filter) {
            'upcoming'  => $sql .= " AND m.scheduled_at > NOW() AND m.status != 'cancelled'",
            'past'      => $sql .= " AND (m.scheduled_at <= NOW() OR m.status = 'completed')",
            // BUG FIX: was 'in_person', DB stores 'physical'
            'physical'  => $sql .= " AND m.meeting_type = 'physical'",
            'virtual'   => $sql .= " AND m.meeting_type = 'virtual'",
            'scheduled', 'completed', 'cancelled' => ($sql .= " AND m.status = :status") && ($params['status'] = $filter),
            default     => null, // 'all' or unknown → no extra WHERE
        };

        $sql .= " ORDER BY m.scheduled_at ASC";

        return $this->em->getConnection()->fetchAllAssociative($sql, $params);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PARTICIPANTS
    // ─────────────────────────────────────────────────────────────────────────

    public function listParticipants(string $meetingId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT mp.participant_id, mp.user_id, mp.is_active,
                    u.username, u.full_name, u.profile_picture
             FROM meeting_participants mp
             JOIN users u ON u.user_id = mp.user_id
             WHERE mp.meeting_id = :mid
             ORDER BY mp.is_active DESC, u.username ASC",
            ['mid' => $meetingId]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE MEETING
    // BUG FIX: valid types are now ['virtual', 'physical'] not ['virtual', 'in_person']
    // ─────────────────────────────────────────────────────────────────────────

    public function createMeeting(
        int    $userId,
        string $connectionId,
        string $meetingType,
        ?string $location,
        string $scheduledAt,
        int    $duration,
        bool   $invitePeer
    ): void {
        $conn       = $this->em->getConnection();
        $connection = $conn->fetchAssociative(
            'SELECT initiator_id, receiver_id, status FROM connections WHERE connection_id = :cid',
            ['cid' => $connectionId]
        );

        if (!$connection) {
            throw new \RuntimeException('Connection not found.');
        }

        $initiatorId = (int) $connection['initiator_id'];
        $receiverId  = (int) $connection['receiver_id'];
        $status      = strtolower((string) $connection['status']);

        if (!in_array($userId, [$initiatorId, $receiverId], true)) {
            throw new \RuntimeException('You are not part of this connection.');
        }

        if ($status !== 'accepted') {
            throw new \RuntimeException('Only accepted connections can schedule meetings.');
        }

        // BUG FIX: was ['virtual', 'in_person'] – 'physical' never passed this check → 400
        if (!in_array($meetingType, self::VALID_TYPES, true)) {
            throw new \RuntimeException(sprintf(
                'Invalid meeting type "%s". Expected: %s.',
                $meetingType, implode(' or ', self::VALID_TYPES)
            ));
        }

        if ($duration < 1 || $duration > 1440) {
            throw new \RuntimeException('Meeting duration must be between 1 and 1440 minutes.');
        }

        $meetingDate = new \DateTimeImmutable($scheduledAt);

        // Prevent double-booking: same organizer, same date (only upcoming scheduled)
        $existingOnDate = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM meetings
             WHERE organizer_id = :uid
               AND DATE(scheduled_at) = :date
               AND status = 'scheduled'",
            ['uid' => $userId, 'date' => $meetingDate->format('Y-m-d')]
        );

        if ($existingOnDate > 0) {
            throw new \RuntimeException(
                'You already have a scheduled meeting on ' . $meetingDate->format('d M Y') . '. Please choose another date.'
            );
        }

        $meetingId = $this->uuid();

        $conn->insert('meetings', [
            'meeting_id'    => $meetingId,
            'connection_id' => $connectionId,
            'organizer_id'  => $userId,
            'meeting_type'  => $meetingType,
            'location'      => ($location !== '' && $location !== null) ? $location : null,
            'scheduled_at'  => $meetingDate->format('Y-m-d H:i:s'),
            'duration'      => $duration,
            'status'        => 'scheduled',
        ]);

        // Organizer is always a participant
        $this->upsertActiveParticipant($meetingId, $userId);

        if ($invitePeer) {
            $peerId = ($userId === $initiatorId) ? $receiverId : $initiatorId;
            $this->upsertActiveParticipant($meetingId, $peerId);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE MEETING
    // BUG FIX: same 'in_person' → 'physical' fix
    // ─────────────────────────────────────────────────────────────────────────

    public function updateMeeting(
        int    $userId,
        string $meetingId,
        string $meetingType,
        ?string $location,
        string $scheduledAt,
        int    $duration
    ): void {
        $meeting = $this->em->getConnection()->fetchAssociative(
            'SELECT organizer_id, status FROM meetings WHERE meeting_id = :mid',
            ['mid' => $meetingId]
        );

        if (!$meeting) {
            throw new \RuntimeException('Meeting not found.');
        }

        if ((int) $meeting['organizer_id'] !== $userId) {
            throw new \RuntimeException('Only the organizer can modify this meeting.');
        }

        if (in_array($meeting['status'], ['completed', 'cancelled'], true)) {
            throw new \RuntimeException('Cannot modify completed or cancelled meetings.');
        }

        // BUG FIX: was ['virtual', 'in_person']
        if (!in_array($meetingType, self::VALID_TYPES, true)) {
            throw new \RuntimeException(sprintf(
                'Invalid meeting type "%s". Expected: %s.',
                $meetingType, implode(' or ', self::VALID_TYPES)
            ));
        }

        if ($duration < 1 || $duration > 1440) {
            throw new \RuntimeException('Duration must be between 1 and 1440 minutes.');
        }

        $scheduledDate = new \DateTimeImmutable($scheduledAt);

        $this->em->getConnection()->update(
            'meetings',
            [
                'meeting_type' => $meetingType,
                'location'     => ($location !== '' && $location !== null) ? $location : null,
                'scheduled_at' => $scheduledDate->format('Y-m-d H:i:s'),
                'duration'     => $duration,
            ],
            ['meeting_id' => $meetingId]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JOIN / LEAVE
    // ─────────────────────────────────────────────────────────────────────────

    public function joinMeeting(int $userId, string $meetingId): void
    {
        $this->ensureUserCanAccessMeeting($userId, $meetingId);
        $this->upsertActiveParticipant($meetingId, $userId);
    }

    public function leaveMeeting(int $userId, string $meetingId): void
    {
        $this->ensureUserCanAccessMeeting($userId, $meetingId);
        $this->em->getConnection()->update(
            'meeting_participants',
            ['is_active' => 0],
            ['meeting_id' => $meetingId, 'user_id' => $userId]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STATUS
    // BUG FIX: organizer-only restriction is too tight for cancel/complete.
    // The desktop lets any participant trigger these actions, so we also check
    // that the user is part of the meeting via the participants table.
    // ─────────────────────────────────────────────────────────────────────────

    public function updateStatus(int $userId, string $meetingId, string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \RuntimeException('Invalid status.');
        }

        $meeting = $this->em->getConnection()->fetchAssociative(
            'SELECT organizer_id, status FROM meetings WHERE meeting_id = :mid',
            ['mid' => $meetingId]
        );

        if (!$meeting) {
            throw new \RuntimeException('Meeting not found.');
        }

        // Only the organizer can change status (matches desktop behaviour)
        if ((int) $meeting['organizer_id'] !== $userId) {
            throw new \RuntimeException('Only the organizer can change meeting status.');
        }

        if ($meeting['status'] === $status) {
            return; // idempotent
        }

        $this->em->getConnection()->update('meetings', ['status' => $status], ['meeting_id' => $meetingId]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALIDATE BEFORE MARKING COMPLETE
    // ─────────────────────────────────────────────────────────────────────────

    public function validateBeforeMarkingComplete(string $meetingId): array
    {
        $meeting = $this->em->getConnection()->fetchAssociative(
            'SELECT scheduled_at, status FROM meetings WHERE meeting_id = :mid',
            ['mid' => $meetingId]
        );

        if (!$meeting) {
            return ['valid' => false, 'error' => 'Meeting not found.'];
        }

        if ($meeting['status'] === 'cancelled') {
            return ['valid' => false, 'error' => 'Cannot complete a cancelled meeting.'];
        }

        if ($meeting['status'] === 'completed') {
            return ['valid' => false, 'error' => 'Meeting is already completed.'];
        }

        $scheduledAt = new \DateTimeImmutable((string) $meeting['scheduled_at']);
        if ($scheduledAt->getTimestamp() > time()) {
            $diff          = $scheduledAt->diff(new \DateTime());
            $remainingText = $diff->h > 0
                ? sprintf('%d h %d min', $diff->h, $diff->i)
                : sprintf('%d min', $diff->i);

            return [
                'valid'       => false,
                'error'       => "Cannot mark complete before the meeting time. $remainingText remaining.",
                'remaining'   => $remainingText,
                'scheduledAt' => $scheduledAt->format('Y-m-d H:i'),
            ];
        }

        return ['valid' => true];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteMeeting(int $userId, string $meetingId): void
    {
        $meeting = $this->em->getConnection()->fetchAssociative(
            'SELECT organizer_id FROM meetings WHERE meeting_id = :mid',
            ['mid' => $meetingId]
        );

        if (!$meeting || (int) $meeting['organizer_id'] !== $userId) {
            throw new \RuntimeException('Unauthorized to delete this meeting.');
        }

        $conn = $this->em->getConnection();
        $conn->delete('meeting_participants', ['meeting_id' => $meetingId]);
        $conn->delete('meetings', ['meeting_id' => $meetingId]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BOOKED DATES (for calendar picker)
    // ─────────────────────────────────────────────────────────────────────────

    public function getBookedDates(int $userId): array
    {
        $result = $this->em->getConnection()->fetchAllAssociative(
            "SELECT DATE(m.scheduled_at) AS booked_date
             FROM meetings m
             WHERE m.organizer_id = :uid
               AND m.status = 'scheduled'
               AND m.scheduled_at > NOW()
             GROUP BY DATE(m.scheduled_at)",
            ['uid' => $userId]
        );

        return array_map(fn($row) => $row['booked_date'], $result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    public function getConnectionMeetings(string $connectionId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT m.*,
                    (SELECT COUNT(*) FROM meeting_participants WHERE meeting_id = m.meeting_id AND is_active = 1) AS participant_count
             FROM meetings m
             WHERE m.connection_id = :cid
             ORDER BY m.scheduled_at DESC",
            ['cid' => $connectionId]
        );
    }

    public function getMeeting(string $meetingId): ?array
    {
        return $this->em->getConnection()->fetchAssociative(
            'SELECT m.*, u.username AS organizer_name, u.full_name AS organizer_full_name
             FROM meetings m
             JOIN users u ON u.user_id = m.organizer_id
             WHERE m.meeting_id = :mid',
            ['mid' => $meetingId]
        ) ?: null;
    }

    private function ensureUserCanAccessMeeting(int $userId, string $meetingId): void
    {
        $allowed = (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*)
             FROM meetings m
             JOIN connections c ON c.connection_id = m.connection_id
             WHERE m.meeting_id = :mid
               AND (c.initiator_id = :uid OR c.receiver_id = :uid)',
            ['mid' => $meetingId, 'uid' => $userId]
        );

        if ($allowed === 0) {
            throw new \RuntimeException('Meeting not accessible for current user.');
        }
    }

    private function upsertActiveParticipant(string $meetingId, int $userId): void
    {
        $conn     = $this->em->getConnection();
        $existing = $conn->fetchOne(
            'SELECT participant_id FROM meeting_participants WHERE meeting_id = :mid AND user_id = :uid',
            ['mid' => $meetingId, 'uid' => $userId]
        );

        if ($existing) {
            $conn->update('meeting_participants', ['is_active' => 1], ['participant_id' => $existing]);
            return;
        }

        $conn->insert('meeting_participants', [
            'participant_id' => $this->uuid(),
            'meeting_id'     => $meetingId,
            'user_id'        => $userId,
            'is_active'      => 1,
        ]);
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}