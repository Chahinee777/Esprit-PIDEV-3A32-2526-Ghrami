<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\NotificationService;
use App\Service\AiNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
final class NotificationController extends AbstractController
{
    // Notification controller with AI integration - testing and integration ai
    #[Route('', name: 'app_notifications_index', methods: ['GET'])]
    public function index(NotificationService $notificationService, AiNotificationService $aiService): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_auth_login');
        }

        $userId = (int) $currentUser->id;
        $digest = $aiService->buildSmartDigest($userId, 3);

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notificationService->getForUser($userId),
            'unreadCount' => $notificationService->countUnread($userId),
            'aiDigest' => $digest,
        ]);
    }

    #[Route('/api/list', name: 'app_notifications_api_list', methods: ['GET'])]
    public function apiList(Request $request, NotificationService $notificationService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $limit = (int) $request->query->get('limit', 50);
        $notifications = $notificationService->getForUser((int) $currentUser->id, min($limit, 100));

        return $this->json([
            'ok' => true,
            'notifications' => $notifications,
            'unreadCount' => $notificationService->countUnread((int) $currentUser->id),
        ]);
    }

    #[Route('/api/unread', name: 'app_notifications_api_unread', methods: ['GET'])]
    public function apiUnread(NotificationService $notificationService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'ok' => true,
            'notifications' => $notificationService->getUnread((int) $currentUser->id),
            'count' => $notificationService->countUnread((int) $currentUser->id),
        ]);
    }

    #[Route('/api/count', name: 'app_notifications_api_count', methods: ['GET'])]
    public function apiCount(NotificationService $notificationService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'ok' => true,
            'unreadCount' => $notificationService->countUnread((int) $currentUser->id),
        ]);
    }

    #[Route('/api/mark-as-read/{notificationId}', name: 'app_notifications_api_mark_read', methods: ['POST'])]
    public function apiMarkAsRead(int $notificationId, NotificationService $notificationService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $notificationService->markAsRead($notificationId, (int) $currentUser->id);
        return $this->json(['ok' => true]);
    }

    #[Route('/api/mark-all-as-read', name: 'app_notifications_api_mark_all_read', methods: ['POST'])]
    public function apiMarkAllAsRead(NotificationService $notificationService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $notificationService->markAllAsRead((int) $currentUser->id);
        return $this->json(['ok' => true]);
    }

    #[Route('/api/since/{afterId}', name: 'app_notifications_api_since', methods: ['GET'])]
    public function apiSince(int $afterId, Request $request, NotificationService $notificationService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $limit = (int) $request->query->get('limit', 20);
        $notifications = $notificationService->getSince((int) $currentUser->id, $afterId, min($limit, 100));

        return $this->json([
            'ok' => true,
            'notifications' => $notifications,
        ]);
    }

    #[Route('/api/delete/{notificationId}', name: 'app_notifications_api_delete', methods: ['DELETE'])]
    public function apiDelete(int $notificationId, NotificationService $notificationService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $notificationService->delete($notificationId);
        return $this->json(['ok' => true]);
    }
}
