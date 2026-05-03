<?php

namespace App\Service;

use InvalidArgumentException;
use DateTimeImmutable;

/**
 * MeetingsValidationService
 * 
 * Validates meeting data before persistence.
 * 
 * Business Rules:
 * 1. Meeting title must not be empty and have minimum 3 characters
 * 2. Description (if provided) must not exceed 2000 characters
 * 3. Organizer ID must be a positive integer
 * 4. Participant limit must be between 2 and 1000
 * 5. Meeting date must be in the future
 * 6. Meeting status must be one of: scheduled, cancelled, completed, postponed
 * 7. Location must be non-empty if meeting is in-person
 */
class MeetingsValidationService
{
    private const VALID_STATUSES = ['scheduled', 'cancelled', 'completed', 'postponed'];
    private const MIN_TITLE_LENGTH = 3;
    private const MAX_DESCRIPTION_LENGTH = 2000;
    private const MIN_PARTICIPANTS = 2;
    private const MAX_PARTICIPANTS = 1000;

    /**
     * Validate meeting data
     * 
     * @param string $title
     * @param int $organizerId
     * @param int $participantLimit
     * @param DateTimeImmutable $meetingDate
     * @param string $status
     * @param string|null $description
     * @param string|null $location
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validate(
        string $title,
        int $organizerId,
        int $participantLimit,
        DateTimeImmutable $meetingDate,
        string $status = 'scheduled',
        ?string $description = null,
        ?string $location = null
    ): bool {
        // Rule 1: Title must not be empty and have minimum length
        $trimmedTitle = trim($title);
        if (empty($trimmedTitle)) {
            throw new InvalidArgumentException('Meeting title cannot be empty.');
        }

        if (strlen($trimmedTitle) < self::MIN_TITLE_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Meeting title must be at least %d characters long.',
                self::MIN_TITLE_LENGTH
            ));
        }

        // Rule 2: Description must not exceed maximum length
        if ($description !== null) {
            $trimmedDesc = trim($description);
            if (strlen($trimmedDesc) > self::MAX_DESCRIPTION_LENGTH) {
                throw new InvalidArgumentException(sprintf(
                    'Meeting description must not exceed %d characters.',
                    self::MAX_DESCRIPTION_LENGTH
                ));
            }
        }

        // Rule 3: Organizer ID must be positive
        if ($organizerId <= 0) {
            throw new InvalidArgumentException('Organizer ID must be a positive integer.');
        }

        // Rule 4: Participant limit must be valid range
        if ($participantLimit < self::MIN_PARTICIPANTS || $participantLimit > self::MAX_PARTICIPANTS) {
            throw new InvalidArgumentException(sprintf(
                'Participant limit must be between %d and %d.',
                self::MIN_PARTICIPANTS,
                self::MAX_PARTICIPANTS
            ));
        }

        // Rule 5: Meeting date must be in the future
        $now = new DateTimeImmutable();
        if ($meetingDate <= $now) {
            throw new InvalidArgumentException('Meeting date must be in the future.');
        }

        // Rule 6: Status must be valid
        $stat = strtolower(trim($status));
        if (!in_array($stat, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid meeting status "%s". Must be one of: %s',
                $status,
                implode(', ', self::VALID_STATUSES)
            ));
        }

        // Rule 7: Location validation if in-person
        if ($location !== null) {
            $trimmedLocation = trim($location);
            if (empty($trimmedLocation)) {
                throw new InvalidArgumentException('Location cannot be empty if provided.');
            }
        }

        return true;
    }

    /**
     * Validate meeting status
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
     * Validate participant limit
     * 
     * @param int $limit
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validateParticipantLimit(int $limit): bool
    {
        if ($limit < self::MIN_PARTICIPANTS || $limit > self::MAX_PARTICIPANTS) {
            throw new InvalidArgumentException(sprintf(
                'Participant limit must be between %d and %d.',
                self::MIN_PARTICIPANTS,
                self::MAX_PARTICIPANTS
            ));
        }
        return true;
    }
}
