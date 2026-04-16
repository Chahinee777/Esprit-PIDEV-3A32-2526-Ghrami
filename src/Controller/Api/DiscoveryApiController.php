<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\GroqSmartMatchingService;
use App\Service\SmartMatchingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/discovery')]
final class DiscoveryApiController extends AbstractController
{
    public function __construct(
        private readonly GroqSmartMatchingService $groqSmartMatchingService,
        private readonly SmartMatchingService $smartMatchingService,
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
    ) {}

    /**
     * Get matched user cards with smart matching algorithm.
     * Uses Groq AI-powered matching if API key is configured,
     * otherwise falls back to rule-based matching.
     */
    #[Route('/matches', name: 'api_discovery_matches', methods: ['GET'])]
    public function getMatches(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        $userId = (int) $user->id;
        $cacheKey = 'discovery_matches_user_' . $userId;
        $forceRefresh = $request->query->getBoolean('refresh', false);

        if ($forceRefresh) {
            $this->cache->delete($cacheKey);
        }

        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($userId) {
            $item->expiresAfter(90);

            try {
                // Try Groq AI-powered matching first
                $matches = $this->groqSmartMatchingService->calculateMatchScores($userId);
            } catch (\RuntimeException $e) {
                // Fallback to rule-based matching if Groq not configured
                if (str_contains($e->getMessage(), 'GROQ_API_KEY')) {
                    $matches = $this->smartMatchingService->calculateMatchScores($userId);
                } else {
                    throw $e;
                }
            }

            // Convert to JSON-serializable array
            return array_map(fn($match) => [
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
        });

        return $this->json(['ok' => true, 'matches' => $data]);

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

        try {
            $hobbies = $conn->fetchFirstColumn(
                'SELECT name FROM hobbies WHERE user_id = :uid ORDER BY name ASC LIMIT 20',
                ['uid' => $userId]
            );
        } catch (\Throwable) {
            // Keep profile payload available even if hobbies query fails.
            $hobbies = [];
        }

        $badges = $conn->fetchAllAssociative(
            'SELECT name, description FROM badges WHERE user_id = :uid ORDER BY name ASC LIMIT 20',
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
            'badges' => array_map(fn($badge) => ['badge_name' => $badge['name'], 'description' => $badge['description']], $badges),
        ]);
    }
}
