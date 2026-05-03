<?php

namespace App\Tests\Service;

use App\Service\FriendsConnectionValidationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FriendsConnectionValidationServiceTest extends TestCase
{
    private FriendsConnectionValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FriendsConnectionValidationService();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALID CASES - Should pass validation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid connection with all required fields and default pending status
     */
    public function testValidateWithValidConnectionData(): void
    {
        $result = $this->service->validate(1, 2, 'friend');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid connection with explicit accepted status
     */
    public function testValidateWithAcceptedStatus(): void
    {
        $result = $this->service->validate(5, 10, 'mentor', 'accepted');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid connection with rejected status
     */
    public function testValidateWithRejectedStatus(): void
    {
        $result = $this->service->validate(3, 7, 'collaborator', 'rejected');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid connection with all connection types
     */
    public function testValidateWithAllValidTypes(): void
    {
        $types = ['mentor', 'mentee', 'friend', 'collaborator', 'hobby_buddy'];

        foreach ($types as $type) {
            $result = $this->service->validate(1, 2, $type);
            $this->assertTrue($result, "Connection type '{$type}' should be valid");
        }
    }

    /**
     * @test
     * Valid connection with large user IDs
     */
    public function testValidateWithLargeUserIds(): void
    {
        $result = $this->service->validate(999999, 888888, 'friend');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid type validation (quick check)
     */
    public function testValidateTypeWithValidType(): void
    {
        $result = $this->service->validateType('mentor');

        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INVALID CASES - Should throw InvalidArgumentException
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Invalid: Cannot connect to yourself
     */
    public function testValidateWithSameUserIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create connection with yourself.');

        $this->service->validate(5, 5, 'friend');
    }

    /**
     * @test
     * Invalid: Initiator ID is zero
     */
    public function testValidateWithZeroInitiatorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Initiator ID must be a positive integer.');

        $this->service->validate(0, 5, 'friend');
    }

    /**
     * @test
     * Invalid: Receiver ID is zero
     */
    public function testValidateWithZeroReceiverId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Receiver ID must be a positive integer.');

        $this->service->validate(5, 0, 'friend');
    }

    /**
     * @test
     * Invalid: Initiator ID is negative
     */
    public function testValidateWithNegativeInitiatorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Initiator ID must be a positive integer.');

        $this->service->validate(-1, 5, 'friend');
    }

    /**
     * @test
     * Invalid: Receiver ID is negative
     */
    public function testValidateWithNegativeReceiverId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Receiver ID must be a positive integer.');

        $this->service->validate(5, -1, 'friend');
    }

    /**
     * @test
     * Invalid: Invalid connection type
     */
    public function testValidateWithInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid connection type');

        $this->service->validate(1, 2, 'partner');
    }

    /**
     * @test
     * Invalid: Invalid connection status
     */
    public function testValidateWithInvalidStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid connection status');

        $this->service->validate(1, 2, 'friend', 'blocked');
    }

    /**
     * @test
     * Valid connection data (ensures test is correct)
     */
    public function testValidateWithMenteeConnection(): void
    {
        $result = $this->service->validate(100, 200, 'mentee', 'pending');

        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDGE CASES - Boundary conditions and special handling
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Type with mixed case (converts to lowercase)
     */
    public function testValidateWithMixedCaseType(): void
    {
        $result = $this->service->validate(1, 2, 'MENTOR', 'pending');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Status with mixed case (converts to lowercase)
     */
    public function testValidateWithMixedCaseStatus(): void
    {
        $result = $this->service->validate(1, 2, 'friend', 'ACCEPTED');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Type with whitespace (trimmed)
     */
    public function testValidateWithWhitespaceInType(): void
    {
        $result = $this->service->validate(1, 2, '  friend  ', 'pending');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Status with whitespace (trimmed)
     */
    public function testValidateWithWhitespaceInStatus(): void
    {
        $result = $this->service->validate(1, 2, 'friend', '  accepted  ');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Get all valid connection types
     */
    public function testGetValidTypes(): void
    {
        $types = $this->service->getValidTypes();

        $this->assertIsArray($types);
        $this->assertCount(5, $types);
        $this->assertContains('mentor', $types);
        $this->assertContains('mentee', $types);
        $this->assertContains('friend', $types);
        $this->assertContains('collaborator', $types);
        $this->assertContains('hobby_buddy', $types);
    }

    /**
     * @test
     * Get all valid statuses
     */
    public function testGetValidStatuses(): void
    {
        $statuses = $this->service->getValidStatuses();

        $this->assertIsArray($statuses);
        $this->assertCount(3, $statuses);
        $this->assertContains('pending', $statuses);
        $this->assertContains('accepted', $statuses);
        $this->assertContains('rejected', $statuses);
    }

    /**
     * @test
     * Invalid type quick check
     */
    public function testValidateTypeWithInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid connection type');

        $this->service->validateType('invalid');
    }

    /**
     * @test
     * Consecutive different connections from same initiator
     */
    public function testValidateMultipleConnectionsFromInitiator(): void
    {
        $result1 = $this->service->validate(1, 2, 'friend');
        $result2 = $this->service->validate(1, 3, 'mentor');
        $result3 = $this->service->validate(1, 4, 'collaborator');

        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($result3);
    }
}
