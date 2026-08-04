// @ts-check
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const STATE_FILE = path.join(__dirname, 'test_state.json');

/**
 * Ensures teacher is logged in cleanly.
 */
async function ensureLoggedIn(page) {
    if (page.url().includes('/teacher/')) {
        return;
    }
    await page.goto('/teacher/dashboard.php');
    if (page.url().includes('index.php')) {
        await page.fill('#login_email', 'russel@questbank.edu.ph');
        await page.fill('#login_password', 'Password123!');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*teacher\/dashboard\.php/);
    }
}

/**
 * Generate or retrieve deterministic test state and upload fixtures if needed.
 * Every test calls this helper so tests can run independently in any order or alone.
 */
async function ensureFixturesLoaded(page) {
    // 1. Ensure test user accounts are seeded
    execSync('php tests/helpers/verify_db_helper.php seed', { cwd: path.join(__dirname, '..') });

    // 2. Resolve Teacher A ID
    const tidRes = execSync('php tests/helpers/verify_db_helper.php get_teacher_id russel', { cwd: path.join(__dirname, '..') }).toString();
    const tidData = JSON.parse(tidRes);
    if (!tidData.success || !tidData.teacher_id) {
        throw new Error('Failed to resolve Teacher A ID from database helper');
    }
    const teacherId = tidData.teacher_id;

    // 3. Check existing state file
    let state = null;
    if (fs.existsSync(STATE_FILE)) {
        try {
            state = JSON.parse(fs.readFileSync(STATE_FILE, 'utf8'));
        } catch (e) {
            state = null;
        }
    }

    // Validate if existing state lessons are valid in database
    if (state && state.teacherId === teacherId && state.lessons && state.lessons.prelim && state.lessons.midterm) {
        const checkRes = execSync(`php tests/helpers/verify_db_helper.php get_uploaded_lessons ${teacherId}`, { cwd: path.join(__dirname, '..') }).toString();
        const checkData = JSON.parse(checkRes);
        if (checkData.success && Array.isArray(checkData.lessons)) {
            const existingIds = checkData.lessons.map(l => l.id);
            const allValid = Object.values(state.lessons).every(id => existingIds.includes(id));
            if (allValid) {
                return state;
            }
        }
    }

    // Generate new unique run ID
    const runId = 'RUN_' + Date.now();
    const fixtures = [
        { file: 'lesson_general.txt', title: `[${runId}] General Civil Engineering Fundamentals E2E`, period: 'general' },
        { file: 'lesson_prelim.txt', title: `[${runId}] Structural Analysis Prelim Module E2E`, period: 'prelim' },
        { file: 'lesson_midterm.txt', title: `[${runId}] Reinforced Concrete Design Midterm Module E2E`, period: 'midterm' },
        { file: 'lesson_finals.txt', title: `[${runId}] Steel Design Finals Module E2E`, period: 'finals' }
    ];

    await ensureLoggedIn(page);
    const uploadedLessonIds = {};

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

    // Query DB for newly uploaded lesson IDs
    const dbRes = execSync(`php tests/helpers/verify_db_helper.php get_uploaded_lessons ${teacherId}`, { cwd: path.join(__dirname, '..') }).toString();
    const dbData = JSON.parse(dbRes);
    if (!dbData.success || !Array.isArray(dbData.lessons)) {
        throw new Error('Failed to query uploaded lesson materials after upload');
    }

    for (const f of fixtures) {
        const matched = dbData.lessons.find(l => l.title === f.title);
        if (!matched) {
            throw new Error(`Uploaded lesson title '${f.title}' not found in database`);
        }
        uploadedLessonIds[f.period] = matched.id;
    }

    state = {
        runId,
        teacherId,
        lessons: uploadedLessonIds
    };

    try {
        fs.writeFileSync(STATE_FILE, JSON.stringify(state, null, 2), 'utf8');
    } catch (e) {
        throw new Error('State file cannot be written: ' + e.message);
    }

    return state;
}

