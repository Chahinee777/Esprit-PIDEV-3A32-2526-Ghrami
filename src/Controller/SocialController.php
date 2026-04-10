<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\Story;
use App\Entity\User;
use App\Service\AiContentService;
use App\Service\SocialService;
use App\Service\BadgeService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/social')]
final class SocialController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }
    #[Route('', name: 'app_social_index', methods: ['GET'])]
    public function index(Request $request, SocialService $socialService): Response
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 8);
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = max(1, min(50, (int) $request->query->get('perPage', 10)));
        $searchQuery = trim((string) $request->query->get('query', ''));
        $sort = (string) $request->query->get('sort', 'recent');
        
        // Validate sort parameter
        if (!in_array($sort, ['recent', 'popular'], true)) {
            $sort = 'recent';
        }

        $feed = $socialService->getFeedForUser($userId, $page, $perPage, $searchQuery, $sort);
        $stories = $socialService->getActiveStoriesForUser($userId);
        $postIds = array_map(static fn(array $row): int => (int) $row['post_id'], $feed);
        $commentsByPost = $socialService->getCommentsForPosts($postIds);
        
        // Build comment threads (P2 Fix 9: Comment threading)
        foreach ($commentsByPost as &$comments) {
            $comments = $socialService->buildCommentThreads($comments);
        }

        // Get user stats
        $postCount = (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM posts WHERE user_id = :uid',
            ['uid' => $userId]
        ) ?? 0;

        $followerCount = (int) $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM connections WHERE receiver_id = :uid AND status = 'accepted'",
            ['uid' => $userId]
        ) ?? 0;

        $hobbyCount = (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM hobbies WHERE user_id = :uid',
            ['uid' => $userId]
        ) ?? 0;

        return $this->render('social/index.html.twig', [
            'userId' => $userId,
            'feed' => $feed,
            'stories' => $stories,
            'commentsByPost' => $commentsByPost,
            'page' => $page,
            'perPage' => $perPage,
            'userPostCount' => $postCount,
            'userFollowerCount' => $followerCount,
            'userHobbyCount' => $hobbyCount,
            'searchQuery' => $searchQuery,
            'sort' => $sort,
        ]);
    }

    #[Route('/post', name: 'app_social_post', methods: ['POST'])]
    public function createPost(Request $request, SocialService $socialService, BadgeService $badgeService, NotificationService $notificationService, SluggerInterface $slugger, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('social_post', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_social_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('user_id');

        $content = trim((string) $request->request->get('content', ''));
        $imageUrl = trim((string) $request->request->get('image_url', ''));
        /** @var UploadedFile|null $imageFile */
        $imageFile = $request->files->get('image_file');

        if ($imageFile !== null && !$imageFile->isValid()) {
            return $this->validationFailure($request, ['image_file' => 'Uploaded image is invalid.']);
        }

        if ($content === '' && $imageUrl === '' && !$imageFile instanceof UploadedFile) {
            return $this->validationFailure($request, ['content' => 'Post text or image is required.']);
        }

        if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
            $imageUrl = $this->storeSocialImage($imageFile, $slugger, 'posts');
        }

        $postValidation = new Post();
        $postValidation->content = $content;
        $postValidation->imageUrl = $imageUrl !== '' ? $imageUrl : null;
        $violations = $validator->validate($postValidation);
        if (count($violations) > 0) {
            return $this->validationFailure($request, $this->normalizeValidationErrors($violations));
        }

        $post = $socialService->createPost(
            $userId,
            $content,
            $imageUrl !== '' ? $imageUrl : null
        );

        // Check and award badges after post creation
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
            return $this->json(['id' => $post->id, 'ok' => true]);
        }

        return $this->redirectToRoute('app_social_index');
    }

    #[Route('/comment', name: 'app_social_comment', methods: ['POST'])]
    public function addComment(Request $request, SocialService $socialService, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('social_comment', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_social_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('user_id');

        $content = trim((string) $request->request->get('content', ''));
        $commentValidation = new Comment();
        $commentValidation->content = $content !== '' ? $content : null;

        $violations = $validator->validate($commentValidation);
        
        if (count($violations) > 0) {
            return $this->validationFailure($request, $this->normalizeValidationErrors($violations));
        }

        $comment = $socialService->addComment(
            (int) $request->request->get('post_id'),
            $userId,
            $content
        );

        if ($request->isXmlHttpRequest() ) {
            return $this->json(['id' => $comment->id, 'ok' => true]);
        }

        return $this->redirectToRoute('app_social_index');
    }

    #[Route('/like', name: 'app_social_like', methods: ['POST'])]
    public function toggleLike(Request $request, SocialService $socialService): Response
    {
        if (!$this->isCsrfTokenValid('social_like', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_social_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('user_id');

        $liked = $socialService->toggleLike(
            (int) $request->request->get('post_id'),
            $userId
        );

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json(['liked' => $liked, 'ok' => true]);
        }

        return $this->redirectToRoute('app_social_index');
    }

    #[Route('/story', name: 'app_social_story', methods: ['POST'])]
    public function createStory(Request $request, SocialService $socialService, SluggerInterface $slugger, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('social_story', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_social_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('user_id');

        $caption = trim((string) $request->request->get('caption', ''));
        $imageUrl = trim((string) $request->request->get('image_url', ''));
        /** @var UploadedFile|null $imageFile */
        $imageFile = $request->files->get('image_file');

        if ($imageFile !== null && !$imageFile->isValid()) {
            return $this->validationFailure($request, ['image_file' => 'Uploaded image is invalid.']);
        }

        if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
            $imageUrl = $this->storeSocialImage($imageFile, $slugger, 'stories');
        }

        $storyValidation = new Story();
        $storyValidation->caption = $caption !== '' ? $caption : null;
        $storyValidation->imageUrl = $imageUrl !== '' ? $imageUrl : null;

        $violations = $validator->validate($storyValidation);
        if (count($violations) > 0) {
            return $this->validationFailure($request, $this->normalizeValidationErrors($violations));
        }

        $story = $socialService->createStory(
            $userId,
            $caption,
            $imageUrl !== '' ? $imageUrl : null
        );

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json(['id' => $story->id, 'ok' => true]);
        }

        return $this->redirectToRoute('app_social_index');
    }

    #[Route('/post/delete', name: 'app_social_post_delete', methods: ['POST'])]
    public function deletePost(Request $request, SocialService $socialService): Response
    {
        if (!$this->isCsrfTokenValid('social_post_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_social_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : 0;
        $deleted = $socialService->deletePost((int) $request->request->get('post_id'), $userId);

        if (!$deleted) {
            $this->addFlash('error', 'Unable to delete this post.');
        }

        return $this->redirectToRoute('app_social_index');
    }

    #[Route('/post/edit', name: 'app_social_post_edit', methods: ['POST'])]
    public function editPost(Request $request, SocialService $socialService, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('social_post_edit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_social_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : 0;
        $postId = (int) $request->request->get('post_id');
        $content = trim((string) $request->request->get('content', ''));

        $existingPost = $this->em->getRepository(Post::class)->find($postId);
        if (!$existingPost instanceof Post || (int) ($existingPost->user?->id ?? 0) !== $userId) {
            $this->addFlash('error', 'Unable to edit this post.');
            return $this->redirectToRoute('app_social_index');
        }

        if ($content === '' && !$existingPost->imageUrl) {
            return $this->validationFailure($request, ['content' => 'Post text or image is required.']);
        }

        $postValidation = new Post();
        $postValidation->content = $content;
        $postValidation->imageUrl = $existingPost->imageUrl;
        $violations = $validator->validate($postValidation);
        if (count($violations) > 0) {
            return $this->validationFailure($request, $this->normalizeValidationErrors($violations));
        }

        if (!$socialService->updatePostContent($postId, $userId, $content)) {
            $this->addFlash('error', 'Unable to edit this post.');
        } else {
            $this->addFlash('success', 'Post updated.');
        }

        return $this->redirectToRoute('app_social_index');
    }

    #[Route('/comment/delete', name: 'app_social_comment_delete', methods: ['POST'])]
    public function deleteComment(Request $request, SocialService $socialService): Response
    {
        if (!$this->isCsrfTokenValid('social_comment_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_social_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : 0;
        $deleted = $socialService->deleteComment((int) $request->request->get('comment_id'), $userId);

        if (!$deleted) {
            $this->addFlash('error', 'Unable to delete this comment.');
        }

        return $this->redirectToRoute('app_social_index');
    }

    #[Route('/comment/edit', name: 'app_social_comment_edit', methods: ['POST'])]
    public function editComment(Request $request, SocialService $socialService, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('social_comment_edit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_social_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : 0;
        $commentId = (int) $request->request->get('comment_id');
        $content = trim((string) $request->request->get('content', ''));

        $commentValidation = new Comment();
        $commentValidation->content = $content;
        $violations = $validator->validate($commentValidation);
        if (count($violations) > 0) {
            return $this->validationFailure($request, $this->normalizeValidationErrors($violations));
        }

        if (!$socialService->updateCommentContent($commentId, $userId, $content)) {
            $this->addFlash('error', 'Unable to edit this comment.');
        } else {
            $this->addFlash('success', 'Comment updated.');
        }

        return $this->redirectToRoute('app_social_index');
    }

    #[Route('/ai/complete', name: 'app_social_ai_complete', methods: ['POST'])]
    public function aiComplete(Request $request, AiContentService $aiContentService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('social_ai_complete', (string) $request->request->get('_csrf_token'))) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], Response::HTTP_BAD_REQUEST);
        }

        $content = trim((string) $request->request->get('content', ''));
        if ($content === '' || mb_strlen($content) < 3) {
            return $this->json(['ok' => false, 'error' => 'Write at least a few words first.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $completion = $aiContentService->completePostText($content);
            $separator = preg_match('/\s$/', $content) ? '' : ' ';

            return $this->json([
                'ok' => true,
                'completion' => $completion,
                'fullText' => $content . $separator . $completion,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/ai/image', name: 'app_social_ai_image', methods: ['POST'])]
    public function aiImage(Request $request, AiContentService $aiContentService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('social_ai_image', (string) $request->request->get('_csrf_token'))) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], Response::HTTP_BAD_REQUEST);
        }

        $prompt = trim((string) $request->request->get('prompt', ''));
        if ($prompt === '' || mb_strlen($prompt) < 3) {
            return $this->json(['ok' => false, 'error' => 'Prompt is too short.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $image = $aiContentService->generateImage($prompt);
            $url = $this->storeGeneratedAiImage($image['bytes'], $image['extension']);

            return $this->json([
                'ok' => true,
                'url' => $url,
                'mime' => $image['mime'],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/ai/caption', name: 'app_social_ai_caption', methods: ['POST'])]
    public function aiCaption(Request $request, AiContentService $aiContentService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('social_ai_caption', (string) $request->request->get('_csrf_token'))) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], Response::HTTP_BAD_REQUEST);
        }

        $file = $request->files->get('image');
        if (!$file) {
            return $this->json(['ok' => false, 'error' => 'No image provided.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Get the original filename from the uploaded file
            $originalFileName = $file->getClientOriginalName();
            $caption = $aiContentService->analyzeImageForCaption($file->getPathname(), $originalFileName);

            return $this->json([
                'ok' => true,
                'caption' => $caption,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    private function storeSocialImage(UploadedFile $file, SluggerInterface $slugger, string $scope): ?string
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array((string) $file->getMimeType(), $allowed, true)) {
            return null;
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : 0;

        // Store in public/images/{scope}/ to match desktop structure
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/images/' . $scope;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // Filename format: {userId}_{timestamp}.{ext} (matches desktop pattern: 8_1771066256693.jpg)
        $extension = $file->guessExtension() ?: 'jpg';
        $timestamp = (int)(microtime(true) * 1000); // Convert to milliseconds like Java
        $filename = sprintf('%d_%d.%s', $userId, $timestamp, $extension);
        $file->move($uploadDir, $filename);

        // Store only filename in database (not full path)
        return $filename;
    }

    private function storeGeneratedAiImage(string $bytes, string $extension): string
    {
        $allowedExt = ['png', 'jpg', 'jpeg', 'webp'];
        $ext = in_array(strtolower($extension), $allowedExt, true) ? strtolower($extension) : 'jpg';

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : 0;

        // Store in public/images/posts/ to match desktop structure
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/images/posts';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // Filename format: {userId}_ai_{timestamp}.{ext}
        $timestamp = (int)(microtime(true) * 1000);
        $filename = sprintf('%d_ai_%d.%s', $userId, $timestamp, $ext);
        file_put_contents($uploadDir . '/' . $filename, $bytes);

        // Return only filename (not full path)
        return $filename;
    }

    #[Route('/api/users/search', name: 'app_social_api_users_search', methods: ['GET'])]
    public function apiUsersSearch(Request $request, SocialService $socialService): JsonResponse
    {
        $query = (string) $request->query->get('q', '');
        if (strlen($query) < 2) {
            return $this->json(['ok' => false, 'users' => []], Response::HTTP_BAD_REQUEST);
        }

        $users = $socialService->searchUsers($query, 20);
        return $this->json(['ok' => true, 'users' => $users]);
    }

    #[Route('/api/stories/feed', name: 'app_social_api_stories_feed', methods: ['GET'])]
    public function apiStoriesFeed(Request $request, SocialService $socialService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $stories = $socialService->getStoriesForFeed((int) $currentUser->id);
        return $this->json(['ok' => true, 'stories' => $stories]);
    }

    #[Route('/api/stories/delete-expired', name: 'app_social_api_stories_delete_expired', methods: ['POST'])]
    public function apiDeleteExpiredStories(SocialService $socialService): JsonResponse
    {
        $deleted = $socialService->deleteExpiredStories();
        return $this->json(['ok' => true, 'deletedCount' => $deleted]);
    }

    private function validationFailure(Request $request, array $errors): Response
    {
        
if ($request->isXmlHttpRequest()) {
                return $this->json([
                'ok' => false,
                'error' => 'Validation failed.',
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        foreach ($errors as $error) {
            $this->addFlash('error', (string) $error);
        }

        return $this->redirectToRoute('app_social_index');
    }

    private function normalizeValidationErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath();
            if (!isset($errors[$field])) {
                $errors[$field] = $violation->getMessage();
            }
        }

        return $errors;
    }
}
