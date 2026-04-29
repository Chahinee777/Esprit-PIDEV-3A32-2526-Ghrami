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
        $profileLocation = $user->location;

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
            $profileLocation = $location;

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
                $user->location = $location;
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

        // Fetch friends list - OPTIMIZED: Single JOIN query instead of N+1 pattern
        $friends = $conn->fetchAllAssociative(
            "SELECT 
                u.user_id, 
                u.username, 
                u.email, 
                u.profile_picture, 
                u.is_online,
                f.created_date AS friend_since
             FROM friendships f
             JOIN users u ON (
                (f.user1_id = ? AND f.user2_id = u.user_id) OR
                (f.user2_id = ? AND f.user1_id = u.user_id)
             )
             WHERE f.status = 'ACCEPTED'
             ORDER BY f.created_date DESC",
            [(int) $user->id, (int) $user->id]
        );

        return $this->render('profile/index.html.twig', [
            'profile' => $user,
            'profileLocation' => $profileLocation,
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

    #[Route('/api/generate-image', name: 'app_profile_generate_image', methods: ['POST'])]
    public function generateProfileImage(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Validate CSRF token
        $data = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('profile', $data['_csrf_token'] ?? '')) {
            return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        try {
            // Get parameters from the request
            $prompt = isset($data['prompt']) ? trim((string) $data['prompt']) : '';
            $model = isset($data['model']) ? trim((string) $data['model']) : 'gemini';
            $aspectRatio = isset($data['ar']) ? trim((string) $data['ar']) : '1:1';
            
            if (empty($prompt)) {
                throw new \Exception('Prompt is required');
            }
            
            // Build the API URL with query parameters
            $apiUrl = 'https://imfin.it/api/generate';
            $queryParams = [
                'prompt' => $prompt,
                'model' => $model,
                'ar' => $aspectRatio,
            ];
            $apiUrl .= '?' . http_build_query($queryParams);
            
            $imageData = null;
            $error = null;

            // Use cURL to fetch the image
            if (function_exists('curl_init')) {
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 120);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
                
                $imageData = curl_exec($ch);
                
                if ($imageData === false) {
                    $error = 'cURL Error: ' . curl_error($ch);
                }
                
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if (!$error && $httpCode !== 200) {
                    $error = "API returned HTTP $httpCode";
                }
            } else {
                // Fallback to file_get_contents
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 120,
                        'ignore_errors' => true,
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ]);
                
                $imageData = @file_get_contents($apiUrl, false, $context);
                
                if ($imageData === false) {
                    $error = 'Failed to connect to imfin.it API';
                }
            }

            if ($error) {
                throw new \Exception($error);
            }

            if (empty($imageData)) {
                throw new \Exception('API returned empty response');
            }

            // Detect MIME type of the generated image
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $imageData);
            finfo_close($finfo);

            if (!in_array($mimeType, ['image/png', 'image/jpeg', 'image/webp', 'image/jpg'], true)) {
                // If MIME detection failed, try magic bytes
                if (str_starts_with($imageData, '\xFF\xD8')) {
                    $mimeType = 'image/jpeg';
                } elseif (str_starts_with($imageData, '\x89PNG')) {
                    $mimeType = 'image/png';
                } elseif (str_starts_with($imageData, 'RIFF')) {
                    $mimeType = 'image/webp';
                } else {
                    throw new \Exception('Invalid image format received. MIME type: ' . $mimeType . ', first bytes: ' . bin2hex(substr($imageData, 0, 10)));
                }
            }

            // Determine file extension from MIME type
            $extensionMap = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/webp' => 'webp',
            ];
            $extension = $extensionMap[$mimeType];  // mimeType is guaranteed to be one of the array keys

            // Save the generated image
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/images/profile_pictures';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            // Generate filename: {userId}_{timestamp}.{ext}
            $timestamp = (int)(microtime(true) * 1000);
            $filename = sprintf('%d_%d.%s', $user->id ?? 0, $timestamp, $extension);
            $filepath = $uploadDir . '/' . $filename;

            // Write the image file
            $bytesWritten = file_put_contents($filepath, $imageData);
            if ($bytesWritten === false) {
                throw new \Exception('Failed to write image file to disk');
            }

            // Update user's profile picture
            $user->profilePicture = $filename;
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Profile picture generated and saved successfully',
                'filename' => $filename,
            ]);
        } catch (\Exception $e) {
            error_log('Profile image generation error: ' . $e->getMessage());
            
            return $this->json([
                'success' => false,
                'error' => 'Image generation failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

