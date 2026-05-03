<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ActivitySummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/discover')]
final class DiscoveryController extends AbstractController
{
    #[Route('', name: 'app_discovery_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em, ActivitySummaryService $activitySummaryService): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $userId = (int)$currentUser->id;
        
        // Get all users except current user and already connected users
        $conn = $em->getConnection();
        $allUsers = $conn->fetchAllAssociative(
            "SELECT u.user_id, u.username, u.full_name, u.bio, u.location, u.profile_picture
             FROM users u
             WHERE u.user_id != :uid
               AND u.user_id NOT IN (
                   SELECT user1_id FROM friendships WHERE user2_id = :uid AND status = 'ACCEPTED'
                   UNION
                   SELECT user2_id FROM friendships WHERE user1_id = :uid AND status = 'ACCEPTED'
               )
             LIMIT 50",
            ['uid' => $userId]
        );

        // Get current user's hobbies
        $myHobbies = $conn->fetchAllAssociative(
            "SELECT h.hobby_id, h.name, h.category FROM hobbies h
             JOIN user_hobbies uh ON uh.hobby_id = h.hobby_id
             WHERE uh.user_id = :uid",
            ['uid' => $userId]
        );

        $myHobbyIds = array_map(static fn(array $h): int => (int)$h['hobby_id'], $myHobbies);
        $myHobbyNames = array_map(static fn(array $h): string => (string)$h['name'], $myHobbies);

        // Calculate match scores for each user
        $recommendations = [];
        foreach ($allUsers as $user) {
            $userId2 = (int)$user['user_id'];
            
            // Get their hobbies
            $theirHobbies = $conn->fetchAllAssociative(
                "SELECT h.hobby_id, h.name, h.category FROM hobbies h
                 JOIN user_hobbies uh ON uh.hobby_id = h.hobby_id
                 WHERE uh.user_id = :uid",
                ['uid' => $userId2]
            );

            $theirHobbyNames = array_map(static fn(array $h): string => (string)$h['name'], $theirHobbies);
            $theirHobbyIds = array_map(static fn(array $h): int => (int)$h['hobby_id'], $theirHobbies);

            $score = 0;
            $commonInterests = [];

            // 1. Shared hobbies (40 points max)
            foreach ($myHobbyNames as $hobby) {
                if (in_array($hobby, $theirHobbyNames, true)) {
                    $score += 10;
                    $commonInterests[] = $hobby;
                }
            }

            // 2. Location proximity (20 points)
            if (!empty($user['location']) && $currentUser->location === $user['location']) {
                $score += 20;
            }

            // 3. Activity level match (10 points)
            $myHobbyCount = count($myHobbies);
            $theirHobbyCount = count($theirHobbies);
            if (abs($myHobbyCount - $theirHobbyCount) <= 2) {
                $score += 10;
            }

            // 4. Badges bonus (5 points)
            $badgeCount = (int)$conn->fetchOne(
                "SELECT COUNT(*) FROM user_badges WHERE user_id = :uid",
                ['uid' => $userId2]
            );
            if ($badgeCount >= 3) {
                $score += 5;
            }

            // Add to recommendations if score > 0
            if ($score > 0 || count($commonInterests) > 0) {
                $recommendations[] = [
                    'user' => $user,
                    'score' => $score,
                    'commonInterests' => array_unique($commonInterests),
                    'matchPercentage' => min(100, $score),
                ];
            }
        }

        // Sort by score descending
        usort($recommendations, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return $this->render('discovery/index.html.twig', [
            'userId' => $userId,
            'recommendations' => array_slice($recommendations, 0, 20),
            'weeklyActivitySummary' => $activitySummaryService->getWeeklySummaryData($userId),
        ]);
    }

    #[Route('/api/connect', name: 'app_discovery_api_connect', methods: ['POST'])]
    public function sendConnection(Request $request, EntityManagerInterface $em): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $receiverId = (int)$request->request->get('receiver_id', 0);
        if ($receiverId <= 0) {
            return $this->json(['ok' => false, 'error' => 'Invalid user'], 400);
        }

        $conn = $em->getConnection();
        
        // Create friendship record (connection request)
        $conn->insert('friendships', [
            'user1_id' => (int)$currentUser->id,
            'user2_id' => $receiverId,
            'status' => 'PENDING',
            'created_date' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        return $this->json(['ok' => true, 'message' => 'Connection request sent']);
    }
}
