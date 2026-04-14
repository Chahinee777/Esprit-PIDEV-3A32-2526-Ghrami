<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Analytics service — executes SQL aggregation queries for the Admin analytics dashboard.
 * Mirrors desktop AnalyticsService.java functionality.
 */
final class AnalyticsService implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public function __construct(private Connection $connection) {}

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function count(string $sql): int
    {
        try {
            return (int) $this->connection->fetchOne($sql);
        } catch (\Exception) {
            return 0;
        }
    }

    private function sum(string $sql): float
    {
        try {
            $result = $this->connection->fetchOne($sql);
            return (float) ($result ?? 0);
        } catch (\Exception) {
            return 0.0;
        }
    }

    private function groupBy(string $sql): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative($sql);
            $result = [];
            foreach ($rows as $row) {
                $result[$row['label']] = (int) $row['count'];
            }
            return $result;
        } catch (\Exception) {
            return [];
        }
    }

    // ── Users ────────────────────────────────────────────────────────────────

    public function getTotalUsers(): int
    {
        return $this->count('SELECT COUNT(*) FROM users');
    }

    public function getBannedUsers(): int
    {
        return $this->count('SELECT COUNT(*) FROM users WHERE is_banned = 1');
    }

    public function getOnlineUsers(): int
    {
        return $this->count('SELECT COUNT(*) FROM users WHERE is_online = 1');
    }

    public function getLocalUsers(): int
    {
        return $this->count("SELECT COUNT(*) FROM users WHERE auth_provider = 'local'");
    }

    public function getGoogleUsers(): int
    {
        return $this->count("SELECT COUNT(*) FROM users WHERE auth_provider = 'google'");
    }

    // ── Social ───────────────────────────────────────────────────────────────

    public function getTotalPosts(): int
    {
        return $this->count('SELECT COUNT(*) FROM posts');
    }

    public function getTotalComments(): int
    {
        return $this->count('SELECT COUNT(*) FROM comments');
    }

    public function getTotalLikes(): int
    {
        return $this->count('SELECT COUNT(*) FROM post_likes');
    }

    public function getAcceptedFriendships(): int
    {
        return $this->count("SELECT COUNT(*) FROM friendships WHERE status = 'ACCEPTED'");
    }

    public function getPendingFriendships(): int
    {
        return $this->count("SELECT COUNT(*) FROM friendships WHERE status = 'PENDING'");
    }

    // ── Badges ───────────────────────────────────────────────────────────────

    public function getTotalBadges(): int
    {
        return $this->count('SELECT COUNT(*) FROM badges');
    }

    public function getUniqueBadgeTypes(): int
    {
        return $this->count('SELECT COUNT(DISTINCT name) FROM badges');
    }

    // ── Hobbies ──────────────────────────────────────────────────────────────

    public function getTotalHobbies(): int
    {
        return $this->count('SELECT COUNT(*) FROM hobbies');
    }

    public function getTotalMilestones(): int
    {
        return $this->count('SELECT COUNT(*) FROM milestones');
    }

    public function getAchievedMilestones(): int
    {
        return $this->count('SELECT COUNT(*) FROM milestones WHERE is_achieved = 1');
    }

    public function getTotalHobbyHours(): float
    {
        return $this->sum('SELECT COALESCE(SUM(hours_spent), 0) FROM progress');
    }

    // ── Classes & Bookings ───────────────────────────────────────────────────

    public function getTotalClasses(): int
    {
        return $this->count('SELECT COUNT(*) FROM classes');
    }

    public function getCompletedBookings(): int
    {
        return $this->count("SELECT COUNT(*) FROM bookings WHERE status = 'completed'");
    }

    public function getScheduledBookings(): int
    {
        return $this->count("SELECT COUNT(*) FROM bookings WHERE status = 'scheduled'");
    }

    public function getPendingBookings(): int
    {
        return $this->count("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
    }

    public function getCancelledBookings(): int
    {
        return $this->count("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'");
    }

    public function getTotalBookings(): int
    {
        return $this->count('SELECT COUNT(*) FROM bookings');
    }

    public function getTotalRevenue(): float
    {
        return $this->sum("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid'");
    }

    // ── Meetings ─────────────────────────────────────────────────────────────

    public function getTotalMeetings(): int
    {
        return $this->count('SELECT COUNT(*) FROM meetings');
    }

    public function getCompletedMeetings(): int
    {
        return $this->count("SELECT COUNT(*) FROM meetings WHERE status = 'completed'");
    }

    public function getScheduledMeetings(): int
    {
        return $this->count("SELECT COUNT(*) FROM meetings WHERE status = 'scheduled'");
    }

    public function getCancelledMeetings(): int
    {
        return $this->count("SELECT COUNT(*) FROM meetings WHERE status = 'cancelled'");
    }

    // ── Grouped / Chart Data ─────────────────────────────────────────────────

    /** @return array<string, int> */
    public function getBookingsByStatus(): array
    {
        return $this->groupBy(
            "SELECT COALESCE(status, 'unknown') AS label, COUNT(*) as count FROM bookings GROUP BY status"
        );
    }

    /** @return array<string, int> */
    public function getMeetingsByStatus(): array
    {
        return $this->groupBy(
            "SELECT COALESCE(status, 'unknown') AS label, COUNT(*) as count FROM meetings GROUP BY status"
        );
    }

    /** @return array<string, int> */
    public function getTopBadges(): array
    {
        $result = [];
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT name, COUNT(*) as count FROM badges GROUP BY name ORDER BY count DESC LIMIT 8'
            );
            foreach ($rows as $row) {
                $result[$row['name']] = (int) $row['count'];
            }
        } catch (\Exception) {
            // silence
        }
        return $result;
    }

    /** @return array<string, int> */
    public function getFriendshipsByStatus(): array
    {
        return $this->groupBy(
            "SELECT COALESCE(status, 'unknown') AS label, COUNT(*) as count FROM friendships GROUP BY status"
        );
    }

    /** @return array<string, int> */
    public function getModulePopulation(): array
    {
        $data = [];
        try {
            $data['Users'] = $this->getTotalUsers();
            $data['Posts'] = $this->getTotalPosts();
            $data['Comments'] = $this->getTotalComments();
            $data['Likes'] = $this->getTotalLikes();
            $data['Friendships'] = $this->getAcceptedFriendships();
            $data['Hobbies'] = $this->getTotalHobbies();
            $data['Classes'] = $this->getTotalClasses();
            $data['Bookings'] = $this->getTotalBookings();
            $data['Meetings'] = $this->getTotalMeetings();
            $data['Badges'] = $this->getTotalBadges();
        } catch (\Exception) {
            // silence
        }
        return $data;
    }

    /**
     * Get all analytics for dashboard display.
     *
     * @return array<string, mixed>
     */
    public function getAllAnalytics(): array
    {
        return [
            'kpi' => [
                'totalUsers' => $this->getTotalUsers(),
                'onlineUsers' => $this->getOnlineUsers(),
                'bannedUsers' => $this->getBannedUsers(),
                'totalPosts' => $this->getTotalPosts(),
                'totalComments' => $this->getTotalComments(),
                'totalLikes' => $this->getTotalLikes(),
                'totalClasses' => $this->getTotalClasses(),
                'totalBookings' => $this->getTotalBookings(),
                'revenue' => $this->getTotalRevenue(),
                'totalHobbies' => $this->getTotalHobbies(),
                'hobbyHours' => $this->getTotalHobbyHours(),
                'achievedMilestones' => $this->getAchievedMilestones(),
                'totalMilestones' => $this->getTotalMilestones(),
                'totalMeetings' => $this->getTotalMeetings(),
                'scheduledMeetings' => $this->getScheduledMeetings(),
                'completedMeetings' => $this->getCompletedMeetings(),
                'totalBadges' => $this->getTotalBadges(),
                'uniqueBadgeTypes' => $this->getUniqueBadgeTypes(),
            ],
            'charts' => [
                'modulePopulation' => $this->getModulePopulation(),
                'bookingsByStatus' => $this->getBookingsByStatus(),
                'meetingsByStatus' => $this->getMeetingsByStatus(),
                'friendshipsByStatus' => $this->getFriendshipsByStatus(),
                'topBadges' => $this->getTopBadges(),
            ],
        ];
    }
}
