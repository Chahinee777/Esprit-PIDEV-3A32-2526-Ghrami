<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class BadgeService
{
    private const BADGES = [
        ['id' => 'first_post', 'name' => 'First Step 🎬', 'category' => 'Social', 'rarity' => 'COMMUN', 'hint' => 'Write your first post', 'icon' => '📝'],
        ['id' => 'social_butterfly', 'name' => 'Social Butterfly 🦋', 'category' => 'Social', 'rarity' => 'RARE', 'hint' => 'Get 50 likes across posts', 'icon' => '🦋'],
        ['id' => '10_friends', 'name' => 'Friendmaker 👥', 'category' => 'Social', 'rarity' => 'COMMUN', 'hint' => 'Make 10 friends', 'icon' => '👥'],
        ['id' => '50_friends', 'name' => 'Influencer 🌟', 'category' => 'Social', 'rarity' => 'ÉPIQUE', 'hint' => 'Get 50 friends', 'icon' => '🌟'],
        ['id' => 'hobby_master', 'name' => 'Hobby Enthusiast 🎯', 'category' => 'Hobbies', 'rarity' => 'RARE', 'hint' => 'Track 5 different hobbies', 'icon' => '🎯'],
        ['id' => '100_hours_hobby', 'name' => 'Dedicated 💪', 'category' => 'Hobbies', 'rarity' => 'RARE', 'hint' => 'Log 100 hours in a hobby', 'icon' => '💪'],
        ['id' => 'milestone_achievement', 'name' => 'Goal Setter 🏆', 'category' => 'Milestones', 'rarity' => 'RARE', 'hint' => 'Complete 5 milestones', 'icon' => '🏆'],
        ['id' => 'all_milestones', 'name' => 'Unstoppable ⚡', 'category' => 'Milestones', 'rarity' => 'LÉGENDAIRE', 'hint' => 'Complete all milestones', 'icon' => '⚡'],
        ['id' => 'first_class', 'name' => 'Student 📚', 'category' => 'Classes', 'rarity' => 'COMMUN', 'hint' => 'Book your first class', 'icon' => '📚'],
        ['id' => '5_classes', 'name' => 'Class Attendee 🎓', 'category' => 'Classes', 'rarity' => 'RARE', 'hint' => 'Complete 5 classes', 'icon' => '🎓'],
        ['id' => '10_classes', 'name' => 'Super Learner 🔥', 'category' => 'Classes', 'rarity' => 'ÉPIQUE', 'hint' => 'Complete 10 classes', 'icon' => '🔥'],
        ['id' => 'first_instructor', 'name' => 'Instructor 👨‍🏫', 'category' => 'Classes', 'rarity' => 'RARE', 'hint' => 'Teach your first class', 'icon' => '👨‍🏫'],
        ['id' => '100_students', 'name' => 'Master Teacher 🎖️', 'category' => 'Classes', 'rarity' => 'ÉPIQUE', 'hint' => 'Teach 100 students', 'icon' => '🎖️'],
        ['id' => 'first_meeting', 'name' => 'Connector 🤝', 'category' => 'Social', 'rarity' => 'COMMUN', 'hint' => 'Attend your first meeting', 'icon' => '🤝'],
        ['id' => 'mentor', 'name' => 'Mentor 🧙', 'category' => 'Social', 'rarity' => 'ÉPIQUE', 'hint' => 'Become a mentor', 'icon' => '🧙'],
        ['id' => 'mentee', 'name' => 'Mentee 👦', 'category' => 'Social', 'rarity' => 'RARE', 'hint' => 'Learn from a mentor', 'icon' => '👦'],
        ['id' => 'week_streak', 'name' => 'On Fire 🔥', 'category' => 'Activity', 'rarity' => 'RARE', 'hint' => 'Log activity for 7 days straight', 'icon' => '🔥'],
        ['id' => 'month_streak', 'name' => 'Unstoppable 💯', 'category' => 'Activity', 'rarity' => 'LÉGENDAIRE', 'hint' => 'Log activity for 30 days straight', 'icon' => '💯'],
        ['id' => '1000_views', 'name' => 'Popular 📺', 'category' => 'Social', 'rarity' => 'ÉPIQUE', 'hint' => 'Get 1000 views on posts', 'icon' => '📺'],
        ['id' => 'vr_explorer', 'name' => 'VR Explorer 🥽', 'category' => 'Hobbies', 'rarity' => 'RARE', 'hint' => 'Visit all 12 VR rooms', 'icon' => '🥽'],
        ['id' => 'community_helper', 'name' => 'Helper ❤️', 'category' => 'Social', 'rarity' => 'RARE', 'hint' => 'Help 10 people', 'icon' => '❤️'],
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getAllBadges(): array
    {
        // First, try to get unique badge definitions from the database
        $dbBadges = $this->em->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT name FROM badges ORDER BY name ASC'
        );

        // If we have badges in the database, use them as the source of truth
        if (!empty($dbBadges)) {
            $allBadges = [];
            $seenNames = [];
            
            foreach ($dbBadges as $row) {
                $name = $row['name'];
                if (isset($seenNames[$name])) {
                    continue; // Skip duplicates
                }
                $seenNames[$name] = true;
                
                // Try to find matching badge in the BADGES constant for metadata
                $metadata = null;
                foreach (self::BADGES as $b) {
                    if ($b['name'] === $name) {
                        $metadata = $b;
                        break;
                    }
                }
                
                // Use database badge with fallback to constant metadata
                if ($metadata) {
                    $allBadges[] = $metadata;
                } else {
                    // If badge is in DB but not in constant, create a basic entry
                    $allBadges[] = [
                        'id' => uniqid('badge_'),
                        'name' => $name,
                        'icon' => '🏆',
                        'category' => 'Activity',
                        'rarity' => 'COMMUN',
                        'hint' => 'Unlock this achievement!',
                    ];
                }
            }
            
            return $allBadges;
        }
        
        // Fallback to hardcoded badges if database is empty
        return self::BADGES;
    }

    public function getBadgeById(string $badgeId): ?array
    {
        foreach (self::BADGES as $badge) {
            if ($badge['id'] === $badgeId) {
                return $badge;
            }
        }
        return null;
    }

    public function getBadgesByCategory(string $category): array
    {
        return array_filter(self::BADGES, fn(array $b) => $b['category'] === $category);
    }

    public function getBadgesByRarity(string $rarity): array
    {
        return array_filter(self::BADGES, fn(array $b) => $b['rarity'] === $rarity);
    }

    public function recordBadgeEarned(int $userId, string $badgeId): bool
    {
        $badge = $this->getBadgeById($badgeId);
        if (!$badge) {
            return false;
        }

        $existing = $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM badges WHERE user_id = :uid AND name = :name',
            ['uid' => $userId, 'name' => $badge['name']]
        );

        if ((int) $existing > 0) {
            return false;
        }

        $this->em->getConnection()->insert('badges', [
            'user_id' => $userId,
            'name' => $badge['name'],
            'description' => $badge['hint'] ?? null,
            'earned_date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function getUserBadges(int $userId): array
    {
        $userBadges = $this->em->getConnection()->fetchAllAssociative(
            'SELECT badge_id, name, description, earned_date FROM badges WHERE user_id = :uid ORDER BY earned_date DESC',
            ['uid' => $userId]
        );

        // Map database badges to include icon and rarity from BADGES array if available
        return array_map(function($badge) {
            // Try to find matching badge in BADGES array by exact name match
            $matchedBadge = null;
            foreach (self::BADGES as $b) {
                if ($b['name'] === $badge['name']) {
                    $matchedBadge = $b;
                    break;
                }
            }
            
            return [
                'id' => $badge['badge_id'],
                'name' => $badge['name'],
                'description' => $badge['description'] ?? 'Achievement débloqué!',
                'icon' => $matchedBadge['icon'] ?? '🏆',
                'rarity' => $matchedBadge['rarity'] ?? 'COMMUN',
                'category' => $matchedBadge['category'] ?? 'Activity',
                'hint' => $matchedBadge['hint'] ?? '',
                'earned_date' => $badge['earned_date'],
            ];
        }, $userBadges);
    }

    public function getUserBadgeDictionary(int $userId): array
    {
        $badges = $this->getUserBadges($userId);
        $map = [];
        foreach ($badges as $badge) {
            $map[$badge['id']] = $badge;
        }
        return $map;
    }

    public function getBadgeStats(): array
    {
        $stats = [];
        foreach (array_unique(array_map(fn(array $b) => $b['category'], self::BADGES)) as $category) {
            $stats[$category] = count($this->getBadgesByCategory($category));
        }
        return $stats;
    }

    public function checkAndAwardBadges(int $userId): array
    {
        $awarded = [];

        // Check First Post
        $postCount = (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM posts WHERE user_id = :uid',
            ['uid' => $userId]
        );
        if ($postCount >= 1 && $this->recordBadgeEarned($userId, 'first_post')) {
            $awarded[] = 'first_post';
        }

        // Check Friends
        $friendCount = (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM friendships WHERE (user1_id = :uid OR user2_id = :uid) AND status = 'ACCEPTED'",
            ['uid' => $userId]
        );
        if ($friendCount >= 10 && $this->recordBadgeEarned($userId, '10_friends')) {
            $awarded[] = '10_friends';
        }
        if ($friendCount >= 50 && $this->recordBadgeEarned($userId, '50_friends')) {
            $awarded[] = '50_friends';
        }

        // Check Hobbies
        $hobbyCount = (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(DISTINCT hobby_id) FROM hobbies WHERE user_id = :uid',
            ['uid' => $userId]
        );
        if ($hobbyCount >= 5 && $this->recordBadgeEarned($userId, 'hobby_master')) {
            $awarded[] = 'hobby_master';
        }

        // Check Classes
        $classCount = (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM bookings WHERE user_id = :uid AND status = 'COMPLETED'",
            ['uid' => $userId]
        );
        if ($classCount >= 1 && $this->recordBadgeEarned($userId, 'first_class')) {
            $awarded[] = 'first_class';
        }
        if ($classCount >= 5 && $this->recordBadgeEarned($userId, '5_classes')) {
            $awarded[] = '5_classes';
        }
        if ($classCount >= 10 && $this->recordBadgeEarned($userId, '10_classes')) {
            $awarded[] = '10_classes';
        }

        // Check Meetings
        $meetingCount = (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM meeting_participants WHERE user_id = :uid',
            ['uid' => $userId]
        );
        if ($meetingCount >= 1 && $this->recordBadgeEarned($userId, 'first_meeting')) {
            $awarded[] = 'first_meeting';
        }

        return $awarded;
    }
}
