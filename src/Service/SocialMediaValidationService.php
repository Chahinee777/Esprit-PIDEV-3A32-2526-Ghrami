<?php

namespace App\Service;

use InvalidArgumentException;

/**
 * SocialMediaValidationService
 * 
 * Validates social media post and comment data before persistence.
 * 
 * Business Rules:
 * 1. Post title must not be empty and have minimum 3 characters
 * 2. Post content must not exceed 5000 characters
 * 3. Post type must be one of: text, image, video, story
 * 4. User ID must be a positive integer
 * 5. Visibility must be one of: public, friends, private
 */
class SocialMediaValidationService
{
    private const VALID_POST_TYPES = ['text', 'image', 'video', 'story'];
    private const VALID_VISIBILITY = ['public', 'friends', 'private'];
    private const MIN_TITLE_LENGTH = 3;
    private const MAX_CONTENT_LENGTH = 5000;

    /**
     * Validate social media post data
     * 
     * @param string $title
     * @param string $content
     * @param string $postType
     * @param int $userId
     * @param string $visibility
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validatePost(
        string $title,
        string $content,
        string $postType = 'text',
        int $userId = 0,
        string $visibility = 'public'
    ): bool {
        // Rule 1: Title must not be empty and have minimum length
        $trimmedTitle = trim($title);
        if (empty($trimmedTitle)) {
            throw new InvalidArgumentException('Post title cannot be empty.');
        }

        if (strlen($trimmedTitle) < self::MIN_TITLE_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Post title must be at least %d characters long.',
                self::MIN_TITLE_LENGTH
            ));
        }

        // Rule 2: Content must not exceed maximum length
        $trimmedContent = trim($content);
        if (strlen($trimmedContent) > self::MAX_CONTENT_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Post content must not exceed %d characters.',
                self::MAX_CONTENT_LENGTH
            ));
        }

        // Rule 3: Post type must be valid
        $type = strtolower(trim($postType));
        if (!in_array($type, self::VALID_POST_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid post type "%s". Must be one of: %s',
                $postType,
                implode(', ', self::VALID_POST_TYPES)
            ));
        }

        // Rule 4: User ID must be positive
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be a positive integer.');
        }

        // Rule 5: Visibility must be valid
        $vis = strtolower(trim($visibility));
        if (!in_array($vis, self::VALID_VISIBILITY, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid visibility "%s". Must be one of: %s',
                $visibility,
                implode(', ', self::VALID_VISIBILITY)
            ));
        }

        return true;
    }

    /**
     * Validate post type
     * 
     * @param string $postType
     * @return bool
     * @throws InvalidArgumentException
     */
    public function validatePostType(string $postType): bool
    {
        $type = strtolower(trim($postType));
        if (!in_array($type, self::VALID_POST_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid post type "%s". Must be one of: %s',
                $postType,
                implode(', ', self::VALID_POST_TYPES)
            ));
        }
        return true;
    }

    /**
     * Get valid post types
     * 
     * @return array
     */
    public function getValidPostTypes(): array
    {
        return self::VALID_POST_TYPES;
    }

    /**
     * Get valid visibility options
     * 
     * @return array
     */
    public function getValidVisibility(): array
    {
        return self::VALID_VISIBILITY;
    }
}
