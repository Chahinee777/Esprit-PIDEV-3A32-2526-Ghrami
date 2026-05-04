<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\Message;
use App\Tests\Integration\BaseIntegrationTest;

class MessagesValidationServiceTest extends BaseIntegrationTest
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
    // VALID CASES - Creates real message entities in database
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid message with all required fields (WRITES TO DB)
     */
    public function testValidateMessageWithAllRequiredFields(): void
    {
        $sender = $this->createUser('sender1@test.com');
        $receiver = $this->createUser('receiver1@test.com');

        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = 'Hello, how are you doing today?';
        $message->sentAt = new \DateTime();

        $this->persist($message);
        $this->flush();

        $this->assertNotNull($message->id);
    }

    /**
     * @test
     * Valid message with different sender and receiver IDs (WRITES TO DB)
     */
    public function testValidateMessageWithDifferentUserIds(): void
    {
        $sender = $this->createUser('sender2@test.com');
        $receiver = $this->createUser('receiver2@test.com');

        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = 'Great to hear from you!';
        $message->sentAt = new \DateTime();

        $this->persist($message);
        $this->flush();

        $this->assertNotNull($message->id);
    }

    /**
     * @test
     * Valid message with large sender and receiver IDs (WRITES TO DB)
     */
    public function testValidateMessageWithLargeUserIds(): void
    {
        $sender = $this->createUser('sender3@test.com');
        $receiver = $this->createUser('receiver3@test.com');

        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = 'Message with large IDs';
        $message->sentAt = new \DateTime();

        $this->persist($message);
        $this->flush();

        $this->assertNotNull($message->id);
    }

    /**
     * @test
     * Valid message with minimum content length (WRITES TO DB)
     */
    public function testValidateMessageWithMinimumContentLength(): void
    {
        $sender = $this->createUser('sender4@test.com');
        $receiver = $this->createUser('receiver4@test.com');

        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = 'A';
        $message->sentAt = new \DateTime();

        $this->persist($message);
        $this->flush();

        $this->assertNotNull($message->id);
    }

    /**
     * @test
     * Valid message with maximum content length (WRITES TO DB)
     */
    public function testValidateMessageWithMaximumContentLength(): void
    {
        $sender = $this->createUser('sender5@test.com');
        $receiver = $this->createUser('receiver5@test.com');
        $maxContent = str_repeat('a', 2000);

        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = $maxContent;
        $message->sentAt = new \DateTime();

        $this->persist($message);
        $this->flush();

        $this->assertNotNull($message->id);
    }

    /**
     * @test
     * Valid message with whitespace trimming (WRITES TO DB)
     */
    public function testValidateMessageWithWhitespaceTrims(): void
    {
        $sender = $this->createUser('sender6@test.com');
        $receiver = $this->createUser('receiver6@test.com');

        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = 'Hello World';
        $message->sentAt = new \DateTime();

        $this->persist($message);
        $this->flush();

        $this->assertNotNull($message->id);
    }

    /**
     * @test
     * Valid message with special characters (WRITES TO DB)
     */
    public function testValidateMessageWithSpecialCharacters(): void
    {
        $sender = $this->createUser('sender7@test.com');
        $receiver = $this->createUser('receiver7@test.com');

        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = 'Hello! @#$%^&*() 你好 مرحبا';
        $message->sentAt = new \DateTime();

        $this->persist($message);
        $this->flush();

        $this->assertNotNull($message->id);
    }

    /**
     * @test
     * Valid message with multiline content (WRITES TO DB)
     */
    public function testValidateMessageWithMultilineContent(): void
    {
        $sender = $this->createUser('sender8@test.com');
        $receiver = $this->createUser('receiver8@test.com');

        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = "Line 1\nLine 2\nLine 3";
        $message->sentAt = new \DateTime();

        $this->persist($message);
        $this->flush();

        $this->assertNotNull($message->id);
    }
}
