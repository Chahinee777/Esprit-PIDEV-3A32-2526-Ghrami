<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Groq-powered analytics — generates AI insights from existing data
 */
final class GroqAnalyticsService
{
    public function __construct(
        private Connection $connection,
        private HttpClientInterface $httpClient,
        private string $groqApiKey,
        private string $groqModel = 'llama-3.1-8b-instant',
    ) {}

    /**
     * Get AI insights for user's hobbies
     */
    public function getUserHobbyInsights(int $userId): array
    {
        try {
            // Gather hobby data
            $hobbies = $this->connection->fetchAllAssociative(
                "SELECT h.hobby_id, h.name, h.category, h.description,
                        COALESCE(SUM(pl.hours_spent), 0) AS total_hours,
                        COUNT(pl.log_id) AS session_count,
                        MAX(pl.log_date) AS last_logged
                 FROM hobbies h
                 LEFT JOIN progress_log pl ON pl.hobby_id = h.hobby_id
                 WHERE h.user_id = ?
                 GROUP BY h.hobby_id, h.name, h.category, h.description
                 ORDER BY total_hours DESC",
                [$userId]
            );

            if (empty($hobbies)) {
                return ['insights' => 'Start tracking hobbies to get personalized insights!'];
            }

            // Build prompt for Groq
            $hobbyData = array_map(fn($h) => sprintf(
                "- %s (%s): %d hours logged in %d sessions, last logged %s",
                $h['name'],
                $h['category'],
                $h['total_hours'],
                $h['session_count'],
                $h['last_logged'] ?? 'never'
            ), $hobbies);

            $prompt = "Analyze these hobbies and provide 3-4 personalized insights and recommendations:\n\n" .
                implode("\n", $hobbyData) .
                "\n\nProvide actionable advice for improving consistency and skill development.";

            return $this->queryGroq($prompt);
        } catch (\Exception $e) {
            error_log("Groq analytics error: " . $e->getMessage());
            return ['insights' => 'Unable to generate insights at this moment.'];
        }
    }

    /**
     * Get engagement trends
     */
    public function getUserEngagementInsights(int $userId): array
    {
        try {
            $stats = [
                'posts' => (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM posts WHERE user_id = ?',
                    [$userId]
                ),
                'comments' => (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM comments WHERE user_id = ?',
                    [$userId]
                ),
                'hobbies' => (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM hobbies WHERE user_id = ?',
                    [$userId]
                ),
                'badges' => (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM badges WHERE user_id = ?',
                    [$userId]
                ),
                'friends' => (int) $this->connection->fetchOne(
                    "SELECT COUNT(*) FROM friendships WHERE status = 'ACCEPTED' AND (user1_id = ? OR user2_id = ?)",
                    [$userId, $userId]
                ),
            ];

            $prompt = "Based on this user engagement data, provide insights about their activity level and recommendations:\n" .
                "Posts: {$stats['posts']}\n" .
                "Comments: {$stats['comments']}\n" .
                "Hobbies tracked: {$stats['hobbies']}\n" .
                "Badges earned: {$stats['badges']}\n" .
                "Friends: {$stats['friends']}\n\n" .
                "Give 3 personalized recommendations to increase engagement.";

            return $this->queryGroq($prompt);
        } catch (\Exception $e) {
            error_log("Groq engagement insights error: " . $e->getMessage());
            return ['insights' => 'Unable to generate insights.'];
        }
    }

