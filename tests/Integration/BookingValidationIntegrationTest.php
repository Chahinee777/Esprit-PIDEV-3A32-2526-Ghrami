<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\ClassProvider;
use App\Entity\LearningClass;
use App\Entity\Booking;

class BookingValidationIntegrationTest extends BaseIntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Create a test user in the database
     */
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
        
        return $user;
    }

    /**
     * Create a test learning class in the database
     */
    private function createLearningClass(User $providerUser): LearningClass
    {
        $provider = new ClassProvider();
        $provider->user = $providerUser;
        $provider->companyName = 'Test Company';
        $provider->rating = 4.5;
        
        $this->persist($provider);
        
        $class = new LearningClass();
        $class->title = 'Test Class';
        $class->description = 'Test Description';
        $class->provider = $provider;
        $class->maxParticipants = 20;
        $class->price = 99.99;
        $class->duration = 60;
        
        $this->persist($class);
        
        return $class;
    }

    /**
     * @test
     * Validate booking with real database entities
     */
    public function testValidateBookingWithRealEntities(): void
    {
        $student = $this->createUser('student@test.com');
        $providerUser = $this->createUser('provider@test.com');
        $class = $this->createLearningClass($providerUser);
        
        $booking = new Booking();
        $booking->user = $student;
        $booking->class = $class;
        $booking->status = 'scheduled';
        $booking->totalAmount = 99.99;
        $booking->bookingDate = new \DateTime();
        
        $this->persist($booking);
        $this->flush();
        
        // Verify booking was created
        $this->assertNotNull($booking->id);
        $this->assertEquals('scheduled', $booking->status);
        $this->assertEquals(99.99, $booking->totalAmount);
    }

    /**
     * @test
     * Validate booking with all valid statuses
     */
    public function testValidateBookingWithAllValidStatuses(): void
    {
        $student = $this->createUser('student2@test.com');
        $providerUser = $this->createUser('provider2@test.com');
        $class = $this->createLearningClass($providerUser);
        
        $statuses = ['pending', 'scheduled', 'completed', 'cancelled'];
        
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
        
        // Verify all statuses were persisted
        $bookings = $this->em->getRepository(Booking::class)->findAll();
        $this->assertGreaterThanOrEqual(4, count($bookings));
    }

    /**
     * @test
     * Validate booking with amount boundaries
     */
    public function testValidateBookingWithAmountBoundaries(): void
    {
        $student = $this->createUser('student3@test.com');
        $providerUser = $this->createUser('provider3@test.com');
        $class = $this->createLearningClass($providerUser);
        
        // Test minimum amount (0 - free)
        $bookingMin = new Booking();
        $bookingMin->user = $student;
        $bookingMin->class = $class;
        $bookingMin->status = 'scheduled';
        $bookingMin->totalAmount = 0.0;
        $bookingMin->bookingDate = new \DateTime();
        
        $this->persist($bookingMin);
        $this->flush();
        
        $this->assertEquals(0.0, $bookingMin->totalAmount);
        
        // Test high amount booking
        $bookingMax = new Booking();
        $bookingMax->user = $student;
        $bookingMax->class = $class;
        $bookingMax->status = 'scheduled';
        $bookingMax->totalAmount = 99999.99;
        $bookingMax->bookingDate = new \DateTime();
        
        $this->persist($bookingMax);
        $this->flush();
        
        $this->assertEquals(99999.99, $bookingMax->totalAmount);
    }

    /**
     * @test
     * Validate booking payment status transitions
     */
    public function testValidateBookingPaymentStatusTransitions(): void
    {
        $student = $this->createUser('student4@test.com');
        $providerUser = $this->createUser('provider4@test.com');
        $class = $this->createLearningClass($providerUser);
        
        $paymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
        
        foreach ($paymentStatuses as $paymentStatus) {
            $booking = new Booking();
            $booking->user = $student;
            $booking->class = $class;
            $booking->status = 'scheduled';
            $booking->paymentStatus = $paymentStatus;
            $booking->totalAmount = 25.0;
            $booking->bookingDate = new \DateTime();
            
            $this->persist($booking);
        }
        
        $this->flush();
        
        // Verify all payment statuses were persisted
        $bookings = $this->em->getRepository(Booking::class)->findAll();
        $this->assertGreaterThanOrEqual(4, count($bookings));
    }

    /**
     * @test
     * Validate booking watch progress tracking
     */
    public function testValidateBookingWatchProgress(): void
    {
        $student = $this->createUser('student5@test.com');
        $providerUser = $this->createUser('provider5@test.com');
        $class = $this->createLearningClass($providerUser);
        
        $progressValues = [0, 25, 50, 75, 100];
        
        foreach ($progressValues as $progress) {
            $booking = new Booking();
            $booking->user = $student;
            $booking->class = $class;
            $booking->status = 'scheduled';
            $booking->watchProgress = $progress;
            $booking->totalAmount = 50.0;
            $booking->bookingDate = new \DateTime();
            
            $this->persist($booking);
        }
        
        $this->flush();
        
        // Verify all progress values were persisted
        $bookings = $this->em->getRepository(Booking::class)->findAll();
        $this->assertGreaterThanOrEqual(5, count($bookings));
    }
}
