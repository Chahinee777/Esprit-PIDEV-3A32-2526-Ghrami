# Ghrami-Web Testing & Analysis Commands

Quick reference for running Unit Tests, Doctrine Doctor, and PHPStan analysis.

## Unit Tests (PHPUnit)

**Total Tests:** 165 across 8 test suites
- 301 assertions
- All tests passing ✅

### Test Suites Overview:

#### 1. **AiNotificationServiceTest.php** (8 tests)
AI-powered notification digest generation and scoring
- `testBuildSmartDigestWithNoNotifications` - Empty notification state
- `testBuildSmartDigestScoringAndSorting` - Urgency scoring and sorting
- `testBuildSmartDigestLimitsToMaxNotifications` - Limit top N notifications
- `testBuildSmartDigestPriorityCalculation` - Calculate priority levels
- `testBuildSmartDigestFallbackWhenGroqFails` - Fallback when Groq unavailable
- `testExtractActionsFromNotifications` - Extract actionable items
- `testGroqScoringEdgeCases` - Handle score >100
- `testGroqScoringNegativeValues` - Handle negative scores

#### 2. **BookingValidationServiceTest.php** (20 tests)
Booking validation for classes/meetings
**Valid Cases:**
- `testValidateBookingWithAllRequiredFields` - All fields present
- `testValidateBookingWithConfirmedStatus` - Confirmed status
- `testValidateBookingWithAllValidStatuses` - All status types (pending/confirmed/cancelled/completed)
- `testValidateBookingWithMinimumSlots` - 1 slot minimum
- `testValidateBookingWithMaximumSlots` - 50 slots maximum
- `testValidateBookingWithZeroCost` - Free bookings
- `testValidateBookingWithMaximumCost` - High cost bookings
- `testValidateBookingWithLargeUserId` - Large user IDs
- `testValidateBookingWithLargeClassId` - Large class IDs
- `testValidateBookingFromPastYear` - Past date bookings

**Invalid Cases:** Zero/negative IDs, duplicate IDs, invalid status

#### 3. **ContentModerationServiceTest.php** (7 tests)
Content moderation for posts and comments
- `testFallbackModerationWithoutSwearing` - Clean content
- `testFallbackModerationWithSwearing` - Blocked (3+ bad words)
- `testFallbackModerationWithWarning` - Warning (1-2 bad words)
- `testGroqModerationApproved` - Groq API approves content
- `testGroqModerationBlocked` - Groq API blocks content
- `testGroqApiFailureFallsBack` - Fallback when Groq fails
- `testCommentModeration` - Comment moderation

#### 4. **FriendsConnectionValidationServiceTest.php** (15 tests)
Friend/connection request validation
- `testValidateWithValidConnectionData` - Valid connection
- `testValidateWithAcceptedStatus` - Accepted status
- `testValidateWithRejectedStatus` - Rejected status
- `testValidateWithAllValidTypes` - All connection types
- `testValidateWithLargeUserIds` - Large user IDs
- `testValidateWithSameUserIds` - Self-connection rejection
- `testValidateWithZeroInitiatorId` - Invalid initiator ID
- `testValidateWithZeroReceiverId` - Invalid receiver ID
- `testValidateWithNegativeIds` - Negative IDs
- `testValidateWithInvalidType` - Invalid connection type
- `testValidateWithInvalidStatus` - Invalid status
- Plus edge cases for mixed case handling

#### 5. **HobbyValidationServiceTest.php** (11 tests)
Hobby creation/validation
**Valid Cases:**
- `testValidateWithValidNameAndCategory` - Valid hobby
- `testValidateWithNameOnly` - Name without category
- `testValidateWithAllValidCategories` - All categories (7 types)
- `testValidateWithMaxLengthDescription` - Max 500 chars
- `testValidateWithMinimumNameLength` - 2 char minimum

**Invalid Cases:** Empty name, too short, invalid category, description too long

