<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * DailySummaryService - AI-powered daily usage summary using Groq
 * Analyzes user activity across all modules and generates personalized insights
 */
final class DailySummaryService
{
    private const GROQ_MODEL = 'llama-3.1-8b-instant';
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private Connection $connection,
        private HttpClientInterface $httpClient,
        private string $groqApiKey = '',
    ) {}

    /**
     * Get daily summary for user based on today's activity
     * 
     * @return array {
     *   'summary': string,
     *   'stats': {
     *     'posts_created': int,
     *     'comments_made': int,
     *     'hobbies_logged': int,
     *     'hours_spent': float,
     *     'connections_made': int,
     *     'classes_attended': int,
     *     'classes_taught': int,
     *     'meetings_attended': int,
     *     'badges_earned': int,
     *     'likes_received': int,
     *     'messages_sent': int,
     *   },
     *   'achievements': string[],
     *   'recommendations': string[]
     * }
     */
    public function getDailySummary(int $userId): array
    {
        try {
            error_log("[DailySummary] Starting for user {$userId}");
            
            $stats = $this->gatherDailyStats($userId);
            error_log("[DailySummary] Stats gathered: " . json_encode($stats));
            
            $achievements = $this->getAchievementsToday($userId);
            error_log("[DailySummary] Achievements: " . count($achievements) . " found");
            
            $summary = $this->generateAISummary($stats, $achievements, $userId);
            error_log("[DailySummary] AI Summary generated: " . substr($summary, 0, 100));
            
            $recommendations = $this->generateRecommendations($stats);
            error_log("[DailySummary] Recommendations: " . count($recommendations) . " generated");

            return [
                'summary' => $summary,
                'stats' => $stats,
                'achievements' => $achievements,
                'recommendations' => $recommendations,
                'generated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            error_log('DailySummaryService error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return $this->getFallbackSummary($userId);
        }
    }

    /**
     * Gather all statistics for today
     */
    private function gatherDailyStats(int $userId): array
    {
        try {
            $today = (new \DateTime())->format('Y-m-d');

            error_log("[DailySummary] Gathering stats for today: {$today}");

            $stats = [
                // Posts created today
                'posts_created' => (int) ($this->connection->fetchOne(
                    "SELECT COUNT(*) FROM posts WHERE user_id = ? AND DATE(created_at) = ?",
                    [$userId, $today]
                ) ?? 0),
                
                // Comments made today
                'comments_made' => (int) ($this->connection->fetchOne(
                    "SELECT COUNT(*) FROM comments WHERE user_id = ? AND DATE(created_at) = ?",
                    [$userId, $today]
                ) ?? 0),
                
                // Hobbies updated today
                'hobbies_logged' => (int) ($this->connection->fetchOne(
                    "SELECT COUNT(DISTINCT p.hobby_id) FROM progress p INNER JOIN hobbies h ON p.hobby_id = h.hobby_id WHERE h.user_id = ?",
                    [$userId]
                ) ?? 0),
                
                // Total hours spent on hobbies
                'hours_spent' => (float) ($this->connection->fetchOne(
                    "SELECT COALESCE(SUM(p.hours_spent), 0) FROM progress p INNER JOIN hobbies h ON p.hobby_id = h.hobby_id WHERE h.user_id = ?",
                    [$userId]
                ) ?? 0),
                
                // Connections made (total)
                'connections_made' => (int) ($this->connection->fetchOne(
                    "SELECT COUNT(*) FROM connections WHERE (initiator_id = ? OR receiver_id = ?) AND status = 'accepted'",
                    [$userId, $userId]
                ) ?? 0),
                
                // Classes attended (completed bookings)
                'classes_attended' => (int) ($this->connection->fetchOne(
                    "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'completed'",
                    [$userId]
                ) ?? 0),
                
                // Classes taught (provider's classes)
                'classes_taught' => (int) ($this->connection->fetchOne(
                    "SELECT COUNT(DISTINCT c.class_id) FROM classes c INNER JOIN class_providers cp ON c.provider_id = cp.provider_id WHERE cp.user_id = ?",
                    [$userId]
                ) ?? 0),
                
                // Meetings attended
                'meetings_attended' => (int) ($this->connection->fetchOne(
                    "SELECT COUNT(DISTINCT mp.meeting_id) FROM meeting_participants mp WHERE mp.user_id = ? AND mp.is_active = 1",
                    [$userId]
                ) ?? 0),
                
                // Badges earned today
                'badges_earned' => (int) ($this->connection->fetchOne(
                    "SELECT COUNT(*) FROM badges WHERE user_id = ? AND DATE(earned_date) = ?",
                    [$userId, $today]
                ) ?? 0),
                
                // Likes received on posts today
                'likes_received' => (int) ($this->connection->fetchOne(
                    "SELECT COUNT(*) FROM post_likes pl INNER JOIN posts p ON pl.post_id = p.post_id WHERE p.user_id = ? AND DATE(pl.created_at) = ?",
                    [$userId, $today]
                ) ?? 0),
                
                // Messages sent today
                'messages_sent' => (int) ($this->connection->fetchOne(
                    "SELECT COUNT(*) FROM messages WHERE sender_id = ? AND DATE(sent_at) = ?",
                    [$userId, $today]
                ) ?? 0),
            ];

            error_log("[DailySummary] Stats gathered: " . json_encode($stats));
            return $stats;
        } catch (\Exception $e) {
            error_log('[DailySummary] gatherDailyStats error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get achievements unlocked today
     */
    private function getAchievementsToday(int $userId): array
    {
        try {
            $today = (new \DateTime())->format('Y-m-d');

            error_log("[DailySummary] Getting achievements for user {$userId}");

            $achievements = $this->connection->fetchAllAssociative(
                "SELECT name FROM badges WHERE user_id = ? AND DATE(earned_date) = ?",
                [$userId, $today]
            );

            error_log("[DailySummary] Found " . count($achievements) . " achievements");
            
            return array_map(fn($a) => '🏆 ' . $a['name'], $achievements);
        } catch (\Exception $e) {
            error_log('[DailySummary] getAchievementsToday error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate AI summary using Groq
     */
    private function generateAISummary(array $stats, array $achievements, int $userId): string
    {
        if (empty($this->groqApiKey)) {
            error_log("[DailySummary] Groq API key is empty, using fallback");
            return $this->generateFallbackSummary($stats);
        }

        try {
            error_log("[DailySummary] Generating AI summary");
            
            $userName = $this->connection->fetchOne(
                "SELECT username FROM users WHERE user_id = ?",
                [$userId]
            ) ?? 'Friend';

            $prompt = $this->buildSummaryPrompt($stats, $achievements, $userName);
            error_log("[DailySummary] Prompt built, length: " . strlen($prompt));
            
            $response = $this->queryGroq($prompt, 0.7, 250);
            error_log("[DailySummary] Got response from Groq");

            return !empty($response) ? $response : $this->generateFallbackSummary($stats);
        } catch (\Exception $e) {
            error_log('DailySummaryService::generateAISummary error: ' . $e->getMessage());
            return $this->generateFallbackSummary($stats);
        }
    }

    /**
     * Build the prompt for Groq
     */
    private function buildSummaryPrompt(array $stats, array $achievements, string $userName): string
    {
        $statsText = sprintf(
            "Posts: %d, Comments: %d, Hobbies tracked: %d (%.1f hours), Connections: %d, Classes: %d attended, %d taught, Meetings: %d, Badges: %d, Likes: %d, Messages: %d",
            $stats['posts_created'],
            $stats['comments_made'],
            $stats['hobbies_logged'],
            $stats['hours_spent'],
            $stats['connections_made'],
            $stats['classes_attended'],
            $stats['classes_taught'],
            $stats['meetings_attended'],
            $stats['badges_earned'],
            $stats['likes_received'],
            $stats['messages_sent']
        );

        $achievementsText = !empty($achievements) 
            ? "Achievements: " . implode(', ', $achievements)
            : "No new achievements today";

        return sprintf(
            "You are a friendly AI assistant for Ghrami, a hobby-sharing social platform. " .
            "Generate a warm, encouraging daily summary for %s based on their activity today. " .
            "Include highlights of their engagement and celebrate their efforts.\n\n" .
            "Activity Stats: %s\n\n%s\n\n" .
            "Write a 3-4 sentence personalized summary that:\n" .
            "1. Celebrates their accomplishments\n" .
            "2. Highlights their most impactful activity\n" .
            "3. Encourages continued engagement\n" .
            "Keep it positive, motivating, and genuine.",
            $userName,
            $statsText,
            $achievementsText
        );
    }

    /**
     * Generate recommendations based on activity
     */
    private function generateRecommendations(array $stats): array
    {
        $recommendations = [];

        if ($stats['posts_created'] === 0) {
            $recommendations[] = "✍️ Share a post about what you're learning today";
        }

        if ($stats['hobbies_logged'] === 0) {
            $recommendations[] = "🎯 Log time for one of your hobbies";
        }

        if ($stats['connections_made'] === 0) {
            $recommendations[] = "👥 Connect with someone who shares your interests";
        }

        if ($stats['classes_attended'] === 0 && $stats['classes_taught'] === 0) {
            $recommendations[] = "📚 Check out available classes or teach something you know";
        }

        if ($stats['meetings_attended'] === 0) {
            $recommendations[] = "📅 Join or organize a meeting with your hobby community";
        }

        if (empty($recommendations)) {
            $recommendations[] = "🌟 You're crushing it today! Keep up the amazing engagement";
            $recommendations[] = "🎓 Explore new hobbies or categories on the platform";
            $recommendations[] = "🏆 Help others by sharing your expertise";
        }

        return array_slice($recommendations, 0, 3);
    }

    /**
     * Fallback summary when Groq is unavailable
     */
    private function generateFallbackSummary(array $stats): string
    {
        $total = $stats['posts_created'] + $stats['comments_made'] + $stats['hobbies_logged'] + 
                 $stats['connections_made'] + $stats['classes_attended'] + $stats['meetings_attended'];

        if ($total === 0) {
            return "No activity recorded yet today. Start by sharing a post, logging a hobby, or connecting with someone!";
        }

        $highlights = [];
        if ($stats['posts_created'] > 0) $highlights[] = "{$stats['posts_created']} post" . ($stats['posts_created'] > 1 ? 's' : '');
        if ($stats['hobbies_logged'] > 0) $highlights[] = "{$stats['hobbies_logged']} hobby" . ($stats['hobbies_logged'] > 1 ? 's' : '') . " ({$stats['hours_spent']}h)";
        if ($stats['classes_attended'] > 0) $highlights[] = "{$stats['classes_attended']} class" . ($stats['classes_attended'] > 1 ? 'es' : '');
        if ($stats['badges_earned'] > 0) $highlights[] = "{$stats['badges_earned']} badge" . ($stats['badges_earned'] > 1 ? 's' : '');

        $highlightText = !empty($highlights) ? implode(', ', $highlights) : 'some activity';

        return "Great day! You've accomplished: $highlightText. Keep up your momentum!";
    }

    /**
     * Get fallback summary
     */
    private function getFallbackSummary(int $userId): array
    {
        return [
            'summary' => 'Unable to generate summary. Try again later!',
            'stats' => array_fill_keys([
                'posts_created', 'comments_made', 'hobbies_logged', 'hours_spent',
                'connections_made', 'classes_attended', 'classes_taught', 'meetings_attended',
                'badges_earned', 'likes_received', 'messages_sent'
            ], 0),
            'achievements' => [],
            'recommendations' => [
                '📝 Share a post today',
                '🎯 Log time for your hobbies',
                '👥 Connect with the community'
            ],
            'generated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Query Groq API
     */
    private function queryGroq(string $prompt, float $temperature = 0.7, int $maxTokens = 250): string
    {
        try {
            error_log("[DailySummary] Querying Groq with {$maxTokens} max tokens, temp={$temperature}");
            
            $response = $this->httpClient->request('POST', self::GROQ_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::GROQ_MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a friendly, encouraging AI assistant for Ghrami. Keep responses warm and motivating.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ],
                    ],
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            error_log("[DailySummary] Groq HTTP Status: {$statusCode}");
            
            if ($statusCode !== 200) {
                $errorBody = $response->getContent(false);
                error_log("[DailySummary] Groq API error response: {$errorBody}");
                throw new \RuntimeException("Groq API failed with status {$statusCode}: {$errorBody}");
            }

            $data = $response->toArray();
            error_log("[DailySummary] Groq response data received");
            
            $text = trim($data['choices'][0]['message']['content'] ?? '');
            error_log("[DailySummary] Groq response text: " . substr($text, 0, 100));
            
            return $text;
        } catch (\Exception $e) {
            error_log('[DailySummaryService::queryGroq] error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
            throw $e;
        }
    }
}
