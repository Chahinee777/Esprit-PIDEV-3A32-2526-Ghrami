<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Simple File-Based Chat Server for Ghrami
 * Stores pending messages in files for real-time delivery
 * No socket dependencies - works on all platforms including Windows
 */
class ChatServerSimple
{
    private string $messageDir;
    private const MESSAGE_LIFETIME = 3600; // 1 hour

    public function __construct(string $projectDir)
    {
        $this->messageDir = $projectDir . '/var/chat_messages';
        if (!is_dir($this->messageDir)) {
            mkdir($this->messageDir, 0755, true);
        }
    }

    /**
     * Store a message for delivery to a recipient
     */
    public function storeMessage(int $senderId, int $receiverId, string $content): void
    {
        $timestamp = microtime(true);
        $filename = $this->messageDir . '/' . $receiverId . '_' . $timestamp . '.msg';
        
        $message = json_encode([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'content' => $content,
            'timestamp' => $timestamp,
        ]);
        
        file_put_contents($filename, $message);
    }

    /**
     * Poll for new messages for a user
     */
    public function getAndDeleteMessages(int $userId): array
    {
        $messages = [];
        $pattern = $this->messageDir . '/' . $userId . '_*.msg';
        
        foreach (glob($pattern) as $file) {
            if (is_file($file)) {
                $data = file_get_contents($file);
                $message = json_decode($data, true);
                
                if ($message) {
                    $messages[] = $message;
                }
                
                unlink($file);
            }
        }
        
        return $messages;
    }

    /**
     * Clean up old messages (older than MESSAGE_LIFETIME)
     */
    public function cleanup(): void
    {
        $pattern = $this->messageDir . '/*.msg';
        $now = microtime(true);
        
        foreach (glob($pattern) as $file) {
            if (filemtime($file) < $now - self::MESSAGE_LIFETIME) {
                unlink($file);
            }
        }
    }

    /**
     * Send a typing indicator notification
     */
    public function storeTypingIndicator(int $senderId, int $receiverId): void
    {
        $this->storeMessage($senderId, $receiverId, '__TYPING__');
    }
}
