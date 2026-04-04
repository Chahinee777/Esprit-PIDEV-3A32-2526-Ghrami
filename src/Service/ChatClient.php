<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class ChatClient
{
    private const HOST = 'localhost';
    private const PORT = 9090;
    private const TIMEOUT = 5;

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Send a message through the TCP chat server
     * 
     * @param int $fromUserId Sender user ID
     * @param int $toUserId Recipient user ID
     * @param string $content Message content
     * @return bool Success status
     */
    public function sendMessage(int $fromUserId, int $toUserId, string $content): bool
    {
        try {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            
            if ($socket === false) {
                $this->logger->error('Failed to create socket: ' . socket_strerror(socket_last_error()));
                return false;
            }

            // Set socket option to allow reuse
            socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
            
            // Set read timeout
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => self::TIMEOUT, 'usec' => 0]);

            // Connect to the chat server
            if (!socket_connect($socket, self::HOST, self::PORT)) {
                $this->logger->warning('Failed to connect to chat server at ' . self::HOST . ':' . self::PORT);
                socket_close($socket);
                return false;
            }

            // Send registration message
            $registerMsg = "REGISTER:{$fromUserId}\n";
            if (!$this->writeLine($socket, $registerMsg)) {
                $this->logger->error('Failed to send REGISTER message');
                socket_close($socket);
                return false;
            }

            // Send the message
            $msgLine = "MSG:{$fromUserId}:{$toUserId}:{$content}\n";
            if (!$this->writeLine($socket, $msgLine)) {
                $this->logger->error('Failed to send message');
                socket_close($socket);
                return false;
            }

            socket_close($socket);
            $this->logger->info("Message sent from user {$fromUserId} to user {$toUserId}");
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('ChatClient error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Write a line to the socket with UTF-8 encoding
     */
    private function writeLine($socket, string $message): bool
    {
        $encoded = mb_convert_encoding($message, 'UTF-8');
        $bytesWritten = socket_write($socket, $encoded, strlen($encoded));
        
        if ($bytesWritten === false) {
            return false;
        }
        
        return $bytesWritten > 0;
    }
}
