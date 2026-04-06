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

        // Parse protocol: REGISTER:userId or MSG:fromId:toId:content
        if (strpos($message, 'REGISTER:') === 0) {
            $this->handleRegister($connection, $message);
        } elseif (strpos($message, 'MSG:') === 0) {
            $this->handleMessage($connection, $message);
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
            $messageEntity->setSender($sender);
            $messageEntity->setReceiver($receiver);
            $messageEntity->setContent($content);
            $messageEntity->setSentAt(new \DateTime());
            $messageEntity->setIsRead(false);

            $this->em->persist($messageEntity);
            $this->em->flush();
            
            echo "[ChatServer] Message saved to DB: ID {$messageEntity->getId()}\n";
        } catch (\Exception $e) {
            echo "[ChatServer] Database error: {$e->getMessage()}\n";
        }
    }
}

