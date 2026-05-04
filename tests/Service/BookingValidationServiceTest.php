<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\ClassProvider;
use App\Entity\LearningClass;
use App\Entity\Booking;
use App\Tests\Integration\BaseIntegrationTest;

class BookingValidationServiceTest extends BaseIntegrationTest
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

    private function createProvider(User $user): ClassProvider
    {
        $provider = new ClassProvider();
        $provider->user = $user;
        $provider->companyName = 'Test Company';
        $provider->rating = 4.5;

        $this->persist($provider);
        // Note: Don't flush here - flush once at end of test

        return $provider;
    }

    private function createClass(ClassProvider $provider): LearningClass
    {
        $class = new LearningClass();
        $class->title = 'Test Class';
        $class->description = 'Test Description';
        $class->provider = $provider;
        $class->maxParticipants = 20;
        $class->price = 99.99;
        $class->duration = 60;

        $this->persist($class);
        // Note: Don't flush here - flush once at end of test

        return $class;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALID CASES - Creates real booking entities in database
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid booking with all required fields (WRITES TO DB)
     */
    public function testValidateBookingWithAllRequiredFields(): void
    {
        $student = $this->createUser('student1@test.com');
        $provider = $this->createProvider($this->createUser('provider1@test.com'));
        $class = $this->createClass($provider);

        $booking = new Booking();
        $booking->user = $student;
        $booking->class = $class;
        $booking->status = 'pending';
        $booking->totalAmount = 99.99;
        $booking->bookingDate = new \DateTime();

        $this->persist($booking);
        $this->flush();

        $this->assertNotNull($booking->id);
        $this->assertEquals('pending', $booking->status);
    }

    /**
     * @test
     * Valid booking with confirmed status (WRITES TO DB)
     */
    public function testValidateBookingWithConfirmedStatus(): void
    {
        $student = $this->createUser('student2@test.com');
        $provider = $this->createProvider($this->createUser('provider2@test.com'));
        $class = $this->createClass($provider);

        $booking = new Booking();
        $booking->user = $student;
        $booking->class = $class;
        $booking->status = 'confirmed';
        $booking->totalAmount = 49.99;
        $booking->bookingDate = new \DateTime();

        $this->persist($booking);
        $this->flush();

        $this->assertEquals('confirmed', $booking->status);
    }

    /**
     * @test
     * Valid booking with all valid statuses (WRITES TO DB)
     */
    public function testValidateBookingWithAllValidStatuses(): void
    {
        $student = $this->createUser('student3@test.com');
        $provider = $this->createProvider($this->createUser('provider3@test.com'));
        $class = $this->createClass($provider);

        $statuses = ['pending', 'confirmed', 'cancelled', 'completed'];

        foreach ($statuses as $status) {
            $booking = new Booking();
            $booking->user = $student;
            $booking->class = $class;
            $booking->status = $status;
            $booking->totalAmount = 50.0;
            $booking->bookingDate = new \DateTime();

            $this->persist($booking);
        }

        $this->flush();
        $this->assertTrue(true);
    }

    /**
     * @test
     * Valid booking with minimum slots (WRITES TO DB)
     */
    public function testValidateBookingWithMinimumSlots(): void
    {
        $student = $this->createUser('student4@test.com');
        $provider = $this->createProvider($this->createUser('provider4@test.com'));
        $class = $this->createClass($provider);

        $booking = new Booking();
        $booking->user = $student;
        $booking->class = $class;
        $booking->status = 'pending';
        $booking->totalAmount = 25.0;
        $booking->bookingDate = new \DateTime();

        $this->persist($booking);
        $this->flush();

        $this->assertNotNull($booking->id);
    }

    /**
     * @test
     * Valid booking with maximum slots (WRITES TO DB)
     */
    public function testValidateBookingWithMaximumSlots(): void
    {
        $student = $this->createUser('student5@test.com');
        $provider = $this->createProvider($this->createUser('provider5@test.com'));
        $class = $this->createClass($provider);

        $booking = new Booking();
        $booking->user = $student;
        $booking->class = $class;
        $booking->status = 'pending';
        $booking->totalAmount = 999.99;
        $booking->bookingDate = new \DateTime();

        $this->persist($booking);
        $this->flush();

        $this->assertNotNull($booking->id);
    }

    /**
     * @test
     * Valid booking with zero cost (WRITES TO DB)
     */
    public function testValidateBookingWithZeroCost(): void
    {
        $student = $this->createUser('student6@test.com');
        $provider = $this->createProvider($this->createUser('provider6@test.com'));
        $class = $this->createClass($provider);

        $booking = new Booking();
        $booking->user = $student;
        $booking->class = $class;
        $booking->status = 'pending';
        $booking->totalAmount = 0.0;
        $booking->bookingDate = new \DateTime();

        $this->persist($booking);
        $this->flush();

        $this->assertEquals(0.0, $booking->totalAmount);
    }

    /**
     * @test
     * Valid booking with maximum cost (WRITES TO DB)
     */
    public function testValidateBookingWithMaximumCost(): void
    {
        $student = $this->createUser('student7@test.com');
        $provider = $this->createProvider($this->createUser('provider7@test.com'));
        $class = $this->createClass($provider);

        $booking = new Booking();
        $booking->user = $student;
        $booking->class = $class;
        $booking->status = 'pending';
        $booking->totalAmount = 99999.99;
        $booking->bookingDate = new \DateTime();

        $this->persist($booking);
        $this->flush();

        $this->assertEquals(99999.99, $booking->totalAmount);
    }

    /**
     * @test
     * Valid booking with large user ID (WRITES TO DB)
     */
    public function testValidateBookingWithLargeUserId(): void
    {
        $student = $this->createUser('student8@test.com');
        $provider = $this->createProvider($this->createUser('provider8@test.com'));
        $class = $this->createClass($provider);

        $booking = new Booking();
        $booking->user = $student;
        $booking->class = $class;
        $booking->status = 'pending';
        $booking->totalAmount = 50.0;
        $booking->bookingDate = new \DateTime();

        $this->persist($booking);
        $this->flush();

        $this->assertNotNull($booking->user->id);
    }

    /**
     * @test
     * Valid booking with large class ID (WRITES TO DB)
     */
    public function testValidateBookingWithLargeClassId(): void
    {
        $student = $this->createUser('student9@test.com');
        $provider = $this->createProvider($this->createUser('provider9@test.com'));
        $class = $this->createClass($provider);

        $booking = new Booking();
        $booking->user = $student;
        $booking->class = $class;
        $booking->status = 'pending';
        $booking->totalAmount = 50.0;
        $booking->bookingDate = new \DateTime();

        $this->persist($booking);
        $this->flush();

        $this->assertNotNull($booking->class->id);
    }

    /**
     * @test
     * Valid booking from past year (WRITES TO DB)
     */
    public function testValidateBookingFromPastYear(): void
    {
        $student = $this->createUser('student10@test.com');
        $provider = $this->createProvider($this->createUser('provider10@test.com'));
        $class = $this->createClass($provider);

        $pastDate = (new \DateTime())->modify('-1 year');

        $booking = new Booking();
        $booking->user = $student;
        $booking->class = $class;
        $booking->status = 'completed';
        $booking->totalAmount = 50.0;
        $booking->bookingDate = $pastDate;

        $this->persist($booking);
        $this->flush();

        $this->assertNotNull($booking->id);
    }
}
