<?php

namespace App\Service;

use App\Entity\Connection;
use App\Entity\Meeting;
use App\Entity\MeetingParticipant;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class MatchingService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function sendFriendRequest(int $fromUserId, int $toUserId): void
    {
        // Prevent self-friend requests
        if ($fromUserId === $toUserId) {
            throw new \Exception('Cannot send friend request to yourself');
        }

        // Check if friendship already exists
        $existing = $this->em->getConnection()->fetchAssociative(
            "SELECT friendship_id, status FROM friendships 
             WHERE (user1_id = :from AND user2_id = :to) OR (user1_id = :to AND user2_id = :from)",
            ['from' => $fromUserId, 'to' => $toUserId]
        );

        if ($existing) {
            $status = $existing['status'];
            if ($status === 'PENDING') {
                throw new \Exception('Friend request already pending');
            } elseif ($status === 'ACCEPTED') {
                throw new \Exception('Already friends');
            } elseif ($status === 'REJECTED') {
                // Allow re-sending if previously rejected
                $this->em->getConnection()->update('friendships', [
                    'status' => 'PENDING',
                    'created_date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ], ['friendship_id' => $existing['friendship_id']]);
            }
        } else {
            $this->em->getConnection()->insert('friendships', [
                'user1_id' => $fromUserId,
                'user2_id' => $toUserId,
                'status' => 'PENDING',
                'created_date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        $this->createNotification(
            $toUserId,
            'FRIEND_REQUEST',
            'You received a new friend request.',
            $fromUserId
        );
    }

    public function acceptFriendRequest(int $friendshipId): void
    {
        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT user1_id, user2_id FROM friendships WHERE friendship_id = :id',
            ['id' => $friendshipId]
        );

        $this->em->getConnection()->update('friendships', [
            'status' => 'ACCEPTED',
            'accepted_date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['friendship_id' => $friendshipId]);

        if (is_array($row)) {
            $this->createNotification(
                (int) $row['user1_id'],
                'FRIEND_ACCEPTED',
                'Your friend request was accepted.',
                (int) $row['user2_id']
            );
        }
    }

    public function rejectFriendRequest(int $friendshipId): void
    {
        $this->em->getConnection()->update('friendships', ['status' => 'REJECTED'], ['friendship_id' => $friendshipId]);
    }

    public function removeFriendship(int $friendshipId, int $userId): void
    {
        $this->em->getConnection()->executeStatement(
            "DELETE FROM friendships
             WHERE friendship_id = :fid
               AND status = 'ACCEPTED'
               AND (user1_id = :uid OR user2_id = :uid)",
            [
                'fid' => $friendshipId,
                'uid' => $userId,
            ]
        );
    }

    public function getPendingRequests(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT * FROM friendships WHERE user2_id = :uid AND status = 'PENDING' ORDER BY created_date DESC",
            ['uid' => $userId]
        );
    }

    public function getAcceptedFriends(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT * FROM friendships WHERE (user1_id = :uid OR user2_id = :uid) AND status = 'ACCEPTED' ORDER BY accepted_date DESC",
            ['uid' => $userId]
        );
    }

    public function sendMessage(int $senderId, int $receiverId, string $content): Message
    {
        $sender = $this->em->getRepository(User::class)->find($senderId);
        $receiver = $this->em->getRepository(User::class)->find($receiverId);

        $msg = new Message();
        $msg->sender = $sender;
        $msg->receiver = $receiver;
        $msg->content = $content;
        $msg->sentAt = new \DateTime();
        $msg->isRead = false;
        $this->em->persist($msg);
        $this->em->flush();

        $this->createNotification(
            $receiverId,
            'MESSAGE',
            'You received a new message.',
            $senderId
        );

        return $msg;
    }

    public function getConversation(int $user1, int $user2): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT * FROM messages WHERE (sender_id = :u1 AND receiver_id = :u2) OR (sender_id = :u2 AND receiver_id = :u1) ORDER BY sent_at ASC",
            ['u1' => $user1, 'u2' => $user2]
        );
    }

    public function getConversationSince(int $user1, int $user2, int $afterMessageId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT m.message_id, m.sender_id, m.receiver_id, m.content, m.sent_at, m.is_read
             FROM messages m
             WHERE ((m.sender_id = :u1 AND m.receiver_id = :u2) OR (m.sender_id = :u2 AND m.receiver_id = :u1))
               AND m.message_id > :afterId
             ORDER BY m.message_id ASC",
            ['u1' => $user1, 'u2' => $user2, 'afterId' => $afterMessageId]
        );
    }

    public function getLatestConversationMessageId(int $user1, int $user2): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            "SELECT COALESCE(MAX(message_id), 0)
             FROM messages
             WHERE (sender_id = :u1 AND receiver_id = :u2) OR (sender_id = :u2 AND receiver_id = :u1)",
            ['u1' => $user1, 'u2' => $user2]
        );
    }

    public function getLatestNotificationId(int $userId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(notification_id), 0) FROM notifications WHERE user_id = :uid',
            ['uid' => $userId]
        );
    }

    public function markConversationAsRead(int $userId, int $otherUserId): void
    {
        $this->em->getConnection()->executeStatement(
            'UPDATE messages SET is_read = 1 WHERE sender_id = :otherUser AND receiver_id = :userId AND is_read = 0',
            [
                'otherUser' => $otherUserId,
                'userId' => $userId,
            ]
        );
    }

    public function listConversations(int $userId): array
    {
        $sql = "SELECT
                    CASE WHEN m.sender_id = :uid THEN m.receiver_id ELSE m.sender_id END AS other_user_id,
                    u.username AS other_username,
                    u.full_name AS other_full_name,
                    u.profile_picture,
                    u.last_login AS other_last_login,
                    CASE
                        WHEN u.is_online = 1 AND u.last_login IS NOT NULL AND u.last_login >= (NOW() - INTERVAL 5 MINUTE) THEN 1
                        ELSE 0
                    END AS is_online,
                    MAX(m.sent_at) AS last_message_at,
                    SUBSTRING_INDEX(GROUP_CONCAT(m.content ORDER BY m.sent_at DESC SEPARATOR '\\n'), '\\n', 1) AS last_message,
                    SUM(CASE WHEN m.receiver_id = :uid AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
                FROM messages m
                JOIN users u ON u.user_id = (CASE WHEN m.sender_id = :uid THEN m.receiver_id ELSE m.sender_id END)
                WHERE m.sender_id = :uid OR m.receiver_id = :uid
                GROUP BY other_user_id, u.username, u.full_name, u.profile_picture
                ORDER BY last_message_at DESC";

        return $this->em->getConnection()->fetchAllAssociative($sql, ['uid' => $userId]);
    }

    public function listConversationPresence(int $userId): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            "SELECT DISTINCT
                    CASE WHEN m.sender_id = :uid THEN m.receiver_id ELSE m.sender_id END AS other_user_id,
                    CASE
                        WHEN u.is_online = 1 AND u.last_login IS NOT NULL AND u.last_login >= (NOW() - INTERVAL 5 MINUTE) THEN 1
                        ELSE 0
                    END AS is_online
             FROM messages m
             JOIN users u ON u.user_id = (CASE WHEN m.sender_id = :uid THEN m.receiver_id ELSE m.sender_id END)
             WHERE m.sender_id = :uid OR m.receiver_id = :uid",
            ['uid' => $userId]
        );

        $presence = [];
        foreach ($rows as $row) {
            $presence[(int) $row['other_user_id']] = (int) $row['is_online'] === 1;
        }

        return $presence;
    }

    public function touchPresence(int $userId): void
    {
        $this->em->getConnection()->update(
            'users',
            [
                'is_online' => 1,
                'last_login' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
            ['user_id' => $userId]
        );
    }

    public function getUnreadMessageCount(int $userId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0',
            ['uid' => $userId]
        );
    }

    public function getUnreadNotificationCount(int $userId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0',
            ['uid' => $userId]
        );
    }

    public function listNotifications(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT n.notification_id, n.type, n.content, n.created_at, n.is_read,
                    ru.user_id AS related_user_id, ru.username AS related_username, ru.full_name AS related_full_name
             FROM notifications n
             LEFT JOIN users ru ON ru.user_id = n.related_user_id
             WHERE n.user_id = :uid
             ORDER BY n.created_at DESC
             LIMIT 100",
            ['uid' => $userId]
        );
    }

    public function listNotificationsSince(int $userId, int $afterNotificationId, int $limit = 20): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT n.notification_id, n.type, n.content, n.created_at, n.is_read,
                    ru.user_id AS related_user_id, ru.username AS related_username, ru.full_name AS related_full_name
             FROM notifications n
             LEFT JOIN users ru ON ru.user_id = n.related_user_id
             WHERE n.user_id = :uid
               AND n.notification_id > :afterId
             ORDER BY n.notification_id ASC
             LIMIT :lim",
            [
                'uid' => $userId,
                'afterId' => $afterNotificationId,
                'lim' => $limit,
            ],
            [
                'lim' => \Doctrine\DBAL\ParameterType::INTEGER,
            ]
        );
    }

    public function markNotificationAsRead(int $userId, int $notificationId): void
    {
        $this->em->getConnection()->update(
            'notifications',
            ['is_read' => 1],
            ['notification_id' => $notificationId, 'user_id' => $userId]
        );
    }

    public function markAllNotificationsAsRead(int $userId): void
    {
        $this->em->getConnection()->executeStatement(
            'UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0',
            ['uid' => $userId]
        );
    }

    public function createConnection(int $initiatorId, int $receiverId, string $type, ?string $receiverSkill, ?string $initiatorSkill): Connection
    {
        $initiator = $this->em->getRepository(User::class)->find($initiatorId);
        $receiver = $this->em->getRepository(User::class)->find($receiverId);

        $c = new Connection();
        $c->id = $this->uuid();
        $c->initiator = $initiator;
        $c->receiver = $receiver;
        $c->connectionType = $type;
        $c->receiverSkill = $receiverSkill;
        $c->initiatorSkill = $initiatorSkill;
        $c->status = 'pending';
        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }

    public function updateConnectionStatus(string $connectionId, string $status): void
    {
        $this->em->getConnection()->update('connections', ['status' => $status], ['connection_id' => $connectionId]);
    }

    public function createMeeting(string $connectionId, int $organizerId, string $meetingType, ?string $location, string $scheduledAt, int $duration): Meeting
    {
        $meeting = new Meeting();
        $meeting->id = $this->uuid();
        $meeting->connection = $this->em->getRepository(Connection::class)->find($connectionId);
        $meeting->organizer = $this->em->getRepository(User::class)->find($organizerId);
        $meeting->meetingType = $meetingType;
        $meeting->location = $location;
        $meeting->scheduledAt = new \DateTime($scheduledAt);
        $meeting->duration = $duration;
        $meeting->status = 'scheduled';
        $this->em->persist($meeting);
        $this->em->flush();

        return $meeting;
    }

    public function addMeetingParticipant(string $meetingId, int $userId): MeetingParticipant
    {
        $p = new MeetingParticipant();
        $p->id = $this->uuid();
        $p->meeting = $this->em->getRepository(Meeting::class)->find($meetingId);
        $p->user = $this->em->getRepository(User::class)->find($userId);
        $p->isActive = true;
        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }

    public function createNotificationPublic(int $userId, string $type, string $content, ?int $relatedUserId = null): void
    {
        $this->createNotification($userId, $type, $content, $relatedUserId);
    }

    private function createNotification(int $userId, string $type, string $content, ?int $relatedUserId = null): void
    {
        $this->em->getConnection()->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'content' => $content,
            'related_user_id' => $relatedUserId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'is_read' => 0,
        ]);
    }

    public function getFriendshipStats(int $userId): array
    {
        $totalFriends = (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM friendships WHERE (user1_id = :uid OR user2_id = :uid) AND status = 'ACCEPTED'",
            ['uid' => $userId]
        );

        $pendingCount = (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM friendships WHERE user2_id = :uid AND status = 'PENDING'",
            ['uid' => $userId]
        );

        return [
            'total_friends' => $totalFriends,
            'pending_count' => $pendingCount,
        ];
    }

    public function getPendingRequestsForUser(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT f.friendship_id, f.user1_id, f.created_date, u.username, u.full_name AS user_full_name, u.bio, u.location, u.profile_picture
             FROM friendships f
             JOIN users u ON f.user1_id = u.user_id
             WHERE f.user2_id = :uid AND f.status = 'PENDING'
             ORDER BY f.created_date DESC",
            ['uid' => $userId]
        );
    }

    public function getAcceptedFriendsDetailed(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT f.friendship_id,
                    CASE WHEN f.user1_id = :uid THEN f.user2_id ELSE f.user1_id END AS friend_id,
                    CASE WHEN f.user1_id = :uid THEN u2.username ELSE u1.username END AS friend_username,
                    CASE WHEN f.user1_id = :uid THEN u2.full_name ELSE u1.full_name END AS friend_full_name
             FROM friendships f
             LEFT JOIN users u1 ON f.user1_id = u1.user_id
             LEFT JOIN users u2 ON f.user2_id = u2.user_id
             WHERE (f.user1_id = :uid OR f.user2_id = :uid) AND f.status = 'ACCEPTED'
             ORDER BY f.accepted_date DESC",
            ['uid' => $userId]
        );
    }

    public function getUsersById(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT user_id, username, full_name, profile_picture, is_online, last_login FROM users WHERE user_id IN ($placeholders)",
            $userIds
        );
    }

    public function getAllUsers(): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT user_id, username, full_name, profile_picture, is_online, location FROM users ORDER BY full_name ASC"
        );
    }

    public function getAcceptedFriendships(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT f.friendship_id, f.user1_id, f.user2_id, f.status, f.created_date, f.accepted_date 
             FROM friendships f
             WHERE (f.user1_id = :uid OR f.user2_id = :uid) AND f.status = 'ACCEPTED'
             ORDER BY f.accepted_date DESC",
            ['uid' => $userId]
        );
    }

    public function getOutgoingPendingRequests(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT f.friendship_id, f.user2_id, f.created_date 
             FROM friendships f
             WHERE f.user1_id = :uid AND f.status = 'PENDING'
             ORDER BY f.created_date DESC",
            ['uid' => $userId]
        );
    }

    public function getFriendshipStatus(int $userId1, int $userId2): ?array
    {
        return $this->em->getConnection()->fetchAssociative(
            "SELECT friendship_id, status, user1_id, user2_id 
             FROM friendships 
             WHERE (user1_id = :uid1 AND user2_id = :uid2) OR (user1_id = :uid2 AND user2_id = :uid1)",
            ['uid1' => $userId1, 'uid2' => $userId2]
        );
    }

    public function deleteFriendship(int $friendshipId): void
    {
        $this->em->getConnection()->executeStatement(
            "DELETE FROM friendships WHERE friendship_id = :fid",
            ['fid' => $friendshipId]
        );
    }

    public function acceptFriendship(int $friendshipId): void
    {
        $this->acceptFriendRequest($friendshipId);
    }

    public function rejectFriendship(int $friendshipId): void
    {
        $this->rejectFriendRequest($friendshipId);
    }
}