#### 6. **MeetingsValidationServiceTest.php** (18 tests)
Meeting creation/scheduling validation
**Valid Cases:**
- `testValidateMeetingWithAllRequiredFields` - All fields
- `testValidateMeetingWithDescription` - With description
- `testValidateMeetingWithLocation` - With location
- `testValidateMeetingWithAllValidStatuses` - All statuses
- `testValidateMeetingWithMinimumParticipants` - Minimum 2
- `testValidateMeetingWithMaximumParticipants` - Up to 1000
- `testValidateMeetingWithMaxDescriptionLength` - Max 2000 chars

**Invalid Cases:** Invalid dates, status, participant count

#### 7. **MessagesValidationServiceTest.php** (20 tests)
Message validation for user-to-user communication
**Valid Cases:**
- `testValidateMessageWithAllRequiredFields` - All fields
- `testValidateMessageWithDifferentUserIds` - Different IDs
- `testValidateMessageWithLargeUserIds` - Large user IDs
- `testValidateMessageWithMinimumContentLength` - 1 char min
- `testValidateMessageWithMaximumContentLength` - 2000 char max
- `testValidateMessageWithWhitespaceTrims` - Trim whitespace
- `testValidateMessageWithSpecialCharacters` - Unicode support
- `testValidateMessageWithMultilineContent` - Multiline support

**Invalid Cases:** Zero/negative IDs, empty content, too long

#### 8. **SocialMediaValidationServiceTest.php** (27 tests)
Social post and comment validation
**Valid Cases:**
- `testValidatePostWithAllRequiredFields` - All fields
- `testValidatePostWithImageType` - Image posts
- `testValidatePostWithVideoType` - Video posts
- `testValidatePostWithAllValidTypes` - All 4 types
- `testValidatePostWithAllVisibilityLevels` - All visibility
- `testValidatePostWithMaxContentLength` - Max content
- `testValidatePostWithMinimumTitleLength` - Min title
- `testValidatePostWithLargeUserId` - Large IDs
- `testValidatePostWithTitleWhitespaceTrimmed` - Trim whitespace
- `testValidatePostWithTypeWhitespace` - Handle whitespace
- `testGetValidPostTypes` - Type enumeration

**Invalid Cases:** Empty title, short title, long content, invalid type/visibility, zero/negative IDs

### Run All Tests:
```bash
php vendor/bin/phpunit
```

### Run specific test class:
```bash
php vendor/bin/phpunit tests/Service/AiNotificationServiceTest.php
php vendor/bin/phpunit tests/Service/BookingValidationServiceTest.php
php vendor/bin/phpunit tests/Service/ContentModerationServiceTest.php
php vendor/bin/phpunit tests/Service/FriendsConnectionValidationServiceTest.php
php vendor/bin/phpunit tests/Service/HobbyValidationServiceTest.php
php vendor/bin/phpunit tests/Service/MeetingsValidationServiceTest.php
php vendor/bin/phpunit tests/Service/MessagesValidationServiceTest.php
php vendor/bin/phpunit tests/Service/SocialMediaValidationServiceTest.php
```

### Run with minimal output:
```bash
php vendor/bin/phpunit --no-progress
```

### Run specific test method:
```bash
php vendor/bin/phpunit --filter testBuildSmartDigest tests/Service/AiNotificationServiceTest.php
php vendor/bin/phpunit --filter testValidatePostWithAllRequiredFields tests/Service/SocialMediaValidationServiceTest.php
```

### Run tests and generate coverage report:
```bash
php vendor/bin/phpunit --coverage-html=coverage
```

### Run tests with verbose output:
```bash
php vendor/bin/phpunit -v
```

**Expected Result:** 165 tests, 301 assertions, ~0.055 seconds, 14.00 MB memory

---

## Doctrine Doctor (Schema Validation)

**What is Doctrine Doctor?** Validates database schema mapping consistency
- Checks if entity mappings match database structure
- Detects missing migrations or schema mismatches
- Ensures ORM and database are synchronized

