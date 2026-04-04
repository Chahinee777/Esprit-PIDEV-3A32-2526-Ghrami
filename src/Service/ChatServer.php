<?php

namespace App\Service;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * TCP Chat Server for Ghrami - Real-time messaging
 * Matches the Desktop JavaFX ChatServer protocol
 * 
 * Protocol:
 *   Client → Server (on connect): REGISTER:<userId>
 *   Client → Server (send msg):   MSG:<fromId>:<toId>:<content>
 *   Server → Client (relay msg):  MSG:<fromId>:<toId>:<content>
 */
class ChatServer
{
    private const PORT = 9090;
    private const HOST = '0.0.0.0';
    
    private $serverSocket;
    private $clients = [];
    private $running = false;
    private $output;

    public function __construct(?OutputInterface $output = null)
    {
        $this->output = $output;
    }

    private function log(string $message): void
    {
        $msg = "[ChatServer] " . $message;
        if ($this->output) {
            $this->output->writeln($msg);
        }
        error_log($msg);
    }

    public function start(): void
    {
        if ($this->running) {
            $this->log("Server already running");
            return;
        }

        // Enable socket extension check
        if (!extension_loaded('sockets')) {
            $this->log("ERROR: PHP sockets extension not loaded!");
            return;
        }

        // Create server socket
        $this->serverSocket = socket_create(AF_INET, SOCK_STREAM, 0);
        if ($this->serverSocket === false) {
            $this->log("ERROR: Could not create socket: " . socket_strerror(socket_last_error()));
            return;
        }

        // Reuse address
        socket_set_option($this->serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);

        // Bind socket
        if (!socket_bind($this->serverSocket, self::HOST, self::PORT)) {
            $this->log("ERROR: Could not bind socket: " . socket_strerror(socket_last_error($this->serverSocket)));
            socket_close($this->serverSocket);
            return;
        }

        // Listen for connections
        if (!socket_listen($this->serverSocket, 5)) {
            $this->log("ERROR: Could not listen on socket: " . socket_strerror(socket_last_error($this->serverSocket)));
            socket_close($this->serverSocket);
            return;
        }

        $this->running = true;
        $this->log("Server listening on port " . self::PORT);

        // Main accept loop
        while ($this->running) {
            // Use socket_select with timeout to allow graceful shutdown
            $read = [$this->serverSocket];
            $write = null;
            $except = null;
            $timeout = 1; // 1 second timeout

            $numChanged = socket_select($read, $write, $except, $timeout);

            if ($numChanged === false) {
                $this->log("ERROR: socket_select failed: " . socket_strerror(socket_last_error($this->serverSocket)));
                break;
            }

            if ($numChanged === 0) {
                continue; // Timeout, check again
            }

            // Accept new connection
            $clientSocket = @socket_accept($this->serverSocket);
            if ($clientSocket === false) {
                continue;
            }

            $this->log("New client connection from " . socket_getpeername($clientSocket, $peer_host, $peer_port) . ":" . $peer_port);
            $this->handleClient($clientSocket);
        }

        $this->stop();
    }

    public function stop(): void
    {
        $this->running = false;
        if ($this->serverSocket) {
            socket_close($this->serverSocket);
            $this->log("Server stopped");
        }
    }

    private function handleClient($clientSocket): void
    {
        $userId = null;

        try {
            // Read registration message
            $line = $this->readLine($clientSocket);
            if ($line && strpos($line, 'REGISTER:') === 0) {
                $userId = (int) substr($line, 9);
                $this->clients[$userId] = $clientSocket;
                $this->log("User $userId registered");

                // Read subsequent messages
                while ($this->running && $line = $this->readLine($clientSocket)) {
                    if (strpos($line, 'MSG:') === 0) {
                        $this->handleMessage($line);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log("ERROR handling client: " . $e->getMessage());
        } finally {
            if ($userId !== null) {
                unset($this->clients[$userId]);
                $this->log("User $userId disconnected");
            }
            @socket_close($clientSocket);
        }
    }

    private function readLine($socket): ?string
    {
        $line = '';
        $timeout = null;
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);

        while (true) {
            $char = @socket_read($socket, 1, PHP_BINARY_READ);
            if ($char === false || $char === '') {
                return $line ?: null;
            }
            if ($char === "\n") {
                return rtrim($line, "\r");
            }
            $line .= $char;
        }
    }

    private function handleMessage(string $line): void
    {
        // Format: MSG:<fromId>:<toId>:<content>
        $parts = explode(':', $line, 4);
        if (count($parts) !== 4) {
            return;
        }

        [, $fromId, $toId, $content] = $parts;
        $fromId = (int) $fromId;
        $toId = (int) $toId;

        // Relay to recipient
        if (isset($this->clients[$toId])) {
            $this->writeLine($this->clients[$toId], "MSG:$fromId:$toId:$content");
            $this->log("Relayed message from $fromId to $toId");
        }
    }

    private function writeLine($socket, string $message): void
    {
        try {
            socket_write($socket, $message . "\n", strlen($message) + 1);
        } catch (\Exception $e) {
            $this->log("ERROR writing to socket: " . $e->getMessage());
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
}