    /**
     * Get hobby recommendations based on popular categories
     */
    public function getHobbyRecommendations(int $userId): array
    {
        try {
            $userHobbies = $this->connection->fetchFirstColumn(
                'SELECT category FROM hobbies WHERE user_id = ? GROUP BY category',
                [$userId]
            );

            $popularCategories = $this->connection->fetchAllAssociative(
                "SELECT h.category, COUNT(DISTINCT h.user_id) AS user_count
                 FROM hobbies h
                 WHERE h.user_id != ?
                 GROUP BY h.category
                 ORDER BY user_count DESC
                 LIMIT 5",
                [$userId]
            );

            $currentStr = !empty($userHobbies) ? implode(', ', $userHobbies) : 'None yet';
            $popularStr = !empty($popularCategories) 
                ? implode(', ', array_map(fn($p) => "{$p['category']} ({$p['user_count']} users)", $popularCategories))
                : 'General interests';

            $prompt = "A user is interested in: $currentStr\n" .
                "Popular hobby categories on the platform: $popularStr\n\n" .
                "Recommend 3-4 new hobbies they might enjoy based on their interests.";

            return $this->queryGroq($prompt);
        } catch (\Exception $e) {
            error_log("Groq recommendations error: " . $e->getMessage());
            return ['insights' => 'Unable to generate recommendations.'];
        }
    }

    /**
     * Get platform-wide insights (admin view)
     */
    public function getPlatformInsights(): array
    {
        try {
            $stats = [
                'total_users' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users'),
                'active_users' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE is_online = 1'),
                'total_posts' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM posts'),
                'total_hobbies' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM hobbies'),
                'total_friendships' => (int) $this->connection->fetchOne("SELECT COUNT(*) FROM friendships WHERE status = 'ACCEPTED'"),
                'total_classes' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM classes'),
                'total_bookings' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM bookings'),
            ];

            $prompt = "Analyze these platform metrics and provide insights:\n" .
                "Total Users: {$stats['total_users']}\n" .
                "Active Users: {$stats['active_users']}\n" .
                "Posts: {$stats['total_posts']}\n" .
                "Hobbies Tracked: {$stats['total_hobbies']}\n" .
                "Friendships: {$stats['total_friendships']}\n" .
                "Classes: {$stats['total_classes']}\n" .
                "Bookings: {$stats['total_bookings']}\n\n" .
                "Provide 4-5 insights about platform health and growth opportunities.";

            return $this->queryGroq($prompt);
        } catch (\Exception $e) {
            error_log("Groq platform insights error: " . $e->getMessage());
            return ['insights' => 'Unable to generate platform insights.'];
        }
    }

    /**
     * Query Groq API for insights
     */
    private function queryGroq(string $prompt): array
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$this->groqApiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->groqModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful analytics assistant. Provide clear, actionable insights.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                $responseContent = $response->getContent(false);
                error_log("Groq API HTTP {$statusCode}: {$responseContent}");
                
                if ($statusCode >= 500) {
                    return ['insights' => 'Groq API is currently unavailable. Please try again in a few moments.'];
                } elseif ($statusCode === 429) {
                    return ['insights' => 'Rate limit exceeded. Please wait a moment before trying again.'];
                } elseif ($statusCode === 401 || $statusCode === 403) {
                    error_log("Groq API authentication error. Check API key configuration.");
                    return ['insights' => 'Authentication failed. Please verify your API key is valid.'];
                } elseif ($statusCode === 400) {
                    return ['insights' => 'Invalid request format. Please try again.'];
                } else {
                    return ['insights' => 'Failed to generate insights. Please try again.'];
                }
            }

            $data = $response->toArray();

            if (isset($data['choices'][0]['message']['content'])) {
                return ['insights' => $data['choices'][0]['message']['content']];
            }

            error_log("Groq API returned unexpected format: " . json_encode($data));
            return ['insights' => 'Unable to process insights'];
        } catch (\Exception $e) {
            error_log("Groq API error: " . $e->getMessage());
            
            $message = $e->getMessage();
            if (strpos($message, 'Connection timed out') !== false) {
                return ['insights' => 'Connection timed out. Please check your internet connection.'];
            } elseif (strpos($message, 'Could not resolve host') !== false) {
                return ['insights' => 'Unable to reach Groq API. Please check your internet connection.'];
            }
            
            return ['insights' => 'Service temporarily unavailable. Please try again.'];
        }
    }
}
