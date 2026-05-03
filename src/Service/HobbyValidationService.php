<?php

namespace App\Service;

use InvalidArgumentException;

/**
 * HobbyValidationService
 * 
 * Validates hobby data before persistence.
 * 
 * Business Rules:
 * 1. Hobby name must not be empty and have minimum 2 characters
 * 2. Hobby category must be one of: sports, music, arts, learning, technology, gaming, other
 * 3. Description (if provided) must not exceed 500 characters
 */
class HobbyValidationService
{
    private const VALID_CATEGORIES = ['sports', 'music', 'arts', 'learning', 'technology', 'gaming', 'other'];
    private const MIN_NAME_LENGTH = 2;
    private const MAX_DESCRIPTION_LENGTH = 500;

    /**
     * Validate hobby data
     * 
     * @param string $name
     * @param string|null $category
     * @param string|null $description
     * @return true
     * @throws InvalidArgumentException
     */
    public function validate(string $name, ?string $category, ?string $description = null): bool
    {
        // Rule 1: Name must not be empty and have minimum length
        $trimmedName = trim($name);
        if (empty($trimmedName)) {
            throw new InvalidArgumentException('Hobby name cannot be empty.');
        }

        if (strlen($trimmedName) < self::MIN_NAME_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Hobby name must be at least %d characters long.',
                self::MIN_NAME_LENGTH
            ));
        }

        // Rule 2: Category must be valid (if provided)
        if ($category !== null) {
            $category = strtolower(trim($category));
            if (!in_array($category, self::VALID_CATEGORIES, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid category "%s". Must be one of: %s',
                    $category,
                    implode(', ', self::VALID_CATEGORIES)
                ));
            }
        }

        // Rule 3: Description must not exceed max length
        if ($description !== null) {
            $trimmedDescription = trim($description);
            if (strlen($trimmedDescription) > self::MAX_DESCRIPTION_LENGTH) {
                throw new InvalidArgumentException(sprintf(
                    'Description must not exceed %d characters. Current length: %d',
                    self::MAX_DESCRIPTION_LENGTH,
                    strlen($trimmedDescription)
                ));
            }
        }

        return true;
    }
}