**Current Status:** ✅ Schema mapping corrected with bi-directional relationship fixes

### Test/Validate Database Schema:
```bash
php bin/console doctrine:schema:validate
```
**Expected Output:** `[OK] The schema is in sync with the database.`

### View what changes would be made (dry-run):
```bash
php bin/console doctrine:schema:update --dump-sql
```
**Use this to review pending schema updates before applying**

### Apply schema updates to database:
```bash
php bin/console doctrine:schema:update --force
```
**⚠️ WARNING:** Use only in development! For production, generate migrations instead.

### View current database entity mappings:
```bash
php bin/console doctrine:mapping:info
```
**Shows all mapped entities and their database tables**

### Generate a migration for schema changes:
```bash
php bin/console make:migration
```
**Creates a migration file in `migrations/` - use this for production deployments**

### Run pending migrations:
```bash
php bin/console doctrine:migrations:migrate
```
**Applies all pending migration files to database**

### View migration status:
```bash
php bin/console doctrine:migrations:status
```
**Shows which migrations have been applied**

---

## PHPStan (Static Analysis)

**What is PHPStan?** Static analysis tool for detecting bugs/errors without running code
- **NOT a test framework** (doesn't run tests)
- Performs type-checking and code quality analysis
- Currently at Level 5 (highest strictness)

**Current Status:** 0 errors ✅ across 102 source files

### Run full analysis on src/ directory (Level 5):
```bash
php vendor/bin/phpstan analyse src/ --level=5
```

### Run analysis with verbose output:
```bash
php vendor/bin/phpstan analyse src/ --level=5 -v
```

### Analyze specific file:
```bash
php vendor/bin/phpstan analyse src/Service/AiNotificationService.php --level=5
```

### Analyze specific controller:
```bash
php vendor/bin/phpstan analyse src/Controller/NotificationController.php --level=5
```

### Analyze specific directory:
```bash
php vendor/bin/phpstan analyse src/Service/ --level=5
```

### Generate baseline (to track only new errors):
```bash
php vendor/bin/phpstan analyse src/ --generate-baseline
```

### Check against baseline:
```bash
php vendor/bin/phpstan analyse src/ --configuration=phpstan.neon
```

### Run PHPStan with custom level (0-9):
```bash
php vendor/bin/phpstan analyse src/ --level=4
```

**Expected Result:** 0 errors (Level 5 analysis)

---

## Combined Commands

Run all checks in sequence:
```bash
echo "=== Running Unit Tests ===" && php vendor/bin/phpunit && echo -e "\n=== Running Doctrine Doctor ===" && php bin/console doctrine:schema:validate && echo -e "\n=== Running PHPStan ===" && php vendor/bin/phpstan analyse src/ --level=5
```

Quick validation (tests + PHPStan only):
```bash
php vendor/bin/phpunit && php vendor/bin/phpstan analyse src/ --level=5
```

---

## Notes

- **PHPUnit** = Runs actual tests (unit, integration, etc.)
- **PHPStan** = Static code analysis (catches potential bugs without running)
- **Doctrine Doctor** = Validates database schema/mappings
- Tests include AI services, content moderation, validation services
- All dependencies use Groq API for AI features with fallback options

```

Quick validation check (no tests):
```bash
php vendor/bin/phpstan analyse src/ && php bin/console doctrine:schema:validate
```

---

## Status Summary

| Tool | Status | Details |
|------|--------|---------|
| **Unit Tests** | ✅ Passing | 165 tests, 301 assertions, 0.079s |
| **Doctrine Mapping** | ✅ Correct | Fixed bi-directional relationships with inversedBy attributes |
| **PHPStan** | ✅ Clean | 0 errors at level 5 across 102 source files |

---

## Configuration Files

- **PHPUnit:** `phpunit.dist.xml`
- **PHPStan:** `phpstan.neon`
- **Doctrine:** `.env` and entity mappings in `src/Entity/`
