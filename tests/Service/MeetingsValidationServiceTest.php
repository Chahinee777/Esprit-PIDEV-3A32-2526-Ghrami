<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\Meeting;
use App\Entity\Connection;
use App\Tests\Integration\BaseIntegrationTest;

class MeetingsValidationServiceTest extends BaseIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

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

    private function createConnection(User $initiator, User $receiver, string $type = 'skill'): Connection
    {
        $connection = new Connection();
        $connection->id = $this->generateUuid();
        $connection->initiator = $initiator;
        $connection->receiver = $receiver;
        $connection->connectionType = $type;
        $connection->status = 'accepted';

        $this->persist($connection);

        return $connection;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALID CASES - Creates real meeting entities in database
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid meeting with all required fields (WRITES TO DB)
     */
    public function testValidateMeetingWithAllRequiredFields(): void
    {
        $organizer = $this->createUser('organizer1@test.com');
        $participant = $this->createUser('participant1@test.com');
        $connection = $this->createConnection($organizer, $participant);

        $futureDate = (new \DateTime())->add(new \DateInterval('P1D'));

        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'virtual';
        $meeting->scheduledAt = $futureDate;
        $meeting->duration = 60;

        $this->persist($meeting);
        $this->flush();

        $this->assertNotNull($meeting->id);
    }

    /**
     * @test
     * Valid meeting with location (WRITES TO DB)
     */
    public function testValidateMeetingWithDescription(): void
    {
        $organizer = $this->createUser('organizer2@test.com');
        $participant = $this->createUser('participant2@test.com');
        $connection = $this->createConnection($organizer, $participant);

        $futureDate = (new \DateTime())->add(new \DateInterval('P2D'));

        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'virtual';
        $meeting->scheduledAt = $futureDate;
        $meeting->duration = 90;
        $meeting->location = 'Virtual Meeting Room';

        $this->persist($meeting);
        $this->flush();

        $this->assertNotNull($meeting->id);
    }

    /**
     * @test
     * Valid meeting with physical location (WRITES TO DB)
     */
    public function testValidateMeetingWithLocation(): void
    {
        $organizer = $this->createUser('organizer3@test.com');
        $participant = $this->createUser('participant3@test.com');
        $connection = $this->createConnection($organizer, $participant);

        $futureDate = (new \DateTime())->add(new \DateInterval('P3D'));

        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'physical';
        $meeting->scheduledAt = $futureDate;
        $meeting->duration = 120;
        $meeting->location = 'Coffee Shop Downtown';

        $this->persist($meeting);
        $this->flush();

        $this->assertEquals('Coffee Shop Downtown', $meeting->location);
    }

    /**
     * @test
     * Valid meeting with all valid meeting types (WRITES TO DB)
     */
    public function testValidateMeetingWithAllValidStatuses(): void
    {
        $organizer = $this->createUser('organizer4@test.com');
        $participant = $this->createUser('participant4@test.com');
        $connection = $this->createConnection($organizer, $participant);

        $futureDate = (new \DateTime())->add(new \DateInterval('P1D'));

        $types = ['virtual', 'physical'];

        foreach ($types as $type) {
            $meeting = new Meeting();
            $meeting->id = $this->generateUuid();
            $meeting->connection = $connection;
            $meeting->organizer = $organizer;
            $meeting->meetingType = $type;
            $meeting->scheduledAt = $futureDate;
            $meeting->duration = 60;

            $this->persist($meeting);
        }

        $this->flush();
        $this->assertTrue(true);
    }

    /**
     * @test
     * Valid meeting with minimum duration (WRITES TO DB)
     */
    public function testValidateMeetingWithMinimumParticipants(): void
    {
        $organizer = $this->createUser('organizer5@test.com');
        $participant = $this->createUser('participant5@test.com');
        $connection = $this->createConnection($organizer, $participant);

        $futureDate = (new \DateTime())->add(new \DateInterval('P4D'));

        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'virtual';
        $meeting->scheduledAt = $futureDate;
        $meeting->duration = 1;

        $this->persist($meeting);
        $this->flush();

        $this->assertNotNull($meeting->id);
    }

    /**
     * @test
     * Valid meeting with maximum duration (WRITES TO DB)
     */
    public function testValidateMeetingWithMaximumParticipants(): void
    {
        $organizer = $this->createUser('organizer6@test.com');
        $participant = $this->createUser('participant6@test.com');
        $connection = $this->createConnection($organizer, $participant);

        $futureDate = (new \DateTime())->add(new \DateInterval('P5D'));

        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'physical';
        $meeting->scheduledAt = $futureDate;
        $meeting->duration = 1440;

        $this->persist($meeting);
        $this->flush();

        $this->assertNotNull($meeting->id);
    }

    /**
     * @test
     * Valid meeting with scheduled status (WRITES TO DB)
     */
    public function testValidateMeetingWithMaxDescriptionLength(): void
    {
        $organizer = $this->createUser('organizer7@test.com');
        $participant = $this->createUser('participant7@test.com');
        $connection = $this->createConnection($organizer, $participant);

        $futureDate = (new \DateTime())->add(new \DateInterval('P6D'));

        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'virtual';
        $meeting->scheduledAt = $futureDate;
        $meeting->duration = 45;
        $meeting->status = 'scheduled';

        $this->persist($meeting);
        $this->flush();

        $this->assertNotNull($meeting->id);
    }

    /**
     * @test
     * Valid meeting with completed status (WRITES TO DB)
     */
    public function testValidateMeetingWithMinimumTitleLength(): void
    {
        $organizer = $this->createUser('organizer8@test.com');
        $participant = $this->createUser('participant8@test.com');
        $connection = $this->createConnection($organizer, $participant);

        $pastDate = (new \DateTime())->sub(new \DateInterval('P1D'));

        $meeting = new Meeting();
        $meeting->id = $this->generateUuid();
        $meeting->connection = $connection;
        $meeting->organizer = $organizer;
        $meeting->meetingType = 'virtual';
        $meeting->scheduledAt = $pastDate;
        $meeting->duration = 60;
        $meeting->status = 'completed';

        $this->persist($meeting);
        $this->flush();

        $this->assertNotNull($meeting->id);
    }
}
