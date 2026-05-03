<?php

namespace App\Service;

use InvalidArgumentException;
use DateTimeImmutable;

/**
 * BookingValidationService
 * 
 * Validates booking data before persistence.
 * 
 * Business Rules:
 * 1. User ID must be a positive integer
 * 2. Class/Meeting ID must be a positive integer
 * 3. Booking status must be one of: pending, confirmed, cancelled, completed
 * 4. Number of slots must be between 1 and 50
 * 5. Booking date must be valid (not in far past)
 * 6. Cost must be non-negative and not exceed 100000
 * 7. User ID and Class/Meeting ID must be different
 */
class BookingValidationService
{
    private const VALID_STATUSES = ['pending', 'confirmed', 'cancelled', 'completed'];
    private const MIN_SLOTS = 1;
    private const MAX_SLOTS = 50;
    private const MAX_COST = 100000;
    private const DAYS_IN_PAST_ALLOWED = 365; // Allow bookings up to 1 year in the past

    /**
     * Validate booking data
     * 
     * @param int $userId
     * @param int $classId
     * @param string $status
     * @param int $numberOfSlots
     * @param float $cost
     * @param DateTimeImmutable $bookingDate
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validate(
        int $userId,
        int $classId,
        string $status = 'pending',
        int $numberOfSlots = 1,
        float $cost = 0,
        DateTimeImmutable $bookingDate = new DateTimeImmutable()
    ): bool {
        // Rule 1: User ID must be positive
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be a positive integer.');
        }

        // Rule 2: Class/Meeting ID must be positive
        if ($classId <= 0) {
            throw new InvalidArgumentException('Class/Meeting ID must be a positive integer.');
        }

        // Rule 7: User and Class must be different
        if ($userId === $classId) {
            throw new InvalidArgumentException('User ID and Class/Meeting ID cannot be the same.');
        }

        // Rule 3: Status must be valid
        $stat = strtolower(trim($status));
        if (!in_array($stat, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid booking status "%s". Must be one of: %s',
                $status,
                implode(', ', self::VALID_STATUSES)
            ));
        }

        // Rule 4: Number of slots must be in valid range
        if ($numberOfSlots < self::MIN_SLOTS || $numberOfSlots > self::MAX_SLOTS) {
            throw new InvalidArgumentException(sprintf(
                'Number of slots must be between %d and %d.',
                self::MIN_SLOTS,
                self::MAX_SLOTS
            ));
        }

        // Rule 6: Cost must be valid
        if ($cost < 0) {
            throw new InvalidArgumentException('Cost cannot be negative.');
        }

        if ($cost > self::MAX_COST) {
            throw new InvalidArgumentException(sprintf(
                'Cost must not exceed %d.',
                self::MAX_COST
            ));
        }

        // Rule 5: Booking date validation
        $now = new DateTimeImmutable();
        $pastLimit = $now->modify(sprintf('-%d days', self::DAYS_IN_PAST_ALLOWED));
        
        if ($bookingDate < $pastLimit) {
            throw new InvalidArgumentException('Booking date cannot be more than 1 year in the past.');
        }

        return true;
    }

    /**
     * Validate booking status
     * 
     * @param string $status
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validateStatus(string $status): bool
    {
        $stat = strtolower(trim($status));
        if (!in_array($stat, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid status "%s". Must be one of: %s',
                $status,
                implode(', ', self::VALID_STATUSES)
            ));
        }
        return true;
    }

    /**
     * Get valid statuses
     * 
     * @return array
     */
    public function getValidStatuses(): array
    {
        return self::VALID_STATUSES;
    }

    /**
     * Validate cost
     * 
     * @param float $cost
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validateCost(float $cost): bool
    {
        if ($cost < 0) {
            throw new InvalidArgumentException('Cost cannot be negative.');
        }

        if ($cost > self::MAX_COST) {
            throw new InvalidArgumentException(sprintf(
                'Cost must not exceed %d.',
                self::MAX_COST
            ));
        }

        return true;
    }

    /**
     * Validate number of slots
     * 
     * @param int $slots
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validateSlots(int $slots): bool
    {
        if ($slots < self::MIN_SLOTS || $slots > self::MAX_SLOTS) {
            throw new InvalidArgumentException(sprintf(
                'Number of slots must be between %d and %d.',
                self::MIN_SLOTS,
                self::MAX_SLOTS
            ));
        }
        return true;
    }
}
