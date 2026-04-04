<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/profile')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger,
        BadgeService $badgeService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('profile', (string) $request->request->get('_csrf_token'))) {
                $errors[] = 'Invalid CSRF token.';
            }

            $username = trim((string) $request->request->get('username', ''));
            $email = trim((string) $request->request->get('email', ''));
            $fullName = trim((string) $request->request->get('full_name', ''));
            $location = trim((string) $request->request->get('location', ''));
            $bio = trim((string) $request->request->get('bio', ''));
            $newPassword = (string) $request->request->get('new_password', '');

            if ($username === '' || mb_strlen($username) < 3) {
                $errors[] = 'Username must be at least 3 characters.';
            }

            // Email validation only if email is provided and different from current
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please provide a valid email address.';
            } elseif ($email === '') {
                // If email field is empty, use current user's email
                $email = $user->email;
            }

            if ($userRepository->hasUsername($username, $user->id)) {
                $errors[] = 'This username is already taken.';
            }

            if ($email !== $user->email && $userRepository->hasEmail($email, $user->id)) {
                $errors[] = 'This email is already in use.';
            }

            if ($newPassword !== '' && mb_strlen($newPassword) < 6) {
                $errors[] = 'New password must be at least 6 characters.';
            }

            /** @var UploadedFile|null $profilePicture */
            $profilePicture = $request->files->get('profile_picture');
            if ($profilePicture instanceof UploadedFile && $profilePicture->isValid()) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array((string) $profilePicture->getMimeType(), $allowed, true)) {
                    $errors[] = 'Profile picture must be a JPG, PNG, or WEBP image.';
                }
            }

            if ($errors === []) {
                $user->username = $username;
                $user->email = mb_strtolower($email);
                $user->fullName = $fullName !== '' ? $fullName : null;
                $user->location = $location !== '' ? $location : null;
                $user->bio = $bio !== '' ? $bio : null;

                if ($newPassword !== '') {
                    $user->password = $passwordHasher->hashPassword($user, $newPassword);
                }

                if ($profilePicture instanceof UploadedFile && $profilePicture->isValid()) {
                    // Use public/images/profile_pictures/ directory to match desktop structure
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/images/profile_pictures';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }

                    // Filename format: {userId}_{currentTimeInMillis}.{ext}
                    // Matches desktop pattern: 8_1770192277278.png
                    $extension = $profilePicture->guessExtension() ?: 'jpg';
                    $timestamp = (int)(microtime(true) * 1000); // Convert to milliseconds like Java
                    $newFilename = sprintf('%d_%d.%s', $user->id ?? 0, $timestamp, $extension);
                    
                    // Move file to storage
                    $profilePicture->move($uploadDir, $newFilename);
                    
                    // Store only filename in database (matches desktop)
                    $user->profilePicture = $newFilename;
                }

                $entityManager->flush();
                $this->addFlash('success', 'Profile updated successfully.');

                return $this->redirectToRoute('app_profile');
            }
        }

        // Fetch statistics
        $conn = $entityManager->getConnection();
        
        // Safe stats fetching with error handling
        $stats = [
            'posts' => 0,
            'hobbies' => 0,
            'friends' => 0,
        ];
        
        // Posts count
        try {
            $posts = $conn->fetchOne('SELECT COUNT(*) FROM posts WHERE user_id = ?', [(int) $user->id]);
            $stats['posts'] = (int) $posts;
        } catch (\Exception $e) {
            $stats['posts'] = 0;
        }
        
        // Hobbies count - query hobbies table directly
        try {
            $hobbies = $conn->fetchOne('SELECT COUNT(*) FROM hobbies WHERE user_id = ?', [(int) $user->id]);
            $stats['hobbies'] = (int) $hobbies;
        } catch (\Exception $e) {
            // Query failed - default to 0
            $stats['hobbies'] = 0;
        }
        
        // Friends count
        try {
            $friends = $conn->fetchOne(
                "SELECT COUNT(*) FROM friendships 
                 WHERE status = 'ACCEPTED' AND (user1_id = ? OR user2_id = ?)",
                [(int) $user->id, (int) $user->id]
            );
            $stats['friends'] = (int) $friends;
        } catch (\Exception $e) {
            $stats['friends'] = 0;
        }

        // Fetch friends list
        $friendships = $conn->fetchAllAssociative(
            "SELECT CASE WHEN user1_id = ? THEN user2_id ELSE user1_id END as friend_id, created_date
             FROM friendships 
             WHERE status = 'ACCEPTED' AND (user1_id = ? OR user2_id = ?)",
            [(int) $user->id, (int) $user->id, (int) $user->id]
        );

        $friends = [];
        foreach ($friendships as $friendship) {
            $friendData = $conn->fetchAssociative(
                'SELECT user_id, username, email, profile_picture, is_online FROM users WHERE user_id = ?',
                [(int) $friendship['friend_id']]
            );
            if ($friendData) {
                $friendData['friend_since'] = $friendship['created_date'];
                $friends[] = $friendData;
            }
        }

        return $this->render('profile/index.html.twig', [
            'profile' => $user,
            'errors' => $errors,
            'badges' => $badgeService->getUserBadges((int) $user->id),
            'stats' => $stats,
            'friends' => $friends,
        ]);
    }

    #[Route('/api/badges', name: 'app_profile_api_badges', methods: ['GET'])]
    public function apiBadges(BadgeService $badgeService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $userBadges = $badgeService->getUserBadges((int) $currentUser->id);
        $stats = $badgeService->getBadgeStats();

        return $this->json([
            'ok' => true,
            'earned' => $userBadges,
            'total' => count($badgeService->getAllBadges()),
            'stats' => $stats,
        ]);
    }

    #[Route('/api/badges/all', name: 'app_profile_api_all_badges', methods: ['GET'])]
    public function apiAllBadges(BadgeService $badgeService): JsonResponse
    {
        $all = $badgeService->getAllBadges();
        return $this->json(['ok' => true, 'badges' => $all]);
    }

    #[Route('/api/badges/check', name: 'app_profile_api_check_badges', methods: ['POST'])]
    public function apiCheckBadges(BadgeService $badgeService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $awarded = $badgeService->checkAndAwardBadges((int) $currentUser->id);
        return $this->json(['ok' => true, 'newBadges' => $awarded]);
    }

    #[Route('/toggle-online', name: 'toggle_online_status', methods: ['POST'])]
    public function toggleOnlineStatus(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Check CSRF token from header
        $token = $request->headers->get('X-CSRF-TOKEN') ?? '';
        if (!$this->isCsrfTokenValid('profile', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $isOnline = $data['online'] ?? false;

        $user->isOnline = $isOnline;
        $entityManager->flush();

        return $this->json(['ok' => true, 'online' => $isOnline]);
    }
}

