<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function create(int $userId, string $type, string $content, ?int $relatedUserId = null): Notification
    {
        $user = $this->em->getRepository(User::class)->find($userId);
        $relatedUser = $relatedUserId ? $this->em->getRepository(User::class)->find($relatedUserId) : null;

        $notification = new Notification();
        $notification->user = $user;
        $notification->type = $type;
        $notification->content = $content;
        $notification->relatedUser = $relatedUser;
        $notification->createdAt = new \DateTime();
        $notification->isRead = false;

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    public function getForUser(int $userId, int $limit = 50): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT n.notification_id, n.type, n.content, n.created_at, n.is_read,
                    ru.user_id AS related_user_id, ru.username AS related_username, ru.full_name AS related_full_name
             FROM notifications n
             LEFT JOIN users ru ON ru.user_id = n.related_user_id
             WHERE n.user_id = :uid
             ORDER BY n.created_at DESC
             LIMIT :lim",
            ['uid' => $userId, 'lim' => $limit],
            ['lim' => \Doctrine\DBAL\ParameterType::INTEGER]
        );
    }

    public function getSince(int $userId, int $afterNotificationId, int $limit = 20): array
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

    public function getUnread(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT n.notification_id, n.type, n.content, n.created_at, n.is_read,
                    ru.user_id AS related_user_id, ru.username AS related_username, ru.full_name AS related_full_name
             FROM notifications n
             LEFT JOIN users ru ON ru.user_id = n.related_user_id
             WHERE n.user_id = :uid AND n.is_read = 0
             ORDER BY n.created_at DESC",
            ['uid' => $userId]
        );
    }

    public function countUnread(int $userId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0',
            ['uid' => $userId]
        );
    }

    public function markAsRead(int $notificationId, int $userId): void
    {
        $this->em->getConnection()->update(
            'notifications',
            ['is_read' => 1],
            ['notification_id' => $notificationId, 'user_id' => $userId]
        );
    }

    public function markAllAsRead(int $userId): void
    {
        $this->em->getConnection()->executeStatement(
            'UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0',
            ['uid' => $userId]
        );
    }

    public function delete(int $notificationId): void
    {
        $this->em->getConnection()->delete('notifications', ['notification_id' => $notificationId]);
    }

    public function deleteAllForUser(int $userId): void
    {
        $this->em->getConnection()->delete('notifications', ['user_id' => $userId]);
    }
}
