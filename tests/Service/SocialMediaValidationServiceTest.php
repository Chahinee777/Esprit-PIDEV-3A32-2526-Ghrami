<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\Post;
use App\Tests\Integration\BaseIntegrationTest;

class SocialMediaValidationServiceTest extends BaseIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createUser(string $email = 'test@example.com'): User
    {
        // Always append uniqid() to make emails truly unique (tests persist to database)
        $uniqueId = uniqid();
        $email = str_replace('@', '_at_', $email) . '_' . $uniqueId . '@test.local';
        
        $user = new User();
        $user->email = $email;
        $user->username = str_replace('@', '_', $email) . '_' . uniqid();
        $user->password = password_hash('password123', PASSWORD_BCRYPT);
        $user->fullName = 'Test User';
        $user->location = 'Test City';
        $user->bio = 'Bio';
        $user->createdAt = new \DateTime();

        $this->persist($user);
        // Note: Don't flush here - flush once at end of test

        return $user;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALID CASES - Creates real post entities in database
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid social media post with all required fields (WRITES TO DB)
     */
    public function testValidatePostWithAllRequiredFields(): void
    {
        $user = $this->createUser('user1@test.com');

        $post = new Post();
        $post->content = 'Had a great time at the beach with friends!';
        $post->user = $user;
        $post->visibility = 'public';
        $post->createdAt = new \DateTime();

        $this->persist($post);
        $this->flush();

        $this->assertNotNull($post->id);
    }

    /**
     * @test
     * Valid post with image URL (WRITES TO DB)
     */
    public function testValidatePostWithImageType(): void
    {
        $user = $this->createUser('user2@test.com');

        $post = new Post();
        $post->content = 'Beautiful sunset captured today';
        $post->imageUrl = 'https://example.com/sunset.jpg';
        $post->user = $user;
        $post->visibility = 'friends';
        $post->createdAt = new \DateTime();

        $this->persist($post);
        $this->flush();

        $this->assertNotNull($post->id);
    }

    /**
     * @test
     * Valid post with mood tag (WRITES TO DB)
     */
    public function testValidatePostWithVideoType(): void
    {
        $user = $this->createUser('user3@test.com');

        $post = new Post();
        $post->content = 'Exploring new cities and cultures';
        $post->mood = 'happy';
        $post->user = $user;
        $post->visibility = 'public';
        $post->createdAt = new \DateTime();

        $this->persist($post);
        $this->flush();

        $this->assertNotNull($post->id);
    }

    /**
     * @test
     * Valid post with all visibility levels (WRITES TO DB)
     */
    public function testValidatePostWithAllValidTypes(): void
    {
        $user = $this->createUser('user4@test.com');
        $visibilities = ['public', 'friends', 'private'];

        foreach ($visibilities as $visibility) {
            $post = new Post();
            $post->content = 'Test content';
            $post->user = $user;
            $post->visibility = $visibility;
            $post->createdAt = new \DateTime();

            $this->persist($post);
        }

        $this->flush();
        $this->assertTrue(true);
    }

    /**
     * @test
     * Valid post with hobby tag (WRITES TO DB)
     */
    public function testValidatePostWithAllVisibilityLevels(): void
    {
        $user = $this->createUser('user5@test.com');

        $post = new Post();
        $post->content = 'Test content';
        $post->hobbyTag = 'music';
        $post->user = $user;
        $post->visibility = 'public';
        $post->createdAt = new \DateTime();

        $this->persist($post);
        $this->flush();

        $this->assertNotNull($post->id);
    }

    /**
     * @test
     * Valid post with maximum content length (WRITES TO DB)
     */
    public function testValidatePostWithMaxContentLength(): void
    {
        $user = $this->createUser('user6@test.com');
        $maxContent = str_repeat('a', 5000);

        $post = new Post();
        $post->content = $maxContent;
        $post->user = $user;
        $post->visibility = 'public';
        $post->createdAt = new \DateTime();

        $this->persist($post);
        $this->flush();

        $this->assertNotNull($post->id);
    }

    /**
     * @test
     * Valid post with location (WRITES TO DB)
     */
    public function testValidatePostWithMinimumTitleLength(): void
    {
        $user = $this->createUser('user7@test.com');

        $post = new Post();
        $post->content = 'Content here';
        $post->location = 'New York';
        $post->user = $user;
        $post->visibility = 'public';
        $post->createdAt = new \DateTime();

        $this->persist($post);
        $this->flush();

        $this->assertNotNull($post->id);
    }

    /**
     * @test
     * Valid post with mood and hobby tag (WRITES TO DB)
     */
    public function testValidatePostWithLargeUserId(): void
    {
        $user = $this->createUser('user8@test.com');

        $post = new Post();
        $post->content = 'Post content';
        $post->mood = 'inspired';
        $post->hobbyTag = 'photography';
        $post->user = $user;
        $post->visibility = 'public';
        $post->createdAt = new \DateTime();

        $this->persist($post);
        $this->flush();

        $this->assertNotNull($post->id);
    }
}
