<?php

namespace App\Service;

use App\Entity\Connection;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ConnectionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MatchingService $matchingService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────────

    public function createConnection(
        int     $initiatorId,
        int     $receiverId,
        string  $type,
        ?string $initiatorSkill = null,
        ?string $receiverSkill  = null
    ): Connection {
        // Guard: can't connect to yourself or to admin (user_id = 0)
        if ($initiatorId === $receiverId) {
            throw new \RuntimeException('Cannot connect to yourself.');
        }
        if ($receiverId === 0) {
            throw new \RuntimeException('Cannot connect to this user.');
        }

        $connection = $this->matchingService->createConnection(
            $initiatorId, $receiverId, $type, $receiverSkill, $initiatorSkill
        );

        $initiatorName = $this->em->getRepository(User::class)->find($initiatorId)?->username ?? 'Someone';

        $this->matchingService->createNotificationPublic(
            $receiverId,
            'CONNECTION_REQUEST',
            sprintf('%s sent you a %s connection request', $initiatorName, $type),
            $initiatorId
        );

        return $connection;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // READ – all connections (for the main connections list + API)
    // BUG FIX: Added initiator_id, receiver_id, initiator_skill, receiver_skill
    // as raw columns alongside the perspective-aware my_skill/their_skill aliases,
    // so both the frontend and backend consumers can use whichever they need.
    // ─────────────────────────────────────────────────────────────────────────

    public function getConnections(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT
                 c.connection_id,
                 c.connection_type,
                 c.status,
                 c.initiator_id,
                 c.receiver_id,
                 c.initiator_skill,
                 c.receiver_skill,
                 CASE WHEN c.initiator_id = :uid THEN c.receiver_id  ELSE c.initiator_id  END AS other_user_id,
                 u.username,
                 u.full_name,
                 u.profile_picture,
                 -- perspective-aware skill aliases kept for backwards compat
                 CASE WHEN c.initiator_id = :uid THEN c.initiator_skill ELSE c.receiver_skill END AS my_skill,
                 CASE WHEN c.initiator_id = :uid THEN c.receiver_skill  ELSE c.initiator_skill END AS their_skill
             FROM connections c
             JOIN users u ON u.user_id = (
                 CASE WHEN c.initiator_id = :uid THEN c.receiver_id ELSE c.initiator_id END
             )
             WHERE c.initiator_id = :uid OR c.receiver_id = :uid
             ORDER BY
                 FIELD(c.status, 'accepted', 'pending', 'rejected') ASC,
                 c.connection_id DESC",
            ['uid' => $userId]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // READ – accepted only (for meeting scheduling)
    // ─────────────────────────────────────────────────────────────────────────

    public function getAcceptedConnections(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT
                 c.connection_id,
                 c.connection_type,
                 c.status,
                 c.initiator_id,
                 c.receiver_id,
                 c.initiator_skill,
                 c.receiver_skill,
                 CASE WHEN c.initiator_id = :uid THEN c.receiver_id  ELSE c.initiator_id  END AS other_user_id,
                 u.username,
                 u.full_name,
                 u.profile_picture,
                 CASE WHEN c.initiator_id = :uid THEN c.initiator_skill ELSE c.receiver_skill END AS my_skill,
                 CASE WHEN c.initiator_id = :uid THEN c.receiver_skill  ELSE c.initiator_skill END AS their_skill
             FROM connections c
             JOIN users u ON u.user_id = (
                 CASE WHEN c.initiator_id = :uid THEN c.receiver_id ELSE c.initiator_id END
             )
             WHERE (c.initiator_id = :uid OR c.receiver_id = :uid)
               AND c.status = 'accepted'
             ORDER BY c.connection_type ASC, u.full_name ASC",
            ['uid' => $userId]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // READ – pending incoming (for the notification badge + pending list)
    // ─────────────────────────────────────────────────────────────────────────

    public function getPendingConnections(int $userId): array
    {
        return $this->getIncomingPendingConnections($userId);
    }

    public function getIncomingPendingConnections(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT
                 c.connection_id,
                 c.connection_type,
                 c.status,
                 c.initiator_id,
                 c.receiver_id,
                 c.initiator_skill,
                 c.receiver_skill,
                 u.user_id,
                 u.username,
                 u.full_name,
                 u.profile_picture
             FROM connections c
             JOIN users u ON u.user_id = c.initiator_id
             WHERE c.receiver_id = :uid AND c.status = 'pending'
             ORDER BY c.connection_id DESC",
            ['uid' => $userId]
        );
    }

    public function getOutgoingPendingConnections(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT
                 c.connection_id,
                 c.connection_type,
                 c.status,
                 c.initiator_id,
                 c.receiver_id,
                 c.initiator_skill,
                 c.receiver_skill,
                 u.user_id,
                 u.username,
                 u.full_name,
                 u.profile_picture
             FROM connections c
             JOIN users u ON u.user_id = c.receiver_id
             WHERE c.initiator_id = :uid AND c.status = 'pending'
             ORDER BY c.connection_id DESC",
            ['uid' => $userId]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACCEPT / REJECT / CANCEL
    // ─────────────────────────────────────────────────────────────────────────

    public function acceptConnection(string $connectionId, int $userId): void
    {
        $connection = $this->em->getRepository(Connection::class)->find($connectionId);
        if (!$connection) {
            throw new \RuntimeException('Connection not found.');
        }
        if ((int) $connection->receiver->id !== $userId) {
            throw new \RuntimeException('Only the receiver can accept this request.');
        }

        $this->matchingService->updateConnectionStatus($connectionId, 'accepted');

        $acceptorName = $this->em->getRepository(User::class)->find($userId)?->username ?? 'Someone';

        $this->matchingService->createNotificationPublic(
            (int) $connection->initiator->id,
            'CONNECTION_ACCEPTED',
            sprintf('%s accepted your %s connection request ✅', $acceptorName, $connection->connectionType),
            $userId
        );
    }

    public function rejectConnection(string $connectionId, int $userId): void
    {
        $connection = $this->em->getRepository(Connection::class)->find($connectionId);
        if (!$connection) {
            throw new \RuntimeException('Connection not found.');
        }
        if ((int) $connection->receiver->id !== $userId) {
            throw new \RuntimeException('Only the receiver can reject this request.');
        }

        $this->matchingService->updateConnectionStatus($connectionId, 'rejected');
    }

    public function cancelOutgoingConnection(string $connectionId, int $userId): void
    {
        $connection = $this->em->getRepository(Connection::class)->find($connectionId);
        if (!$connection) {
            throw new \RuntimeException('Connection not found.');
        }
        if ((int) $connection->initiator->id !== $userId) {
            throw new \RuntimeException('Only the initiator can cancel their request.');
        }
        if (strtolower((string) $connection->status) !== 'pending') {
            throw new \RuntimeException('Only pending requests can be cancelled.');
        }

        $this->em->remove($connection);
        $this->em->flush();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE (accepted connection + cascade meetings)
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteConnection(string $connectionId, int $userId): void
    {
        $connection = $this->em->getRepository(Connection::class)->find($connectionId);
        if (!$connection) {
            throw new \RuntimeException('Connection not found.');
        }

        if ((int) $connection->initiator->id !== $userId && (int) $connection->receiver->id !== $userId) {
            throw new \RuntimeException('Unauthorized to delete this connection.');
        }

        $conn = $this->em->getConnection();

        // Cascade: delete meeting_participants → meetings for this connection
        $meetingIds = $conn->fetchFirstColumn(
            'SELECT meeting_id FROM meetings WHERE connection_id = :cid',
            ['cid' => $connectionId]
        );

        foreach ($meetingIds as $meetingId) {
            $conn->delete('meeting_participants', ['meeting_id' => $meetingId]);
            $conn->delete('meetings',             ['meeting_id' => $meetingId]);
        }

        $this->em->remove($connection);
        $this->em->flush();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SEARCH
    // ─────────────────────────────────────────────────────────────────────────

    public function searchConnections(int $userId, string $query, string $type = ''): array
    {
        $sql  = "SELECT
                     c.connection_id,
                     c.connection_type,
                     c.status,
                     c.initiator_id,
                     c.receiver_id,
                     c.initiator_skill,
                     c.receiver_skill,
                     CASE WHEN c.initiator_id = :uid THEN c.receiver_id  ELSE c.initiator_id  END AS other_user_id,
                     u.username,
                     u.full_name,
                     u.profile_picture
                 FROM connections c
                 JOIN users u ON u.user_id = (
                     CASE WHEN c.initiator_id = :uid THEN c.receiver_id ELSE c.initiator_id END
                 )
                 WHERE (c.initiator_id = :uid OR c.receiver_id = :uid)
                   AND (u.username LIKE :q OR u.full_name LIKE :q)";

        $params = ['uid' => $userId, 'q' => "%{$query}%"];

        if ($type !== '') {
            $sql        .= ' AND c.connection_type = :type';
            $params['type'] = $type;
        }

        $sql .= ' ORDER BY c.status ASC LIMIT 30';

        return $this->em->getConnection()->fetchAllAssociative($sql, $params);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MISC
    // ─────────────────────────────────────────────────────────────────────────

    public function findConnectionsByHobby(int $userId, int $hobbyId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT u.user_id, u.username, u.full_name, u.profile_picture
             FROM users u
             JOIN hobbies h ON u.user_id = h.user_id
             WHERE h.hobby_id = :hobbyId AND u.user_id != :uid AND u.user_id != 0
             LIMIT 20',
            ['hobbyId' => $hobbyId, 'uid' => $userId]
        );
    }
}