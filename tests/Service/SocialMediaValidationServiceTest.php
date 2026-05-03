<?php

namespace App\Tests\Service;

use App\Service\SocialMediaValidationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SocialMediaValidationServiceTest extends TestCase
{
    private SocialMediaValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SocialMediaValidationService();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALID CASES - Should pass validation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid social media post with all required fields
     */
    public function testValidatePostWithAllRequiredFields(): void
    {
        $result = $this->service->validatePost(
            'Amazing Weekend',
            'Had a great time at the beach with friends!',
            'text',
            1,
            'public'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid post with image type
     */
    public function testValidatePostWithImageType(): void
    {
        $result = $this->service->validatePost(
            'Sunset Photo',
            'Beautiful sunset captured today',
            'image',
            5,
            'friends'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid post with video type
     */
    public function testValidatePostWithVideoType(): void
    {
        $result = $this->service->validatePost(
            'Travel Vlog',
            'Exploring new cities and cultures',
            'video',
            10,
            'public'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid post with all valid post types
     */
    public function testValidatePostWithAllValidTypes(): void
    {
        $types = ['text', 'image', 'video', 'story'];

        foreach ($types as $type) {
            $result = $this->service->validatePost(
                'Test Post',
                'Test content',
                $type,
                1,
                'public'
            );
            $this->assertTrue($result, "Type '{$type}' should be valid");
        }
    }

    /**
     * @test
     * Valid post with all visibility levels
     */
    public function testValidatePostWithAllVisibilityLevels(): void
    {
        $visibilities = ['public', 'friends', 'private'];

        foreach ($visibilities as $visibility) {
            $result = $this->service->validatePost(
                'Test Post',
                'Test content',
                'text',
                1,
                $visibility
            );
            $this->assertTrue($result, "Visibility '{$visibility}' should be valid");
        }
    }

    /**
     * @test
     * Valid post with maximum content length
     */
    public function testValidatePostWithMaxContentLength(): void
    {
        $maxContent = str_repeat('a', 5000);
        $result = $this->service->validatePost(
            'Long Post',
            $maxContent,
            'text',
            1,
            'public'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid post with minimum title length (3 characters)
     */
    public function testValidatePostWithMinimumTitleLength(): void
    {
        $result = $this->service->validatePost(
            'Hi!',
            'Content here',
            'text',
            1,
            'public'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid post with large user ID
     */
    public function testValidatePostWithLargeUserId(): void
    {
        $result = $this->service->validatePost(
            'Post Title',
            'Post content',
            'text',
            999999,
            'public'
        );

        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INVALID CASES - Should throw InvalidArgumentException
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Invalid: Empty title
     */
    public function testValidatePostWithEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Post title cannot be empty.');

        $this->service->validatePost(
            '',
            'Content here',
            'text',
            1,
            'public'
        );
    }

    /**
     * @test
     * Invalid: Title with only whitespace
     */
    public function testValidatePostWithWhitespaceTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Post title cannot be empty.');

        $this->service->validatePost(
            '   ',
            'Content here',
            'text',
            1,
            'public'
        );
    }

    /**
     * @test
     * Invalid: Title too short (less than 3 characters)
     */
    public function testValidatePostWithTitleTooShort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Post title must be at least 3 characters long.');

        $this->service->validatePost(
            'Hi',
            'Content here',
            'text',
            1,
            'public'
        );
    }

    /**
     * @test
     * Invalid: Content exceeds maximum length
     */
    public function testValidatePostWithContentTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Post content must not exceed 5000 characters.');

        $tooLongContent = str_repeat('a', 5001);
        $this->service->validatePost(
            'Long Post',
            $tooLongContent,
            'text',
            1,
            'public'
        );
    }

    /**
     * @test
     * Invalid: Invalid post type
     */
    public function testValidatePostWithInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid post type');

        $this->service->validatePost(
            'Post Title',
            'Content here',
            'invalid_type',
            1,
            'public'
        );
    }

    /**
     * @test
     * Invalid: Zero user ID
     */
    public function testValidatePostWithZeroUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID must be a positive integer.');

        $this->service->validatePost(
            'Post Title',
            'Content here',
            'text',
            0,
            'public'
        );
    }

    /**
     * @test
     * Invalid: Negative user ID
     */
    public function testValidatePostWithNegativeUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID must be a positive integer.');

        $this->service->validatePost(
            'Post Title',
            'Content here',
            'text',
            -5,
            'public'
        );
    }

    /**
     * @test
     * Invalid: Invalid visibility level
     */
    public function testValidatePostWithInvalidVisibility(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid visibility');

        $this->service->validatePost(
            'Post Title',
            'Content here',
            'text',
            1,
            'secret'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDGE CASES - Boundary conditions and special scenarios
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Edge case: Mixed case post type
     */
    public function testValidatePostWithMixedCaseType(): void
    {
        $result = $this->service->validatePost(
            'Post Title',
            'Content here',
            'IMAGE',
            1,
            'public'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Edge case: Mixed case visibility
     */
    public function testValidatePostWithMixedCaseVisibility(): void
    {
        $result = $this->service->validatePost(
            'Post Title',
            'Content here',
            'text',
            1,
            'PRIVATE'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Edge case: Title with whitespace trimmed
     */
    public function testValidatePostWithTitleWhitespaceTrimmed(): void
    {
        $result = $this->service->validatePost(
            '  Hello World  ',
            'Content here',
            'text',
            1,
            'public'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Edge case: Type with whitespace
     */
    public function testValidatePostWithTypeWhitespace(): void
    {
        $result = $this->service->validatePost(
            'Post Title',
            'Content here',
            '  text  ',
            1,
            'public'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Helper: Get valid post types
     */
    public function testGetValidPostTypes(): void
    {
        $types = $this->service->getValidPostTypes();

        $this->assertCount(4, $types);
        $this->assertContains('text', $types);
        $this->assertContains('image', $types);
        $this->assertContains('video', $types);
        $this->assertContains('story', $types);
    }

    /**
     * @test
     * Helper: Get valid visibility options
     */
    public function testGetValidVisibility(): void
    {
        $visibility = $this->service->getValidVisibility();

        $this->assertCount(3, $visibility);
        $this->assertContains('public', $visibility);
        $this->assertContains('friends', $visibility);
        $this->assertContains('private', $visibility);
    }

    /**
     * @test
     * Validate post type method
     */
    public function testValidatePostTypeMethod(): void
    {
        $result = $this->service->validatePostType('video');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Validate post type with invalid type
     */
    public function testValidatePostTypeWithInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->validatePostType('invalid');
    }
}
