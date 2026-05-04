<?php

namespace App\Tests\Service;

use App\Entity\Hobby;
use App\Entity\User;
use App\Tests\Integration\BaseIntegrationTest;

class HobbyValidationServiceTest extends BaseIntegrationTest
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
    // VALID CASES - Creates real hobby entities in database
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid hobby with name and valid category (WRITES TO DB)
     */
    public function testValidateWithValidNameAndCategory(): void
    {
        $user = $this->createUser('hobbyuser1@test.com');

        $hobby = new Hobby();
        $hobby->name = 'Guitar';
        $hobby->category = 'Music';
        $hobby->description = 'Learn to play acoustic guitar';
        $hobby->user = $user;

        $this->persist($hobby);
        $this->flush();

        $this->assertNotNull($hobby->id);
    }

    /**
     * @test
     * Valid hobby with name only (WRITES TO DB)
     */
    public function testValidateWithNameOnly(): void
    {
        $user = $this->createUser('hobbyuser2@test.com');

        $hobby = new Hobby();
        $hobby->name = 'Basketball';
        $hobby->category = null;
        $hobby->description = null;
        $hobby->user = $user;

        $this->persist($hobby);
        $this->flush();

        $this->assertNotNull($hobby->id);
    }

    /**
     * @test
     * Valid hobby with all valid categories (WRITES TO DB)
     */
    public function testValidateWithAllValidCategories(): void
    {
        $user = $this->createUser('hobbyuser3@test.com');
        $categories = ['Sports & Fitness', 'Arts & Crafts', 'Music', 'Cooking', 'Gaming', 'Reading', 'Technology', 'Photography'];

        foreach ($categories as $category) {
            $hobby = new Hobby();
            $hobby->name = 'Test Hobby';
            $hobby->category = $category;
            $hobby->user = $user;

            $this->persist($hobby);
        }

        $this->flush();
        $this->assertTrue(true);
    }

    /**
     * @test
     * Valid hobby with maximum length description (WRITES TO DB)
     */
    public function testValidateWithMaxLengthDescription(): void
    {
        $user = $this->createUser('hobbyuser4@test.com');
        $maxDescription = str_repeat('a', 1200);

        $hobby = new Hobby();
        $hobby->name = 'Photography';
        $hobby->category = 'Photography';
        $hobby->description = $maxDescription;
        $hobby->user = $user;

        $this->persist($hobby);
        $this->flush();

        $this->assertNotNull($hobby->id);
    }

    /**
     * @test
     * Valid hobby with minimum name length (WRITES TO DB)
     */
    public function testValidateWithMinimumNameLength(): void
    {
        $user = $this->createUser('hobbyuser5@test.com');

        $hobby = new Hobby();
        $hobby->name = 'Go';
        $hobby->category = 'Gaming';
        $hobby->user = $user;

        $this->persist($hobby);
        $this->flush();

        $this->assertNotNull($hobby->id);
    }

    /**
     * @test
     * Valid hobby with simple name (WRITES TO DB)
     */
    public function testValidateWithSimpleName(): void
    {
        $user = $this->createUser('hobbyuser6@test.com');

        $hobby = new Hobby();
        $hobby->name = 'Coding';
        $hobby->category = 'Technology';
        $hobby->user = $user;

        $this->persist($hobby);
        $this->flush();

        $this->assertNotNull($hobby->id);
    }
}
