<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\SmartMatchingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/discovery')]
final class DiscoveryApiController extends AbstractController
{
    public function __construct(
        private readonly SmartMatchingService $smartMatchingService,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Get matched user cards with smart matching algorithm.
     */
    #[Route('/matches', name: 'api_discovery_matches', methods: ['GET'])]
    public function getMatches(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $matches = $this->smartMatchingService->calculateMatchScores((int) $user->id);
            
            // Convert to JSON-serializable array
            $data = array_map(fn($match) => [
                'id' => $match->id,
                'username' => $match->username,
                'full_name' => $match->fullName,
                'location' => $match->location,
                'bio' => $match->bio,
                'profile_picture' => $match->profilePicture,
                'score' => $match->score,
                'percentage' => $match->getPercentage(),
                'score_color' => $match->getScoreColor(),
                'reason' => $match->reason,
                'common_interests' => array_slice($match->commonInterests, 0, 3),
            ], $matches);

            return $this->json(['ok' => true, 'matches' => $data]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/user/{userId}', name: 'api_discovery_user_profile', methods: ['GET'])]
    public function getUserProfile(int $userId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        $conn = $this->em->getConnection();
        $profile = $conn->fetchAssociative(
            'SELECT user_id, username, full_name, bio, location, profile_picture FROM users WHERE user_id = :uid',
            ['uid' => $userId]
        );

        if (!$profile) {
            return $this->json(['ok' => false, 'error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $hobbies = $conn->fetchFirstColumn(
            'SELECT hobby_name FROM hobbies WHERE user_id = :uid ORDER BY hobby_name ASC LIMIT 20',
            ['uid' => $userId]
        );

        $badges = $conn->fetchAllAssociative(
            'SELECT badge_name, description FROM badges WHERE user_id = :uid ORDER BY badge_name ASC LIMIT 20',
            ['uid' => $userId]
        );

        return $this->json([
            'ok' => true,
            'user' => [
                'id' => (int) $profile['user_id'],
                'username' => $profile['username'],
                'full_name' => $profile['full_name'] ?: $profile['username'],
                'bio' => $profile['bio'],
                'location' => $profile['location'],
                'profile_picture' => $profile['profile_picture'],
            ],
            'hobbies' => $hobbies,
            'badges' => $badges,
        ]);
    }
}
