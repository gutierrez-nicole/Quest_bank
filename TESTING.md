# QuestBank Testing & E2E Architecture Guide

This document describes the testing architecture, test environment bootstrap, database setup, and security isolation rules for QuestBank.

---

## 1. Safe Test Server Startup

To execute Playwright browser tests or E2E integration verification safely, start the local PHP development server in **Testing Mode**:

```bash
# Recommended command via npm:
npm run test:server

# Equivalent direct command:
APP_ENV=testing TEST_BOOTSTRAP_ACTIVE=1 php -S localhost:8000 -t .
```

### Environment Variables

| Variable | Required Value | Description |
| :--- | :--- | :--- |
| `APP_ENV` | `testing` | Set environment to testing mode. |
| `TEST_BOOTSTRAP_ACTIVE` | `1` | Explicit activation signal required by `app/testing_bootstrap.php`. |

---

## 2. Test Database Setup & Seeding

Before running Playwright E2E suites:

```bash
# 1. Execute database migrations
php database/migrate.php

# 2. Seed test fixtures (Teacher A, Teacher B, Roles, Specialization Assignments)
php tests/helpers/verify_db_helper.php seed
```

---

## 3. Playwright E2E Test Execution

```bash
# Execute the full Playwright test suite
npm run test

# Execute single Playwright test in isolation
npx playwright test -g "4. Real Missing-Source"
```

---

## 4. Security Isolation & Mock Rules

QuestBank enforces strict server-authoritative mock isolation for AI generation:

1. **Explicit Bootstrap Enforcement**:
   Mock AI generation (`GroqService::$testMode`) is enabled **ONLY IF ALL THREE** conditions are met:
   - `APP_ENV === 'testing'`
   - `TEST_BOOTSTRAP_ACTIVE === '1'`
   - Invoked via `app/testing_bootstrap.php`

2. **Production & Development Safety**:
   - `APP_ENV = production` or `APP_ENV = development` **NEVER** enables test mode under any circumstances.
   - User inputs containing `MOCK_*` (e.g. exam titles) in production or development requests are treated as standard text and will **NEVER** activate mock generation.
   - Browser inputs, query parameters, missing API keys, or custom request headers cannot enable mock behavior.

---

## 5. Security Architecture Verification Script

Run the automated test-mode architecture verification suite:

```bash
php database/verify_epic22_test_architecture.php
```

Verification output:
- **TEST 1**: Production `APP_ENV` never enables test mode.
- **TEST 2**: Development `APP_ENV` never enables test mode.
- **TEST 3**: Testing `APP_ENV` without explicit test bootstrap rejects mock mode.
- **TEST 4**: Testing bootstrap enables mock mode cleanly under `APP_ENV=testing`.
- **TEST 5**: Production request with `MOCK_*` title rejects mock execution.
- **TEST 6**: Test request under testing bootstrap generates deterministic mock items.
