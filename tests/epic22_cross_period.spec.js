// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Epic 2.2 Cross-Period Lesson Pool UI & Filter Suite', () => {

    test.beforeEach(async ({ page }) => {
        await page.goto('/index.php');
        await page.fill('#login_email', 'russel@questbank.edu.ph');
        await page.fill('#login_password', 'Password123!');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*teacher\/dashboard\.php/);
    });

    test('1. Period Grouping & Dynamic Filter Controls Render Test', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');
        await expect(page.locator('h2')).toContainText('Civil Engineering AI Item Generator');

        // Assert Period Group Headers render
        await expect(page.locator('[data-testid="period-group-general"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-finals"]')).toBeVisible();

        // Test Filter Selection
        await page.selectOption('[data-testid="filter-academic-period"]', 'prelim');
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeHidden();

        // Reset Filters
        await page.click('button:has-text("Reset Filters")');
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();
    });

});
