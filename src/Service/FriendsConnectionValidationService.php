<?php

namespace App\Service;

use InvalidArgumentException;

/**
 * FriendsConnectionValidationService
 * 
 * Validates friendship/connection data before persistence.
 * 
 * Business Rules:
 * 1. Connection type must be one of: mentor, mentee, friend, collaborator, hobby_buddy
 * 2. Both user IDs must be positive integers and different (cannot connect to self)
 * 3. Connection status must be one of: pending, accepted, rejected
 */
class FriendsConnectionValidationService
{
    private const VALID_TYPES = ['mentor', 'mentee', 'friend', 'collaborator', 'hobby_buddy'];
    private const VALID_STATUSES = ['pending', 'accepted', 'rejected'];

    /**
     * Validate connection data
     * 
     * @param int $initiatorId
     * @param int $receiverId
     * @param string $connectionType
     * @param string $status
     * @return true
     * @throws InvalidArgumentException
     */
    public function validate(int $initiatorId, int $receiverId, string $connectionType, string $status = 'pending'): bool
    {
        // Rule 1: Connection type must be valid
        $type = strtolower(trim($connectionType));
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid connection type "%s". Must be one of: %s',
                $connectionType,
                implode(', ', self::VALID_TYPES)
            ));
        }

        // Rule 2: User IDs must be positive and different
        if ($initiatorId <= 0) {
            throw new InvalidArgumentException('Initiator ID must be a positive integer.');
        }

        if ($receiverId <= 0) {
            throw new InvalidArgumentException('Receiver ID must be a positive integer.');
        }

        if ($initiatorId === $receiverId) {
            throw new InvalidArgumentException('Cannot create connection with yourself.');
        }

        // Rule 3: Connection status must be valid
        $status = strtolower(trim($status));
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid connection status "%s". Must be one of: %s',
                $status,
                implode(', ', self::VALID_STATUSES)
            ));
        }

        return true;
    }

    /**
     * Validate connection type only (for quick type checks)
     * 
     * @param string $connectionType
     * @return true
     * @throws InvalidArgumentException
     */
    public function validateType(string $connectionType): bool
    {
        $type = strtolower(trim($connectionType));
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid connection type "%s". Must be one of: %s',
                $connectionType,
                implode(', ', self::VALID_TYPES)
            ));
        }
        return true;
    }

    /**
     * Get all valid connection types
     * 
     * @return array<string>
     */
    public function getValidTypes(): array
    {
        return self::VALID_TYPES;
    }

    /**
     * Get all valid statuses
     * 
     * @return array<string>
     */
    public function getValidStatuses(): array
    {
        return self::VALID_STATUSES;
    }
}
