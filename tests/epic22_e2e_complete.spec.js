// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Epic 2.2 Final Repair 12 Complete Production-Grade Playwright E2E Suite', () => {

    let seededLessonIds = {};

    test.beforeEach(async ({ page }) => {
        // Step 1: Seed environment deterministically via authorized internal route
        const response = await page.goto('/database/seed_e2e_fixtures.php', {
            headers: {
                'X-INTERNAL-TEST-KEY': 'questbank_internal_e2e_secret'
            }
        });
        const seedData = await response.json();
        expect(seedData.success).toBe(true);
        seededLessonIds = seedData.lesson_ids;
        expect(seededLessonIds.general).toBeGreaterThan(0);
        expect(seededLessonIds.prelim).toBeGreaterThan(0);
        expect(seededLessonIds.midterm).toBeGreaterThan(0);
        expect(seededLessonIds.finals).toBeGreaterThan(0);

        // Step 2: Login as Teacher A (russel)
        await page.goto('/index.php');
        await page.fill('input[name="username"]', 'russel');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*teacher\/dashboard\.php/);
    });

    test('1. Full End-to-End Cross-Period Exam Generation & Verification Workflow', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');
        await expect(page.locator('h2')).toContainText('Civil Engineering AI Item Generator');

        // Assert Period-Grouped Renderings
        await expect(page.locator('[data-testid="period-group-general"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-finals"]')).toBeVisible();

        // Academic Period Filtering
        await page.selectOption('[data-testid="filter-academic-period"]', 'prelim');
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeHidden();

        // Reset Filters
        await page.click('button:has-text("Reset Filters")');
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();

        // Quick Select Prelim
        await page.click('[data-testid="select-all-prelim"]');
        await expect(page.locator(`[data-testid="lesson-checkbox-${seededLessonIds.prelim}"]`)).toBeChecked();

        // Explicit Check Midterm
        await page.check(`[data-testid="lesson-checkbox-${seededLessonIds.midterm}"]`);
        await expect(page.locator(`[data-testid="lesson-checkbox-${seededLessonIds.midterm}"]`)).toBeChecked();

        // Generate Questions
        await page.fill('input[name="exam_title"]', 'Production E2E Verified Exam');
        await page.fill('input[name="subject"]', 'Soil Mechanics');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        // Review Form & Audit Summary Assertions
        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });
        const questionCards = page.locator('[data-testid="generated-question-item"]');
        await expect(questionCards).toHaveCount(5);

        // Save Exam
        await page.fill('input[name="save_title"]', 'Saved Production E2E Exam');
        await page.click('button[name="save_ai_exam"]');

        // Success Alert Assertions
        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved');
    });

    test('2. Unauthorized Lesson Injection Security Rejection with Valid CSRF', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        const response = await page.evaluate(async () => {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('input_source', 'extracted');
            formData.append('selected_lessons[]', '999999'); // Injected unauthorized lesson ID
            formData.append('num_questions', '5');
            formData.append('subject', 'Soil Mechanics');
            formData.append('exam_title', 'Injection Attack');
            formData.append('generate_questions', '1');

            const res = await fetch('/teacher/generate_ai.php', {
                method: 'POST',
                body: formData
            });
            return res.text();
        });

        expect(response).toContain('Access denied');
    });

    test('3. Server-Side Max Selection Rejection (>20 Lessons) with Valid CSRF', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        const response = await page.evaluate(async () => {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('input_source', 'extracted');
            for (let i = 1; i <= 25; i++) {
                formData.append('selected_lessons[]', i.toString());
            }
            formData.append('num_questions', '5');
            formData.append('subject', 'Soil Mechanics');
            formData.append('generate_questions', '1');

            const res = await fetch('/teacher/generate_ai.php', {
                method: 'POST',
                body: formData
            });
            return res.text();
        });

        expect(response).toContain('Maximum lesson selection exceeded');
    });

    test('4. Replayed Confirmation Token Rejection with Valid CSRF', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        const response = await page.evaluate(async () => {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('confirm_partial_token', '1');
            formData.append('partial_token', 'replayed_fake_token_string_123');
            formData.append('num_questions', '5');
            formData.append('subject', 'Soil Mechanics');

            const res = await fetch('/teacher/generate_ai.php', {
                method: 'POST',
                body: formData
            });
            return res.text();
        });

        expect(response).toContain('Invalid, expired, replayed, or tampered');
    });
});
