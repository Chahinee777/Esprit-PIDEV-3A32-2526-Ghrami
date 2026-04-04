<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * SmartMatchingService – 5-factor matching algorithm matching the desktop version exactly.
 *
 * Factor                          Points  Cap
 * ─────────────────────────────── ─────── ───
 * 1. Shared hobbies (exact name)   +10/ea  40
 * 2. Shared hobby category         +5/ea   (within factor 1 cap)
 * 3. Complementary skills          +15/match 30
 * 4. Location proximity            +20    –
 * 5. Activity-level similarity     +10    –
 * 6. Badges (≥3)                   +5     –
 *
 * Total possible: 100 → used as percentage directly.
 */
class SmartMatchingService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Returns MatchScore[] sorted by score DESC, top 50.
     */
    public function calculateMatchScores(int $userId): array
    {
        $conn = $this->em->getConnection();

        $currentUser = $conn->fetchAssociative(
            'SELECT user_id, location, username, full_name, profile_picture
             FROM users WHERE user_id = :uid',
            ['uid' => $userId]
        );

        if (!$currentUser) {
            return [];
        }

        // Exclude self, admin (user_id = 0), banned users, and already-connected users
        $otherUsers = $conn->fetchAllAssociative(
            'SELECT user_id, username, full_name, bio, profile_picture, location
             FROM users
             WHERE user_id != :uid
               AND user_id != 0
               AND is_banned = 0
               AND user_id NOT IN (
                   SELECT CASE WHEN initiator_id = :uid THEN receiver_id ELSE initiator_id END
                   FROM connections
                   WHERE initiator_id = :uid OR receiver_id = :uid
               )
             LIMIT 100',
            ['uid' => $userId]
        );

        // Pre-fetch current user's hobbies (name + category + progress hours)
        $myHobbies = $this->getUserHobbies($userId);

        $matches = [];

        foreach ($otherUsers as $other) {
            $otherId      = (int) $other['user_id'];
            $score        = 0;
            $reasons      = [];
            $commonInterests = [];

            // ── 1 & 2. SHARED HOBBIES (40 pts max) ──────────────────────────
            $theirHobbies   = $this->getUserHobbies($otherId);
            $hobbyPoints    = 0;

            foreach ($myHobbies as $mine) {
                foreach ($theirHobbies as $theirs) {
                    if (strcasecmp($mine['name'], $theirs['name']) === 0) {
                        // Exact name match → 10 pts
                        if ($hobbyPoints < 40) {
                            $hobbyPoints += 10;
                        }
                        $commonInterests[] = $mine['name'];
                    } elseif (
                        $mine['category'] !== null &&
                        $theirs['category'] !== null &&
                        strcasecmp($mine['category'], $theirs['category']) === 0
                    ) {
                        // Same category → 5 pts
                        if ($hobbyPoints < 40) {
                            $hobbyPoints += 5;
                        }
                        $commonInterests[] = $mine['category'] . ' enthusiast';
                    }
                }
            }

            $score += min($hobbyPoints, 40);
            $commonInterests = array_values(array_unique($commonInterests));

            if (!empty($commonInterests)) {
                $reasons[] = 'Shared hobbies: ' . implode(', ', array_slice($commonInterests, 0, 3));
            }

            // ── 3. COMPLEMENTARY SKILLS (30 pts) ─────────────────────────────
            // Desktop logic: I'm a beginner (<50 h) in X and they're expert (>100 h)
            $skillPoints = 0;
            foreach ($myHobbies as $mine) {
                $myHours = (float) ($mine['hours_spent'] ?? 0);
                if ($myHours < 50) {           // I'm a beginner in this hobby
                    foreach ($theirHobbies as $theirs) {
                        if (
                            strcasecmp($mine['name'], $theirs['name']) === 0 &&
                            (float) ($theirs['hours_spent'] ?? 0) > 100
                        ) {
                            $skillPoints = 15; // One match is enough for bonus
                            $reasons[]   = 'Can teach you ' . $mine['name'];
                            break 2;
                        }
                    }
                }
            }
            $score += min($skillPoints, 30);

            // ── 4. LOCATION PROXIMITY (20 pts) ───────────────────────────────
            if (
                !empty($currentUser['location']) &&
                !empty($other['location']) &&
                strcasecmp($currentUser['location'], $other['location']) === 0
            ) {
                $score    += 20;
                $reasons[] = 'Same location: ' . $currentUser['location'];
            }

            // ── 5. ACTIVITY-LEVEL SIMILARITY (10 pts) ────────────────────────
            $diff = abs(count($myHobbies) - count($theirHobbies));
            if ($diff <= 2) {
                $score    += 10;
                // Don't add reason text for this (noise reduction)
            }

            // ── 6. BADGES (5 pts bonus) ───────────────────────────────────────
            $badgeCount = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM badges WHERE user_id = :uid',
                ['uid' => $otherId]
            );
            if ($badgeCount >= 3) {
                $score    += 5;
                $reasons[] = $badgeCount . ' badges 🏆';
            }

            if (empty($reasons)) {
                $reasons[] = 'New connection opportunity';
            }

            $matches[] = new MatchScore(
                id:              $otherId,
                username:        (string) $other['username'],
                fullName:        (string) ($other['full_name'] ?: $other['username']),
                location:        $other['location'] ?? null,
                bio:             $other['bio'] ?? null,
                profilePicture:  $other['profile_picture'] ?? null,
                score:           $score,
                reason:          implode(' · ', $reasons),
                commonInterests: $commonInterests
            );
        }

        usort($matches, fn($a, $b) => $b->score <=> $a->score);

        return array_slice($matches, 0, 50);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns hobby rows for a user: name, category, hours_spent.
     * BUG FIX: old code queried `h.hobby_name` – DB column is `name`.
     * Also joins progress to get hours_spent for skill-complementarity scoring.
     */
    private function getUserHobbies(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            'SELECT h.hobby_id, h.name, h.category, COALESCE(p.hours_spent, 0) AS hours_spent
             FROM hobbies h
             LEFT JOIN progress p ON p.hobby_id = h.hobby_id
             WHERE h.user_id = :uid',
            ['uid' => $userId]
        );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// VALUE OBJECT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Immutable data class representing one user's match result.
 * Max raw score is 100, so getPercentage() maps 1:1.
 */
class MatchScore
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $username,
        public readonly string  $fullName,
        public readonly ?string $location,
        public readonly ?string $bio,
        public readonly ?string $profilePicture,
        public readonly int     $score,
        public readonly string  $reason,
        public readonly array   $commonInterests = []
    ) {}

    /** Clamp to 0–100 %. */
    public function getPercentage(): int
    {
        return max(0, min(100, $this->score));
    }

    public function getScoreColor(): string
    {
        $pct = $this->getPercentage();
        if ($pct >= 60) return 'green';
        if ($pct >= 40) return 'blue';
        return 'orange';
    }
}