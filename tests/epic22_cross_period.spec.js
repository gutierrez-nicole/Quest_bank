// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Epic 2.2 Cross-Period Lesson Pool Playwright End-to-End Workflow', () => {
    
    test('Complete Teacher Cross-Period Generation & Traceability Workflow', async ({ page }) => {
        // Step 1: Login as Teacher A
        await page.goto('/index.php');
        await page.fill('input[name="username"]', 'russel');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*teacher\/dashboard\.php/);

        // Step 2: Open AI Generator
        await page.goto('/teacher/generate_ai.php');
        await expect(page.locator('h2')).toContainText('Civil Engineering AI Item Generator');

        // Step 3: Verify Grouped Lesson Selectors render under Period headers
        await expect(page.locator('[data-testid="period-group-general"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-finals"]')).toBeVisible();

        // Step 4: Test Dynamic Filtering Controls
        await page.selectOption('[data-testid="filter-academic-period"]', 'prelim');
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeHidden();

        // Reset Filters
        await page.click('button:has-text("Reset Filters")');
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();

        // Step 5: Test Quick Selection Controls
        await page.click('[data-testid="select-all-prelim"]');
        
        // Verify Prelim checkboxes are checked
        const prelimCheckboxes = page.locator('[data-period="prelim"].lesson-checkbox');
        const count = await prelimCheckboxes.count();
        if (count > 0) {
            await expect(prelimCheckboxes.first()).toBeChecked();
        }

        // Step 6: Select additional Midterm lesson if present
        const midtermCheckboxes = page.locator('[data-period="midterm"].lesson-checkbox:not([disabled])');
        if (await midtermCheckboxes.count() > 0) {
            await midtermCheckboxes.first().check();
        }

        // Step 7: Clear Selection Test
        await page.click('[data-testid="clear-selection"]');
        if (count > 0) {
            await expect(prelimCheckboxes.first()).not.toBeChecked();
        }
    });

    test('Unauthorized Lesson ID Security Injection Block', async ({ page }) => {
        // Login as Teacher
        await page.goto('/index.php');
        await page.fill('input[name="username"]', 'russel');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');

        // Attempt POST with unauthorized lesson ID via browser fetch
        const response = await page.evaluate(async () => {
            const formData = new FormData();
            formData.append('input_source', 'extracted');
            formData.append('selected_lessons[]', '99999'); // Non-existent / unauthorized ID
            formData.append('num_questions', '5');
            formData.append('subject', 'Soil Mechanics');
            formData.append('exam_title', 'Security Test');
            formData.append('generate_questions', '1');

            const res = await fetch('/teacher/generate_ai.php', {
                method: 'POST',
                body: formData
            });
            return res.text();
        });

        expect(response).toContain('Access denied');
    });
});
