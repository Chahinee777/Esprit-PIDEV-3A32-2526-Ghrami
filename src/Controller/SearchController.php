<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/search')]
final class SearchController extends AbstractController
{
    #[Route('', name: 'app_search_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $query = (string)$request->query->get('q', '');
        $results = ['posts' => [], 'users' => [], 'hobbies' => []];

        if (strlen($query) >= 2) {
            $results = $this->performSearch($query, $em);
        }

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'results' => $results,
        ]);
    }

    #[Route('/api', name: 'app_search_api', methods: ['GET'])]
    public function searchApi(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $query = (string)$request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json(['ok' => false, 'error' => 'Query too short'], 400);
        }

        $results = $this->performSearch($query, $em);
        return $this->json(['ok' => true, 'results' => $results]);
    }

    private function performSearch(string $query, EntityManagerInterface $em): array
    {
        $conn = $em->getConnection();
        $searchTerm = '%' . addcslashes($query, '%_') . '%';

        // Search posts
        $posts = $conn->fetchAllAssociative(
            "SELECT p.post_id, p.content, p.created_at, u.user_id, u.username, u.full_name, u.profile_picture,
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS likes_count
             FROM posts p
             JOIN users u ON u.user_id = p.user_id
             WHERE p.content LIKE :query
             ORDER BY p.created_at DESC
             LIMIT 10",
            ['query' => $searchTerm]
        );

        // Search users
        $users = $conn->fetchAllAssociative(
            "SELECT u.user_id, u.username, u.full_name, u.bio, u.location, u.profile_picture
             FROM users u
             WHERE u.username LIKE :query OR u.full_name LIKE :query
             LIMIT 10",
            ['query' => $searchTerm]
        );

        // Search hobbies  
        $hobbies = $conn->fetchAllAssociative(
            "SELECT h.hobby_id, h.name, h.category,
                    (SELECT COUNT(DISTINCT uh.user_id) FROM user_hobbies uh WHERE uh.hobby_id = h.hobby_id) AS user_count
             FROM hobbies h
             WHERE h.name LIKE :query OR h.category LIKE :query
             ORDER BY user_count DESC
             LIMIT 10",
            ['query' => $searchTerm]
        );

        return [
            'posts' => $posts,
            'users' => $users,
            'hobbies' => $hobbies,
        ];
    }
}
