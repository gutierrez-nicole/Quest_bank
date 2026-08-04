// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Epic 2.2 Final Repair 7 Complete Playwright E2E Test Suite', () => {

    test('1. Full E2E Teacher Generation, Source Attribution, and Persistence Workflow', async ({ page }) => {
        // Step 1: Login as Teacher A (russel)
        await page.goto('/index.php');
        await page.fill('input[name="username"]', 'russel');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*teacher\/dashboard\.php/);

        // Step 2: Open AI Generator Page
        await page.goto('/teacher/generate_ai.php');
        await expect(page.locator('h2')).toContainText('Civil Engineering AI Item Generator');

        // Step 3: Verify Period-Grouped Lesson Sections
        await expect(page.locator('[data-testid="period-group-general"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-finals"]')).toBeVisible();

        // Step 4: Filter by Academic Period
        await page.selectOption('[data-testid="filter-academic-period"]', 'prelim');
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeHidden();

        // Reset Filter
        await page.click('button:has-text("Reset Filters")');
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();

        // Step 5: Quick Select Prelim Lessons
        await page.click('[data-testid="select-all-prelim"]');

        // Step 6: Select first available Midterm lesson
        const midtermBoxes = page.locator('[data-period="midterm"].lesson-checkbox:not([disabled])');
        if (await midtermBoxes.count() > 0) {
            await midtermBoxes.first().check();
        }

        // Step 7: Fill Generation Form & Submit
        await page.fill('input[name="exam_title"]', 'E2E Cross-Period Exam');
        await page.selectOption('select[name="subject"]', 'Soil Mechanics');
        await page.fill('input[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        // Step 8: Assert Generated Questions
        await expect(page.locator('[data-testid="generated-review-section"]')).toBeVisible({ timeout: 15000 });
        const questionCards = page.locator('[data-testid="question-item-card"]');
        await expect(questionCards).toHaveCount(5);

        // Step 9: Save Exam to Question Bank
        await page.fill('input[name="save_title"]', 'Verified Playwright E2E Exam');
        await page.click('button[name="save_ai_exam"]');

        // Step 10: Assert Success Banner
        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved');
    });

    test('2. Security Authorization Rejection for Injected Teacher B Lesson ID', async ({ page }) => {
        await page.goto('/index.php');
        await page.fill('input[name="username"]', 'russel');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');

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

    test('3. Server-Side Max Lesson Selection Rejection (>20 Lessons)', async ({ page }) => {
        await page.goto('/index.php');
        await page.fill('input[name="username"]', 'russel');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');

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
        await page.goto('/index.php');
        await page.fill('input[name="username"]', 'russel');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');

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
