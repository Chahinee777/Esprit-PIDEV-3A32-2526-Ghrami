<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\Message;

class MessagesValidationIntegrationTest extends BaseIntegrationTest
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
        $user->bio = 'Bio';
        $user->createdAt = new \DateTime();
        
        $this->persist($user);
        
        return $user;
    }

    /**
     * @test
     * Send a real message between users
     */
    public function testSendMessageWithRealEntity(): void
    {
        $sender = $this->createUser('sender@test.com');
        $receiver = $this->createUser('receiver@test.com');
        
        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = 'Hello! This is a test message.';
        $message->sentAt = new \DateTime();
        $message->isRead = false;
        
        $this->persist($message);
        $this->flush();
        
        // Verify message was created
        $this->assertNotNull($message->id);
        $this->assertEquals('Hello! This is a test message.', $message->content);
        $this->assertFalse($message->isRead);
    }

    /**
     * @test
     * Create multiple messages in conversation
     */
    public function testCreateMessageConversation(): void
    {
        $user1 = $this->createUser('user1@test.com');
        $user2 = $this->createUser('user2@test.com');
        
        // User1 sends to User2
        $message1 = new Message();
        $message1->sender = $user1;
        $message1->receiver = $user2;
        $message1->content = 'Hi there!';
        $message1->sentAt = new \DateTime();
        $message1->isRead = false;
        
        // User2 sends to User1
        $message2 = new Message();
        $message2->sender = $user2;
        $message2->receiver = $user1;
        $message2->content = 'Hello! How are you?';
        $message2->sentAt = (new \DateTime())->add(new \DateInterval('PT1M'));
        $message2->isRead = false;
        
        // User1 sends reply
        $message3 = new Message();
        $message3->sender = $user1;
        $message3->receiver = $user2;
        $message3->content = 'I am doing great!';
        $message3->sentAt = (new \DateTime())->add(new \DateInterval('PT2M'));
        $message3->isRead = false;
        
        $this->persist($message1);
        $this->persist($message2);
        $this->persist($message3);
        $this->flush();
        
        // Verify all messages were created
        $allMessages = $this->em->getRepository(Message::class)->findAll();
        $this->assertGreaterThanOrEqual(3, count($allMessages));
    }

    /**
     * @test
     * Create message with minimum content length
     */
    public function testCreateMessageWithMinimumContent(): void
    {
        $sender = $this->createUser('sender2@test.com');
        $receiver = $this->createUser('receiver2@test.com');
        
        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = 'a'; // 1 character minimum
        $message->sentAt = new \DateTime();
        $message->isRead = false;
        
        $this->persist($message);
        $this->flush();
        
        $this->assertEquals(1, strlen($message->content));
    }

    /**
     * @test
     * Create message with maximum content length
     */
    public function testCreateMessageWithMaximumContent(): void
    {
        $sender = $this->createUser('sender3@test.com');
        $receiver = $this->createUser('receiver3@test.com');
        
        $maxContent = str_repeat('a', 2000);
        
        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = $maxContent;
        $message->sentAt = new \DateTime();
        $message->isRead = false;
        
        $this->persist($message);
        $this->flush();
        
        $this->assertEquals(2000, strlen($message->content));
    }

    /**
     * @test
     * Mark messages as read
     */
    public function testMarkMessagesAsRead(): void
    {
        $sender = $this->createUser('sender4@test.com');
        $receiver = $this->createUser('receiver4@test.com');
        
        // Create unread messages
        for ($i = 1; $i <= 3; $i++) {
            $message = new Message();
            $message->sender = $sender;
            $message->receiver = $receiver;
            $message->content = "Message $i";
            $message->sentAt = new \DateTime();
            $message->isRead = false;
            
            $this->persist($message);
        }
        
        $this->flush();
        
        // Mark as read
        $unreadMessages = $this->em->getRepository(Message::class)->findBy(['isRead' => false]);
        foreach ($unreadMessages as $msg) {
            $msg->isRead = true;
        }
        
        $this->flush();
        
        $stillUnread = $this->em->getRepository(Message::class)->findBy(['isRead' => false]);
        $this->assertCount(0, $stillUnread);
    }

    /**
     * @test
     * Create message with special characters
     */
    public function testCreateMessageWithSpecialCharacters(): void
    {
        $sender = $this->createUser('sender5@test.com');
        $receiver = $this->createUser('receiver5@test.com');
        
        $specialContent = "Hello! 👋 How are you? 🎉 @mention #hashtag";
        
        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = $specialContent;
        $message->sentAt = new \DateTime();
        $message->isRead = false;
        
        $this->persist($message);
        $this->flush();
        
        $this->assertStringContainsString('👋', $message->content);
        $this->assertStringContainsString('@mention', $message->content);
    }

    /**
     * @test
     * Create multiline message
     */
    public function testCreateMultilineMessage(): void
    {
        $sender = $this->createUser('sender6@test.com');
        $receiver = $this->createUser('receiver6@test.com');
        
        $multilineContent = "Line 1\nLine 2\nLine 3\n\nParagraph 2";
        
        $message = new Message();
        $message->sender = $sender;
        $message->receiver = $receiver;
        $message->content = $multilineContent;
        $message->sentAt = new \DateTime();
        $message->isRead = false;
        
        $this->persist($message);
        $this->flush();
        
        $this->assertStringContainsString("\n", $message->content);
        $this->assertCount(5, explode("\n", $message->content));
    }
}
