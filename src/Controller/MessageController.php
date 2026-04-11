<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Service\MatchingService;
use App\Service\ChatClient;
use App\Service\ChatServerSimple;
use App\Service\SmartRepliesService;
use App\Service\VoiceTranscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/messages')]
final class MessageController extends AbstractController
{
    #[Route('', name: 'app_messages_index', methods: ['GET'])]
    public function index(Request $request, MatchingService $matchingService): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->redirectToRoute('app_login');
        }

        $userId = (int) $currentUser->id;
        $selectedConversationUser = (int) ($request->query->get('with') ?? 0);
        $conversation = [];
        
        if ($selectedConversationUser > 0) {
            $conversation = $matchingService->getConversation($userId, $selectedConversationUser);
            $matchingService->markConversationAsRead($userId, $selectedConversationUser);
        }

        // Get all conversations with metadata
        $conversations = $matchingService->listConversations($userId);
        
        // Get accepted friends for new message selector
        $acceptedFriendsRaw = $matchingService->getAcceptedFriends($userId);
        $acceptedFriendsIds = array_map(function ($f) use ($userId) {
            return $f['user1_id'] == $userId ? $f['user2_id'] : $f['user1_id'];
        }, $acceptedFriendsRaw);

        // Get all users to fetch their details
        $allUsers = $matchingService->getUsersById($acceptedFriendsIds);
        $friendsByUserId = [];
        foreach ($allUsers as $friend) {
            $friendsByUserId[$friend['user_id']] = $friend;
        }

        return $this->render('messages/index.html.twig', [
            'userId' => $userId,
            'currentUser' => $currentUser,
            'conversations' => $conversations,
            'acceptedFriends' => $friendsByUserId,
            'selectedConversationUser' => $selectedConversationUser,
            'conversation' => $conversation,
            'unreadMessageCount' => $matchingService->getUnreadMessageCount($userId),
        ]);
    }

    #[Route('/send', name: 'app_messages_send', methods: ['POST'])]
    public function send(Request $request, MatchingService $matchingService, ChatServerSimple $chatServerSimple, ValidatorInterface $validator): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $receiverId = (int) $request->request->get('receiver_id');
        $content = trim((string) $request->request->get('content', ''));

        if ($receiverId <= 0) {
            return $this->json(['error' => 'Invalid receiver_id'], 400);
        }

        $messageValidation = new Message();
        $messageValidation->content = $content;
        $violations = $validator->validate($messageValidation);
        if (count($violations) > 0) {
            return $this->json(['error' => $violations[0]->getMessage()], 400);
        }

        try {
            $message = $matchingService->sendMessage((int) $currentUser->id, $receiverId, $content);
            
            // Store message in file queue for real-time delivery
            $chatServerSimple->storeMessage((int) $currentUser->id, $receiverId, $content);
            
            return $this->json([
                'success' => true,
                'message' => [
                    'message_id' => $message->id,
                    'sender_id' => $currentUser->id,
                    'receiver_id' => $receiverId,
                    'content' => $message->content,
                    'sent_at' => $message->sentAt->format('Y-m-d H:i:s'),
                    'is_read' => $message->isRead,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/mark-as-read', name: 'app_messages_mark_read', methods: ['POST'])]
    public function markAsRead(Request $request, MatchingService $matchingService): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $otherUserId = (int) $request->request->get('other_user_id');

        if ($otherUserId <= 0) {
            return $this->json(['error' => 'Invalid other_user_id'], 400);
        }

        try {
            $matchingService->markConversationAsRead((int) $currentUser->id, $otherUserId);
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/poll', name: 'app_messages_poll', methods: ['GET'])]
    public function poll(Request $request, MatchingService $matchingService, ChatServerSimple $chatServerSimple): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $otherUserId = (int) ($request->query->get('other_user_id') ?? 0);

        if ($otherUserId <= 0) {
            return $this->json(['error' => 'Invalid other_user_id'], 400);
        }

        try {
            // Get messages from file-based queue (real-time messages)
            $queuedMessages = $chatServerSimple->getAndDeleteMessages((int) $currentUser->id);
            
            // Filter queued messages to only those from the selected conversation
            $newMessages = array_filter($queuedMessages, function ($msg) use ($otherUserId) {
                return $msg['sender_id'] == $otherUserId;
            });

            return $this->json([
                'success' => true,
                'messages' => array_values($newMessages),
                'unreadCount' => $matchingService->getUnreadMessageCount((int) $currentUser->id),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/smart-replies', name: 'app_messages_smart_replies', methods: ['POST'])]
    public function getSmartReplies(Request $request, SmartRepliesService $smartRepliesService): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $lastMessage = trim($request->request->get('last_message', ''));

        if (empty($lastMessage)) {
            return $this->json(['error' => 'Invalid parameters'], 400);
        }

        try {
            // Generate smart replies using Groq
            $replies = $smartRepliesService->generateReplies($lastMessage);

            return $this->json([
                'success' => true,
                'replies' => $replies,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/transcribe', name: 'app_messages_transcribe', methods: ['POST'])]
    public function transcribeVoice(Request $request, VoiceTranscriptionService $voiceTranscriptionService): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $audioFile = $request->files->get('audio');
        if (!$audioFile) {
            return $this->json(['error' => 'No audio file provided'], 400);
        }

        try {
            // Transcribe audio using Groq Whisper
            $transcript = $voiceTranscriptionService->transcribeFromFile($audioFile);

            if (!$transcript) {
                return $this->json(['error' => 'Could not transcribe audio'], 400);
            }

            return $this->json([
                'success' => true,
                'text' => $transcript,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

}
