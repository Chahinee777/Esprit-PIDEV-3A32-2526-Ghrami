<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\SocialService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/stories')]
final class StoryController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }



    /**
     * Create a new story.
     */
    #[Route('/create', name: 'app_story_create', methods: ['POST'])]
    public function create(
        Request $request,
        SocialService $socialService,
        SluggerInterface $slugger
    ): Response {
        if (!$this->isCsrfTokenValid('story_create', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            if ($request->isXmlHttpRequest()) {
                return $this->json(['ok' => false, 'error' => 'CSRF validation failed'], Response::HTTP_BAD_REQUEST);
            }
            return $this->redirectToRoute('app_stories_index');
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User || $currentUser->id === null) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $caption = trim((string) $request->request->get('caption', ''));
            $imageUrl = '';

            /** @var UploadedFile|null $imageFile */
            $imageFile = $request->files->get('image');
            if ($imageFile instanceof UploadedFile && $imageFile->isValid()) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array((string) $imageFile->getMimeType(), $allowed, true)) {
                    throw new \Exception('Story image must be JPG, PNG, or WEBP.');
                }

                if ($imageFile->getSize() > 5242880) { // 5MB
                    throw new \Exception('Story image must be under 5MB.');
                }

                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/stories';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = sprintf(
                    '%d_%s.%s',
                    $currentUser->id,
                    uniqid((string) $safeFilename . '_', true),
                    $imageFile->guessExtension() ?: 'jpg'
                );
                $imageFile->move($uploadDir, $newFilename);
                $imageUrl = '/uploads/stories/' . $newFilename;
            }

            $story = $socialService->createStory(
                (int) $currentUser->id,
                $caption,
                $imageUrl
            );

            // Always return JSON for consistency - modal will handle the response
            return $this->json(['ok' => true, 'id' => $story->id ?? 0, 'message' => 'Story posted! It will expire in 24 hours.']);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();

            return $this->json(['ok' => false, 'error' => $msg], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a story (only owner can delete).
     */
    #[Route('/{storyId}/delete', name: 'app_story_delete', methods: ['POST'])]
    public function delete(int $storyId, SocialService $socialService): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User || $currentUser->id === null) {
            return $this->redirectToRoute('app_login');
        }

        try {
            // Verify ownership before deleting
            $story = $this->em->getConnection()->fetchAssociative(
                'SELECT story_id, user_id FROM stories WHERE story_id = ?',
                [$storyId]
            );

            if (!$story || (int) $story['user_id'] !== (int) $currentUser->id) {
                throw new \Exception('Unauthorized: You can only delete your own stories.');
            }

            $socialService->deleteStory($storyId);

            return $this->json(['ok' => true, 'message' => 'Story deleted.']);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            if ($this->isXmlHttpRequest()) {
                return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('error', 'Failed to delete story.');
            return $this->redirectToRoute('app_stories_index');
        }
    }

    /**
     * API: List active stories for feed.
     */
    #[Route('/api/feed', name: 'app_stories_api_feed', methods: ['GET'])]
    public function apiFeed(SocialService $socialService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $stories = $socialService->getActiveStoriesForUser((int) $currentUser->id);
        return $this->json(['ok' => true, 'stories' => $stories]);
    }

    /**
     * Helper to check if request is AJAX/XHR.
     */
    private function isXmlHttpRequest(): bool
    {
        return $this->getRequest()->isXmlHttpRequest();
    }

    private function getRequest(): Request
    {
        return $this->container->get('request_stack')->getCurrentRequest() ?? new Request();
    }
}
