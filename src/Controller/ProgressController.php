<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ProgressService;
use App\Service\BadgeService;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/progress')]
final class ProgressController extends AbstractController
{
    #[Route('', name: 'app_progress_index', methods: ['GET'])]
    public function index(Request $request, ProgressService $progressService): Response
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 8);
        $hobbies = $progressService->listHobbies($userId);
        $stats = $progressService->getStats($userId);
        $logs = $progressService->listProgressLogs($userId, 60);
        $milestones = $progressService->listMilestones($userId);
        $badges = $progressService->listBadges($userId);

        return $this->render('progress/index.html.twig', [
            'userId' => $userId,
            'hobbies' => $hobbies,
            'stats' => $stats,
            'logs' => $logs,
            'milestones' => $milestones,
            'badges' => $badges,
        ]);
    }

    #[Route('/hobby', name: 'app_progress_hobby_add', methods: ['POST'])]
    public function addHobby(Request $request, ProgressService $progressService, BadgeService $badgeService, NotificationService $notificationService): Response
    {
        if (!$this->isCsrfTokenValid('progress_hobby', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_progress_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('user_id');

        $hobby = $progressService->addHobby(
            $userId,
            (string) $request->request->get('name'),
            $request->request->get('category'),
            $request->request->get('description')
        );

        // Check and award badges after hobby addition
        $awardedBadges = $badgeService->checkAndAwardBadges($userId);
        foreach ($awardedBadges as $badgeId) {
            $badge = $badgeService->getBadgeById($badgeId);
            if ($badge) {
                $notificationService->create(
                    $userId,
                    'BADGE_EARNED',
                    '🏆 You earned the "' . $badge['name'] . '" badge!'
                );
            }
        }

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json(['ok' => true, 'id' => $hobby->id]);
        }

        return $this->redirectToRoute('app_progress_index');
    }

    #[Route('/log', name: 'app_progress_log_add', methods: ['POST'])]
    public function logProgress(Request $request, ProgressService $progressService): Response
    {
        if (!$this->isCsrfTokenValid('progress_log', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_progress_index');
        }

        $progressService->logProgress(
            (int) $request->request->get('hobby_id'),
            (float) $request->request->get('hours'),
            $request->request->get('notes'),
            $request->request->get('log_date')
        );

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json(['ok' => true]);
        }

        return $this->redirectToRoute('app_progress_index');
    }

    #[Route('/milestone', name: 'app_progress_milestone_add', methods: ['POST'])]
    public function addMilestone(Request $request, ProgressService $progressService): Response
    {
        if (!$this->isCsrfTokenValid('progress_milestone', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_progress_index');
        }

        $milestone = $progressService->addMilestone(
            (int) $request->request->get('hobby_id'),
            (string) $request->request->get('title'),
            $request->request->get('target_date')
        );

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json(['ok' => true, 'id' => $milestone->id]);
        }

        return $this->redirectToRoute('app_progress_index');
    }

    #[Route('/milestone/toggle', name: 'app_progress_milestone_toggle', methods: ['POST'])]
    public function toggleMilestone(Request $request, ProgressService $progressService): Response
    {
        if (!$this->isCsrfTokenValid('progress_milestone_toggle', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_progress_index');
        }

        $progressService->toggleMilestone((int) $request->request->get('milestone_id'));

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json(['ok' => true]);
        }

        return $this->redirectToRoute('app_progress_index');
    }

    #[Route('/badge', name: 'app_progress_badge_award', methods: ['POST'])]
    public function awardBadge(Request $request, ProgressService $progressService): Response
    {
        if (!$this->isCsrfTokenValid('progress_badge', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_progress_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('user_id');

        $badge = $progressService->awardBadge(
            $userId,
            (string) $request->request->get('name'),
            $request->request->get('description')
        );

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json(['ok' => true, 'id' => $badge->id]);
        }

        return $this->redirectToRoute('app_progress_index');
    }

    #[Route('/hobby/delete', name: 'app_progress_hobby_delete', methods: ['POST'])]
    public function deleteHobby(Request $request, ProgressService $progressService): Response
    {
        if (!$this->isCsrfTokenValid('progress_hobby_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_progress_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : 0;
        $deleted = $progressService->deleteHobby((int) $request->request->get('hobby_id'), $userId);

        if (!$deleted) {
            $this->addFlash('error', 'Unable to delete hobby.');
        }

        return $this->redirectToRoute('app_progress_index');
    }

    #[Route('/api/charts', name: 'app_progress_api_charts', methods: ['GET'])]
    public function getCharts(Request $request, ProgressService $progressService): JsonResponse
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 8);

        $chartData = $progressService->getAllChartData($userId);

        return $this->json($chartData);
    }

    #[Route('/api/heatmap', name: 'app_progress_api_heatmap', methods: ['GET'])]
    public function getHeatmap(Request $request, ProgressService $progressService): JsonResponse
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 8);
        $weeks = (int) $request->query->get('weeks', 4);

        $heatmapData = $progressService->getDayOfWeekHeatmap($userId, $weeks);
        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        return $this->json([
            'labels' => $dayNames,
            'data' => array_values($heatmapData),
        ]);
    }

    #[Route('/api/trends', name: 'app_progress_api_trends', methods: ['GET'])]
    public function getTrends(Request $request, ProgressService $progressService): JsonResponse
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 8);
        $weeks = (int) $request->query->get('weeks', 12);

        $trendsData = $progressService->getWeeklyTrends($userId, $weeks);

        return $this->json($trendsData);
    }

    #[Route('/api/milestones', name: 'app_progress_api_milestones', methods: ['GET'])]
    public function getMilestones(Request $request, ProgressService $progressService): JsonResponse
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 8);

        $milestoneData = $progressService->getMilestoneProgress($userId);

        return $this->json([
            'hobbies' => array_keys($milestoneData),
            'data' => array_map(fn($m) => $m['percentage'], $milestoneData),
        ]);
    }

    #[Route('/hobbies', name: 'app_progress_hobbies', methods: ['GET'])]
    public function hobbies(Request $request, ProgressService $progressService): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $userId = (int) $currentUser->id;

        // Get all hobbies with detailed information
        $hobbies = $progressService->listHobbiesDetailed($userId);
        
        // Calculate stats
        $totalHours = 0;
        $totalMilestones = 0;
        $completedMilestones = 0;
        
        foreach ($hobbies as &$hobby) {
            $totalHours += $hobby['total_hours'] ?? 0;
            // Get milestones for this hobby if available
            $hobby['next_milestone'] = $progressService->getNextMilestone($hobby['hobby_id']);
        }
        
        $milestones = $progressService->listMilestones($userId);
        $totalMilestones = count($milestones ?? []);
        $completedMilestones = count(array_filter($milestones ?? [], fn($m) => $m['is_completed']));

        $avgProgress = $totalMilestones > 0 ? round(($completedMilestones / $totalMilestones) * 100) : 0;

        $hobbyStats = [
            'total_hobbies' => count($hobbies),
            'total_hours' => (int) $totalHours,
            'total_milestones' => $totalMilestones,
            'completed_milestones' => $completedMilestones,
            'avg_progress' => $avgProgress,
        ];

        return $this->render('progress/hobbies.html.twig', [
            'hobbies' => $hobbies,
            'hobbyStats' => $hobbyStats,
        ]);
    }

    #[Route('/api/hobby/create', name: 'app_progress_api_hobby_create', methods: ['POST'])]
    public function apiCreateHobby(Request $request, ProgressService $progressService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $hobby = $progressService->addHobby(
                (int) $currentUser->id,
                (string) $request->request->get('name', ''),
                $request->request->get('category'),
                $request->request->get('description')
            );

            return $this->json(['ok' => true, 'id' => $hobby->id]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/log', name: 'app_progress_api_log', methods: ['POST'])]
    public function apiLogProgress(Request $request, ProgressService $progressService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $progressService->logProgress(
                (int) $request->request->get('hobby_id'),
                (float) $request->request->get('hours', 0),
                $request->request->get('notes'),
                $request->request->get('date')
            );

            return $this->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // P2 Fix 13: Gemini AI Insights for hobbies
    #[Route('/api/hobby-insights', name: 'app_progress_api_hobby_insights', methods: ['GET'])]
    public function getHobbyInsights(Request $request, ProgressService $progressService, \App\Service\AiContentService $aiContentService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $hobbies = $progressService->listHobbies((int)$currentUser->id);
        if (empty($hobbies)) {
            return $this->json(['ok' => false, 'error' => 'No hobbies to analyze'], Response::HTTP_BAD_REQUEST);
        }

        // Build hobby summary for AI
        $hobbyNames = implode(', ', array_map(static fn(array $h): string => $h['name'] ?? '', $hobbies));
        $totalHours = array_sum(array_map(static fn(array $h): float => (float)($h['hours_spent'] ?? 0), $hobbies));

        try {
            $prompt = "I have been pursuing these hobbies: $hobbyNames. Total hours spent: $totalHours hours. " .
                      "Give me 3 specific, encouraging insights or tips to improve my hobby journey. Be concise and motivating.";

            $insights = $aiContentService->completeText($prompt);
            return $this->json([
                'ok' => true,
                'insights' => $insights,
                'hobbies' => $hobbyNames,
                'hours' => $totalHours,
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => 'Failed to generate insights'], Response::HTTP_BAD_GATEWAY);
        }
    }
}


