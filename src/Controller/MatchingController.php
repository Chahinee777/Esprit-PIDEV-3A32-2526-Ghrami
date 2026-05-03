<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AiSpeechService;
use App\Service\MatchingService;
use App\Service\BadgeService;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/matching')]
final class MatchingController extends AbstractController
{
    #[Route('', name: 'app_matching_index', methods: ['GET'])]
    public function index(Request $request, MatchingService $matchingService): Response
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 8);

        $selectedConversationUser = (int) $request->query->get('chatWith', 0);
        $conversation = [];
        if ($selectedConversationUser > 0) {
            $conversation = $matchingService->getConversation($userId, $selectedConversationUser);
            $matchingService->markConversationAsRead($userId, $selectedConversationUser);
        }

        return $this->render('matching/index.html.twig', [
            'userId' => $userId,
            'pending' => $matchingService->getPendingRequests($userId),
            'friends' => $matchingService->getAcceptedFriends($userId),
            'conversations' => $matchingService->listConversations($userId),
            'selectedConversationUser' => $selectedConversationUser,
            'conversation' => $conversation,
            'notifications' => $matchingService->listNotifications($userId),
            'unreadMessages' => $matchingService->getUnreadMessageCount($userId),
            'unreadNotifications' => $matchingService->getUnreadNotificationCount($userId),
        ]);
    }

    #[Route('/friend/request', name: 'app_matching_friend_request', methods: ['POST'])]
    public function sendFriendRequest(Request $request, MatchingService $matchingService): JsonResponse
    {
        $currentUser = $this->getUser();
        $fromUserId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('from_user_id');

        $matchingService->sendFriendRequest(
            $fromUserId,
            (int) $request->request->get('to_user_id')
        );

        return $this->json(['ok' => true]);
    }

    #[Route('/friend/accept', name: 'app_matching_friend_accept', methods: ['POST'])]
    public function acceptFriendRequest(Request $request, MatchingService $matchingService, BadgeService $badgeService, NotificationService $notificationService): JsonResponse
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : 0;

        $matchingService->acceptFriendRequest((int) $request->request->get('friendship_id'));

        // Check and award badges after accepting friend request
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

        return $this->json(['ok' => true]);
    }

    #[Route('/friend/reject', name: 'app_matching_friend_reject', methods: ['POST'])]
    public function rejectFriendRequest(Request $request, MatchingService $matchingService): JsonResponse
    {
        $matchingService->rejectFriendRequest((int) $request->request->get('friendship_id'));
        return $this->json(['ok' => true]);
    }

    #[Route('/friend/remove', name: 'app_matching_friend_remove', methods: ['POST'])]
    public function removeFriend(Request $request, MatchingService $matchingService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $matchingService->removeFriendship(
            (int) $request->request->get('friendship_id'),
            (int) $currentUser->id
        );

        return $this->json(['ok' => true]);
    }

    #[Route('/message/send', name: 'app_matching_message_send', methods: ['POST'])]
    public function sendMessage(Request $request, MatchingService $matchingService): JsonResponse
    {
        $currentUser = $this->getUser();
        $senderId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('sender_id');

        $msg = $matchingService->sendMessage(
            $senderId,
            (int) $request->request->get('receiver_id'),
            (string) $request->request->get('content')
        );

        return $this->json(['ok' => true, 'id' => $msg->id]);
    }

    #[Route('/stt/transcribe', name: 'app_matching_stt_transcribe', methods: ['POST'])]
    public function transcribeAudio(Request $request, AiSpeechService $aiSpeechService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $audioFile = $request->files->get('audio_file');
        if (!$audioFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json(['ok' => false, 'error' => 'Audio file is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $text = $aiSpeechService->transcribeAudio($audioFile);

            return $this->json([
                'ok' => true,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/conversation', name: 'app_matching_conversation', methods: ['GET'])]
    public function conversation(Request $request, MatchingService $matchingService): JsonResponse
    {
        $data = $matchingService->getConversation(
            (int) $request->query->get('user1'),
            (int) $request->query->get('user2')
        );

        return $this->json($data);
    }

    #[Route('/conversation/read', name: 'app_matching_conversation_read', methods: ['POST'])]
    public function markConversationRead(Request $request, MatchingService $matchingService): JsonResponse
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('user_id');
        $otherUserId = (int) $request->request->get('other_user_id');

        $matchingService->markConversationAsRead($userId, $otherUserId);

        return $this->json(['ok' => true]);
    }

    #[Route('/notification/read', name: 'app_matching_notification_read', methods: ['POST'])]
    public function markNotificationRead(Request $request, MatchingService $matchingService): Response
    {
        if (!$this->isCsrfTokenValid('matching_notification_read', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_matching_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('user_id');
        $notificationId = (int) $request->request->get('notification_id');

        $matchingService->markNotificationAsRead($userId, $notificationId);

        return $this->redirectToRoute('app_matching_index');
    }

    #[Route('/notification/read-all', name: 'app_matching_notification_read_all', methods: ['POST'])]
    public function markAllNotificationsRead(Request $request, MatchingService $matchingService): Response
    {
        if (!$this->isCsrfTokenValid('matching_notification_read_all', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_matching_index');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('user_id');

        $matchingService->markAllNotificationsAsRead($userId);

        return $this->redirectToRoute('app_matching_index');
    }

    #[Route('/connection', name: 'app_matching_connection_create', methods: ['POST'])]
    public function createConnection(Request $request, MatchingService $matchingService): JsonResponse
    {
        $currentUser = $this->getUser();
        $initiatorId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('initiator_id');

        $connection = $matchingService->createConnection(
            $initiatorId,
            (int) $request->request->get('receiver_id'),
            (string) $request->request->get('connection_type'),
            $request->request->get('receiver_skill'),
            $request->request->get('initiator_skill')
        );

        return $this->json(['ok' => true, 'id' => $connection->id]);
    }

    #[Route('/meeting', name: 'app_matching_meeting_create', methods: ['POST'])]
    public function createMeeting(Request $request, MatchingService $matchingService): JsonResponse
    {
        $currentUser = $this->getUser();
        $organizerId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('organizer_id');

        $meeting = $matchingService->createMeeting(
            (string) $request->request->get('connection_id'),
            $organizerId,
            (string) $request->request->get('meeting_type'),
            $request->request->get('location'),
            (string) $request->request->get('scheduled_at'),
            (int) $request->request->get('duration')
        );

        if ($request->request->has('participant_user_id')) {
            $matchingService->addMeetingParticipant($meeting->id, (int) $request->request->get('participant_user_id'));
        }

        return $this->json(['ok' => true, 'id' => $meeting->id]);
    }

    #[Route('/inbox/summary', name: 'app_matching_inbox_summary', methods: ['GET'])]
    public function inboxSummary(Request $request, MatchingService $matchingService): JsonResponse
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 0);

        return $this->json([
            'unreadMessages' => $matchingService->getUnreadMessageCount($userId),
            'unreadNotifications' => $matchingService->getUnreadNotificationCount($userId),
        ]);
    }

    #[Route('/typing', name: 'app_matching_typing', methods: ['POST'])]
    public function typing(Request $request, CacheItemPoolInterface $cache): JsonResponse
    {
        $currentUser = $this->getUser();
        $senderId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->request->get('sender_id', 0);
        $receiverId = (int) $request->request->get('receiver_id', 0);
        $isTyping = $request->request->getBoolean('is_typing');

        if ($senderId <= 0 || $receiverId <= 0 || $senderId === $receiverId) {
            return $this->json(['ok' => false], Response::HTTP_BAD_REQUEST);
        }

        $key = sprintf('matching_typing_%d_%d', $senderId, $receiverId);

        if ($isTyping) {
            $item = $cache->getItem($key);
            $item->set(1);
            $item->expiresAfter(7);
            $cache->save($item);
        } else {
            $cache->deleteItem($key);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/realtime/stream', name: 'app_matching_realtime_stream', methods: ['GET'])]
    public function realtimeStream(Request $request, MatchingService $matchingService, CacheItemPoolInterface $cache): Response
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 0);

        if ($userId <= 0) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $chatWith = (int) $request->query->get('chatWith', 0);
        $afterMessageId = max(0, (int) $request->query->get('afterMessageId', 0));
        $afterNotificationId = max(0, (int) $request->query->get('afterNotificationId', 0));

        $response = new StreamedResponse(function () use ($matchingService, $cache, $userId, $chatWith, $afterMessageId, $afterNotificationId): void {
            ignore_user_abort(true);
            @set_time_limit(0);

            $cursorMessageId = $afterMessageId;
            $cursorNotificationId = $afterNotificationId;
            $lastPayloadHash = '';
            $start = time();

            while ((time() - $start) < 25) {
                $matchingService->touchPresence($userId);

                $payload = [
                    'unreadMessages' => $matchingService->getUnreadMessageCount($userId),
                    'unreadNotifications' => $matchingService->getUnreadNotificationCount($userId),
                    'newMessages' => [],
                    'newNotifications' => [],
                    'presence' => $matchingService->listConversationPresence($userId),
                    'typing' => false,
                    'latestMessageId' => $cursorMessageId,
                    'latestNotificationId' => $cursorNotificationId,
                ];

                if ($chatWith > 0) {
                    $newMessages = $matchingService->getConversationSince($userId, $chatWith, $cursorMessageId);
                    if ($newMessages !== []) {
                        $payload['newMessages'] = $newMessages;
                        $cursorMessageId = (int) end($newMessages)['message_id'];
                        $payload['latestMessageId'] = $cursorMessageId;
                    } else {
                        $payload['latestMessageId'] = $matchingService->getLatestConversationMessageId($userId, $chatWith);
                    }

                    $typingKey = sprintf('matching_typing_%d_%d', $chatWith, $userId);
                    $payload['typing'] = $cache->hasItem($typingKey);
                }

                $newNotifications = $matchingService->listNotificationsSince($userId, $cursorNotificationId, 20);
                if ($newNotifications !== []) {
                    $payload['newNotifications'] = $newNotifications;
                    $cursorNotificationId = (int) end($newNotifications)['notification_id'];
                }
                $payload['latestNotificationId'] = $cursorNotificationId;

                $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
                if (!is_string($payloadJson)) {
                    $payloadJson = '{}';
                }

                $currentHash = md5($payloadJson);
                if ($currentHash !== $lastPayloadHash) {
                    echo "event: update\n";
                    echo "data: {$payloadJson}\n\n";
                    @ob_flush();
                    flush();
                    $lastPayloadHash = $currentHash;
                }

                sleep(2);
            }

            echo "event: done\n";
            echo "data: {}\n\n";
            @ob_flush();
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    #[Route('/realtime/poll', name: 'app_matching_realtime_poll', methods: ['GET'])]
    public function realtimePoll(Request $request, MatchingService $matchingService, CacheItemPoolInterface $cache): JsonResponse
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user', 0);

        if ($userId <= 0) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $chatWith = (int) $request->query->get('chatWith', 0);
        $afterMessageId = max(0, (int) $request->query->get('afterMessageId', 0));
        $afterNotificationId = max(0, (int) $request->query->get('afterNotificationId', 0));

        $matchingService->touchPresence($userId);

        $payload = [
            'ok' => true,
            'unreadMessages' => $matchingService->getUnreadMessageCount($userId),
            'unreadNotifications' => $matchingService->getUnreadNotificationCount($userId),
            'newMessages' => [],
            'newNotifications' => [],
            'presence' => $matchingService->listConversationPresence($userId),
            'typing' => false,
            'latestMessageId' => $afterMessageId,
            'latestNotificationId' => $afterNotificationId,
        ];

        if ($chatWith > 0) {
            $newMessages = $matchingService->getConversationSince($userId, $chatWith, $afterMessageId);
            if ($newMessages !== []) {
                $payload['newMessages'] = $newMessages;
                $payload['latestMessageId'] = (int) end($newMessages)['message_id'];
            } else {
                $payload['latestMessageId'] = $matchingService->getLatestConversationMessageId($userId, $chatWith);
            }

            $typingKey = sprintf('matching_typing_%d_%d', $chatWith, $userId);
            $payload['typing'] = $cache->hasItem($typingKey);
        }

        $newNotifications = $matchingService->listNotificationsSince($userId, $afterNotificationId, 20);
        if ($newNotifications !== []) {
            $payload['newNotifications'] = $newNotifications;
            $payload['latestNotificationId'] = (int) end($newNotifications)['notification_id'];
        } else {
            $payload['latestNotificationId'] = $matchingService->getLatestNotificationId($userId);
        }

        return $this->json($payload);
    }

    #[Route('/generate-reply', name: 'app_matching_generate_reply', methods: ['POST'])]
    public function generateSmartReply(Request $request, \App\Service\AiContentService $aiContentService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $messageContent = (string) $request->request->get('message', '');
        if (strlen($messageContent) < 3) {
            return $this->json(['ok' => false, 'error' => 'Message too short'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $prompt = "Generate 3 short, casual, professional reply suggestions for this message: \"$messageContent\". Return exactly 3 short suggestions (under 20 words each), one per line. Be concise and friendly.";
            
            $replies = $aiContentService->completePostText($prompt);
            $items = array_filter(array_map('trim', explode("\n", $replies)));
            $suggestions = array_slice($items, 0, 3);

            return $this->json([
                'ok' => true,
                'suggestions' => $suggestions,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => 'Failed to generate suggestions'], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/api/search-messages', name: 'app_matching_api_search_messages', methods: ['GET'])]
    public function apiSearchMessages(Request $request, \App\Service\SocialService $socialService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $otherUserId = (int) $request->query->get('user_id', 0);
        $query = (string) $request->query->get('q', '');

        if ($otherUserId <= 0 || strlen($query) < 2) {
            return $this->json(['ok' => false, 'error' => 'Invalid parameters'], Response::HTTP_BAD_REQUEST);
        }

        $results = $socialService->searchMessages((int) $currentUser->id, $otherUserId, $query);
        return $this->json(['ok' => true, 'messages' => $results]);
    }

    #[Route('/friends', name: 'app_matching_friends', methods: ['GET'])]
    public function friends(Request $request, MatchingService $matchingService, \App\Repository\UserRepository $userRepository): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $userId = (int) $currentUser->id;

        // Get friends statistics
        $friendStats = $matchingService->getFriendshipStats($userId);

        // Get all available users (except current user and existing friends)
        $allUsers = $userRepository->findAll();
        $acceptedFriends = $matchingService->getAcceptedFriendsDetailed($userId);
        $friendIds = array_column($acceptedFriends, 'friend_id');
        
        $availableUsers = array_filter($allUsers, function($u) use ($userId, $friendIds) {
            return (int)$u->id !== $userId && !in_array((int)$u->id, $friendIds);
        });

        // Get pending requests
        $pendingRequests = $matchingService->getPendingRequestsForUser($userId);

        // Get my friends
        $myFriends = $matchingService->getAcceptedFriendsDetailed($userId);

        return $this->render('matching/friends.html.twig', [
            'friendStats' => $friendStats,
            'available_users' => array_slice($availableUsers, 0, 20),
            'pending_requests' => $pendingRequests,
            'my_friends' => $myFriends,
        ]);
    }
}


