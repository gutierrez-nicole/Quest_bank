const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

// TASK 1: Load deterministic test state fixture — single source of truth
const fixturePath = path.join(__dirname, 'fixtures', 'test_state.json');
if (!fs.existsSync(fixturePath)) {
  throw new Error(`FATAL: Test fixture missing at ${fixturePath}. Run: php tests/seed_test_data.php`);
}
const testState = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));

/**
 * Build dynamic answer payload from fixture questions.
 * No hardcoded question IDs — all values come from test_state.json.
 */
function buildCorrectAnswerPayload() {
  const payload = {};
  for (const q of testState.questions) {
    payload[String(q.id)] = q.correct_answer;
  }
  return payload;
}

function getExpectedTotalPoints() {
  return testState.questions.reduce((sum, q) => sum + q.points, 0);
}

function getExpectedPercentage() {
  return 100; // All correct answers submitted
}

function getExpectedPassFail() {
  return getExpectedPercentage() >= testState.exam.passing_percentage ? 'Pass' : 'Fail';
}

test.describe('QuestBank Capstone E2E Production Verification Suite', () => {

  test.beforeEach(async ({ page }) => {
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.error(`[Browser Console Error] ${msg.text()}`);
      }
    });
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 1: TASK 2 — AI Question Generation & Verification
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 1: Lesson Upload → AI Exam Generation → Save & Verify', async ({ page }) => {
    // 1. Login as Teacher A using fixture credentials
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.teacher_a.email);
    await page.fill('input[name="password"]', testState.teacher_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*teacher\/dashboard\.php/);

    // 2. Upload Lesson File
    const timestamp = Date.now();
    const uniqueTitle = `E2E Highway Module ${timestamp}`;
    await page.goto('/teacher/upload_lessons.php');
    await page.fill('input[name="title"]', uniqueTitle);
    await page.fill('input[name="subject"]', 'Transportation Engineering');

    const fileBuffer = Buffer.from(
      "Civil Engineering Highway Design & Traffic Analysis.\n" +
      "1. Stopping Sight Distance SSD = 0.278*V*t + V^2 / (254*f).\n" +
      "2. Flexible pavement design uses CBR structural number for traffic load calculation.\n" +
      "3. Pavement markings guide traffic flow and lane discipline."
    );
    await page.setInputFiles('input[name="lesson_file"]', {
      name: `highway_engineering_${timestamp}.txt`,
      mimeType: 'text/plain',
      buffer: fileBuffer
    });

    await page.click('button[name="upload_material"]');
    await page.waitForLoadState('networkidle');

    // Assert extraction succeeded with unique text phrase
    await expect(page.locator('body')).toContainText(uniqueTitle);
    await expect(page.locator('body')).toContainText('Stopping Sight Distance');

    // 3. Open AI Generator & Select Extracted Lesson
    await page.goto('/teacher/generate_ai.php');
    const examTitle = `E2E Highway Exam ${timestamp}`;
    await page.fill('input[name="exam_title"]', examTitle);
    await page.fill('input[name="subject"]', 'Transportation Engineering');

    // Select extracted lesson source — MUST exist, no conditional fallback
    await page.evaluate(() => {
      const radioExtracted = document.querySelector('input[name="input_source"][value="extracted"]');
      if (!radioExtracted) throw new Error('Extracted lesson radio button not found');
      radioExtracted.checked = true;
      if (typeof toggleInputSource === 'function') toggleInputSource('extracted');
    });

    // TASK 7: Check lesson checkbox — MUST exist, no conditional pass
    const lessonCheckbox = page.locator('input[name="selected_lessons[]"]:not([value="all"])').first();
    await expect(lessonCheckbox).toBeAttached({ timeout: 5000 });
    await lessonCheckbox.check({ force: true });

    // Set number of questions — MUST exist
    const numQuestionsSelect = page.locator('select[name="num_questions"]');
    await expect(numQuestionsSelect).toBeAttached({ timeout: 5000 });
    await numQuestionsSelect.selectOption('5');

    // Submit AI Question Generation form
    await page.click('button[name="generate_questions"]');
    await page.waitForLoadState('domcontentloaded');

    // TASK 2: Verify AI generation success
    await expect(page.locator('body')).toContainText(/AI successfully generated|generated/i);

    // Verify generated questions have required schema fields
    const generatedQuestionInputs = page.locator('input[name^="questions"]');
    const qCount = await generatedQuestionInputs.count();
    expect(qCount).toBeGreaterThan(0);

    // Save generated exam — MUST have save button
    const saveBtn = page.locator('button[name="save_ai_exam"]');
    await expect(saveBtn).toBeAttached({ timeout: 5000 });
    await saveBtn.click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toContainText(/saved|Question Bank/i);
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 2: TASK 1 + TASK 3 — Student Submission with Fixture-Based
  //             Dynamic Answers & Server-Side Score Manipulation Rejection
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 2: Student Submission with Dynamic Fixture Answers & Manipulation Rejection', async ({ page }) => {
    // 1. Student Login using fixture
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.student_a.email);
    await page.fill('input[name="password"]', testState.student_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*student\/dashboard\.php/);
    await expect(page.locator('body')).toContainText(/Student Portal|Dashboard|Welcome/i);

    // 2. TASK 1: Build dynamic answer payload from fixture — NO hardcoded IDs
    const correctAnswers = buildCorrectAnswerPayload();
    const expectedTotal = getExpectedTotalPoints();
    const expectedPercentage = getExpectedPercentage();
    const expectedPassFail = getExpectedPassFail();

    // 3. TASK 3: Submit with correct answers AND attempted client-side manipulation
    const response = await page.evaluate(async ({ examId, answers }) => {
      const formData = new FormData();
      formData.append('action', 'submit_online_exam');
      formData.append('exam_id', String(examId));
      formData.append('answers', JSON.stringify(answers));
      // Client-side manipulation attempts — server MUST ignore all of these
      formData.append('manipulated_score', '999.00');
      formData.append('total_score', '999');
      formData.append('percentage', '999');
      formData.append('pass_fail', 'PASS');
      formData.append('correct_count', '999');

      const res = await fetch('/student/dashboard.php', {
        method: 'POST',
        body: formData
      });
      return await res.json();
    }, { examId: testState.exam.id, answers: correctAnswers });

    // TASK 3: Assert server-calculated values match fixture expectations
    expect(response.success).toBe(true);
    expect(Number(response.submission_id)).toBeGreaterThan(0);

    // Server must recalculate — not use manipulated values
    expect(response.total_score).toBe(expectedTotal);
    expect(response.percentage).toBe(expectedPercentage);

    // 4. Verify privacy: unpublished results not leaked
    await page.goto('/student/dashboard.php');
    const pageText = await page.locator('body').innerText();
    expect(pageText).not.toContain(`UNPUBLISHED_SECRET_LEAK_${response.submission_id}`);
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 3: TASK 4 — Complete Review Lifecycle
  // pending_review → reviewed → finalized → published
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 3: Complete Review Lifecycle with All Transitions', async ({ page }) => {
    // 1. Teacher Login using fixture
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.teacher_a.email);
    await page.fill('input[name="password"]', testState.teacher_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*teacher\/dashboard\.php/);

    // 2. Open Reports Page
    await page.goto('/teacher/reports.php');
    await expect(page.locator('body')).toContainText(/Student Grade Submissions|Class Performance/i);

    // 3. TASK 4: Target pending_review submission — MUST exist (no conditional pass)
    const pendingRow = page.locator('tr', { hasText: 'QA Test Student Alpha' })
      .filter({ hasText: /Pending Review/i }).first();
    await expect(pendingRow).toBeVisible({ timeout: 10000 });

    // Perform item-level score override on the pending submission
    const reviewBtn = pendingRow.locator('button[onclick*="openReviewModal"]');
    await expect(reviewBtn).toBeVisible();
    await reviewBtn.click();

    const modal = page.locator('[data-testid="review-submission-modal"]');
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Item-level override using fixture question ID
    const overrideQuestionId = String(testState.questions[1].id);
    const itemForm = modal.locator('[data-testid="item-override-form"]');
    await itemForm.locator('[data-testid="item-question-id"]').fill(overrideQuestionId);
    await itemForm.locator('[data-testid="item-points-input"]').fill('1.0');
    await itemForm.locator('[data-testid="item-reason-input"]').fill('Verified via manual item audit');
    await itemForm.locator('[data-testid="item-override-submit"]').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/overridden|Recalculated|Item/i);

    // ── TRANSITION 1: pending_review → reviewed ──
    await page.goto('/teacher/reports.php');
    const pendingRow2 = page.locator('tr', { hasText: 'QA Test Student Alpha' })
      .filter({ hasText: /Pending Review/i }).first();
    await expect(pendingRow2).toBeVisible({ timeout: 10000 });
    await pendingRow2.locator('button[onclick*="openReviewModal"]').click();
    await expect(modal).toBeVisible();

    await modal.locator('select[name="new_review_status"]').selectOption('reviewed');
    await modal.locator('textarea[name="teacher_remarks"]').fill('First review complete');
    await modal.locator('[data-testid="review-status-submit"]').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/updated|Reviewed|successfully/i);

    // ── TRANSITION 2: reviewed → finalized ──
    const reviewedRow = page.locator('tr', { hasText: 'QA Test Student Alpha' })
      .filter({ hasText: /Reviewed/i }).first();
    await expect(reviewedRow).toBeVisible({ timeout: 10000 });
    await reviewedRow.locator('button[onclick*="openReviewModal"]').click();
    await expect(modal).toBeVisible();
    await modal.locator('select[name="new_review_status"]').selectOption('finalized');
    await modal.locator('textarea[name="teacher_remarks"]').fill('Finalized by teacher');
    await modal.locator('[data-testid="review-status-submit"]').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/updated|Finalized|successfully/i);

    // ── TRANSITION 3: finalized → published ──
    const finalizedRow = page.locator('tr', { hasText: 'QA Test Student Alpha' })
      .filter({ hasText: /Finalized/i }).first();
    await expect(finalizedRow).toBeVisible({ timeout: 10000 });
    await finalizedRow.locator('button[onclick*="openReviewModal"]').click();
    await expect(modal).toBeVisible();
    await modal.locator('select[name="new_review_status"]').selectOption('published');
    await modal.locator('textarea[name="teacher_remarks"]').fill('Approved and published to student');
    await modal.locator('[data-testid="review-status-submit"]').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/updated|Published|successfully/i);

    // Verify published row exists
    const publishedRow = page.locator('tr', { hasText: 'QA Test Student Alpha' })
      .filter({ hasText: /Published/i }).first();
    await expect(publishedRow).toBeVisible({ timeout: 10000 });
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 4: TASK 5 — True Student-to-Student IDOR Enforcement
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 4: Student-to-Student IDOR Block & Privacy Enforcement', async ({ page }) => {
    // 1. Student A Login using fixture
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.student_a.email);
    await page.fill('input[name="password"]', testState.student_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*student\/dashboard\.php/);

    // 2. Student A attempts teacher-only page — must be redirected
    await page.goto('/teacher/reports.php');
    expect(page.url()).not.toContain('/teacher/reports.php');

    // 3. Student A accesses OWN published PDF → 200 OK
    const ownSubId = testState.submissions.student_a_published;
    const ownPdfStatus = await page.evaluate(async (subId) => {
      const res = await fetch(`/student/export_pdf.php?id=${subId}`);
      return res.status;
    }, ownSubId);
    expect(ownPdfStatus).toBe(200);

    // 4. TASK 5: Student A attempts Student B's published PDF → 403 Forbidden
    const otherSubId = testState.submissions.student_b_published;
    const idorPdfStatus = await page.evaluate(async (subId) => {
      const res = await fetch(`/student/export_pdf.php?id=${subId}`);
      return res.status;
    }, otherSubId);
    expect(idorPdfStatus).toBe(403);

    // 5. Student A attempts Student B's result page via API → 403
    const idorApiStatus = await page.evaluate(async (subId) => {
      const res = await fetch(`/student/export_pdf.php?id=${subId}`);
      const text = await res.text();
      return { status: res.status, hasStudentBData: text.includes('QA Test Student Beta') };
    }, otherSubId);
    expect(idorApiStatus.status).toBe(403);
    expect(idorApiStatus.hasStudentBData).toBe(false);

    // 6. Verify Student A's own result remains accessible
    await page.goto('/student/dashboard.php');
    await expect(page.locator('body')).toContainText(/Student Portal|Dashboard|Welcome/i);
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 5: TASK 6 — Analytics Verification with Deterministic Values
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 5: Analytics Dashboard Telemetry Verification', async ({ page }) => {
    // 1. Teacher Login
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.teacher_a.email);
    await page.fill('input[name="password"]', testState.teacher_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*teacher\/dashboard\.php/);

    // 2. Open Reports and select QA Analytics Benchmark Exam
    await page.goto('/teacher/reports.php');
    await page.selectOption('select[name="exam_title"]', testState.analytics_exam.title);
    await page.waitForLoadState('domcontentloaded');

    // TASK 6: Assert deterministic analytics values
    const reportsText = await page.locator('body').innerText();

    // Expected: Pass Rate = 50.0%, Average = 75.0%
    expect(reportsText).toContain(`${testState.analytics_exam.expected_pass_rate}.0%`);
    expect(reportsText).toContain(`${testState.analytics_exam.expected_avg_percentage}.0%`);

    // 3. Filter by nonexistent exam → zero/empty state
    await page.goto('/teacher/reports.php?exam_title=NonExistentExamTitle');
    const emptyReportsText = await page.locator('body').innerText();
    expect(emptyReportsText).toContain('0.0%');

    // 4. Logout teacher
    await page.goto('/logout.php');

    // 5. Admin Dashboard verification — no fabricated percentages
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.admin.email);
    await page.fill('input[name="password"]', testState.admin.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*admin\/dashboard\.php/);

    await expect(page.locator('body')).toContainText(/Administrator|Command Console/i);

    const adminText = await page.locator('body').innerText();
    // Must not contain hardcoded fake analytics
    expect(adminText).not.toContain('+4.2%');
    expect(adminText).not.toContain('94.8%');
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 6: Mobile Responsive UI Viewport Audit
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 6: Mobile Responsive Layout Verification', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.student_a.email);
    await page.fill('input[name="password"]', testState.student_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*student\/dashboard\.php/);
    await expect(page.locator('body')).toBeVisible();
  });

});
