<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * GroqSmartMatchingService – AI-powered matching using Groq API.
 * Free LLM inference with extremely fast response times (100+ tokens/sec).
 * Requires GROQ_API_KEY in .env
 *
 * Groq API: https://console.groq.com
 */
class GroqSmartMatchingService
{
    private const GROQ_MODEL = 'llama-3.1-8b-instant'; // Fast & free (560 tokens/sec)
    private readonly string $groqApiKey;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        ?string $groqApiKey = null,
    ) {
        $this->groqApiKey = $groqApiKey ?? $_ENV['GROQ_API_KEY'] ?? '';
    }

    /**
     * Returns MatchScore[] sorted by score DESC, top 50.
     * Uses Groq API for intelligent compatibility analysis.
     */
    public function calculateMatchScores(int $userId): array
    {
        if (empty($this->groqApiKey)) {
            throw new \RuntimeException(
                'GROQ_API_KEY not configured. Get one at https://console.groq.com'
            );
        }

        $conn = $this->em->getConnection();

        $currentUser = $conn->fetchAssociative(
            'SELECT user_id, location, username, full_name, bio, profile_picture
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

        $myHobbies = $this->getUserHobbies($userId);
        $matches = [];

        foreach ($otherUsers as $other) {
            $otherId = (int) $other['user_id'];
            $theirHobbies = $this->getUserHobbies($otherId);

            // Get Groq AI score
            $aiScore = $this->getGroqScore($currentUser, $other, $myHobbies, $theirHobbies);

            // Also compute rule-based score as secondary signal
            $ruleScore = $this->getRuleScore($currentUser, $other, $myHobbies, $theirHobbies);

            // Blend: 70% AI + 30% rules for stability
            $finalScore = (int) (0.7 * $aiScore['score'] + 0.3 * $ruleScore);

            $commonInterests = $this->findCommonHobbies($myHobbies, $theirHobbies);

            $matches[] = new MatchScore(
                id: $otherId,
                username: (string) $other['username'],
                fullName: (string) ($other['full_name'] ?: $other['username']),
                location: $other['location'] ?? null,
                bio: $other['bio'] ?? null,
                profilePicture: $other['profile_picture'] ?? null,
                score: $finalScore,
                reason: $aiScore['reason'],
                commonInterests: $commonInterests
            );
        }

        usort($matches, fn($a, $b) => $b->score <=> $a->score);

        return array_slice($matches, 0, 50);
    }

    /**
     * Call Groq API to get compatibility score and reason.
     */
    private function getGroqScore(array $current, array $other, array $myHobbies, array $theirHobbies): array
    {
        $myHobbyText = implode(', ', array_map(fn($h) => $h['name'], $myHobbies)) ?: 'None specified';
        $theirHobbyText = implode(', ', array_map(fn($h) => $h['name'], $theirHobbies)) ?: 'None specified';

        $prompt = sprintf(
            "Rate compatibility 0-100 between these users.\nRespond ONLY in format: SCORE:XX|REASON:brief reason\n\n" .
            "User A:\nUsername: %s\nLocation: %s\nBio: %s\nHobbies: %s\n\n" .
            "User B:\nUsername: %s\nLocation: %s\nBio: %s\nHobbies: %s",
            $current['username'],
            $current['location'] ?? 'Not specified',
            $current['bio'] ?? 'No bio',
            $myHobbyText,
            $other['username'],
            $other['location'] ?? 'Not specified',
            $other['bio'] ?? 'No bio',
            $theirHobbyText
        );

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://api.groq.com/openai/v1/chat/completions',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->groqApiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => self::GROQ_MODEL,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a user compatibility analyzer. Respond in format: SCORE:XX|REASON:brief reason explaining the score.'
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'max_tokens' => 150,
                        'temperature' => 0.3,
                    ],
                    'timeout' => 10,
                ]
            );

            $data = $response->toArray();
            
            // Check for API error in response
            if (isset($data['error'])) {
                $errorMsg = $data['error']['message'] ?? json_encode($data['error']);
                error_log("Groq API error response: " . $errorMsg);
                return [
                    'score' => 50,
                    'reason' => 'Groq analysis skipped',
                ];
            }
            
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Parse response: SCORE:85|REASON:Shared hobbies and location
            if (preg_match('/SCORE:(\d+)/', $content, $matches)) {
                $score = (int) $matches[1];
                $reason = 'Compatible match';

                if (preg_match('/REASON:([^|]+)/', $content, $matches)) {
                    $reason = trim($matches[1]);
                }

                return [
                    'score' => max(0, min(100, $score)),
                    'reason' => $reason,
                ];
            }

            // Fallback if parse fails
            error_log("Groq response parse failed: " . $content);
            return [
                'score' => 50,
                'reason' => 'Groq analysis inconclusive',
            ];
        } catch (\Exception $e) {
            error_log('Groq API exception: ' . $e->getMessage() . ' | Code: ' . $e->getCode());

            return [
                'score' => 50,
                'reason' => 'Analysis unavailable (check logs)',
            ];
        }
    }

    /**
     * Fallback rule-based scoring (same logic as SmartMatchingService).
     */
    private function getRuleScore(array $current, array $other, array $myHobbies, array $theirHobbies): int
    {
        $score = 0;

        // 1. Shared hobbies (exact name match)
        $hobbyPoints = 0;
        foreach ($myHobbies as $mine) {
            foreach ($theirHobbies as $theirs) {
                if (strcasecmp($mine['name'], $theirs['name']) === 0) {
                    if ($hobbyPoints < 40) {
                        $hobbyPoints += 10;
                    }
                }
            }
        }
        $score += min($hobbyPoints, 40);

        // 2. Location proximity
        if (
            !empty($current['location']) &&
            !empty($other['location']) &&
            strcasecmp($current['location'], $other['location']) === 0
        ) {
            $score += 20;
        }

        // 3. Activity level similarity
        $diff = abs(count($myHobbies) - count($theirHobbies));
        if ($diff <= 2) {
            $score += 10;
        }

        return min($score, 100);
    }

    /**
     * Find common hobbies between two users.
     */
    private function findCommonHobbies(array $myHobbies, array $theirHobbies): array
    {
        $myNames = array_map(fn($h) => strtolower($h['name']), $myHobbies);
        $theirNames = array_map(fn($h) => strtolower($h['name']), $theirHobbies);

        return array_values(array_intersect($myNames, $theirNames));
    }

    /**
     * Get user hobbies with details.
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
