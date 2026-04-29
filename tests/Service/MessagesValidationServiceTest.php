<?php

namespace App\Tests\Service;

use App\Service\MessagesValidationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MessagesValidationServiceTest extends TestCase
{
    private MessagesValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MessagesValidationService();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALID CASES - Should pass validation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid message with all required fields
     */
    public function testValidateMessageWithAllRequiredFields(): void
    {
        $result = $this->service->validate(
            1,
            2,
            'Hello, how are you doing today?'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid message with different sender and receiver IDs
     */
    public function testValidateMessageWithDifferentUserIds(): void
    {
        $result = $this->service->validate(
            5,
            10,
            'Great to hear from you!'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid message with large sender and receiver IDs
     */
    public function testValidateMessageWithLargeUserIds(): void
    {
        $result = $this->service->validate(
            999999,
            1000000,
            'Message with large IDs'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid message with minimum content length (1 character)
     */
    public function testValidateMessageWithMinimumContentLength(): void
    {
        $result = $this->service->validate(
            1,
            2,
            'A'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid message with maximum content length (2000 characters)
     */
    public function testValidateMessageWithMaximumContentLength(): void
    {
        $maxContent = str_repeat('a', 2000);
        $result = $this->service->validate(
            1,
            2,
            $maxContent
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid message with whitespace trimming
     */
    public function testValidateMessageWithWhitespaceTrims(): void
    {
        $result = $this->service->validate(
            1,
            2,
            '   Hello World   '
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid message with special characters
     */
    public function testValidateMessageWithSpecialCharacters(): void
    {
        $result = $this->service->validate(
            1,
            2,
            'Hello! @#$%^&*() 你好 مرحبا'
        );

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid message with multiline content
     */
    public function testValidateMessageWithMultilineContent(): void
    {
        $result = $this->service->validate(
            1,
            2,
            "Line 1\nLine 2\nLine 3"
        );

        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INVALID CASES - Should throw exceptions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Message with sender ID of zero throws exception
     */
    public function testValidateMessageWithZeroSenderId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sender ID must be a positive integer.');

        $this->service->validate(0, 2, 'Hello');
    }

    /**
     * @test
     * Message with negative sender ID throws exception
     */
    public function testValidateMessageWithNegativeSenderId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sender ID must be a positive integer.');

        $this->service->validate(-1, 2, 'Hello');
    }

    /**
     * @test
     * Message with receiver ID of zero throws exception
     */
    public function testValidateMessageWithZeroReceiverId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Receiver ID must be a positive integer.');

        $this->service->validate(1, 0, 'Hello');
    }

    /**
     * @test
     * Message with negative receiver ID throws exception
     */
    public function testValidateMessageWithNegativeReceiverId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Receiver ID must be a positive integer.');

        $this->service->validate(1, -5, 'Hello');
    }

    /**
     * @test
     * Message where sender equals receiver throws exception
     */
    public function testValidateMessageWithSenderEqualsReceiver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot send a message to yourself.');

        $this->service->validate(1, 1, 'Talking to myself');
    }

    /**
     * @test
     * Message with empty content throws exception
     */
    public function testValidateMessageWithEmptyContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message content cannot be empty.');

        $this->service->validate(1, 2, '');
    }

    /**
     * @test
     * Message with only whitespace throws exception
     */
    public function testValidateMessageWithOnlyWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message content cannot be empty.');

        $this->service->validate(1, 2, '   ');
    }

    /**
     * @test
     * Message with content exceeding 2000 characters throws exception
     */
    public function testValidateMessageWithContentTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message content must not exceed 2000 characters.');

        $tooLongContent = str_repeat('a', 2001);
        $this->service->validate(1, 2, $tooLongContent);
    }

    /**
     * @test
     * Message with sender and receiver both invalid throws first error
     */
    public function testValidateMessageWithMultipleInvalidFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Should fail on sender ID first
        $this->expectExceptionMessage('Sender ID must be a positive integer.');

        $this->service->validate(0, 0, '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDGE CASES - Boundary conditions and special scenarios
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Message with sender ID = 1 and receiver ID = 2 (boundary case)
     */
    public function testValidateMessageWithSmallUserIds(): void
    {
        $result = $this->service->validate(1, 2, 'Boundary test');
        $this->assertTrue($result);
    }

    /**
     * @test
     * Message with consecutive sender and receiver IDs
     */
    public function testValidateMessageWithConsecutiveUserIds(): void
    {
        $result = $this->service->validate(100, 101, 'Consecutive IDs');
        $this->assertTrue($result);
    }

    /**
     * @test
     * Message with content exactly at 2000 character limit
     */
    public function testValidateMessageAtExactCharacterLimit(): void
    {
        $exactContent = str_repeat('x', 2000);
        $result = $this->service->validate(1, 2, $exactContent);
        $this->assertTrue($result);
    }

    /**
     * @test
     * Message with content one character over limit throws exception
     */
    public function testValidateMessageOneCharacterOverLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $overContent = str_repeat('x', 2001);
        $this->service->validate(1, 2, $overContent);
    }

    /**
     * @test
     * ValidateSenderId method works correctly
     */
    public function testValidateSenderIdMethod(): void
    {
        $result = $this->service->validateSenderId(5);
        $this->assertTrue($result);
    }

    /**
     * @test
     * ValidateSenderId method throws on invalid ID
     */
    public function testValidateSenderIdMethodThrowsOnInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->validateSenderId(0);
    }

    /**
     * @test
     * ValidateReceiverId method works correctly
     */
    public function testValidateReceiverIdMethod(): void
    {
        $result = $this->service->validateReceiverId(5);
        $this->assertTrue($result);
    }

    /**
     * @test
     * ValidateReceiverId method throws on invalid ID
     */
    public function testValidateReceiverIdMethodThrowsOnInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->validateReceiverId(-1);
    }

    /**
     * @test
     * ValidateContent method works correctly
     */
    public function testValidateContentMethod(): void
    {
        $result = $this->service->validateContent('Valid message content');
        $this->assertTrue($result);
    }

    /**
     * @test
     * ValidateContent method throws on empty content
     */
    public function testValidateContentMethodThrowsOnEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->validateContent('');
    }

    /**
     * @test
     * ValidateContent method throws on content too long
     */
    public function testValidateContentMethodThrowsOnTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $tooLong = str_repeat('a', 2001);
        $this->service->validateContent($tooLong);
    }

    /**
     * @test
     * GetMaxContentLength returns correct value
     */
    public function testGetMaxContentLength(): void
    {
        $maxLength = $this->service->getMaxContentLength();
        $this->assertEquals(2000, $maxLength);
    }

    /**
     * @test
     * Message content with tabs and newlines is valid
     */
    public function testValidateMessageWithTabsAndNewlines(): void
    {
        $result = $this->service->validate(
            1,
            2,
            "Hello\t\tWorld\n\nHow are you?"
        );
        $this->assertTrue($result);
    }

    /**
     * @test
     * Message with all maximum length content and valid IDs
     */
    public function testValidateMessageWithMaxLengthAndValidIds(): void
    {
        $maxContent = str_repeat('message ', 250);
        $result = $this->service->validate(999, 1000, substr($maxContent, 0, 2000));
        $this->assertTrue($result);
    }
}
