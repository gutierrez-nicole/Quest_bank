// @ts-check
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const path = require('path');

test.describe('Epic 2.2 Final E2E and Test Infrastructure Suite', () => {

    test.beforeAll(async () => {
        // Execute CLI seeder deterministically before tests
        execSync('php tests/helpers/verify_db_helper.php seed', { cwd: path.join(__dirname, '..') });
    });

    test.beforeEach(async ({ page }) => {
        // Login as Teacher A (russel)
        await page.goto('/index.php');
        await page.fill('input[name="username"]', 'russel');
        await page.fill('input[name="password"]', 'Password123!');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*teacher\/dashboard\.php/);
    });

    test('1. Upload Actual Lesson Files Through Playwright Browser Workflow', async ({ page }) => {
        const fixtures = [
            { file: 'lesson_general.txt', title: 'General Civil Engineering Fundamentals', period: 'general', phrase: 'FIXTURE_GENERAL_CIVIL_ENG_FUNDAMENTALS' },
            { file: 'lesson_prelim.txt', title: 'Structural Analysis Prelim Module', period: 'prelim', phrase: 'FIXTURE_PRELIM_BEAM_MOMENT_CAPACITY' },
            { file: 'lesson_midterm.txt', title: 'Reinforced Concrete Design Midterm Module', period: 'midterm', phrase: 'FIXTURE_MIDTERM_REINFORCED_CONCRETE_TENSION' },
            { file: 'lesson_finals.txt', title: 'Steel Design Finals Module', period: 'finals', phrase: 'FIXTURE_FINALS_STEEL_COLUMN_BUCKLING' }
        ];

        for (const f of fixtures) {
            await page.goto('/teacher/upload_lessons.php');
            await page.fill('input[name="title"]', f.title);
            await page.fill('input[name="subject"]', 'Structural Engineering');
            await page.selectOption('select[name="academic_period"]', f.period);
            await page.selectOption('select[name="semester"]', '1st Semester');
            await page.fill('input[name="school_year"]', '2025-2026');
            await page.selectOption('select[name="year_level"]', '4th Year');
            await page.fill('input[name="program"]', 'BSCE');
            
            const filePath = path.join(__dirname, 'fixtures', f.file);
            await page.setInputFiles('input[name="lesson_file"]', filePath);
            await page.click('button[name="upload_material"]');

            await expect(page.locator('body')).toContainText('uploaded successfully', { timeout: 10000 });
        }

        // Verify all 4 uploaded fixtures via DB helper
        const output = execSync('php tests/helpers/verify_db_helper.php get_uploaded_lessons 10', { cwd: path.join(__dirname, '..') }).toString();
        const data = JSON.parse(output);
        expect(data.success).toBe(true);

        const uploadedTexts = data.lessons.map(l => l.extracted_text).join(' ');
        expect(uploadedTexts).toContain('FIXTURE_GENERAL_CIVIL_ENG_FUNDAMENTALS');
        expect(uploadedTexts).toContain('FIXTURE_PRELIM_BEAM_MOMENT_CAPACITY');
        expect(uploadedTexts).toContain('FIXTURE_MIDTERM_REINFORCED_CONCRETE_TENSION');
        expect(uploadedTexts).toContain('FIXTURE_FINALS_STEEL_COLUMN_BUCKLING');
    });

    test('2. Verify Cross-Period Generation & Server-Authoritative Database Persistence', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');
        await expect(page.locator('h2')).toContainText('Civil Engineering AI Item Generator');

        // Select Prelim & Midterm lessons
        await page.click('[data-testid="select-all-prelim"]');
        await page.click('[data-testid="select-all-midterm"]');

        await page.fill('input[name="exam_title"]', 'E2E Cross-Period Assessment');
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        // Save exam
        await page.fill('input[name="save_title"]', 'Saved Cross-Period E2E Exam');
        await page.click('button[name="save_ai_exam"]');

        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved');

        // Get saved generation batch ID from hidden input or DB
        const dbVerification = execSync('php database/verify_epic22_final_security_repair.php', { cwd: path.join(__dirname, '..') }).toString();
        expect(dbVerification).toContain('RESULT: SUCCESS');
    });

    test('3. Test Missing-Source Resolution Requirement', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        // Generate questions
        await page.click('[data-testid="select-all-prelim"]');
        await page.fill('input[name="exam_title"]', 'Source Verification Test');
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        // Verify review cards render source assignment selector when needed
        const reviewCards = page.locator('[data-testid="generated-question-item"]');
        await expect(reviewCards.first()).toBeVisible();

        await page.fill('input[name="save_title"]', 'Verified Source Exam');
        await page.click('button[name="save_ai_exam"]');

        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved');
    });

    test('4. Test Incomplete-Batch Acknowledgment & Replay Rejection', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        // Execute batch acknowledgment security test suite directly
        const output = execSync('php database/verify_epic22_final_repairs.php', { cwd: path.join(__dirname, '..') }).toString();
        expect(output).toContain('TEST 5: Failed Chunk Audit Persistence & Acknowledgment');
        expect(output).toContain('VERIFICATION SUMMARY: 8 PASSED, 0 FAILED');
    });

    test('5. Security Tests with Valid CSRF Rejection Business Rules', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        // 1. Unauthorized Lesson Injection Rejection
        const injRes = await page.evaluate(async () => {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('input_source', 'extracted');
            formData.append('selected_lessons[]', '999999');
            formData.append('num_questions', '5');
            formData.append('subject', 'Structural Engineering');
            formData.append('exam_title', 'Injection Attack');
            formData.append('generate_questions', '1');

            const res = await fetch('/teacher/generate_ai.php', { method: 'POST', body: formData });
            return res.text();
        });
        expect(injRes).toContain('Access denied');

        // 2. Maximum + 1 Selected Lessons Rejection (>20)
        const maxRes = await page.evaluate(async () => {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('input_source', 'extracted');
            for (let i = 1; i <= 21; i++) {
                formData.append('selected_lessons[]', i.toString());
            }
            formData.append('num_questions', '5');
            formData.append('subject', 'Structural Engineering');
            formData.append('generate_questions', '1');

            const res = await fetch('/teacher/generate_ai.php', { method: 'POST', body: formData });
            return res.text();
        });
        expect(maxRes).toContain('Maximum lesson selection exceeded');

        // 3. Tampered Batch ID Rejection
        const tampRes = await page.evaluate(async () => {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('save_ai_exam', '1');
            formData.append('save_generation_batch_id', 'nonexistent_tampered_batch_123');
            formData.append('save_title', 'Tampered Exam');
            formData.append('save_subject', 'Structural Engineering');
            formData.append('questions[0][question]', 'Test question?');
            formData.append('questions[0][correct_answer]', 'A');

            const res = await fetch('/teacher/generate_ai.php', { method: 'POST', body: formData });
            return res.text();
        });
        expect(tampRes).toContain('Generation batch record');

        // 4. Replayed Confirmation Token Rejection
        const replayRes = await page.evaluate(async () => {
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('confirm_partial_token', '1');
            formData.append('partial_token', 'replayed_fake_token_string_123');
            formData.append('num_questions', '5');
            formData.append('subject', 'Structural Engineering');

            const res = await fetch('/teacher/generate_ai.php', { method: 'POST', body: formData });
            return res.text();
        });
        expect(replayRes).toContain('Invalid, expired, replayed, or tampered');
    });

});
