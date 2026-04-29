# Ghrami-Web Testing & Analysis Commands

Quick reference for running Unit Tests, Doctrine Doctor, and PHPStan analysis.

## Unit Tests (PHPUnit)

Run all tests with full output:
```bash
php vendor/bin/phpunit
```

Run tests with minimal output:
```bash
php vendor/bin/phpunit --no-progress
```

Run specific test class:
```bash
php vendor/bin/phpunit tests/Service/MessagesValidationServiceTest.php
```

Run specific test method:
```bash
php vendor/bin/phpunit --filter testMethodName tests/Service/MessagesValidationServiceTest.php
```

Run tests and generate coverage report:
```bash
php vendor/bin/phpunit --coverage-html=coverage
```

Run tests with verbose output:
```bash
php vendor/bin/phpunit -v
```

**Expected Result:** 150 tests, 250 assertions, ~0.037 seconds, 12.00 MB memory

---

## Doctrine Doctor (Schema Validation)

Check database schema for issues:
```bash
php bin/console doctrine:schema:validate
```

Generate SQL for schema updates:
```bash
php bin/console doctrine:schema:update --dump-sql
```

Apply schema updates (careful with production):
```bash
php bin/console doctrine:schema:update --force
```

View current database mappings:
```bash
php bin/console doctrine:mapping:info
```

---

## PHPStan (Static Analysis)

Run full analysis on src/ directory:
```bash
php vendor/bin/phpstan analyse src/
```

Run analysis with verbose output:
```bash
php vendor/bin/phpstan analyse src/ -v
```

Analyze specific file:
```bash
php vendor/bin/phpstan analyse src/Service/MessagesValidationService.php
```

Generate baseline (to track only new errors):
```bash
php vendor/bin/phpstan analyse src/ --generate-baseline
```

Check against baseline:
```bash
php vendor/bin/phpstan analyse src/ --configuration=phpstan.neon
```

**Expected Result:** 0 errors (Level 5 analysis)

---

## Combined Commands

Run all checks in sequence:
```bash
echo "=== Running Unit Tests ===" && php vendor/bin/phpunit && echo -e "\n=== Running Doctrine Doctor ===" && php bin/console doctrine:schema:validate && echo -e "\n=== Running PHPStan ===" && php vendor/bin/phpstan analyse src/
```

Quick validation check (no tests):
```bash
php vendor/bin/phpstan analyse src/ && php bin/console doctrine:schema:validate
```

---

## Status Summary

| Tool | Status | Details |
|------|--------|---------|
| **Unit Tests** | ✅ Passing | 150/150 tests, 250 assertions, 0.037s |
| **Doctrine Doctor** | ✅ Valid | Schema fully mapped and valid |
| **PHPStan** | ✅ Clean | 0 errors at level 5 |

---

## Configuration Files

- **PHPUnit:** `phpunit.dist.xml`
- **PHPStan:** `phpstan.neon`
- **Doctrine:** `.env` and entity mappings in `src/Entity/`
