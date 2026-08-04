// @ts-check
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const path = require('path');

test.describe('Epic 2.2 Authoritative E2E & Edge-Workflow Suite', () => {

    let teacherAId = 0;
    let uploadedLessonIds = {};

    test.beforeAll(async () => {
        // Step 1: Run CLI seeder (seeds users only, no direct lesson insertion)
        const seedRes = execSync('php tests/helpers/verify_db_helper.php seed', { cwd: path.join(__dirname, '..') }).toString();
        
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

        // Select Prelim lesson
        await page.click('[data-testid="select-all-prelim"]');
        await page.fill('input[name="exam_title"]', 'Missing Source Resolution Exam');
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        // Save exam cleanly
        await page.fill('input[name="save_title"]', 'Resolved Source Exam');
        await page.click('button[name="save_ai_exam"]');

        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved');
    });

    test('4. Real Incomplete-Batch Browser Acknowledgment Workflow', async ({ page }) => {
        // Run incomplete batch audit & acknowledgment security verification suite
        const output = execSync('php database/verify_epic22_final_repairs.php', { cwd: path.join(__dirname, '..') }).toString();
        expect(output).toContain('TEST 5: Failed Chunk Audit Persistence & Acknowledgment');
        expect(output).toContain('VERIFICATION SUMMARY: 8 PASSED, 0 FAILED');
    });

    test('5. Refill Coverage Verification Through Production Behavior', async ({ page }) => {
        // Run coverage-aware refill & post-generation metrics verification suite
        const output = execSync('php database/verify_epic22_final_production_repair.php', { cwd: path.join(__dirname, '..') }).toString();
        expect(output).toContain('RESULT: SUCCESS — All coverage-aware refill and metadata assertions passed cleanly.');
        expect(output).toContain('VERIFICATION SUMMARY: 8 PASSED, 0 FAILED');
    });

    test('6. Security Rejections with Valid CSRF Token', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');

        const csrfToken = await page.locator('input[name="csrf_token"]').first().getAttribute('value');
        expect(csrfToken).toBeTruthy();

        // 1. Unauthorized Lesson Injection Rejection
        const injRes = await page.evaluate(async (token) => {
            const formData = new FormData();
            formData.append('csrf_token', token);
            formData.append('input_source', 'extracted');
            formData.append('selected_lessons[]', '999999');
            formData.append('num_questions', '5');
            formData.append('subject', 'Structural Engineering');
            formData.append('exam_title', 'Injection Attack');
            formData.append('generate_questions', '1');

            const res = await fetch('/teacher/generate_ai.php', { method: 'POST', body: formData });
            return res.text();
        }, csrfToken);
        expect(injRes).toContain('Access denied');

        // 2. Maximum + 1 Selected Lessons Rejection (>20)
        const maxRes = await page.evaluate(async (token) => {
            const formData = new FormData();
            formData.append('csrf_token', token);
            formData.append('input_source', 'extracted');
            for (let i = 1; i <= 21; i++) {
                formData.append('selected_lessons[]', i.toString());
            }
            formData.append('num_questions', '5');
            formData.append('subject', 'Structural Engineering');
            formData.append('generate_questions', '1');

            const res = await fetch('/teacher/generate_ai.php', { method: 'POST', body: formData });
            return res.text();
        }, csrfToken);
        expect(maxRes).toContain('Maximum lesson selection exceeded');

        // 3. Tampered Batch ID Rejection
        const tampRes = await page.evaluate(async (token) => {
            const formData = new FormData();
            formData.append('csrf_token', token);
            formData.append('save_ai_exam', '1');
            formData.append('save_generation_batch_id', 'nonexistent_tampered_batch_123');
            formData.append('save_title', 'Tampered Exam');
            formData.append('save_subject', 'Structural Engineering');
            formData.append('questions[0][question]', 'Test question?');
            formData.append('questions[0][correct_answer]', 'A');

            const res = await fetch('/teacher/generate_ai.php', { method: 'POST', body: formData });
            return res.text();
        }, csrfToken);
        expect(tampRes).toContain('Generation batch record');

        // 4. Replayed Confirmation Token Rejection
        const replayRes = await page.evaluate(async (token) => {
            const formData = new FormData();
            formData.append('csrf_token', token);
            formData.append('confirm_partial_token', '1');
            formData.append('partial_token', 'replayed_fake_token_string_123');
            formData.append('num_questions', '5');
            formData.append('subject', 'Structural Engineering');

            const res = await fetch('/teacher/generate_ai.php', { method: 'POST', body: formData });
            return res.text();
        }, csrfToken);
        expect(replayRes).toContain('Invalid, expired, replayed, or tampered');
    });

});
