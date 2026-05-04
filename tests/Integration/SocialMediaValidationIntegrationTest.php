<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\Post;

class SocialMediaValidationIntegrationTest extends BaseIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Create a test user in the database
     */
    private function createUser(string $email = 'user@test.com'): User
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
        $user->bio = 'User bio';
        $user->createdAt = new \DateTime();
        
        $this->persist($user);
        
        return $user;
    }

    /**
     * @test
     * Create a real post in the database
     */
    public function testCreatePostWithRealEntity(): void
    {
        $user = $this->createUser('poster@test.com');
        
        $post = new Post();
        $post->content = 'This is a test post content about my day!';
        $post->visibility = 'public';
        $post->user = $user;
        $post->createdAt = new \DateTime();
        
        $this->persist($post);
        $this->flush();
        
        // Verify post was created
        $this->assertNotNull($post->id);
        $this->assertEquals('This is a test post content about my day!', $post->content);
        $this->assertEquals('public', $post->visibility);
    }

    /**
     * @test
     * Create posts with all valid visibility levels
     */
    public function testCreatePostsWithAllValidVisibilityLevels(): void
    {
        $user = $this->createUser('poster2@test.com');
        
        $visibilities = ['public', 'friends', 'private'];
        
        foreach ($visibilities as $visibility) {
            $post = new Post();
            $post->content = "Post with $visibility visibility level";
            $post->visibility = $visibility;
            $post->user = $user;
            $post->createdAt = new \DateTime();
            
            $this->persist($post);
        }
        
        $this->flush();
        
        // Verify all visibility posts were created
        $posts = $this->em->getRepository(Post::class)->findAll();
        $this->assertGreaterThanOrEqual(3, count($posts));
    }

    /**
     * @test
     * Create posts with mood and hobby tags
     */
    public function testCreatePostsWithMoodAndHobbyTags(): void
    {
        $user = $this->createUser('poster3@test.com');
        
        $moods = ['happy', 'sad', 'excited', 'tired'];
        $hobbies = ['sports', 'music', 'gaming', 'reading'];
        
        for ($i = 0; $i < 4; $i++) {
            $post = new Post();
            $post->content = "Post $i with mood and hobby tag";
            $post->mood = $moods[$i];
            $post->hobbyTag = $hobbies[$i];
            $post->visibility = 'public';
            $post->user = $user;
            $post->createdAt = new \DateTime();
            
            $this->persist($post);
        }
        
        $this->flush();
        
        // Verify posts with tags were created
        $posts = $this->em->getRepository(Post::class)->findAll();
        $this->assertGreaterThanOrEqual(4, count($posts));
    }

    /**
     * @test
     * Create post with maximum content length
     */
    public function testCreatePostWithMaxContentLength(): void
    {
        $user = $this->createUser('poster4@test.com');
        
        $maxContent = str_repeat('a', 5000);
        
        $post = new Post();
        $post->content = $maxContent;
        $post->visibility = 'public';
        $post->user = $user;
        $post->createdAt = new \DateTime();
        
        $this->persist($post);
        $this->flush();
        
        $this->assertNotNull($post->id);
        $this->assertEquals(5000, strlen($post->content));
    }

    /**
     * @test
     * Create post with location
     */
    public function testCreatePostWithLocation(): void
    {
        $user = $this->createUser('poster5@test.com');
        
        $post = new Post();
        $post->content = 'Enjoying the view!';
        $post->location = 'Central Park, New York';
        $post->visibility = 'public';
        $post->user = $user;
        $post->createdAt = new \DateTime();
        
        $this->persist($post);
        $this->flush();
        
        $this->assertEquals('Central Park, New York', $post->location);
    }

    /**
     * @test
     * Create multiple posts from same user
     */
    public function testCreateMultiplePostsFromSameUser(): void
    {
        $user = $this->createUser('prolific@test.com');
        
        for ($i = 1; $i <= 5; $i++) {
            $post = new Post();
            $post->content = "Post $i from prolific user";
            $post->visibility = 'public';
            $post->user = $user;
            $post->createdAt = new \DateTime();
            
            $this->persist($post);
        }
        
        $this->flush();
        
        // Verify all posts from user
        $userPosts = $this->em->getRepository(Post::class)->findBy(['user' => $user]);
        $this->assertCount(5, $userPosts);
    }
}
