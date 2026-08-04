// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Epic 2.2 Final Blocker 5 Deterministic Playwright E2E Test Suite', () => {

    let seededLessonIds = {};

    test.beforeEach(async ({ page }) => {
        // Step 1: Seed environment deterministically
        const response = await page.goto('/database/seed_e2e_fixtures.php');
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

    test('1. Grouped Periods, Filtering, Quick Select, Cross-Period Generation & Exact Source Attribution', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');
        await expect(page.locator('h2')).toContainText('Civil Engineering AI Item Generator');

        // Verify Period-Grouped Sections Exist
        await expect(page.locator('[data-testid="period-group-general"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-finals"]')).toBeVisible();

        // Apply Academic Period Filter
        await page.selectOption('[data-testid="filter-academic-period"]', 'prelim');
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeHidden();

        // Reset Filter
        await page.click('button:has-text("Reset Filters")');
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();

        // Quick Select Prelim Lessons
        await page.click('[data-testid="select-all-prelim"]');
        await expect(page.locator(`[data-testid="lesson-checkbox-${seededLessonIds.prelim}"]`)).toBeChecked();

        // Explicitly Check Midterm Lesson
        await page.check(`[data-testid="lesson-checkbox-${seededLessonIds.midterm}"]`);
        await expect(page.locator(`[data-testid="lesson-checkbox-${seededLessonIds.midterm}"]`)).toBeChecked();

        // Generate Assessment Items
        await page.fill('input[name="exam_title"]', 'Deterministic E2E Cross-Period Exam');
        await page.fill('input[name="subject"]', 'Soil Mechanics');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        // Assert Question Review Form & Items
        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });
        const questionCards = page.locator('[data-testid="generated-question-item"]');
        await expect(questionCards).toHaveCount(5);

        // Assert Source Attribution Badges on Generated Items
        const firstAttr = questionCards.first().locator('[data-testid="question-source-attribution"]');
        await expect(firstAttr).toBeVisible();

        // Save Exam
        await page.fill('input[name="save_title"]', 'Verified Deterministic E2E Exam');
        await page.click('button[name="save_ai_exam"]');

        // Assert Save Success Message
        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved');
    });

    test('2. Unauthorized Lesson Injection Security Block', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        const response = await page.evaluate(async () => {
            const formData = new FormData();
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

    test('3. Server-Side Max Selection Limit Rejection (>20 Lessons)', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        const response = await page.evaluate(async () => {
            const formData = new FormData();
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

    test('4. Replayed Partial Confirmation Token Rejection', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        const response = await page.evaluate(async () => {
            const formData = new FormData();
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
