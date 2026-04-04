<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\BadgeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/badges')]
final class BadgesController extends AbstractController
{
    #[Route('', name: 'app_badges_index', methods: ['GET'])]
    public function index(Request $request, BadgeService $badgeService): Response
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 0);

        if (!$userId) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Get all badges and user's earned badges
        $allBadges = $badgeService->getAllBadges();
        $userBadges = $badgeService->getUserBadges($userId);
        
        // Create a quick lookup of earned badge names
        $earnedBadgeNames = [];
        foreach ($userBadges as $badge) {
            $earnedBadgeNames[$badge['name']] = $badge;
        }
        
        // Enrich all badges with earned status
        $badgesWithStatus = [];
        foreach ($allBadges as $badge) {
            $badgeName = $badge['name'];
            $isEarned = isset($earnedBadgeNames[$badgeName]);
            $earnedData = $earnedBadgeNames[$badgeName] ?? null;
            
            $badgesWithStatus[] = [
                'id' => $badge['id'] ?? uniqid(),
                'name' => $badgeName,
                'icon' => $badge['icon'],
                'description' => $badge['hint'] ?? 'Achievement débloqué!',
                'hint' => $badge['hint'] ?? '',
                'category' => $badge['category'],
                'rarity' => $badge['rarity'],
                'is_earned' => $isEarned,
                'earned_date' => $earnedData['earned_date'] ?? null,
            ];
        }

        // Calculate stats
        $earnedCount = count($userBadges);
        $totalCount = count($allBadges);
        $lockedCount = $totalCount - $earnedCount;
        $completionPercentage = $totalCount > 0 ? ($earnedCount / $totalCount) * 100 : 0;

        // Calculate rank based on earned badges
        $rank = $this->calculateRank($earnedCount);

        // Get unique categories for filtering
        $categories = [];
        foreach ($allBadges as $badge) {
            if (!in_array($badge['category'], $categories)) {
                $categories[] = $badge['category'];
            }
        }
        sort($categories);

        return $this->render('badges/index.html.twig', [
            'userId' => $userId,
            'badges' => $badgesWithStatus,
            'earned_count' => $earnedCount,
            'total_count' => $totalCount,
            'locked_count' => $lockedCount,
            'completion_percentage' => $completionPercentage,
            'rank' => $rank,
            'categories' => $categories,
            'current_user' => $currentUser,
        ]);
    }

    private function calculateRank(int $earnedCount): array
    {
        if ($earnedCount == 0) {
            return [
                'icon' => '🌱',
                'name' => 'Novice',
                'subtitle' => 'Commencez votre quête — gagnez votre premier badge !',
            ];
        } elseif ($earnedCount < 3) {
            return [
                'icon' => '🧙',
                'name' => 'Apprenti',
                'subtitle' => 'Vous avez commencé votre voyage. Continuez !',
            ];
        } elseif ($earnedCount < 6) {
            return [
                'icon' => '⚔️',
                'name' => 'Aventurier',
                'subtitle' => 'Un explorateur chevronné de Ghrami !',
            ];
        } elseif ($earnedCount < 10) {
            return [
                'icon' => '🛡️',
                'name' => 'Guerrier',
                'subtitle' => 'Vous prouvez votre valeur sur Ghrami !',
            ];
        } elseif ($earnedCount < 15) {
            return [
                'icon' => '🦅',
                'name' => 'Champion',
                'subtitle' => 'Un vrai champion de la plateforme !',
            ];
        } elseif ($earnedCount < 20) {
            return [
                'icon' => '👑',
                'name' => 'Légende',
                'subtitle' => 'Votre héritage sur Ghrami est indéniable !',
            ];
        } else {
            return [
                'icon' => '🏆',
                'name' => 'Grand Maître',
                'subtitle' => 'Le sommet — vous avez conquis toutes les quêtes !',
            ];
        }
    }
}
