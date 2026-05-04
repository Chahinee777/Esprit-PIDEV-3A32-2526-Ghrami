<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\Meeting;
use App\Entity\Connection;

class MeetingsValidationIntegrationTest extends BaseIntegrationTest
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
     * Generate a UUID v4 string
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Create a test connection between two users
     */
    private function createConnection(User $initiator, User $receiver): Connection
    {
        $connection = new Connection();
        $connection->id = $this->generateUuid();
        $connection->initiator = $initiator;
        $connection->receiver = $receiver;
        $connection->connectionType = 'general';
        $connection->status = 'accepted';
        
        $this->persist($connection);
        
        return $connection;
    }

    /**
     * @test
     * Create a real meeting in the database
     */
    public function testCreateMeetingWithRealEntity(): void
    {
        $organizer = $this->createUser('organizer@test.com');
        $participant = $this->createUser('participant@test.com');
        $connection = $this->createConnection($organizer, $participant);
        
        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'virtual';
        $meeting->scheduledAt = (new \DateTime())->add(new \DateInterval('P1D'));
        $meeting->duration = 60;
        $meeting->status = 'scheduled';
        
        $this->persist($meeting);
        $this->flush();
        
        // Verify meeting was created
        $this->assertNotNull($meeting->id);
        $this->assertEquals('virtual', $meeting->meetingType);
        $this->assertEquals('scheduled', $meeting->status);
    }

    /**
     * @test
     * Create meeting with physical location
     */
    public function testCreateMeetingWithPhysicalLocation(): void
    {
        $organizer = $this->createUser('organizer2@test.com');
        $participant = $this->createUser('participant2@test.com');
        $connection = $this->createConnection($organizer, $participant);
        
        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'physical';
        $meeting->location = 'Conference Room A';
        $meeting->scheduledAt = (new \DateTime())->add(new \DateInterval('P2D'));
        $meeting->duration = 90;
        $meeting->status = 'scheduled';
        
        $this->persist($meeting);
        $this->flush();
        
        $this->assertEquals('physical', $meeting->meetingType);
        $this->assertEquals('Conference Room A', $meeting->location);
    }

    /**
     * @test
     * Create meetings with all valid statuses
     */
    public function testCreateMeetingsWithAllValidStatuses(): void
    {
        $organizer = $this->createUser('organizer3@test.com');
        $participant = $this->createUser('participant3@test.com');
        $connection = $this->createConnection($organizer, $participant);
        
        $statuses = ['scheduled', 'completed', 'cancelled'];
        
        foreach ($statuses as $status) {
            $meeting = new Meeting();
            $meeting->id = $this->generateUuid();
            $meeting->connection = $connection;
            $meeting->organizer = $organizer;
            $meeting->meetingType = 'virtual';
            $meeting->scheduledAt = (new \DateTime())->add(new \DateInterval('P3D'));
            $meeting->duration = 60;
            $meeting->status = $status;
            
            $this->persist($meeting);
        }
        
        $this->flush();
        
        // Verify all statuses were persisted
        $meetings = $this->em->getRepository(Meeting::class)->findAll();
        $this->assertGreaterThanOrEqual(3, count($meetings));
    }

    /**
     * @test
     * Create meeting with minimum duration
     */
    public function testCreateMeetingWithMinimumDuration(): void
    {
        $organizer = $this->createUser('organizer4@test.com');
        $participant = $this->createUser('participant4@test.com');
        $connection = $this->createConnection($organizer, $participant);
        
        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'virtual';
        $meeting->scheduledAt = (new \DateTime())->add(new \DateInterval('P4D'));
        $meeting->duration = 1; // Minimum: 1 minute
        $meeting->status = 'scheduled';
        
        $this->persist($meeting);
        $this->flush();
        
        $this->assertEquals(1, $meeting->duration);
    }

    /**
     * @test
     * Create meeting with maximum duration
     */
    public function testCreateMeetingWithMaximumDuration(): void
    {
        $organizer = $this->createUser('organizer5@test.com');
        $participant = $this->createUser('participant5@test.com');
        $connection = $this->createConnection($organizer, $participant);
        
        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'virtual';
        $meeting->scheduledAt = (new \DateTime())->add(new \DateInterval('P5D'));
        $meeting->duration = 1440; // Maximum: 1440 minutes (24 hours)
        $meeting->status = 'scheduled';
        
        $this->persist($meeting);
        $this->flush();
        
        $this->assertEquals(1440, $meeting->duration);
    }

    /**
     * @test
     * Create multiple meetings from same organizer
     */
    public function testCreateMultipleMeetingsFromSameOrganizer(): void
    {
        $organizer = $this->createUser('organizer6@test.com');
        
        for ($i = 1; $i <= 3; $i++) {
            $participant = $this->createUser("participant_$i@test.com");
            $connection = $this->createConnection($organizer, $participant);
            
            $meeting = new Meeting();
            $meeting->id = $this->generateUuid();
            $meeting->connection = $connection;
            $meeting->organizer = $organizer;
            $meeting->meetingType = 'virtual';
            $meeting->scheduledAt = (new \DateTime())->add(new \DateInterval("P{$i}D"));
            $meeting->duration = 60;
            $meeting->status = 'scheduled';
            
            $this->persist($meeting);
        }
        
        $this->flush();
        
        // Verify all meetings from organizer
        $organizerMeetings = $this->em->getRepository(Meeting::class)->findBy(['organizer' => $organizer]);
        $this->assertCount(3, $organizerMeetings);
    }
}
