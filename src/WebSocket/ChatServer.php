<?php

namespace App\WebSocket;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Message;
use App\Entity\User;
use Workerman\Connection\TcpConnection;

/**
 * Real-time Chat WebSocket Server Handler using Workerman
 * 
 * Protocol (same as Desktop ChatClient):
 *   On connect:  REGISTER:{userId}
 *   Send msg:    MSG:{fromId}:{toId}:{content}
 *   Receive msg: MSG:{fromId}:{toId}:{content}
 *   Typing:      __TYPING__ (as content)
 */
class ChatServer
{
    /** @var array<int, TcpConnection> Map of userId => Connection */
    private array $userConnections = [];

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function onConnect(TcpConnection $connection): void
    {
        echo "[ChatServer] New connection. ID: {$connection->id}\n";
    }

    public function onMessage(TcpConnection $connection, $msg): void
    {
        $message = trim($msg);
        
        if (empty($message)) {
            return;
        }

        // Parse protocol: REGISTER:userId or MSG:fromId:toId:content or CALL_*
        if (strpos($message, 'REGISTER:') === 0) {
            $this->handleRegister($connection, $message);
        } elseif (strpos($message, 'MSG:') === 0) {
            $this->handleMessage($connection, $message);
        } elseif (strpos($message, 'CALL_INIT:') === 0) {
            $this->handleCallInit($connection, $message);
        } elseif (strpos($message, 'CALL_ANSWER:') === 0) {
            $this->handleCallAnswer($connection, $message);
        } elseif (strpos($message, 'CALL_REJECT:') === 0) {
            $this->handleCallReject($connection, $message);
        } elseif (strpos($message, 'CALL_OFFER:') === 0) {
            $this->handleCallOffer($connection, $message);
        } elseif (strpos($message, 'CALL_ANSWER_OFFER:') === 0) {
            $this->handleCallAnswerOffer($connection, $message);
        } elseif (strpos($message, 'CALL_ICE:') === 0) {
            $this->handleCallIce($connection, $message);
        } elseif (strpos($message, 'CALL_END:') === 0) {
            $this->handleCallEnd($connection, $message);
        } else {
            echo "[ChatServer] Unknown message format: {$message}\n";
        }
    }

    public function onClose(TcpConnection $connection): void
    {
        // Find and remove user
        $userId = array_search($connection, $this->userConnections, true);
        if ($userId !== false) {
            unset($this->userConnections[$userId]);
            echo "[ChatServer] User {$userId} disconnected\n";
        }
    }

    public function onError(TcpConnection $connection, int $code, string $msg): void
    {
        echo "[ChatServer] Error ({$code}): {$msg}\n";
    }

    /**
     * Handle REGISTER:userId
     * Stores the user connection so we can route messages to them
     */
    private function handleRegister(TcpConnection $connection, string $message): void
    {
        $parts = explode(':', $message, 2);
        if (count($parts) < 2) {
            echo "[ChatServer] Invalid REGISTER format: {$message}\n";
            return;
        }

        $userId = (int) $parts[1];
        
        // If user was already connected, close old connection
        if (isset($this->userConnections[$userId])) {
            echo "[ChatServer] User {$userId} reconnecting, closing old connection\n";
            $this->userConnections[$userId]->close();
        }

        $this->userConnections[$userId] = $connection;
        
        echo "[ChatServer] User {$userId} registered\n";
    }

    /**
     * Handle MSG:fromId:toId:content
     * Route message to recipient if connected, save to DB regardless
     */
    private function handleMessage(TcpConnection $from, string $message): void
    {
        // Parse: MSG:fromId:toId:content (using explode with limit to preserve colons in content)
        $colonPos = strpos($message, ':');
        if ($colonPos === false) return;
        
        $rest = substr($message, $colonPos + 1);
        $parts = explode(':', $rest, 3);
        
        if (count($parts) < 3) {
            echo "[ChatServer] Invalid MSG format: {$message}\n";
            return;
        }

        $fromId = (int) $parts[0];
        $toId = (int) $parts[1];
        $content = $parts[2];

        // Special handling for typing indicator
        if ($content === '__TYPING__') {
            // Send typing indicator to recipient only
            if (isset($this->userConnections[$toId])) {
                $this->userConnections[$toId]->send("MSG:{$fromId}:{$toId}:__TYPING__");
            }
            return;
        }

        // Broadcast message to recipient if connected
        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send($message);
            echo "[ChatServer] Message routed: {$fromId} -> {$toId}\n";
        } else {
            echo "[ChatServer] Recipient {$toId} not connected, message queued in DB\n";
        }

