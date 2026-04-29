<?php

namespace App\Service;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AI-Powered Notification Service using Groq.
 * 
 * Intelligently scores, filters, and composes notifications into smart digests.
 * Works with existing Notification entity — NO database changes required.
 */
final class AiNotificationService
{
    private const GROQ_MODEL = 'llama-3.1-8b-instant';
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey,
    ) {}

    /**
     * Build a smart AI digest from pending notifications.
     * 
     * @return array|null Digest data or null if no notifications
     */
    public function buildSmartDigest(int $userId, int $maxNotifications = 3): ?array
    {
        try {
            // 1. Fetch pending notifications
            $pending = $this->getPendingNotifications($userId);
            if (empty($pending)) {
                return null;
            }

            // 2. Score each by urgency
            $scored = [];
            foreach ($pending as $notif) {
                $score = $this->scoreNotification($notif);
                $scored[] = ['notification' => $notif, 'score' => $score];
            }

            // 3. Sort & take top N
            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
            $topNotifs = array_slice($scored, 0, $maxNotifications);

            // 4. Compose digest
            $digest = $this->composeDigest($topNotifs);

            // 5. Extract actions
            $actions = $this->extractActions($topNotifs);

            // 6. Calculate priority
            $avgScore = array_sum(array_column($topNotifs, 'score')) / count($topNotifs);
            $priority = match (true) {
                $avgScore >= 80 => 'critical',
                $avgScore >= 60 => 'high',
                $avgScore >= 40 => 'medium',
                default => 'low',
            };

            return [
                'digest' => $digest,
                'notifications' => array_map(fn($t) => $t['notification'], $topNotifs),
                'actions' => $actions,
                'priority' => $priority,
                'count_total' => count($pending),
                'count_condensed' => count($topNotifs),
            ];
        } catch (\Exception $e) {
            error_log('AiNotificationService error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get pending unread notifications (existing entity, no changes).
     */
    private function getPendingNotifications(int $userId): array
    {
        return $this->em->getRepository(Notification::class)->findBy(
            ['user_id' => $userId, 'is_read' => false],
            ['created_at' => 'DESC'],
            20
        );
    }

    /**
     * Score notification urgency using Groq (0-100).
     */
    private function scoreNotification(Notification $notif): int
    {
        if (empty($this->groqApiKey)) {
            return $this->fallbackScore($notif);
        }

        try {
            $prompt = sprintf(
                "Rate urgency 0-100 (ONLY NUMBER):\n" .
                "Type: %s | Content: %s\n" .
                "Factors: time-sensitive? money? friend? achievement?",
                $notif->type,
                substr($notif->content, 0, 80)
            );

            $response = $this->queryGroq($prompt, 0.2, 20);
            $score = (int) preg_match('/(\d+)/', $response, $m) ? $m[1] : 50;
            return max(0, min(100, $score));
        } catch (\Exception $e) {
            return $this->fallbackScore($notif);
        }
    }

    /**
     * Fallback scoring (no Groq).
     */
    private function fallbackScore(Notification $notif): int
    {
        return match ($notif->type) {
            'booking' => 90,
            'message' => 70,
            'connection_request' => 60,
            'achievement' => 50,
            'like' => 30,
            default => 40,
        };
    }

    /**
     * Compose smart digest using Groq.
     */
    private function composeDigest(array $topNotifs): string
    {
        if (empty($this->groqApiKey) || empty($topNotifs)) {
            return $this->fallbackDigest($topNotifs);
        }

        try {
            $notifList = implode("\n", array_map(
                fn($tn) => "• {$tn['notification']->type}: {$tn['notification']->content}",
                $topNotifs
            ));

            $prompt = "Write 1-2 sentence notification digest (max 50 words, friendly, action-focused):\n$notifList";

            return $this->queryGroq($prompt, 0.6, 100);
        } catch (\Exception $e) {
            return $this->fallbackDigest($topNotifs);
        }
    }

    /**
     * Fallback digest (no Groq).
     */
    private function fallbackDigest(array $topNotifs): string
    {
        $count = count($topNotifs);
        return $count === 1 
            ? "You have 1 new notification." 
            : "You have {$count} new notifications.";
    }

    /**
     * Extract actionable items.
     */
    private function extractActions(array $topNotifs): array
    {
        $actions = [];
        foreach ($topNotifs as $tn) {
            $action = $this->mapToAction($tn['notification']->type);
            if ($action) {
                $actions[] = $action;
            }
        }
        return $actions;
    }

    /**
     * Map notification type to action button.
     */
    private function mapToAction(string $type): ?array
    {
        return match ($type) {
            'message' => ['label' => '💬 Reply', 'url' => '/messages'],
            'booking' => ['label' => '✓ Confirm', 'url' => '/bookings'],
            'connection_request' => ['label' => '👥 Accept', 'url' => '/connections'],
            'achievement' => ['label' => '🏆 View', 'url' => '/hobbies'],
            'class_reminder' => ['label' => '📚 Join', 'url' => '/classes'],
            default => null,
        };
    }

    /**
     * Query Groq API.
     */
    private function queryGroq(string $prompt, float $temperature = 0.5, int $maxTokens = 150): string
    {
        $response = $this->httpClient->request('POST', self::GROQ_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => self::GROQ_MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => 'Brief, concise responses only.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ],
            'timeout' => 8,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Groq API failed');
        }

        $data = $response->toArray();
        return trim($data['choices'][0]['message']['content'] ?? '');
    }
}
