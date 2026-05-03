<?php

namespace App\Service;

/**
 * Simple TCP socket server for real-time messaging, mimicking Desktop's ChatServer.
 * Protocol:
 *   Client → Server (on connect):  REGISTER:<userId>
 *   Client → Server (send msg):    MSG:<fromId>:<toId>:<content>
 *   Server → Client (relay msg):   MSG:<fromId>:<toId>:<content>
 */
class ChatSocketServer
{
    private const PORT = 9090;
    private const HOST = '0.0.0.0';

    private ?\Socket $serverSocket = null;
    private array $clients = [];
    private bool $running = false;

    public function start(): void
    {
        if ($this->running) {
            echo "[ChatServer] Already running.\n";
            return;
        }

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            echo "[ChatServer] Failed to create socket: " . socket_strerror(socket_last_error()) . "\n";
            return;
        }
        $this->serverSocket = $socket;

        socket_set_option($this->serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);

        if (!socket_bind($this->serverSocket, self::HOST, self::PORT)) {
            echo "[ChatServer] Failed to bind to port " . self::PORT . ": " . socket_strerror(socket_last_error()) . "\n";
            socket_close($this->serverSocket);
            return;
        }

        if (!socket_listen($this->serverSocket, 5)) {
            echo "[ChatServer] Failed to listen: " . socket_strerror(socket_last_error()) . "\n";
            socket_close($this->serverSocket);
            return;
        }

        $this->running = true;
        echo "[ChatServer] Listening on port " . self::PORT . " ...\n";

        while ($this->running) {
            $read = array_merge([$this->serverSocket], array_keys($this->clients));
            $write = null;
            $except = null;

            $changed = socket_select($read, $write, $except, 0, 500000); // 500ms timeout

            if ($changed === false) {
                echo "[ChatServer] Socket select error: " . socket_strerror(socket_last_error()) . "\n";
                break;
            }

            foreach ($read as $socket) {
                if ($socket === $this->serverSocket) {
                    $newClient = socket_accept($socket);
                    if ($newClient === false) {
                        echo "[ChatServer] Failed to accept: " . socket_strerror(socket_last_error()) . "\n";
                        continue;
                    }
                    $this->handleNewClient($newClient);
                } else {
                    $this->handleMessage($socket);
                }
            }
        }

        $this->stop();
    }

    private function handleNewClient(\Socket $clientSocket): void
    {
        $data = @socket_read($clientSocket, 1024, PHP_NORMAL_READ);
        if ($data === false || $data === '') {
            socket_close($clientSocket);
            return;
        }

        $line = trim($data);
        if (strpos($line, 'REGISTER:') === 0) {
            $userId = (int) trim(substr($line, 9));
            if ($userId > 0) {
                $this->clients[$userId] = $clientSocket;
                echo "[ChatServer] User $userId connected.\n";
                return;
            }
        }

        socket_close($clientSocket);
    }

    private function handleMessage(\Socket $clientSocket): void
    {
        $data = @socket_read($clientSocket, 4096, PHP_NORMAL_READ);

        if ($data === false || $data === '') {
            $this->removeClient($clientSocket);
            return;
        }

        $line = trim($data);
        if (strpos($line, 'MSG:') === 0) {
            $parts = explode(':', $line, 4);
            if (count($parts) >= 4) {
                $fromId = (int) $parts[1];
                $toId = (int) $parts[2];
                $content = $parts[3];

                // Find recipient and relay
                foreach ($this->clients as $userId => $socket) {
                    if ($userId === $toId) {
                        $msg = "MSG:$fromId:$toId:$content\n";
                        @socket_write($socket, $msg);
                        echo "[ChatServer] Message relayed from $fromId to $toId.\n";
                        return;
                    }
                }
            }
        }
    }

    private function removeClient(\Socket $clientSocket): void
    {
        foreach ($this->clients as $userId => $socket) {
            if ($socket === $clientSocket) {
                socket_close($socket);
                unset($this->clients[$userId]);
                echo "[ChatServer] User $userId disconnected.\n";
                return;
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
        foreach ($this->clients as $socket) {
            @socket_close($socket);
        }
        $this->clients = [];
        if ($this->serverSocket) {
            @socket_close($this->serverSocket);
            $this->serverSocket = null;
        }
        echo "[ChatServer] Stopped.\n";
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
}
