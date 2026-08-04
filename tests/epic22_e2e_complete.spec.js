// @ts-check
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const path = require('path');

test.describe('Epic 2.2 Authoritative E2E & Edge-Workflow Suite', () => {

    let teacherAId = 0;
    let uploadedLessonIds = {};

    test.beforeAll(async () => {
        // Step 1: Run CLI seeder (seeds users only, no direct lesson insertion)
        execSync('php tests/helpers/verify_db_helper.php seed', { cwd: path.join(__dirname, '..') });
        
        // Resolve Teacher A ID dynamically
        const tidRes = execSync('php tests/helpers/verify_db_helper.php get_teacher_id russel', { cwd: path.join(__dirname, '..') }).toString();
        const tidData = JSON.parse(tidRes);
        expect(tidData.success).toBe(true);
        teacherAId = tidData.teacher_id;
        expect(teacherAId).toBeGreaterThan(0);
    });

    test.beforeEach(async ({ page }) => {
        // Authenticate as Teacher A
        await page.goto('/index.php');
        await page.fill('#login_email', 'russel@questbank.edu.ph');
        await page.fill('#login_password', 'Password123!');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*teacher\/dashboard\.php/);
    });

    test('1. Upload Actual Lesson Files & Capture Uploaded Lesson IDs', async ({ page }) => {
        const fixtures = [
            { file: 'lesson_general.txt', title: 'General Civil Engineering Fundamentals E2E', period: 'general', phrase: 'FIXTURE_GENERAL_CIVIL_ENG_FUNDAMENTALS' },
            { file: 'lesson_prelim.txt', title: 'Structural Analysis Prelim Module E2E', period: 'prelim', phrase: 'FIXTURE_PRELIM_BEAM_MOMENT_CAPACITY' },
            { file: 'lesson_midterm.txt', title: 'Reinforced Concrete Design Midterm Module E2E', period: 'midterm', phrase: 'FIXTURE_MIDTERM_REINFORCED_CONCRETE_TENSION' },
            { file: 'lesson_finals.txt', title: 'Steel Design Finals Module E2E', period: 'finals', phrase: 'FIXTURE_FINALS_STEEL_COLUMN_BUCKLING' }
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

            await expect(page.locator('.bg-emerald-50')).toContainText('uploaded successfully', { timeout: 10000 });
        }

        // Query uploaded lessons via verify_db_helper using resolved teacher ID
        const output = execSync(`php tests/helpers/verify_db_helper.php get_uploaded_lessons ${teacherAId}`, { cwd: path.join(__dirname, '..') }).toString();
        const data = JSON.parse(output);
        expect(data.success).toBe(true);
        expect(data.lessons.length).toBeGreaterThanOrEqual(4);

        for (const f of fixtures) {
            const matched = data.lessons.find(l => l.title === f.title);
            expect(matched).toBeDefined();
            expect(matched.processing_status).toBe('completed');
            expect(matched.academic_period).toBe(f.period);
            expect(matched.subject).toBe('Structural Engineering');
            expect(matched.lesson_text).toContain(f.phrase);
            uploadedLessonIds[f.period] = matched.id;
        }

        expect(uploadedLessonIds.prelim).toBeGreaterThan(0);
        expect(uploadedLessonIds.midterm).toBeGreaterThan(0);
    });

    test('2. Verify Cross-Period Generation & Database Persistence of Saved Exam', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        // Select uploaded Prelim and Midterm lessons
        await page.click('[data-testid="select-all-prelim"]');
        await page.click('[data-testid="select-all-midterm"]');

        await page.fill('input[name="exam_title"]', 'Authoritative Cross-Period Exam');
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        // Capture save_generation_batch_id from form
        const batchId = await page.getAttribute('input[name="save_generation_batch_id"]', 'value');
        expect(batchId).toBeTruthy();

        // Save exam
        await page.fill('input[name="save_title"]', 'Authoritative Saved Cross-Period Exam');
        await page.click('button[name="save_ai_exam"]');

        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved');

        // Execute DB verification helper for exact batch ID
        const dbRes = execSync(`php tests/helpers/verify_db_helper.php verify_exam_saved ${batchId}`, { cwd: path.join(__dirname, '..') }).toString();
        const dbData = JSON.parse(dbRes);
        expect(dbData.success).toBe(true);
        expect(dbData.batch.teacher_id).toBe(teacherAId);
        expect(dbData.batch.batch_consumed_at).toBeTruthy();
        expect(dbData.batch.saved_exam_id).toBeGreaterThan(0);
        expect(dbData.exam.title).toBe('Authoritative Saved Cross-Period Exam');
        expect(dbData.exam.subject).toBe('Structural Engineering');
        expect(dbData.questions_count).toBe(5);
        expect(dbData.sources_count).toBeGreaterThanOrEqual(1);

        // Verify every saved source belongs to selected pool
        const selectedPool = [uploadedLessonIds.prelim, uploadedLessonIds.midterm];
        for (const src of dbData.sources) {
            expect(selectedPool).toContain(src.source_lesson_id);
        }
    });

    test('3. Real Missing-Source Resolution Browser Workflow', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        // Select Prelim & Midterm uploaded lessons
        await page.click('[data-testid="select-all-prelim"]');
        await page.click('[data-testid="select-all-midterm"]');

        await page.fill('input[name="exam_title"]', 'MOCK_MISSING_SOURCE Real Missing Source Resolution Exam');
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        // Assert exact affected question card displays 'Source verification required'
        await expect(page.locator('[data-testid="source-verification-required"]').first()).toBeVisible();
        await expect(page.locator('[data-testid="source-verification-required"]').first()).toContainText('Source verification required.');

        // Attempt save without choosing a source
        await page.fill('input[name="save_title"]', 'Unresolved Source Save Attempt');
        await page.click('button[name="save_ai_exam"]');

        // Assert save is rejected
        await expect(page.locator('.bg-red-50')).toContainText('has no verified lesson source', { timeout: 5000 });

        // Select one valid source using manual-source-select
        const manualSelects = page.locator('[data-testid="manual-source-select"]');
        const countSelects = await manualSelects.count();
        expect(countSelects).toBeGreaterThan(0);

        // Select the first valid lesson option
        await manualSelects.first().selectOption({ index: 1 });

        // Save again
        await page.click('button[name="save_ai_exam"]');

        // Assert save succeeds
        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved', { timeout: 10000 });

        // Query database via verify_db_helper to assert exact database state
        const batchId = await page.getAttribute('input[name="save_generation_batch_id"]', 'value');
        expect(batchId).toBeTruthy();

        const dbRes = execSync(`php tests/helpers/verify_db_helper.php verify_exam_saved ${batchId}`, { cwd: path.join(__dirname, '..') }).toString();
        const dbData = JSON.parse(dbRes);
        expect(dbData.success).toBe(true);
        expect(dbData.sources_count).toBeGreaterThanOrEqual(1);

        for (const src of dbData.sources) {
            expect(src.source_verified_by).toBe(teacherAId);
            expect(src.source_verified_at).toBeTruthy();
        }
    });

    test('4. Real Incomplete-Batch Browser Acknowledgment Workflow', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        // Select Prelim & Midterm lessons
        await page.click('[data-testid="select-all-prelim"]');
        await page.click('[data-testid="select-all-midterm"]');

        await page.fill('input[name="exam_title"]', 'MOCK_INCOMPLETE_BATCH Incomplete Assessment');
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        // Capture batch ID
        const batchId = await page.getAttribute('input[name="save_generation_batch_id"]', 'value');
        expect(batchId).toBeTruthy();

        // Attempt save without an acknowledgment reason
        await page.fill('input[name="save_title"]', 'Unacknowledged Incomplete Exam');
        await page.click('button[name="save_ai_exam"]');

        // Assert save is rejected
        await expect(page.locator('.bg-red-50')).toContainText('Incomplete AI generation batch requires an explicit teacher acknowledgement reason');

        // Enter an acknowledgment reason
        await page.fill('[data-testid="ack-reason-input"]', 'Approved partial prelim/midterm coverage for quiz setup');

        // Submit save with signed acknowledgment
        await page.click('button[name="save_ai_exam"]');

        // Assert save succeeds
        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved', { timeout: 10000 });

        // Database Verification: teacher_acknowledged_by, teacher_acknowledged_at, acknowledgement_reason, acknowledgement_token_hash, batch_consumed_at, saved_exam_id
        const dbRes = execSync(`php tests/helpers/verify_db_helper.php verify_exam_saved ${batchId}`, { cwd: path.join(__dirname, '..') }).toString();
        const dbData = JSON.parse(dbRes);
        expect(dbData.success).toBe(true);
        expect(dbData.batch.teacher_acknowledged_by).toBe(teacherAId);
        expect(dbData.batch.teacher_acknowledged_at).toBeTruthy();
        expect(dbData.batch.acknowledgement_reason).toBe('Approved partial prelim/midterm coverage for quiz setup');
        expect(dbData.batch.acknowledgement_token_hash).toBeTruthy();
        expect(dbData.batch.batch_consumed_at).toBeTruthy();
        expect(dbData.batch.saved_exam_id).toBeGreaterThan(0);
    });

    test('5. Real Coverage-Aware Refill Browser Workflow', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        // Select Prelim & Midterm lessons
        await page.click('[data-testid="select-all-prelim"]');
        await page.click('[data-testid="select-all-midterm"]');

        await page.fill('input[name="exam_title"]', 'MOCK_REFILL_MIDTERM Refill Coverage Assessment');
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        // Assert generated questions count is 5
        const questionCards = page.locator('[data-testid="generated-question-item"]');
        await expect(questionCards).toHaveCount(5);

        // Capture save_generation_batch_id
        const batchId = await page.getAttribute('input[name="save_generation_batch_id"]', 'value');
        expect(batchId).toBeTruthy();

        // Save exam
        await page.fill('input[name="save_title"]', 'Saved Refill Coverage Exam');
        await page.click('button[name="save_ai_exam"]');

        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved');

        // DB Assertions
        const dbRes = execSync(`php tests/helpers/verify_db_helper.php verify_exam_saved ${batchId}`, { cwd: path.join(__dirname, '..') }).toString();
        const dbData = JSON.parse(dbRes);
        expect(dbData.success).toBe(true);
        expect(dbData.questions_count).toBe(5);
        expect(dbData.batch.batch_status).toBe('completed');
    });

    test('6. Security Rejections with Valid CSRF Token', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        // 1. Unauthorized Lesson Injection Rejection
        await page.evaluate(() => {
            const form = document.getElementById('ai_form');
            if (form) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'checkbox';
                hiddenInput.name = 'selected_lessons[]';
                hiddenInput.value = '999999';
                hiddenInput.checked = true;
                form.appendChild(hiddenInput);
            }
        });
        await page.fill('input[name="exam_title"]', 'Injection Attack Exam');
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('.bg-red-50')).toContainText('Access denied', { timeout: 5000 });

        // 2. Maximum + 1 Selected Lessons Rejection (>20)
        await page.goto('/teacher/generate_ai.php');
        await page.evaluate(() => {
            const form = document.getElementById('ai_form');
            if (form) {
                for (let i = 1; i <= 21; i++) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'checkbox';
                    hiddenInput.name = 'selected_lessons[]';
                    hiddenInput.value = i.toString();
                    hiddenInput.checked = true;
                    form.appendChild(hiddenInput);
                }
            }
        });
        await page.fill('input[name="exam_title"]', 'Excessive Selection Exam');
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('.bg-red-50')).toContainText('Maximum lesson selection exceeded', { timeout: 5000 });
    });

});
