<?php

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Hobby;
use App\Entity\Milestone;
use App\Entity\Progress;
use App\Entity\ProgressLog;
use App\Entity\User;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

class ProgressService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function listHobbies(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            'SELECT h.*, p.progress_id, p.hours_spent, p.notes AS progress_notes
             FROM hobbies h
             LEFT JOIN progress p ON p.hobby_id = h.hobby_id
             WHERE h.user_id = :uid
             ORDER BY h.hobby_id DESC',
            ['uid' => $userId]
        );
    }

    public function listHobbiesDetailed(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT h.hobby_id, h.name, h.category, h.description,
                    COALESCE(SUM(pl.hours_spent), 0) AS total_hours,
                    COUNT(pl.log_id) AS session_count,
                    COALESCE(ROUND((SUM(CASE WHEN m.is_achieved = 1 THEN 1 ELSE 0 END) * 100.0)
                        / NULLIF(COUNT(m.milestone_id), 0), 0), 0) AS progress
             FROM hobbies h
             LEFT JOIN progress_log pl ON pl.hobby_id = h.hobby_id
             LEFT JOIN milestones m ON m.hobby_id = h.hobby_id
             WHERE h.user_id = :uid
             GROUP BY h.hobby_id, h.name, h.category, h.description
             ORDER BY h.hobby_id DESC",
            ['uid' => $userId]
        );
    }

    public function getNextMilestone(int $hobbyId): ?array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            "SELECT milestone_id, title, target_date
             FROM milestones
             WHERE hobby_id = :hid AND is_achieved = 0
             ORDER BY target_date ASC, milestone_id ASC
             LIMIT 1",
            ['hid' => $hobbyId]
        );

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['milestone_id'],
            'name' => (string) $row['title'],
            'target_date' => $row['target_date'],
            'progress' => 0,
        ];
    }

    public function getStats(int $userId): array
    {
        $conn = $this->em->getConnection();

        return [
            'hobbies' => (int) $conn->fetchOne('SELECT COUNT(*) FROM hobbies WHERE user_id = :uid', ['uid' => $userId]),
            'hours' => (float) $conn->fetchOne(
                'SELECT COALESCE(SUM(p.hours_spent), 0)
                 FROM progress p
                 JOIN hobbies h ON h.hobby_id = p.hobby_id
                 WHERE h.user_id = :uid',
                ['uid' => $userId]
            ),
            'milestones_total' => (int) $conn->fetchOne(
                'SELECT COUNT(*)
                 FROM milestones m
                 JOIN hobbies h ON h.hobby_id = m.hobby_id
                 WHERE h.user_id = :uid',
                ['uid' => $userId]
            ),
            'milestones_done' => (int) $conn->fetchOne(
                'SELECT COUNT(*)
                 FROM milestones m
                 JOIN hobbies h ON h.hobby_id = m.hobby_id
                 WHERE h.user_id = :uid AND m.is_achieved = 1',
                ['uid' => $userId]
            ),
            'badges' => (int) $conn->fetchOne('SELECT COUNT(*) FROM badges WHERE user_id = :uid', ['uid' => $userId]),
        ];
    }

    public function listProgressLogs(int $userId, int $limit = 100): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            'SELECT pl.log_id AS progress_log_id, pl.hobby_id, h.name AS hobby_name, pl.hours_spent, pl.notes, pl.log_date
             FROM progress_log pl
             JOIN hobbies h ON h.hobby_id = pl.hobby_id
             WHERE h.user_id = :uid
             ORDER BY pl.log_date DESC, pl.log_id DESC
             LIMIT :lim',
            ['uid' => $userId, 'lim' => $limit],
            ['lim' => ParameterType::INTEGER]
        );
    }

    public function listMilestones(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            'SELECT m.milestone_id, m.hobby_id, h.name AS hobby_name, m.title, m.target_date, m.is_achieved
             FROM milestones m
             JOIN hobbies h ON h.hobby_id = m.hobby_id
             WHERE h.user_id = :uid
             ORDER BY m.target_date ASC, m.milestone_id DESC',
            ['uid' => $userId]
        );
    }

    public function listBadges(int $userId): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            'SELECT badge_id, name, description, earned_date
             FROM badges
             WHERE user_id = :uid
             ORDER BY earned_date DESC, badge_id DESC',
            ['uid' => $userId]
        );
    }

    public function addHobby(int $userId, string $name, ?string $category, ?string $description): Hobby
    {
        $hobby = new Hobby();
        $hobby->user = $this->em->getRepository(User::class)->find($userId);
        $hobby->name = $name;
        $hobby->category = $category;
        $hobby->description = $description;
        $this->em->persist($hobby);
        $this->em->flush();

        $progress = new Progress();
        $progress->hobby = $hobby;
        $progress->hoursSpent = 0;
        $progress->notes = 'Started tracking';
        $this->em->persist($progress);
        $this->em->flush();

        return $hobby;
    }

    public function logProgress(int $hobbyId, float $hours, ?string $notes, ?string $logDate = null): void
    {
        $hobby = $this->em->getRepository(Hobby::class)->find($hobbyId);

        $progress = $this->em->getRepository(Progress::class)->findOneBy(['hobby' => $hobby]);
        if (!$progress) {
            $progress = new Progress();
            $progress->hobby = $hobby;
            $progress->hoursSpent = 0;
        }
        $progress->hoursSpent = (float)($progress->hoursSpent ?? 0) + $hours;
        $progress->notes = $notes;
        $this->em->persist($progress);

        $log = new ProgressLog();
        $log->hobby = $hobby;
        $log->hoursSpent = $hours;
        $log->notes = $notes;
        $log->logDate = $logDate ? new \DateTime($logDate) : new \DateTime();
        $this->em->persist($log);

        $this->em->flush();
    }

    public function addMilestone(int $hobbyId, string $title, ?string $targetDate): Milestone
    {
        $milestone = new Milestone();
        $milestone->hobby = $this->em->getRepository(Hobby::class)->find($hobbyId);
        $milestone->title = $title;
        $milestone->targetDate = $targetDate ? new \DateTime($targetDate) : null;
        $milestone->isAchieved = false;
        $this->em->persist($milestone);
        $this->em->flush();

        return $milestone;
    }

    public function toggleMilestone(int $milestoneId): void
    {
        /** @var Milestone|null $milestone */
        $milestone = $this->em->getRepository(Milestone::class)->find($milestoneId);
        if (!$milestone) {
            return;
        }
        $milestone->isAchieved = !$milestone->isAchieved;
        $this->em->flush();
    }

    public function awardBadge(int $userId, string $name, ?string $description): Badge
    {
        $badge = new Badge();
        $badge->user = $this->em->getRepository(User::class)->find($userId);
        $badge->name = $name;
        $badge->description = $description;
        $badge->earnedDate = new \DateTime();
        $this->em->persist($badge);
        $this->em->flush();

        return $badge;
    }

    public function deleteHobby(int $hobbyId, int $userId): bool
    {
        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT hobby_id FROM hobbies WHERE hobby_id = :hid AND user_id = :uid',
            ['hid' => $hobbyId, 'uid' => $userId]
        );

        if (!is_array($row)) {
            return false;
        }

        $this->em->getConnection()->delete('hobbies', ['hobby_id' => $hobbyId]);
        return true;
    }

    /**
     * Get hours spent by day of week (0=Sunday, 6=Saturday)
     * @return array<int, float>
     */
    public function getDayOfWeekHeatmap(int $userId, int $weeks = 4): array
    {
        $result = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        $startDate = (new \DateTime())->modify("-{$weeks} weeks");
        $rows = $this->em->getConnection()->fetchAllAssociative(
            "SELECT DAYOFWEEK(pl.log_date) - 1 AS dow, SUM(pl.hours_spent) AS total
             FROM progress_log pl
             JOIN hobbies h ON h.hobby_id = pl.hobby_id
             WHERE h.user_id = :uid AND pl.log_date >= :startDate
             GROUP BY DAYOFWEEK(pl.log_date)",
            ['uid' => $userId, 'startDate' => $startDate->format('Y-m-d')]
        );

        foreach ($rows as $row) {
            $dow = (int) $row['dow'];
            if ($dow < 0) $dow = 6; // MySQL DAYOFWEEK: 1=Sunday, so -1 gives -1 for Sunday, fix to 6
            $result[$dow] = (float) ($row['total'] ?? 0);
        }

        return $result;
    }

    /**
     * Get weekly hour totals for the last N weeks
     * @return array{labels: string[], data: float[]}
     */
    public function getWeeklyTrends(int $userId, int $weeks = 12): array
    {
        $labels = [];
        $data = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = (new \DateTime())->modify("-{$i} weeks")->modify('Monday this week');
            $weekEnd = (clone $weekStart)->modify('+6 days');
            $labels[] = $weekStart->format('M d');

            $total = (float) $this->em->getConnection()->fetchOne(
                "SELECT COALESCE(SUM(pl.hours_spent), 0)
                 FROM progress_log pl
                 JOIN hobbies h ON h.hobby_id = pl.hobby_id
                 WHERE h.user_id = :uid AND pl.log_date BETWEEN :start AND :end",
                [
                    'uid' => $userId,
                    'start' => $weekStart->format('Y-m-d'),
                    'end' => $weekEnd->format('Y-m-d'),
                ]
            );

            $data[] = $total;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Get milestone completion percentage per hobby
     * @return array<string, array{total: int, completed: int, percentage: float}>
     */
    public function getMilestoneProgress(int $userId): array
    {
        $result = [];

        $rows = $this->em->getConnection()->fetchAllAssociative(
            "SELECT 
                h.hobby_id,
                h.name,
                COUNT(m.milestone_id) AS total,
                SUM(CASE WHEN m.is_achieved = 1 THEN 1 ELSE 0 END) AS completed
             FROM hobbies h
             LEFT JOIN milestones m ON m.hobby_id = h.hobby_id
             WHERE h.user_id = :uid
             GROUP BY h.hobby_id, h.name
             HAVING total > 0
             ORDER BY h.name ASC",
            ['uid' => $userId]
        );

        foreach ($rows as $row) {
            $total = (int) $row['total'];
            $completed = (int) ($row['completed'] ?? 0);
            $percentage = $total > 0 ? round((100 * $completed) / $total, 1) : 0;

            $result[$row['name']] = [
                'total' => $total,
                'completed' => $completed,
                'percentage' => $percentage,
            ];
        }

        return $result;
    }

    /**
     * Get all chart data for progress dashboard
     * @return array<string, mixed>
     */
    public function getAllChartData(int $userId): array
    {
        return [
            'dayOfWeekHeatmap' => $this->getDayOfWeekHeatmap($userId),
            'weeklyTrends' => $this->getWeeklyTrends($userId),
            'milestoneProgress' => $this->getMilestoneProgress($userId),
        ];
    }
}