test.describe('Epic 2.2 Authoritative E2E & Edge-Workflow Suite', () => {

    test.setTimeout(60000);

    test.beforeEach(async ({ page }) => {
        await ensureLoggedIn(page);
    });

    test('1. Period Grouping & Dynamic Filter Controls Render Test', async ({ page }) => {
        await page.goto('/teacher/generate_ai.php');
        await expect(page.locator('h2')).toContainText('Civil Engineering AI Item Generator');

        // Assert Period Group Headers render
        await expect(page.locator('[data-testid="period-group-general"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-finals"]')).toBeVisible();

        // Test Period Filter Selection
        await page.selectOption('[data-testid="filter-academic-period"]', 'prelim');
        await expect(page.locator('[data-testid="period-group-prelim"]')).toBeVisible();
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeHidden();

        // Reset Filters
        await page.click('button:has-text("Reset Filters")');
        await expect(page.locator('[data-testid="period-group-midterm"]')).toBeVisible();
    });

    test('2. Upload Actual Lesson Files & Persist Deterministic State', async ({ page }) => {
        const state = await ensureFixturesLoaded(page);
        expect(state.teacherId).toBeGreaterThan(0);
        expect(state.lessons.general).toBeGreaterThan(0);
        expect(state.lessons.prelim).toBeGreaterThan(0);
        expect(state.lessons.midterm).toBeGreaterThan(0);
        expect(state.lessons.finals).toBeGreaterThan(0);
    });

    test('3. Verify Cross-Period Generation & Database Persistence of Saved Exam', async ({ page }) => {
        const state = await ensureFixturesLoaded(page);
        await page.goto('/teacher/generate_ai.php');

        // Isolated Filter Setup
        await page.selectOption('[data-testid="filter-subject"]', 'Structural Engineering');
        await page.selectOption('[data-testid="filter-year-level"]', '4th Year');
        await page.selectOption('[data-testid="filter-program"]', 'BSCE');

        // Select exact deterministic fixture lesson checkboxes
        const prelimCb = page.locator(`input[name="selected_lessons[]"][value="${state.lessons.prelim}"]`);
        const midtermCb = page.locator(`input[name="selected_lessons[]"][value="${state.lessons.midterm}"]`);
        await expect(prelimCb).toBeVisible();
        await expect(midtermCb).toBeVisible();

        await prelimCb.check();
        await midtermCb.check();

        // Assert exact selection count
        const checkedBoxes = page.locator('input[name="selected_lessons[]"]:checked');
        await expect(checkedBoxes).toHaveCount(2);

        const examTitle = `[${state.runId}] Authoritative Cross-Period Exam`;
        await page.fill('input[name="exam_title"]', examTitle);
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        const batchId = await page.getAttribute('input[name="save_generation_batch_id"]', 'value');
        expect(batchId).toBeTruthy();

        // Save exam
        await page.fill('input[name="save_title"]', examTitle);
        await page.click('button[name="save_ai_exam"]');

        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved');

        // Execute strengthened DB verification helper
        const dbRes = execSync(`php tests/helpers/verify_db_helper.php verify_exam_saved ${batchId}`, { cwd: path.join(__dirname, '..') }).toString();
        const dbData = JSON.parse(dbRes);
        expect(dbData.success).toBe(true);
        expect(dbData.batch.teacher_id).toBe(state.teacherId);
        expect(dbData.batch.batch_consumed_at).toBeTruthy();
        expect(dbData.batch.saved_exam_id).toBeGreaterThan(0);
        expect(dbData.exam.title).toBe(examTitle);
        expect(dbData.exam.subject).toBe('Structural Engineering');
        expect(dbData.questions_count).toBe(5);
        expect(dbData.sources_count).toBeGreaterThanOrEqual(1);

        // Verify every question has valid source relation
        for (const q of dbData.questions) {
            expect(q.source_relations_count).toBeGreaterThan(0);
            expect(q.is_review_required).toBe(0);
        }
    });

    test('4. Real Missing-Source Resolution Browser Workflow', async ({ page }) => {
        const state = await ensureFixturesLoaded(page);
        await page.goto('/teacher/generate_ai.php');

        // Isolated Filter & Checkbox Setup
        await page.selectOption('[data-testid="filter-subject"]', 'Structural Engineering');
        const prelimCb = page.locator(`input[name="selected_lessons[]"][value="${state.lessons.prelim}"]`);
        const midtermCb = page.locator(`input[name="selected_lessons[]"][value="${state.lessons.midterm}"]`);
        await prelimCb.check();
        await midtermCb.check();

        const examTitle = `[${state.runId}] MOCK_MISSING_SOURCE Missing Source Resolution Exam`;
        await page.fill('input[name="exam_title"]', examTitle);
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        // Assert exact affected question card displays 'Source verification required'
        await expect(page.locator('[data-testid="source-verification-required"]').first()).toBeVisible();
        await expect(page.locator('[data-testid="source-verification-required"]').first()).toContainText('Source verification required.');

        // Attempt save without choosing a source -> Assert rejection
        await page.fill('input[name="save_title"]', `[${state.runId}] Unresolved Source Save Attempt`);
        await page.click('button[name="save_ai_exam"]');

        await expect(page.locator('.bg-red-50')).toContainText('has no verified lesson source', { timeout: 5000 });

        // Select one valid source using manual-source-select
        const manualSelects = page.locator('[data-testid="manual-source-select"]');
        expect(await manualSelects.count()).toBeGreaterThan(0);
        await manualSelects.first().selectOption({ index: 1 });

        // Save again -> Assert success
        await page.click('button[name="save_ai_exam"]');
        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved', { timeout: 10000 });

        // Query database via strengthened verify_db_helper
        const batchId = await page.getAttribute('input[name="save_generation_batch_id"]', 'value');
        expect(batchId).toBeTruthy();

        const dbRes = execSync(`php tests/helpers/verify_db_helper.php verify_exam_saved ${batchId}`, { cwd: path.join(__dirname, '..') }).toString();
        const dbData = JSON.parse(dbRes);
        expect(dbData.success).toBe(true);

        for (const q of dbData.questions) {
            expect(q.source_relations_count).toBeGreaterThan(0);
            expect(q.source_verified_by).toBe(state.teacherId);
            expect(q.source_verified_at).toBeTruthy();
        }
    });

    test('5. Real Incomplete-Batch Browser Acknowledgment Workflow', async ({ page }) => {
        const state = await ensureFixturesLoaded(page);
        await page.goto('/teacher/generate_ai.php');

        // Isolated Filter & Checkbox Setup
        await page.selectOption('[data-testid="filter-subject"]', 'Structural Engineering');
        const prelimCb = page.locator(`input[name="selected_lessons[]"][value="${state.lessons.prelim}"]`);
        const midtermCb = page.locator(`input[name="selected_lessons[]"][value="${state.lessons.midterm}"]`);
        await prelimCb.check();
        await midtermCb.check();

        const examTitle = `[${state.runId}] MOCK_INCOMPLETE_BATCH Incomplete Assessment`;
        await page.fill('input[name="exam_title"]', examTitle);
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        const batchId = await page.getAttribute('input[name="save_generation_batch_id"]', 'value');
        expect(batchId).toBeTruthy();

        // Attempt save without acknowledgment reason -> Assert rejection
        await page.fill('input[name="save_title"]', `[${state.runId}] Unacknowledged Incomplete Exam`);
        await page.click('button[name="save_ai_exam"]');

        await expect(page.locator('.bg-red-50')).toContainText('Incomplete AI generation batch requires an explicit teacher acknowledgement reason');

        // Enter acknowledgment reason and submit
        await page.fill('[data-testid="ack-reason-input"]', 'Approved partial prelim/midterm coverage for quiz setup');
        await page.click('button[name="save_ai_exam"]');

        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved', { timeout: 10000 });

        // Database Verification: teacher_acknowledged_by, teacher_acknowledged_at, acknowledgement_reason, acknowledgement_token_hash, batch_consumed_at, saved_exam_id
        const dbRes = execSync(`php tests/helpers/verify_db_helper.php verify_exam_saved ${batchId}`, { cwd: path.join(__dirname, '..') }).toString();
        const dbData = JSON.parse(dbRes);
        expect(dbData.success).toBe(true);
        expect(dbData.batch.teacher_acknowledged_by).toBe(state.teacherId);
        expect(dbData.batch.teacher_acknowledged_at).toBeTruthy();
        expect(dbData.batch.acknowledgement_reason).toBe('Approved partial prelim/midterm coverage for quiz setup');
        expect(dbData.batch.acknowledgement_token_hash).toBeTruthy();
        expect(dbData.batch.batch_consumed_at).toBeTruthy();
        expect(dbData.batch.saved_exam_id).toBeGreaterThan(0);
    });

    test('6. Real Coverage-Aware Refill Browser Workflow', async ({ page }) => {
        const state = await ensureFixturesLoaded(page);
        await page.goto('/teacher/generate_ai.php');

        // Isolated Filter & Checkbox Setup
        await page.selectOption('[data-testid="filter-subject"]', 'Structural Engineering');
        const prelimCb = page.locator(`input[name="selected_lessons[]"][value="${state.lessons.prelim}"]`);
        const midtermCb = page.locator(`input[name="selected_lessons[]"][value="${state.lessons.midterm}"]`);
        await prelimCb.check();
        await midtermCb.check();

        const examTitle = `[${state.runId}] MOCK_REFILL_MIDTERM Refill Coverage Assessment`;
        await page.fill('input[name="exam_title"]', examTitle);
        await page.fill('input[name="subject"]', 'Structural Engineering');
        await page.selectOption('select[name="num_questions"]', '5');
        await page.click('button[name="generate_questions"]');

        await expect(page.locator('[data-testid="generation-audit-summary"]')).toBeVisible({ timeout: 15000 });

        const questionCards = page.locator('[data-testid="generated-question-item"]');
        await expect(questionCards).toHaveCount(5);

        const batchId = await page.getAttribute('input[name="save_generation_batch_id"]', 'value');
        expect(batchId).toBeTruthy();

        // Save exam
        await page.fill('input[name="save_title"]', `[${state.runId}] Saved Refill Coverage Exam`);
        await page.click('button[name="save_ai_exam"]');

        await expect(page.locator('.bg-emerald-50')).toContainText('successfully created and saved');

        // DB Assertions via strengthened verify_db_helper
        const dbRes = execSync(`php tests/helpers/verify_db_helper.php verify_exam_saved ${batchId}`, { cwd: path.join(__dirname, '..') }).toString();
        const dbData = JSON.parse(dbRes);
        expect(dbData.success).toBe(true);
        expect(dbData.questions_count).toBe(5);
        expect(dbData.batch.batch_status).toBe('completed');
    });

    test('7. Security Rejections with Valid CSRF Token', async ({ page }) => {
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
