<?php

namespace App\Tests\Service;

use App\Service\HobbyValidationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HobbyValidationServiceTest extends TestCase
{
    private HobbyValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HobbyValidationService();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALID CASES - Should pass validation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Valid hobby with name and valid category
     */
    public function testValidateWithValidNameAndCategory(): void
    {
        $result = $this->service->validate('Guitar', 'music', 'Learn to play acoustic guitar');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid hobby with name only (category and description optional)
     */
    public function testValidateWithNameOnly(): void
    {
        $result = $this->service->validate('Basketball', null, null);

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid hobby with all valid categories
     */
    public function testValidateWithAllValidCategories(): void
    {
        $categories = ['sports', 'music', 'arts', 'learning', 'technology', 'gaming', 'other'];

        foreach ($categories as $category) {
            $result = $this->service->validate('Test Hobby', $category);
            $this->assertTrue($result, "Category '{$category}' should be valid");
        }
    }

    /**
     * @test
     * Valid hobby with maximum length description
     */
    public function testValidateWithMaxLengthDescription(): void
    {
        $maxDescription = str_repeat('a', 500);
        $result = $this->service->validate('Photography', 'arts', $maxDescription);

        $this->assertTrue($result);
    }

    /**
     * @test
     * Valid hobby with minimum name length (2 characters)
     */
    public function testValidateWithMinimumNameLength(): void
    {
        $result = $this->service->validate('Go', 'gaming');

        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INVALID CASES - Should throw InvalidArgumentException
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Invalid: Empty hobby name
     */
    public function testValidateWithEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hobby name cannot be empty.');

        $this->service->validate('', 'sports');
    }

    /**
     * @test
     * Invalid: Name with only whitespace
     */
    public function testValidateWithWhitespaceOnlyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hobby name cannot be empty.');

        $this->service->validate('   ', 'sports');
    }

    /**
     * @test
     * Invalid: Name shorter than minimum length
     */
    public function testValidateWithNameTooShort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hobby name must be at least 2 characters long.');

        $this->service->validate('X', 'sports');
    }

    /**
     * @test
     * Invalid: Invalid category
     */
    public function testValidateWithInvalidCategory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid category');

        $this->service->validate('Chess', 'strategy');
    }

    /**
     * @test
     * Invalid: Description exceeds maximum length
     */
    public function testValidateWithDescriptionTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Description must not exceed 500 characters');

        $tooLongDescription = str_repeat('a', 501);
        $this->service->validate('Painting', 'arts', $tooLongDescription);
    }

    /**
     * @test
     * Valid hobby with simple name
     */
    public function testValidateWithSimpleName(): void
    {
        $result = $this->service->validate('Coding', 'technology');
        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDGE CASES - Boundary conditions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @test
     * Category name is case-insensitive (converts to lowercase)
     */
    public function testValidateWithUppercaseCategory(): void
    {
        $result = $this->service->validate('Surfing', 'SPORTS', 'Ride the waves');

        $this->assertTrue($result);
    }

    /**
     * @test
     * Description with exactly 500 characters (boundary)
     */
    public function testValidateWithExactMaxDescription(): void
    {
        $exactMaxDescription = str_repeat('x', 500);
        $result = $this->service->validate('Writing', 'arts', $exactMaxDescription);

        $this->assertTrue($result);
    }

    /**
     * @test
     * Name with leading/trailing whitespace is trimmed
     */
    public function testValidateWithWhitespaceInName(): void
    {
        $result = $this->service->validate('  Dancing  ', 'arts');

        $this->assertTrue($result);
    }
}
