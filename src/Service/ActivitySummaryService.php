<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ActivitySummaryService
{
    private const GROQ_MODEL = 'llama-3.1-8b-instant';
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationService $notificationService,
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey,
    ) {
    }

    public function getWeeklySummaryData(int $userId): ?array
    {
        $weekStart = $this->currentWeekStartUtc();
        $context = $this->buildUserActivityContext($userId, $weekStart);
        if (!$this->hasActivity($context)) {
            return null;
        }

        return [
            'content' => $this->generateSummary($context),
            'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'week_start' => $weekStart->format('Y-m-d'),
            'context' => $context,
        ];
    }

    private function buildUserActivityContext(int $userId, \DateTimeImmutable $since): array
    {
        $until = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $user = $this->connection()->fetchAssociative(
            'SELECT username, full_name FROM users WHERE user_id = :uid',
            ['uid' => $userId]
        ) ?: [];

        return [
            'user_name' => (string) (($user['full_name'] ?? '') !== '' ? $user['full_name'] : ($user['username'] ?? 'Utilisateur')),
            'posts' => [
                'published' => (int) $this->connection()->fetchOne(
                    'SELECT COUNT(*) FROM posts WHERE user_id = :uid AND created_at BETWEEN :since AND :until',
                    ['uid' => $userId, 'since' => $since->format('Y-m-d H:i:s'), 'until' => $until->format('Y-m-d H:i:s')]
                ),
                'latest' => $this->connection()->fetchFirstColumn(
                    'SELECT content FROM posts WHERE user_id = :uid AND created_at BETWEEN :since AND :until ORDER BY created_at DESC LIMIT 3',
                    ['uid' => $userId, 'since' => $since->format('Y-m-d H:i:s'), 'until' => $until->format('Y-m-d H:i:s')]
                ),
            ],
            'comments' => [
                'count' => (int) $this->connection()->fetchOne(
                    'SELECT COUNT(*) FROM comments WHERE user_id = :uid AND created_at BETWEEN :since AND :until',
                    ['uid' => $userId, 'since' => $since->format('Y-m-d H:i:s'), 'until' => $until->format('Y-m-d H:i:s')]
                ),
            ],
            'hobbies' => [
                'count_current' => (int) $this->connection()->fetchOne(
                    'SELECT COUNT(*) FROM hobbies WHERE user_id = :uid',
                    ['uid' => $userId]
                ),
                'latest_names' => $this->connection()->fetchFirstColumn(
                    'SELECT name FROM hobbies WHERE user_id = :uid ORDER BY hobby_id DESC LIMIT 5',
                    ['uid' => $userId]
                ),
            ],
            'classes' => [
                'joined' => (int) $this->connection()->fetchOne(
                    'SELECT COUNT(*) FROM bookings WHERE user_id = :uid AND booking_date BETWEEN :since AND :until',
                    ['uid' => $userId, 'since' => $since->format('Y-m-d H:i:s'), 'until' => $until->format('Y-m-d H:i:s')]
                ),
                'completed' => (int) $this->connection()->fetchOne(
                    "SELECT COUNT(*) FROM bookings WHERE user_id = :uid AND LOWER(COALESCE(status, '')) = 'completed' AND booking_date BETWEEN :since AND :until",
                    ['uid' => $userId, 'since' => $since->format('Y-m-d H:i:s'), 'until' => $until->format('Y-m-d H:i:s')]
                ),
                'joined_titles' => $this->connection()->fetchFirstColumn(
                    'SELECT c.title
                     FROM bookings b
                     INNER JOIN classes c ON c.class_id = b.class_id
                     WHERE b.user_id = :uid AND b.booking_date BETWEEN :since AND :until
                     ORDER BY b.booking_date DESC LIMIT 5',
                    ['uid' => $userId, 'since' => $since->format('Y-m-d H:i:s'), 'until' => $until->format('Y-m-d H:i:s')]
                ),
                'created_titles' => $this->connection()->fetchFirstColumn(
                    'SELECT c.title
                     FROM classes c
                     INNER JOIN class_providers cp ON cp.provider_id = c.provider_id
                     WHERE cp.user_id = :uid
                     ORDER BY c.class_id DESC LIMIT 5',
                    ['uid' => $userId]
                ),
            ],
            'meetings' => [
                'created' => (int) $this->connection()->fetchOne(
                    'SELECT COUNT(*) FROM meetings WHERE organizer_id = :uid AND scheduled_at BETWEEN :since AND :until',
                    ['uid' => $userId, 'since' => $since->format('Y-m-d H:i:s'), 'until' => $until->format('Y-m-d H:i:s')]
                ),
            ],
            'badges' => [
                'new' => $this->connection()->fetchFirstColumn(
                    'SELECT name FROM badges WHERE user_id = :uid AND earned_date BETWEEN :since AND :until ORDER BY earned_date DESC LIMIT 5',
                    ['uid' => $userId, 'since' => $since->format('Y-m-d H:i:s'), 'until' => $until->format('Y-m-d H:i:s')]
                ),
            ],
            'streak_days' => $this->computeStreakDays($userId, $since, $until),
        ];
    }

    private function generateSummary(array $context): string
    {
        if ($this->groqApiKey === '') {
            return $this->fallbackSummary($context);
        }

        $contextJson = (string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $prompt = <<<PROMPT
Tu résumes l'activité hebdomadaire d'un utilisateur dans une application sociale.

Données:
{$contextJson}

Réponds exactement avec ces sections en français:
RESUME: ...
POSTS: ...
CLASSES: ...
HOBBIES: ...
MEETINGS: ...
COMMENTS: ...
BADGES: ...

Pas de markdown. Pas de puces. Ton chaleureux et précis.
PROMPT;

        try {
            $response = $this->httpClient->request('POST', self::GROQ_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::GROQ_MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu es un coach personnel chaleureux. Réponse brève et structurée.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 260,
                ],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Groq API failed');
            }

            $data = $response->toArray(false);
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

            return $content !== '' ? $content : $this->fallbackSummary($context);
        } catch (\Throwable) {
            return $this->fallbackSummary($context);
        }
    }

    private function fallbackSummary(array $context): string
    {
        $postTitles = array_values(array_filter(array_map(fn($v) => $this->truncate((string) $v), $context['posts']['latest'] ?? [])));
        $hobbies = array_values(array_filter(array_map('strval', $context['hobbies']['latest_names'] ?? [])));
        $joinedClasses = array_values(array_filter(array_map('strval', $context['classes']['joined_titles'] ?? [])));
        $createdClasses = array_values(array_filter(array_map('strval', $context['classes']['created_titles'] ?? [])));
        $badges = array_values(array_filter(array_map('strval', $context['badges']['new'] ?? [])));

        return implode("\n", [
            sprintf('RESUME: %s, cette semaine tu as gardé une belle présence sur Ghrami. Ton activité montre de la régularité et une vraie progression.', $context['user_name']),
            sprintf('POSTS: Tu as ajouté %d post(s)%s.', (int) ($context['posts']['published'] ?? 0), $postTitles ? ' comme ' . implode(', ', array_slice($postTitles, 0, 2)) : ''),
            sprintf('CLASSES: Tu as rejoint %d classe(s) et terminé %d classe(s)%s%s.', (int) ($context['classes']['joined'] ?? 0), (int) ($context['classes']['completed'] ?? 0), $joinedClasses ? ' ; classes rejointes : ' . implode(', ', array_slice($joinedClasses, 0, 2)) : '', $createdClasses ? ' ; classes que tu proposes : ' . implode(', ', array_slice($createdClasses, 0, 2)) : ''),
            sprintf('HOBBIES: Tu as actuellement %d hobby(s)%s.', (int) ($context['hobbies']['count_current'] ?? 0), $hobbies ? ' comme ' . implode(', ', array_slice($hobbies, 0, 3)) : ''),
            sprintf('MEETINGS: Tu as ajouté %d meeting(s) cette semaine.', (int) ($context['meetings']['created'] ?? 0)),
            sprintf('COMMENTS: Tu as publié %d commentaire(s).', (int) ($context['comments']['count'] ?? 0)),
            sprintf('BADGES: %s', $badges ? 'Nouveaux badges débloqués : ' . implode(', ', $badges) . '.' : 'Pas de nouveau badge cette semaine, mais ta progression continue.'),
        ]);
    }

    private function hasActivity(array $context): bool
    {
        return (int) ($context['posts']['published'] ?? 0) > 0
            || (int) ($context['comments']['count'] ?? 0) > 0
            || (int) ($context['classes']['joined'] ?? 0) > 0
            || (int) ($context['classes']['completed'] ?? 0) > 0
            || (int) ($context['meetings']['created'] ?? 0) > 0
            || !empty($context['badges']['new'] ?? []);
    }

    private function computeStreakDays(int $userId, \DateTimeImmutable $since, \DateTimeImmutable $until): int
    {
        $days = $this->connection()->fetchFirstColumn(
            'SELECT active_day
             FROM (
                 SELECT DATE(created_at) AS active_day FROM posts WHERE user_id = :uid AND created_at BETWEEN :since AND :until
                 UNION
                 SELECT DATE(created_at) AS active_day FROM comments WHERE user_id = :uid AND created_at BETWEEN :since AND :until
                 UNION
                 SELECT DATE(booking_date) AS active_day FROM bookings WHERE user_id = :uid AND booking_date BETWEEN :since AND :until
                 UNION
                 SELECT DATE(scheduled_at) AS active_day FROM meetings WHERE organizer_id = :uid AND scheduled_at BETWEEN :since AND :until
                 UNION
                 SELECT DATE(earned_date) AS active_day FROM badges WHERE user_id = :uid AND earned_date BETWEEN :since AND :until
             ) activity_days
             ORDER BY active_day DESC',
            [
                'uid' => $userId,
                'since' => $since->format('Y-m-d H:i:s'),
                'until' => $until->format('Y-m-d H:i:s'),
            ]
        );

        $activeDays = array_values(array_filter($days, static fn($day): bool => is_string($day) && $day !== ''));
        if ($activeDays === []) {
            return 0;
        }

        $streak = 1;
        $cursor = new \DateTimeImmutable($activeDays[0], new \DateTimeZone('UTC'));

        foreach (array_slice($activeDays, 1) as $day) {
            $activeDay = new \DateTimeImmutable((string) $day, new \DateTimeZone('UTC'));
            if ($activeDay->format('Y-m-d') !== $cursor->sub(new \DateInterval('P1D'))->format('Y-m-d')) {
                break;
            }
            ++$streak;
            $cursor = $activeDay;
        }

        return $streak;
    }

    private function currentWeekStartUtc(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('monday this week')->setTime(0, 0);
    }

    private function truncate(string $text): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        return mb_strlen($normalized) > 60 ? mb_substr($normalized, 0, 57) . '...' : $normalized;
    }

    private function connection(): Connection
    {
        return $this->em->getConnection();
    }
}
