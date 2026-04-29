<?php

namespace App\Tests\Service;

use App\Service\BookingValidationService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BookingValidationServiceTest extends TestCase
{
    private BookingValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BookingValidationService();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALID CASES - Should pass validation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid booking with all required fields
     */
    public function testValidateBookingWithAllRequiredFields(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            1,
            5,
            'pending',
            2,
            99.99,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid booking with confirmed status
     */
    public function testValidateBookingWithConfirmedStatus(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            10,
            20,
            'confirmed',
            1,
            49.99,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid booking with all valid statuses
     */
    public function testValidateBookingWithAllValidStatuses(): void
    {
        $statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        $bookingDate = new DateTimeImmutable();

        foreach ($statuses as $status) {
            $result = $this->service->validate(
                1,
                5,
                $status,
                1,
                50.0,
                $bookingDate
            );
            $this->assertTrue($result, "Status '{$status}' should be valid");
        }
    }

    /**
     * @test
     * Valid booking with minimum slots
     */
    public function testValidateBookingWithMinimumSlots(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            1,
            5,
            'pending',
            1,
            25.0,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid booking with maximum slots
     */
    public function testValidateBookingWithMaximumSlots(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            1,
            5,
            'pending',
            50,
            100.0,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid booking with zero cost (free class)
     */
    public function testValidateBookingWithZeroCost(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            1,
            5,
            'pending',
            2,
            0,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid booking with maximum cost
     */
    public function testValidateBookingWithMaximumCost(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            1,
            5,
            'confirmed',
            10,
            100000,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid booking with large user ID
     */
    public function testValidateBookingWithLargeUserId(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            999999,
            5,
            'pending',
            2,
            50.0,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid booking with large class ID
     */
    public function testValidateBookingWithLargeClassId(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            1,
            999999,
            'pending',
            2,
            50.0,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid booking from past year
     */
    public function testValidateBookingFromPastYear(): void
    {
        $pastDate = new DateTimeImmutable('-200 days');
        
        $result = $this->service->validate(
            1,
            5,
            'completed',
            2,
            50.0,
            $pastDate
        );

        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INVALID CASES - Should throw InvalidArgumentException
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Invalid: Zero user ID
     */
    public function testValidateBookingWithZeroUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID must be a positive integer.');

        $bookingDate = new DateTimeImmutable();
        $this->service->validate(0, 5, 'pending', 2, 50.0, $bookingDate);
    }

    /**
     * @test
     * Invalid: Negative user ID
     */
    public function testValidateBookingWithNegativeUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID must be a positive integer.');

        $bookingDate = new DateTimeImmutable();
        $this->service->validate(-1, 5, 'pending', 2, 50.0, $bookingDate);
    }

    /**
     * @test
     * Invalid: Zero class ID
     */
    public function testValidateBookingWithZeroClassId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class/Meeting ID must be a positive integer.');

        $bookingDate = new DateTimeImmutable();
        $this->service->validate(1, 0, 'pending', 2, 50.0, $bookingDate);
    }

    /**
     * @test
     * Invalid: Negative class ID
     */
    public function testValidateBookingWithNegativeClassId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class/Meeting ID must be a positive integer.');

        $bookingDate = new DateTimeImmutable();
        $this->service->validate(1, -5, 'pending', 2, 50.0, $bookingDate);
    }

    /**
     * @test
     * Invalid: User ID equals Class ID
     */
    public function testValidateBookingWithUserIdEqualsClassId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID and Class/Meeting ID cannot be the same.');

        $bookingDate = new DateTimeImmutable();
        $this->service->validate(5, 5, 'pending', 2, 50.0, $bookingDate);
    }

    /**
     * @test
     * Invalid: Invalid booking status
     */
    public function testValidateBookingWithInvalidStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid booking status');

        $bookingDate = new DateTimeImmutable();
        $this->service->validate(1, 5, 'invalid_status', 2, 50.0, $bookingDate);
    }

    /**
     * @test
     * Invalid: Number of slots too low
     */
    public function testValidateBookingWithSlotsTooLow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Number of slots must be between 1 and 50.');

        $bookingDate = new DateTimeImmutable();
        $this->service->validate(1, 5, 'pending', 0, 50.0, $bookingDate);
    }

    /**
     * @test
     * Invalid: Number of slots too high
     */
    public function testValidateBookingWithSlotsTooHigh(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Number of slots must be between 1 and 50.');

        $bookingDate = new DateTimeImmutable();
        $this->service->validate(1, 5, 'pending', 51, 50.0, $bookingDate);
    }

    /**
     * @test
     * Invalid: Negative cost
     */
    public function testValidateBookingWithNegativeCost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cost cannot be negative.');

        $bookingDate = new DateTimeImmutable();
        $this->service->validate(1, 5, 'pending', 2, -10.0, $bookingDate);
    }

    /**
     * @test
     * Invalid: Cost exceeds maximum
     */
    public function testValidateBookingWithCostTooHigh(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cost must not exceed 100000.');

        $bookingDate = new DateTimeImmutable();
        $this->service->validate(1, 5, 'pending', 2, 100001, $bookingDate);
    }

    /**
     * @test
     * Invalid: Booking date too far in the past
     */
    public function testValidateBookingWithDateTooFarInPast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Booking date cannot be more than 1 year in the past.');

        $farPastDate = new DateTimeImmutable('-400 days');
        $this->service->validate(1, 5, 'completed', 2, 50.0, $farPastDate);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDGE CASES - Boundary conditions and special scenarios
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Edge case: Mixed case status
     */
    public function testValidateBookingWithMixedCaseStatus(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            1,
            5,
            'CONFIRMED',
            2,
            50.0,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Edge case: Status with whitespace
     */
    public function testValidateBookingWithStatusWhitespace(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            1,
            5,
            '  pending  ',
            2,
            50.0,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Edge case: Cost with decimal precision
     */
    public function testValidateBookingWithDecimalCost(): void
    {
        $bookingDate = new DateTimeImmutable();
        
        $result = $this->service->validate(
            1,
            5,
            'pending',
            2,
            49.99,
            $bookingDate
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Edge case: Within one year boundary (364 days ago)
     */
    public function testValidateBookingWithinOneYearBoundary(): void
    {
        $almostOneYearAgo = new DateTimeImmutable('-364 days');
        
        $result = $this->service->validate(
            1,
            5,
            'completed',
            2,
            50.0,
            $almostOneYearAgo
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Helper: Validate cost method
     */
    public function testValidateCostMethod(): void
    {
        $result = $this->service->validateCost(99.99);

        $this->assertTrue($result);
    }

    /**
     * @test
     * Helper: Validate cost with invalid value
     */
    public function testValidateCostMethodWithInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->validateCost(-5.0);
    }

    /**
     * @test
     * Helper: Validate slots method
     */
    public function testValidateSlotsMethod(): void
    {
        $result = $this->service->validateSlots(25);

        $this->assertTrue($result);
    }

    /**
     * @test
     * Helper: Validate slots with invalid value
     */
    public function testValidateSlotsMethodWithInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->validateSlots(100);
    }

    /**
     * @test
     * Helper: Get valid statuses
     */
    public function testGetValidStatuses(): void
    {
        $statuses = $this->service->getValidStatuses();

        $this->assertCount(4, $statuses);
        $this->assertContains('pending', $statuses);
        $this->assertContains('confirmed', $statuses);
        $this->assertContains('cancelled', $statuses);
        $this->assertContains('completed', $statuses);
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
