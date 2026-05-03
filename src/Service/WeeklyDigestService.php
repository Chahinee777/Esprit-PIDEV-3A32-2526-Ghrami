<?php

namespace App\Service;

use App\Entity\DigestLog;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;

final class WeeklyDigestService
{
    private const BATCH_SIZE = 20;
    private array $tableExistsCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WeeklyDigestAiService $weeklyDigestAiService,
        private readonly EmailService $emailService,
        private readonly NotificationService $notificationService,
        private readonly BadgeService $badgeService,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendWeeklyDigests(
        ?callable $output = null,
        ?int $onlyUserId = null,
        bool $dryRun = false
    ): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $periodStart = $now->sub(new \DateInterval('P7D'));
        $activeSince = $now->sub(new \DateInterval('P14D'));
        $weekStart = $this->startOfWeekUtc($now);
        $offset = 0;

        while (true) {
            $users = $this->fetchActiveUsers($activeSince, self::BATCH_SIZE, $offset, $onlyUserId);
            if ($users === []) {
                break;
            }

            foreach ($users as $userRow) {
                $userId = (int) $userRow['user_id'];

                try {
                    if ($this->hasDigestThisWeek($userId, $weekStart)) {
                        $this->write($output, sprintf('Skipping user %d, digest already sent this week.', $userId));
                        continue;
                    }

                    $context = $this->buildContext($userRow, $periodStart, $now);
                    if (!$this->hasActivity($context)) {
                        continue;
                    }

                    $digest = $this->weeklyDigestAiService->generateDigest($context);
                    $user = $this->em->getRepository(User::class)->find($userId);

                    if (!$user instanceof User) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->write($output, sprintf('Dry run for user %d (%s)', $userId, $user->email));
                        $this->write($output, 'Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        $this->write($output, "Digest:\n" . $digest);
                    } else {
                        $html = $this->twig->render('emails/digest_email.html.twig', [
                            'user' => $user,
                            'context' => $context,
                            'digest' => $digest,
                        ]);

                        $emailSent = $this->emailService->sendEmail(
                            (string) $user->email,
                            $this->displayName($user),
                            'Votre digest hebdomadaire Ghrami',
                            $html
                        );

                        if ($emailSent) {
                            $this->persistDigestLog($user, $digest, 'email', $now);
                        }

                        $this->notificationService->create(
                            $userId,
                            'WEEKLY_DIGEST',
                            $this->buildNotificationPreview($digest)
                        );
                        $this->persistDigestLog($user, $digest, 'inapp', $now);

                        $this->write($output, sprintf('Processed weekly digest for user %d.', $userId));
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('Weekly digest failed for user.', [
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                    $this->write($output, sprintf('Failed for user %d: %s', $userId, $e->getMessage()));
                    $this->em->clear();
                }
            }

            $offset += self::BATCH_SIZE;
            if (count($users) === self::BATCH_SIZE) {
                sleep(1);
            }
        }
    }

    private function fetchActiveUsers(
        \DateTimeImmutable $activeSince,
        int $limit,
        int $offset,
        ?int $onlyUserId = null
    ): array
    {
        $sql = 'SELECT user_id, username, full_name, email, last_login
             FROM users
             WHERE digest_opted_in = 1
               AND is_banned = 0
               AND last_login IS NOT NULL
               AND last_login >= :activeSince';
        $params = [
            'activeSince' => $activeSince->format('Y-m-d H:i:s'),
            'lim' => $limit,
            'off' => $offset,
        ];
        $types = [
            'lim' => ParameterType::INTEGER,
            'off' => ParameterType::INTEGER,
        ];

        if ($onlyUserId !== null) {
            $sql .= ' AND user_id = :userId';
            $params['userId'] = $onlyUserId;
            $types['userId'] = ParameterType::INTEGER;
        }

        $sql .= '
             ORDER BY user_id ASC
             LIMIT :lim OFFSET :off';

        return $this->connection()->fetchAllAssociative($sql, $params, $types);
    }

    private function hasDigestThisWeek(int $userId, \DateTimeImmutable $weekStart): bool
    {
        $count = $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM digest_logs WHERE user_id = :userId AND sent_at >= :weekStart',
            [
                'userId' => $userId,
                'weekStart' => $weekStart->format('Y-m-d H:i:s'),
            ]
        );

        return (int) $count > 0;
    }

    private function buildContext(array $userRow, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        $userId = (int) $userRow['user_id'];
        $postStats = $this->collectPostStats($userId, $periodStart, $periodEnd);
        $hobbyStats = $this->collectHobbyStats($userId, $periodStart, $periodEnd);
        $classStats = $this->collectClassStats($userId, $periodStart, $periodEnd);
        $meetingStats = $this->collectMeetingStats($userId, $periodStart, $periodEnd);
        $badgeStats = $this->collectBadgeStats($userId, $periodStart, $periodEnd);

        return [
            'user_name' => (string) ($userRow['full_name'] ?: $userRow['username']),
            'posts' => $postStats,
            'hobbies' => $hobbyStats,
            'classes' => $classStats,
            'meetings' => $meetingStats,
            'badges' => $badgeStats,
            'streak_days' => $this->collectStreakDays($userId, $periodStart, $periodEnd),
        ];
    }

    private function collectPostStats(int $userId, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        $published = (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM posts
             WHERE user_id = :userId
               AND created_at BETWEEN :start AND :end',
            [
                'userId' => $userId,
                'start' => $periodStart->format('Y-m-d H:i:s'),
                'end' => $periodEnd->format('Y-m-d H:i:s'),
            ]
        );

        $topPost = $this->connection()->fetchAssociative(
            'SELECT p.content,
                    COUNT(pl.post_id) AS like_count
             FROM posts p
             LEFT JOIN post_likes pl ON pl.post_id = p.post_id
             WHERE p.user_id = :userId
               AND p.created_at BETWEEN :start AND :end
             GROUP BY p.post_id, p.content, p.created_at
             ORDER BY like_count DESC, p.created_at DESC
             LIMIT 1',
            [
                'userId' => $userId,
                'start' => $periodStart->format('Y-m-d H:i:s'),
                'end' => $periodEnd->format('Y-m-d H:i:s'),
            ]
        );

        return [
            'published' => $published,
            'top_post_title' => $this->truncateTitle((string) ($topPost['content'] ?? '')),
        ];
    }

    private function collectHobbyStats(int $userId, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        if (!$this->tableExists('progress_log')) {
            return $this->collectHobbyStatsFromProgressSnapshot($userId);
        }

        $stats = $this->connection()->fetchAssociative(
            'SELECT COUNT(*) AS sessions,
                    COALESCE(SUM(pl.hours_spent), 0) AS total_hours
             FROM progress_log pl
             INNER JOIN hobbies h ON h.hobby_id = pl.hobby_id
             WHERE h.user_id = :userId
               AND pl.log_date BETWEEN :startDate AND :endDate',
            [
                'userId' => $userId,
                'startDate' => $periodStart->format('Y-m-d'),
                'endDate' => $periodEnd->format('Y-m-d'),
            ]
        );

        $topHobby = $this->connection()->fetchAssociative(
            'SELECT h.name, COALESCE(SUM(pl.hours_spent), 0) AS total_hours
             FROM progress_log pl
             INNER JOIN hobbies h ON h.hobby_id = pl.hobby_id
             WHERE h.user_id = :userId
               AND pl.log_date BETWEEN :startDate AND :endDate
             GROUP BY h.hobby_id, h.name
             ORDER BY total_hours DESC, h.name ASC
             LIMIT 1',
            [
                'userId' => $userId,
                'startDate' => $periodStart->format('Y-m-d'),
                'endDate' => $periodEnd->format('Y-m-d'),
            ]
        );

        $minutes = (int) round(((float) ($stats['total_hours'] ?? 0)) * 60);

        return [
            'sessions' => (int) ($stats['sessions'] ?? 0),
            'top_hobby' => (string) ($topHobby['name'] ?? ''),
            'total_minutes' => $minutes,
        ];
    }

    private function collectHobbyStatsFromProgressSnapshot(int $userId): array
    {
        $stats = $this->connection()->fetchAssociative(
            'SELECT COUNT(*) AS sessions,
                    COALESCE(SUM(p.hours_spent), 0) AS total_hours
             FROM progress p
             INNER JOIN hobbies h ON h.hobby_id = p.hobby_id
             WHERE h.user_id = :userId',
            ['userId' => $userId]
        );

        $topHobby = $this->connection()->fetchAssociative(
            'SELECT h.name, COALESCE(SUM(p.hours_spent), 0) AS total_hours
             FROM progress p
             INNER JOIN hobbies h ON h.hobby_id = p.hobby_id
             WHERE h.user_id = :userId
             GROUP BY h.hobby_id, h.name
             ORDER BY total_hours DESC, h.name ASC
             LIMIT 1',
            ['userId' => $userId]
        );

        return [
            'sessions' => (int) ($stats['sessions'] ?? 0),
            'top_hobby' => (string) ($topHobby['name'] ?? ''),
            'total_minutes' => (int) round(((float) ($stats['total_hours'] ?? 0)) * 60),
        ];
    }

    private function collectClassStats(int $userId, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        $completed = (int) $this->connection()->fetchOne(
            'SELECT COUNT(*)
             FROM bookings b
             WHERE b.user_id = :userId
               AND LOWER(COALESCE(b.status, \'\')) = :completed
               AND b.booking_date BETWEEN :start AND :end',
            [
                'userId' => $userId,
                'completed' => 'completed',
                'start' => $periodStart->format('Y-m-d H:i:s'),
                'end' => $periodEnd->format('Y-m-d H:i:s'),
            ]
        );

        $inProgress = $this->connection()->fetchOne(
            'SELECT c.title
             FROM bookings b
             INNER JOIN classes c ON c.class_id = b.class_id
             WHERE b.user_id = :userId
               AND b.booking_date BETWEEN :start AND :end
               AND (
                    COALESCE(b.watch_progress, 0) BETWEEN 1 AND 99
                    OR LOWER(COALESCE(b.status, \'\')) IN (:pending, :scheduled)
               )
             ORDER BY COALESCE(b.watch_progress, 0) DESC, b.booking_date DESC
             LIMIT 1',
            [
                'userId' => $userId,
                'start' => $periodStart->format('Y-m-d H:i:s'),
                'end' => $periodEnd->format('Y-m-d H:i:s'),
                'pending' => 'pending',
                'scheduled' => 'scheduled',
            ],
            [
                'pending' => ParameterType::STRING,
                'scheduled' => ParameterType::STRING,
            ]
        );

        return [
            'completed' => $completed,
            'in_progress' => (string) ($inProgress ?: ''),
        ];
    }

    private function collectMeetingStats(int $userId, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        $count = (int) $this->connection()->fetchOne(
            'SELECT COUNT(DISTINCT m.meeting_id)
             FROM meetings m
             LEFT JOIN meeting_participants mp ON mp.meeting_id = m.meeting_id
             WHERE m.scheduled_at BETWEEN :start AND :end
               AND (
                    m.organizer_id = :userId
                    OR (mp.user_id = :userId AND mp.is_active = 1)
               )',
            [
                'userId' => $userId,
                'start' => $periodStart->format('Y-m-d H:i:s'),
                'end' => $periodEnd->format('Y-m-d H:i:s'),
            ]
        );

        return [
            'count' => $count,
            'action_items_open' => 0,
        ];
    }

    private function collectBadgeStats(int $userId, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT name
             FROM badges
             WHERE user_id = :userId
               AND earned_date BETWEEN :start AND :end
             ORDER BY earned_date DESC',
            [
                'userId' => $userId,
                'start' => $periodStart->format('Y-m-d H:i:s'),
                'end' => $periodEnd->format('Y-m-d H:i:s'),
            ]
        );

        $newBadges = array_map(static fn(array $row): string => (string) $row['name'], $rows);
        $nextBadge = $this->determineNextBadgeGoal($userId);

        return [
            'new' => $newBadges,
            'next_badge' => $nextBadge['name'],
            'points_missing' => $nextBadge['points_missing'],
        ];
    }

    private function determineNextBadgeGoal(int $userId): array
    {
        $earnedNames = array_map(
            static fn(array $badge): string => (string) ($badge['name'] ?? ''),
            $this->badgeService->getUserBadges($userId)
        );

        $friendCount = (int) $this->connection()->fetchOne(
            "SELECT COUNT(*) FROM friendships WHERE (user1_id = :uid OR user2_id = :uid) AND UPPER(COALESCE(status, '')) = 'ACCEPTED'",
            ['uid' => $userId]
        );
        $postCount = (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM posts WHERE user_id = :uid',
            ['uid' => $userId]
        );
        $completedClassCount = (int) $this->connection()->fetchOne(
            "SELECT COUNT(*) FROM bookings WHERE user_id = :uid AND LOWER(COALESCE(status, '')) = 'completed'",
            ['uid' => $userId]
        );
        $meetingCount = (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM meeting_participants WHERE user_id = :uid',
            ['uid' => $userId]
        );
        $hobbyCount = (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM hobbies WHERE user_id = :uid',
            ['uid' => $userId]
        );
        $totalHours = (float) $this->connection()->fetchOne(
            $this->tableExists('progress_log')
                ? 'SELECT COALESCE(SUM(hours_spent), 0) FROM progress_log pl INNER JOIN hobbies h ON h.hobby_id = pl.hobby_id WHERE h.user_id = :uid'
                : 'SELECT COALESCE(SUM(hours_spent), 0) FROM progress p INNER JOIN hobbies h ON h.hobby_id = p.hobby_id WHERE h.user_id = :uid',
            ['uid' => $userId]
        );

        $goals = [
            ['name' => 'First Step 🎬', 'current' => $postCount, 'target' => 1],
            ['name' => 'Friendmaker 👥', 'current' => $friendCount, 'target' => 10],
            ['name' => 'Hobby Enthusiast 🎯', 'current' => $hobbyCount, 'target' => 5],
            ['name' => 'Student 📚', 'current' => $completedClassCount, 'target' => 1],
            ['name' => 'Class Attendee 🎓', 'current' => $completedClassCount, 'target' => 5],
            ['name' => 'Connector 🤝', 'current' => $meetingCount, 'target' => 1],
            ['name' => 'Dedicated 💪', 'current' => (int) floor($totalHours), 'target' => 100],
        ];

        foreach ($goals as $goal) {
            if (in_array($goal['name'], $earnedNames, true)) {
                continue;
            }

            return [
                'name' => $goal['name'],
                'points_missing' => max(0, $goal['target'] - $goal['current']),
            ];
        }

        return [
            'name' => 'Nouveau défi mystère',
            'points_missing' => 0,
        ];
    }

    private function collectStreakDays(int $userId, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): int
    {
        $progressSegment = $this->tableExists('progress_log')
            ? 'UNION
                    SELECT DATE(log_date) AS active_day
                    FROM progress_log pl
                    INNER JOIN hobbies h ON h.hobby_id = pl.hobby_id
                    WHERE h.user_id = :userId
                      AND pl.log_date BETWEEN :startDate AND :endDate'
            : '';

        $days = $this->connection()->fetchFirstColumn(
            'SELECT active_day
               FROM (
                    SELECT DATE(created_at) AS active_day
                FROM posts
               WHERE user_id = :userId
                 AND created_at BETWEEN :start AND :end
                    ' . $progressSegment . '
                    UNION
                    SELECT DATE(booking_date) AS active_day
                FROM bookings
               WHERE user_id = :userId
                 AND booking_date BETWEEN :start AND :end
                    UNION
                    SELECT DATE(scheduled_at) AS active_day
                FROM meetings
               WHERE organizer_id = :userId
                 AND scheduled_at BETWEEN :start AND :end
               ) activity_days
              ORDER BY active_day DESC',
            [
                'userId' => $userId,
                'start' => $periodStart->format('Y-m-d H:i:s'),
                'end' => $periodEnd->format('Y-m-d H:i:s'),
                'startDate' => $periodStart->format('Y-m-d'),
                'endDate' => $periodEnd->format('Y-m-d'),
            ]
        );

        $activeDays = array_values(array_filter($days, static fn($day): bool => is_string($day) && $day !== ''));
        if ($activeDays === []) {
            return 0;
        }

        $streak = 1;
        $cursor = new \DateTimeImmutable($activeDays[0], new \DateTimeZone('UTC'));

        foreach (array_slice($activeDays, 1) as $day) {
            $activeDay = new \DateTimeImmutable($day, new \DateTimeZone('UTC'));
            $expectedPreviousDay = $cursor->sub(new \DateInterval('P1D'));

            if ($activeDay->format('Y-m-d') !== $expectedPreviousDay->format('Y-m-d')) {
                break;
            }

            ++$streak;
            $cursor = $activeDay;
        }

        return $streak;
    }

    private function tableExists(string $tableName): bool
    {
        if (array_key_exists($tableName, $this->tableExistsCache)) {
            return $this->tableExistsCache[$tableName];
        }

        $exists = (bool) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tableName',
            ['tableName' => $tableName]
        );

        $this->tableExistsCache[$tableName] = $exists;

        return $exists;
    }

    private function hasActivity(array $context): bool
    {
        return (int) ($context['posts']['published'] ?? 0) > 0
            || (int) ($context['hobbies']['sessions'] ?? 0) > 0
            || (int) ($context['hobbies']['total_minutes'] ?? 0) > 0
            || (int) ($context['classes']['completed'] ?? 0) > 0
            || (string) ($context['classes']['in_progress'] ?? '') !== ''
            || (int) ($context['meetings']['count'] ?? 0) > 0
            || count($context['badges']['new'] ?? []) > 0
            || (int) ($context['streak_days'] ?? 0) > 0;
    }

    private function buildNotificationPreview(string $digest): string
    {
        $singleLine = trim(preg_replace('/\s+/', ' ', $digest) ?? '');

        return mb_strlen($singleLine) > 220
            ? mb_substr($singleLine, 0, 217) . '...'
            : $singleLine;
    }

    private function persistDigestLog(User $user, string $digest, string $channel, \DateTimeImmutable $sentAt): void
    {
        $log = new DigestLog();
        $log->user = $user;
        $log->content = $digest;
        $log->channel = $channel;
        $log->sentAt = \DateTime::createFromImmutable($sentAt);
        $log->opened = false;

        $this->em->persist($log);
        $this->em->flush();
    }

    private function truncateTitle(string $content): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $content) ?? '');

        if ($normalized === '') {
            return '';
        }

        return mb_strlen($normalized) > 70
            ? mb_substr($normalized, 0, 67) . '...'
            : $normalized;
    }

    private function displayName(User $user): string
    {
        return $user->fullName !== '' ? $user->fullName : $user->username;
    }

    private function startOfWeekUtc(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->modify('monday this week')->setTime(0, 0);
    }

    private function write(?callable $output, string $message): void
    {
        if ($output !== null) {
            $output($message);
        }
    }

    private function connection(): Connection
    {
        return $this->em->getConnection();
    }
}