        // Always save to database
        try {
            $sender = $this->em->find(User::class, $fromId);
            $receiver = $this->em->find(User::class, $toId);

            if (!$sender || !$receiver) {
                echo "[ChatServer] User not found: sender={$fromId}, receiver={$toId}\n";
                return;
            }

            $messageEntity = new Message();
            $messageEntity->sender = $sender;
            $messageEntity->receiver = $receiver;
            $messageEntity->content = $content;
            $messageEntity->sentAt = new \DateTime();
            $messageEntity->isRead = false;

            $this->em->persist($messageEntity);
            $this->em->flush();
            
            echo "[ChatServer] Message saved to DB: ID {$messageEntity->id}\n";
        } catch (\Exception $e) {
            echo "[ChatServer] Database error: {$e->getMessage()}\n";
        }
    }

    /**
     * Handle CALL_INIT:fromId:toId (initiate incoming call)
     * Route to recipient with ringing notification
     */
    private function handleCallInit(TcpConnection $from, string $message): void
    {
        // Parse: CALL_INIT:fromId:toId
        $parts = explode(':', $message, 3);
        if (count($parts) < 3) {
            echo "[ChatServer] Invalid CALL_INIT format: {$message}\n";
            return;
        }

        $fromId = (int) $parts[1];
        $toId = (int) $parts[2];

        // Route ringing notification to recipient if connected
        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send("CALL_INIT:{$fromId}:{$toId}");
            echo "[ChatServer] Call initiated: {$fromId} -> {$toId}\n";
        } else {
            echo "[ChatServer] Recipient {$toId} not connected for call\n";
        }
    }

    /**
     * Handle CALL_ANSWER:fromId:toId (accept call)
     * Send to caller that recipient accepted
     */
    private function handleCallAnswer(TcpConnection $from, string $message): void
    {
        $parts = explode(':', $message, 3);
        if (count($parts) < 3) {
            echo "[ChatServer] Invalid CALL_ANSWER format: {$message}\n";
            return;
        }

        $fromId = (int) $parts[1];
        $toId = (int) $parts[2];

        // Send acceptance to caller
        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send("CALL_ANSWER:{$fromId}:{$toId}");
            echo "[ChatServer] Call accepted: {$toId} accepted call from {$fromId}\n";
        }
    }

    /**
     * Handle CALL_REJECT:fromId:toId (decline call)
     * Send rejection to caller
     */
    private function handleCallReject(TcpConnection $from, string $message): void
    {
        $parts = explode(':', $message, 3);
        if (count($parts) < 3) {
            echo "[ChatServer] Invalid CALL_REJECT format: {$message}\n";
            return;
        }

        $fromId = (int) $parts[1];
        $toId = (int) $parts[2];

        // Send rejection to caller
        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send("CALL_REJECT:{$fromId}:{$toId}");
            echo "[ChatServer] Call rejected: {$toId} rejected call from {$fromId}\n";
        }
    }

    /**
     * Handle CALL_OFFER:fromId:toId:sdpOffer (WebRTC offer)
     * Route SDP offer to recipient for peer connection
     */
    private function handleCallOffer(TcpConnection $from, string $message): void
    {
        // Parse: CALL_OFFER:fromId:toId:sdpData
        // Use regex to properly extract JSON without breaking on colons it contains
        if (!preg_match('/^CALL_OFFER:(\d+):(\d+):(.+)$/s', $message, $matches)) {
            echo "[ChatServer] Invalid CALL_OFFER format: {$message}\n";
            return;
        }

        $fromId = (int) $matches[1];
        $toId = (int) $matches[2];
        $sdpOffer = $matches[3];

        // Route offer to recipient
        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send("CALL_OFFER:{$fromId}:{$toId}:{$sdpOffer}");
            echo "[ChatServer] WebRTC offer routed: {$fromId} -> {$toId}\n";
        }
    }

    /**
     * Handle CALL_ANSWER_OFFER:fromId:toId:sdpAnswer (WebRTC answer)
     * Route SDP answer to caller
     */
    private function handleCallAnswerOffer(TcpConnection $from, string $message): void
    {
        // Parse: CALL_ANSWER_OFFER:fromId:toId:sdpData
        // Use regex to properly extract JSON without breaking on colons it contains
        if (!preg_match('/^CALL_ANSWER_OFFER:(\d+):(\d+):(.+)$/s', $message, $matches)) {
            echo "[ChatServer] Invalid CALL_ANSWER_OFFER format: {$message}\n";
            return;
        }

        $fromId = (int) $matches[1];
        $toId = (int) $matches[2];
        $sdpAnswer = $matches[3];

        // Route answer to caller
        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send("CALL_ANSWER_OFFER:{$fromId}:{$toId}:{$sdpAnswer}");
            echo "[ChatServer] WebRTC answer routed: {$fromId} -> {$toId}\n";
        }
    }

    /**
     * Handle CALL_ICE:fromId:toId:candidate (ICE candidate for NAT traversal)
     * Route ICE candidate to peer
     */
    private function handleCallIce(TcpConnection $from, string $message): void
    {
        // Parse: CALL_ICE:fromId:toId:candidateData
        // Use regex to properly extract JSON without breaking on colons it contains
        if (!preg_match('/^CALL_ICE:(\d+):(\d+):(.+)$/s', $message, $matches)) {
            echo "[ChatServer] Invalid CALL_ICE format: {$message}\n";
            return;
        }

        $fromId = (int) $matches[1];
        $toId = (int) $matches[2];
        $candidate = $matches[3];

        // Route ICE candidate to peer
        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send("CALL_ICE:{$fromId}:{$toId}:{$candidate}");
            // Don't spam logs for ICE candidates
        }
    }

    /**
     * Handle CALL_END:fromId:toId (end call)
     * Notify peer that call has ended
     */
    private function handleCallEnd(TcpConnection $from, string $message): void
    {
        $parts = explode(':', $message, 3);
        if (count($parts) < 3) {
            echo "[ChatServer] Invalid CALL_END format: {$message}\n";
            return;
        }

        $fromId = (int) $parts[1];
        $toId = (int) $parts[2];

        // Notify peer of call end
        if (isset($this->userConnections[$toId])) {
            $this->userConnections[$toId]->send("CALL_END:{$fromId}:{$toId}");
            echo "[ChatServer] Call ended: {$fromId} ended call with {$toId}\n";
        }
    }
}

