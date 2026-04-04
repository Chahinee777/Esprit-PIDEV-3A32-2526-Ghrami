<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Service\MatchingService;
use App\Service\ChatClient;
use App\Service\ChatServerSimple;
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
    public function getSmartReplies(Request $request, MatchingService $matchingService): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $otherUserId = (int) $request->request->get('other_user_id');
        $lastMessage = trim($request->request->get('last_message', ''));

        if ($otherUserId <= 0 || empty($lastMessage)) {
            return $this->json(['error' => 'Invalid parameters'], 400);
        }

        try {
            // Get recent message history for context
            $conversation = $matchingService->getConversation((int) $currentUser->id, $otherUserId);
            $selectedFriend = $matchingService->getUsersById([$otherUserId])[0] ?? null;
            
            if (!$selectedFriend) {
                return $this->json(['error' => 'Friend not found'], 404);
            }

            // Build context from last 6 messages
            $start = max(0, count($conversation) - 6);
            $context = '';
            foreach (array_slice($conversation, $start) as $msg) {
                $isMine = $msg['sender_id'] == $currentUser->id;
                $senderName = $isMine ? 'Moi' : $selectedFriend['username'];
                $context .= $senderName . ": " . $msg['content'] . "\n";
            }
            $context .= $selectedFriend['username'] . ": " . $lastMessage . "\n";

            // Call Groq LLaMA API
            $replies = $this->callGroqSmartReplies($context);

            return $this->json([
                'success' => true,
                'replies' => $replies,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/transcribe', name: 'app_messages_transcribe', methods: ['POST'])]
    public function transcribeVoice(Request $request): JsonResponse
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
            // Call Groq Whisper API
            $transcript = $this->transcribeWithGroq($audioFile->getPathname());

            return $this->json([
                'success' => true,
                'text' => $transcript,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Call Groq LLaMA API for smart reply suggestions
     */
    private function callGroqSmartReplies(string $context): array
    {
        $apiKey = getenv('GROQ_API_KEY');
        if (!$apiKey) {
            throw new \Exception('GROQ_API_KEY environment variable not set');
        }

        $systemPrompt = 'Tu es un assistant de messagerie. ' .
            'En te basant sur la conversation fournie, génère exactement 3 réponses courtes et naturelles ' .
            'que l\'utilisateur ("Moi") pourrait envoyer. ' .
            'Réponds UNIQUEMENT avec les 3 réponses, une par ligne, sans numérotation ni ponctuation initiale. ' .
            'Chaque réponse doit faire maximum 8 mots.';

        $requestBody = json_encode([
            'model' => 'llama-3.1-8b-instant',
            'max_tokens' => 120,
            'temperature' => 0.8,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $context],
            ],
        ]);

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Groq API error: ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid Groq API response');
        }

        $content = $data['choices'][0]['message']['content'];
        $replies = [];
        foreach (explode("\n", $content) as $line) {
            $trimmed = preg_replace('/^[\d.\-*) ]+/', '', trim($line));
            if (!empty($trimmed)) {
                $replies[] = $trimmed;
                if (count($replies) === 3) break;
            }
        }

        return $replies;
    }

    /**
     * Transcribe audio file using Groq Whisper API
     */
    private function transcribeWithGroq(string $audioFilePath): string
    {
        $apiKey = getenv('GROQ_API_KEY');
        if (!$apiKey) {
            throw new \Exception('GROQ_API_KEY environment variable not set');
        }

        // Create multipart form data
        $boundary = '----GhramiBoundary' . time();
        $body = '';

        // Add model field
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= "whisper-large-v3-turbo\r\n";

        // Add language field
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
        $body .= "fr\r\n";

        // Add response format field
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
        $body .= "json\r\n";

        // Add audio file
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"audio.wav\"\r\n";
        $body .= "Content-Type: audio/wav\r\n\r\n";
        $body .= file_get_contents($audioFilePath) . "\r\n";
        $body .= "--" . $boundary . "--\r\n";

        $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: multipart/form-data; boundary=' . $boundary,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Groq Whisper API error: ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (!isset($data['text'])) {
            throw new \Exception('Invalid Groq Whisper API response');
        }

        return $data['text'];
    }
}
