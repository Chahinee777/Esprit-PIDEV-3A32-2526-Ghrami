<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\DailySummaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/summary', name: 'app_summary_')]
final class SummaryController extends AbstractController
{
    #[Route('/daily', name: 'daily', methods: ['GET'])]
    public function daily(DailySummaryService $summaryService): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            error_log('Summary: User not authenticated');
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $userId = $user->id;
            if (!$userId) {
                error_log('Summary: User has no ID');
                return $this->json(['error' => 'Invalid user'], Response::HTTP_BAD_REQUEST);
            }

            error_log("Summary: Fetching for user {$userId}");
            $summary = $summaryService->getDailySummary($userId);

            return $this->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            error_log('Summary service error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return $this->json(
                ['error' => 'Failed to generate summary: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
