<?php

namespace App\Tests\Service;

use App\Service\MeetingsValidationService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MeetingsValidationServiceTest extends TestCase
{
    private MeetingsValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MeetingsValidationService();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALID CASES - Should pass validation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid meeting with all required fields
     */
    public function testValidateMeetingWithAllRequiredFields(): void
    {
        $futureDate = new DateTimeImmutable('+1 day');
        
        $result = $this->service->validate(
            'Team Sync Meeting',
            1,
            10,
            $futureDate,
            'scheduled'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid meeting with description
     */
    public function testValidateMeetingWithDescription(): void
    {
        $futureDate = new DateTimeImmutable('+2 days');
        
        $result = $this->service->validate(
            'Project Planning',
            5,
            20,
            $futureDate,
            'scheduled',
            'Discuss quarterly goals and roadmap'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid meeting with location
     */
    public function testValidateMeetingWithLocation(): void
    {
        $futureDate = new DateTimeImmutable('+3 days');
        
        $result = $this->service->validate(
            'Annual Gathering',
            2,
            50,
            $futureDate,
            'scheduled',
            null,
            'Conference Room A'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid meeting with all statuses
     */
    public function testValidateMeetingWithAllValidStatuses(): void
    {
        $statuses = ['scheduled', 'cancelled', 'completed', 'postponed'];
        $futureDate = new DateTimeImmutable('+1 day');

        foreach ($statuses as $status) {
            $result = $this->service->validate(
                'Test Meeting',
                1,
                10,
                $futureDate,
                $status
            );
            $this->assertTrue($result, "Status '{$status}' should be valid");
        }
    }

    /**
     * @test
     * Valid meeting with minimum participants
     */
    public function testValidateMeetingWithMinimumParticipants(): void
    {
        $futureDate = new DateTimeImmutable('+1 day');
        
        $result = $this->service->validate(
            'One on One',
            1,
            2,
            $futureDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid meeting with maximum participants
     */
    public function testValidateMeetingWithMaximumParticipants(): void
    {
        $futureDate = new DateTimeImmutable('+1 day');
        
        $result = $this->service->validate(
            'Large Conference',
            1,
            1000,
            $futureDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid meeting with maximum description length
     */
    public function testValidateMeetingWithMaxDescriptionLength(): void
    {
        $futureDate = new DateTimeImmutable('+1 day');
        $maxDescription = str_repeat('a', 2000);
        
        $result = $this->service->validate(
            'Documentation Meeting',
            3,
            15,
            $futureDate,
            'scheduled',
            $maxDescription
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid meeting with minimum title length
     */
    public function testValidateMeetingWithMinimumTitleLength(): void
    {
        $futureDate = new DateTimeImmutable('+1 day');
        
        $result = $this->service->validate(
            'Mtg',
            1,
            5,
            $futureDate
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
    public function testValidateMeetingWithEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Meeting title cannot be empty.');

        $futureDate = new DateTimeImmutable('+1 day');
        $this->service->validate('', 1, 10, $futureDate);
    }

    /**
     * @test
     * Invalid: Title too short (less than 3 characters)
     */
    public function testValidateMeetingWithTitleTooShort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Meeting title must be at least 3 characters long.');

        $futureDate = new DateTimeImmutable('+1 day');
        $this->service->validate('MT', 1, 10, $futureDate);
    }

    /**
     * @test
     * Invalid: Description exceeds maximum length
     */
    public function testValidateMeetingWithDescriptionTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Meeting description must not exceed 2000 characters.');

        $futureDate = new DateTimeImmutable('+1 day');
        $tooLongDesc = str_repeat('a', 2001);
        $this->service->validate('Meeting', 1, 10, $futureDate, 'scheduled', $tooLongDesc);
    }

    /**
     * @test
     * Invalid: Zero organizer ID
     */
    public function testValidateMeetingWithZeroOrganizerId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Organizer ID must be a positive integer.');

        $futureDate = new DateTimeImmutable('+1 day');
        $this->service->validate('Meeting', 0, 10, $futureDate);
    }

    /**
     * @test
     * Invalid: Negative organizer ID
     */
    public function testValidateMeetingWithNegativeOrganizerId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Organizer ID must be a positive integer.');

        $futureDate = new DateTimeImmutable('+1 day');
        $this->service->validate('Meeting', -5, 10, $futureDate);
    }

    /**
     * @test
     * Invalid: Participant limit too low
     */
    public function testValidateMeetingWithParticipantLimitTooLow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Participant limit must be between 2 and 1000.');

        $futureDate = new DateTimeImmutable('+1 day');
        $this->service->validate('Meeting', 1, 1, $futureDate);
    }

    /**
     * @test
     * Invalid: Participant limit too high
     */
    public function testValidateMeetingWithParticipantLimitTooHigh(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Participant limit must be between 2 and 1000.');

        $futureDate = new DateTimeImmutable('+1 day');
        $this->service->validate('Meeting', 1, 1001, $futureDate);
    }

    /**
     * @test
     * Invalid: Meeting date in the past
     */
    public function testValidateMeetingWithPastDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Meeting date must be in the future.');

        $pastDate = new DateTimeImmutable('-1 day');
        $this->service->validate('Meeting', 1, 10, $pastDate);
    }

    /**
     * @test
     * Invalid: Meeting date is now
     */
    public function testValidateMeetingWithCurrentDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Meeting date must be in the future.');

        $now = new DateTimeImmutable();
        $this->service->validate('Meeting', 1, 10, $now);
    }

    /**
     * @test
     * Invalid: Invalid meeting status
     */
    public function testValidateMeetingWithInvalidStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid meeting status');

        $futureDate = new DateTimeImmutable('+1 day');
        $this->service->validate('Meeting', 1, 10, $futureDate, 'invalid_status');
    }

    /**
     * @test
     * Invalid: Empty location when provided
     */
    public function testValidateMeetingWithEmptyLocation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Location cannot be empty if provided.');

        $futureDate = new DateTimeImmutable('+1 day');
        $this->service->validate('Meeting', 1, 10, $futureDate, 'scheduled', null, '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDGE CASES - Boundary conditions and special scenarios
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Edge case: Mixed case status
     */
    public function testValidateMeetingWithMixedCaseStatus(): void
    {
        $futureDate = new DateTimeImmutable('+1 day');
        
        $result = $this->service->validate(
            'Meeting',
            1,
            10,
            $futureDate,
            'SCHEDULED'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Edge case: Status with whitespace
     */
    public function testValidateMeetingWithStatusWhitespace(): void
    {
        $futureDate = new DateTimeImmutable('+1 day');
        
        $result = $this->service->validate(
            'Meeting',
            1,
            10,
            $futureDate,
            '  scheduled  '
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Edge case: Very far future date
     */
    public function testValidateMeetingWithFarFutureDate(): void
    {
        $farFuture = new DateTimeImmutable('+10 years');
        
        $result = $this->service->validate(
            'Future Meeting',
            1,
            10,
            $farFuture
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Edge case: Exactly at boundary - minimum participants
     */
    public function testValidateMeetingBoundaryMinParticipants(): void
    {
        $futureDate = new DateTimeImmutable('+1 day');
        
        $result = $this->service->validateParticipantLimit(2);

        $this->assertTrue($result);
    }

    /**
     * @test
     * Edge case: Exactly at boundary - maximum participants
     */
    public function testValidateMeetingBoundaryMaxParticipants(): void
    {
        $futureDate = new DateTimeImmutable('+1 day');
        
        $result = $this->service->validateParticipantLimit(1000);

        $this->assertTrue($result);
    }

    /**
     * @test
     * Helper: Get valid statuses
     */
    public function testGetValidStatuses(): void
    {
        $statuses = $this->service->getValidStatuses();

        $this->assertCount(4, $statuses);
        $this->assertContains('scheduled', $statuses);
        $this->assertContains('cancelled', $statuses);
        $this->assertContains('completed', $statuses);
        $this->assertContains('postponed', $statuses);
    }

    /**
     * @test
     * Validate status method
     */
    public function testValidateStatusMethod(): void
    {
        $result = $this->service->validateStatus('completed');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Validate status with invalid value
     */
    public function testValidateStatusMethodWithInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->validateStatus('invalid_status');
    }
}
