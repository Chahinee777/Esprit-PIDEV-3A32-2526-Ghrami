<?php

namespace App\Service;

use InvalidArgumentException;

/**
 * MessagesValidationService
 * 
 * Validates message data before persistence.
 * 
 * Business Rules:
 * 1. Sender ID must be a positive integer
 * 2. Receiver ID must be a positive integer
 * 3. Sender ID must not equal Receiver ID (cannot message yourself)
 * 4. Message content must not be empty
 * 5. Message content must not exceed 2000 characters
 */
class MessagesValidationService
{
    private const MAX_CONTENT_LENGTH = 2000;

    /**
     * Validate message data
     * 
     * @param int $senderId
     * @param int $receiverId
     * @param string $content
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validate(
        int $senderId,
        int $receiverId,
        string $content
    ): bool {
        // Rule 1: Sender ID must be positive
        if ($senderId <= 0) {
            throw new InvalidArgumentException('Sender ID must be a positive integer.');
        }

        // Rule 2: Receiver ID must be positive
        if ($receiverId <= 0) {
            throw new InvalidArgumentException('Receiver ID must be a positive integer.');
        }

        // Rule 3: Sender ID must not equal Receiver ID
        if ($senderId === $receiverId) {
            throw new InvalidArgumentException('Cannot send a message to yourself.');
        }

        // Rule 4: Content must not be empty
        $trimmedContent = trim($content);
        if (empty($trimmedContent)) {
            throw new InvalidArgumentException('Message content cannot be empty.');
        }

        // Rule 5: Content must not exceed maximum length
        if (strlen($trimmedContent) > self::MAX_CONTENT_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Message content must not exceed %d characters.',
                self::MAX_CONTENT_LENGTH
            ));
        }

        return true;
    }

    /**
     * Validate sender ID
     * 
     * @param int $senderId
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validateSenderId(int $senderId): bool
    {
        if ($senderId <= 0) {
            throw new InvalidArgumentException('Sender ID must be a positive integer.');
        }
        return true;
    }

    /**
     * Validate receiver ID
     * 
     * @param int $receiverId
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validateReceiverId(int $receiverId): bool
    {
        if ($receiverId <= 0) {
            throw new InvalidArgumentException('Receiver ID must be a positive integer.');
        }
        return true;
    }

    /**
     * Validate content
     * 
     * @param string $content
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validateContent(string $content): bool
    {
        $trimmedContent = trim($content);
        if (empty($trimmedContent)) {
            throw new InvalidArgumentException('Message content cannot be empty.');
        }

        if (strlen($trimmedContent) > self::MAX_CONTENT_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Message content must not exceed %d characters.',
                self::MAX_CONTENT_LENGTH
            ));
        }

        return true;
    }

    /**
     * Get maximum content length
     * 
     * @return int
     */
    public function getMaxContentLength(): int
    {
        return self::MAX_CONTENT_LENGTH;
    }
}
