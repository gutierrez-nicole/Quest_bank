const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Load deterministic test state fixture — single source of truth
const fixturePath = path.join(__dirname, 'fixtures', 'test_state.json');
if (!fs.existsSync(fixturePath)) {
  throw new Error(`FATAL: Test fixture missing at ${fixturePath}. Run: php tests/seed_test_data.php`);
}
const testState = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));

/**
 * Helper to query MySQL database via stdin-piped PHP script (no shell escaping issues)
 */
function queryDB(sql) {
  const output = execSync('php tests/query_db.php', {
    cwd: path.join(__dirname, '..'),
    input: sql,
    encoding: 'utf8'
  });
  return JSON.parse(output.trim());
}

function executePHP(code) {
  const output = execSync('php tests/eval_php.php', {
    cwd: path.join(__dirname, '..'),
    input: code,
    encoding: 'utf8'
  });
  return output.trim();
}

/**
 * Build answer payload from fixture questions.
 * Returns { "questionId": "answer", ... } using fixture data only.
 */
function buildCorrectAnswerPayload() {
  const payload = {};
  for (const q of testState.questions) {
    payload[String(q.id)] = q.correct_answer;
  }
  return payload;
}

function buildMixedAnswerPayload() {
  // First question correct, second question wrong
  const payload = {};
  const q0 = testState.questions[0];
  const q1 = testState.questions[1];
  payload[String(q0.id)] = q0.correct_answer; // correct
  payload[String(q1.id)] = q1.correct_answer === 'true' ? 'false' : 'a'; // wrong
  return payload;
}

test.describe('QuestBank Capstone Final E2E QA Certification Suite', () => {

  test.beforeEach(async ({ page }) => {
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.error(`[Browser Console Error] ${msg.text()}`);
      }
    });
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 1: TASK 1 — Assert Exact AI-Generated Question Set
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 1: Exact AI-Generated Question Set & Saved Exam Verification', async ({ page }) => {
    // 1. Login as Teacher A
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.teacher_a.email);
    await page.fill('input[name="password"]', testState.teacher_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*teacher\/dashboard\.php/);

    // 2. Upload deterministic lesson
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

    // 3. Assert extraction completed
    await expect(page.locator('body')).toContainText(uniqueTitle);
    await expect(page.locator('body')).toContainText('Stopping Sight Distance');

    // 4. Open AI Generator & Select Extracted Lesson
    await page.goto('/teacher/generate_ai.php');
    const examTitle = `E2E Highway Exam ${timestamp}`;
    await page.fill('input[name="exam_title"]', examTitle);
    await page.fill('input[name="subject"]', 'Transportation Engineering');

    // Select extracted source — no Manual Paste mode
    await page.evaluate(() => {
      const radioExtracted = document.querySelector('input[name="input_source"][value="extracted"]');
      if (!radioExtracted) throw new Error('Extracted lesson radio not found');
      radioExtracted.checked = true;
      if (typeof toggleInputSource === 'function') toggleInputSource('extracted');
    });

    // Check lesson checkbox (MUST exist)
    const lessonCheckbox = page.locator('input[name="selected_lessons[]"]:not([value="all"])').first();
    await expect(lessonCheckbox).toBeAttached({ timeout: 5000 });
    await lessonCheckbox.check({ force: true });

    // Request exactly 5 questions (MUST exist)
    const numQuestionsSelect = page.locator('select[name="num_questions"]');
    await expect(numQuestionsSelect).toBeAttached({ timeout: 5000 });
    await numQuestionsSelect.selectOption('5');

    // Generate questions
    await page.click('button[name="generate_questions"]');
    await page.waitForLoadState('domcontentloaded');

    // 5. Assert exactly 5 generated question containers
    const questionContainers = page.locator('[data-testid="generated-question-item"]');
    const containerCount = await questionContainers.count();
    expect(containerCount).toBe(5);

    // Verify each generated question
    for (let i = 0; i < containerCount; i++) {
      const item = questionContainers.nth(i);

      // Question text must be non-empty
      const textVal = await item.locator('[data-testid="question-text"]').inputValue();
      expect(textVal.trim().length).toBeGreaterThan(0);

      // Question type must be supported
      const typeVal = (await item.locator('[data-testid="question-type"]').innerText()).trim().toLowerCase();
      expect(['multiple_choice', 'true_false', 'short_answer']).toContain(typeVal);

      // Answer key must be non-empty
      const keyVal = await item.locator('[data-testid="answer-key"]').inputValue();
      expect(keyVal.trim().length).toBeGreaterThan(0);

      // Points must be positive
      const pointsVal = parseFloat(await item.locator('[data-testid="question-points"]').inputValue());
      expect(pointsVal).toBeGreaterThan(0);

      // MCQ questions must have valid options
      if (typeVal === 'multiple_choice') {
        const mcqBox = item.locator('[data-testid="mcq-options"]');
        await expect(mcqBox).toBeVisible();
        const optA = await mcqBox.locator('input[name*="[opt_a]"]').inputValue();
        const optB = await mcqBox.locator('input[name*="[opt_b]"]').inputValue();
        expect(optA.trim().length).toBeGreaterThan(0);
        expect(optB.trim().length).toBeGreaterThan(0);
      }

      // Lesson association via data-lesson-id attribute
      const lessonIdAttr = await item.getAttribute('data-lesson-id');
      expect(lessonIdAttr).toBeTruthy();
    }

    // 6. Save exam (MUST exist)
    const saveBtn = page.locator('button[name="save_ai_exam"]');
    await expect(saveBtn).toBeAttached({ timeout: 5000 });
    await saveBtn.click();
    await page.waitForLoadState('domcontentloaded');

    // Navigate to exam list and verify saved exam
    await page.goto('/teacher/create_exam.php');
    const savedExamItem = page.locator(`[data-testid="saved-exam-item"][data-exam-title="${examTitle}"]`);
    await expect(savedExamItem).toBeVisible({ timeout: 5000 });

    const itemText = await savedExamItem.innerText();
    expect(itemText).toContain('5 Items');
    expect(itemText).toContain('Transportation Engineering');
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 2: TASK 2 + TASK 3 — Student Submission & Manipulation Protection
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 2: Student Submission, Forgery Rejection & Unpublished Privacy', async ({ page }) => {
    // 1. Login as Student A
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.student_a.email);
    await page.fill('input[name="password"]', testState.student_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*student\/dashboard\.php/);

    // 2. Build dynamic mixed answer payload from fixture (1 correct, 1 wrong)
    const mixedAnswers = buildMixedAnswerPayload();

    // 3. Submit with forged client-side fields
    const response = await page.evaluate(async ({ examId, answers }) => {
      const formData = new FormData();
      formData.append('action', 'submit_online_exam');
      formData.append('exam_id', String(examId));
      formData.append('answers', JSON.stringify(answers));
      // Client-side manipulation attempts
      formData.append('manipulated_score', '999.00');
      formData.append('total_score', '999');
      formData.append('percentage', '999');
      formData.append('correct_count', '999');
      formData.append('wrong_count', '0');
      formData.append('status', 'Pass');

      const res = await fetch('/student/dashboard.php', {
        method: 'POST',
        body: formData
      });
      return await res.json();
    }, { examId: testState.exam.id, answers: mixedAnswers });

    expect(response.success).toBe(true);
    const createdSubId = Number(response.submission_id);
    expect(createdSubId).toBeGreaterThan(0);

    // 4. TASK 2: Verify server-recalculated values via DB query
    const subRecords = queryDB(`SELECT id, student_id, exam_id, total_score, total_possible_score, percentage, status, review_status FROM exam_submissions WHERE id = ${createdSubId}`);
    expect(subRecords.length).toBe(1);
    const sub = subRecords[0];

    expect(Number(sub.student_id)).toBe(testState.student_a.id);
    expect(Number(sub.exam_id)).toBe(testState.exam.id);
    expect(parseFloat(sub.total_score)).toBe(1.0);        // 1 correct out of 2
    expect(parseFloat(sub.total_possible_score)).toBe(2.0);
    expect(parseFloat(sub.percentage)).toBe(50.0);         // 50%, NOT 999
    expect(sub.status).toBe('Fail');                        // Fail, NOT 'Pass'
    expect(sub.review_status).toMatch(/pending_review|finalized/);

    // 5. Verify individual answer rows
    const ansRecords = queryDB(`SELECT question_id, student_answer, correct_answer, awarded_points, evaluation_status FROM submission_answers WHERE submission_id = ${createdSubId} ORDER BY question_id ASC`);
    expect(ansRecords.length).toBe(2);

    // First question: correct
    expect(ansRecords[0].student_answer).toBe(testState.questions[0].correct_answer);
    expect(parseFloat(ansRecords[0].awarded_points)).toBe(1.0);
    expect(ansRecords[0].evaluation_status).toBe('correct');

    // Second question: incorrect
    expect(parseFloat(ansRecords[1].awarded_points)).toBe(0.0);
    expect(ansRecords[1].evaluation_status).toBe('incorrect');

    // 6. TASK 3: Verify unpublished result is hidden
    const pdfStatus = await page.evaluate(async (subId) => {
      const res = await fetch(`/student/export_pdf.php?id=${subId}`);
      return res.status;
    }, createdSubId);
    expect(pdfStatus).toBe(403);
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 3: TASK 4 — Complete Review Lifecycle & Item Override Audit
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 3: Review Lifecycle, Item Override Audit & Status History', async ({ page }) => {
    const targetSubId = testState.submissions.student_a_pending; // 102

    // RESET: Clear stale overrides & restore submission #102 to pristine seeded state
    // This ensures idempotency across browser projects (chromium first, then Mobile Chrome)
    queryDB(`DELETE FROM submission_score_overrides WHERE submission_id = ${targetSubId}`);
    queryDB(`DELETE FROM submission_status_history WHERE submission_id = ${targetSubId}`);
    // Purge ALL answer rows for this submission (stale rows from prior test runs contaminate SUM)
    queryDB(`DELETE FROM submission_answers WHERE submission_id = ${targetSubId}`);
    // Re-insert clean answer rows
    queryDB(`INSERT INTO submission_answers (submission_id, exam_id, student_id, question_id, student_answer, correct_answer, awarded_points, max_points, evaluation_status) VALUES (${targetSubId}, ${testState.exam.id}, ${testState.student_a.id}, ${testState.questions[0].id}, '${testState.questions[0].correct_answer}', '${testState.questions[0].correct_answer}', 1.00, 1.00, 'correct'), (${targetSubId}, ${testState.exam.id}, ${testState.student_a.id}, ${testState.questions[1].id}, 'false', '${testState.questions[1].correct_answer}', 0.00, 1.00, 'incorrect')`);
    queryDB(`UPDATE exam_submissions SET review_status = 'pending_review', total_score = 1.00, percentage = 50.00, status = 'Fail', published_at = NULL, reviewed_at = NULL, reviewed_by = NULL, teacher_remarks = NULL WHERE id = ${targetSubId}`);

    // 1. Login as Teacher A
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.teacher_a.email);
    await page.fill('input[name="password"]', testState.teacher_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*teacher\/dashboard\.php/);

    // 2. Open Reports filtered to the exam under test — direct URL avoids onchange race
    await page.goto(`/teacher/reports.php?exam_title=${encodeURIComponent(testState.exam.title)}`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toContainText(/Class Performance/i);

    // 3. Find submission #102 button
    const initBtnSub102 = page.locator(`button[onclick*='"id":${targetSubId}']`).first();
    await expect(initBtnSub102).toBeVisible({ timeout: 10000 });
    await initBtnSub102.click();
    const modal = page.locator('[data-testid="review-submission-modal"]');
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Assert modal displays correct student name (rendered via JS in modal_title)
    await expect(modal.locator('#modal_title')).toContainText('QA Test Student Alpha');
    // Assert modal displays exam title (rendered in modal_subtitle)
    await expect(modal.locator('#modal_subtitle')).toContainText('QA Civil Engineering Fundamentals Exam');

    // 4. Override item-level score using fixture question ID
    const overrideQuestionId = String(testState.questions[1].id);
    const itemForm = modal.locator('[data-testid="item-override-form"]');
    await itemForm.locator('[data-testid="item-question-id"]').fill(overrideQuestionId);
    await itemForm.locator('[data-testid="item-points-input"]').fill('1.0');
    await itemForm.locator('[data-testid="item-reason-input"]').fill('Manual regrade audit');
    await itemForm.locator('[data-testid="item-override-submit"]').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/overridden|Recalculated|Item/i);

    // 5. Verify audit record in submission_score_overrides
    const auditRecords = queryDB(`SELECT submission_id, question_id, old_points, new_points, reviewer_id, reason FROM submission_score_overrides WHERE submission_id = ${targetSubId} ORDER BY id DESC LIMIT 1`);
    expect(auditRecords.length).toBe(1);
    expect(Number(auditRecords[0].submission_id)).toBe(targetSubId);
    expect(Number(auditRecords[0].question_id)).toBe(testState.questions[1].id);
    expect(parseFloat(auditRecords[0].old_points)).toBe(0.0);
    expect(parseFloat(auditRecords[0].new_points)).toBe(1.0);
    expect(Number(auditRecords[0].reviewer_id)).toBe(testState.teacher_a.id);
    expect(auditRecords[0].reason).toBe('Manual regrade audit');

    // Verify recalculated totals
    const recDb = queryDB(`SELECT total_score, percentage, status FROM exam_submissions WHERE id = ${targetSubId}`);
    expect(parseFloat(recDb[0].total_score)).toBe(2.0);
    expect(parseFloat(recDb[0].percentage)).toBe(100.0);
    expect(recDb[0].status).toBe('Pass');

    // 6. Test skipped transition rejection: pending_review -> published must fail
    const skipResult = executePHP(`
      try {
        ResultWorkflowService::transitionStatus(${targetSubId}, 'published', ${testState.teacher_a.id}, 'Skip attempt');
        echo 'ALLOWED';
      } catch (Exception $e) {
        echo $e->getMessage();
      }
    `);
    expect(skipResult).not.toBe('ALLOWED');

    // 7. Sequential transitions: pending_review -> reviewed -> finalized -> published
    // Transition 1: pending_review -> reviewed
    await page.goto(`/teacher/reports.php?exam_title=${encodeURIComponent(testState.exam.title)}`);
    await page.waitForLoadState('domcontentloaded');
    const btnSub102 = page.locator(`button[onclick*='"id":${targetSubId}']`).first();
    await expect(btnSub102).toBeVisible({ timeout: 10000 });
    await btnSub102.click();
    await expect(modal).toBeVisible();
    await modal.locator('select[name="new_review_status"]').selectOption('reviewed');
    await modal.locator('textarea[name="teacher_remarks"]').fill('Step 1: Reviewed');
    await modal.locator('[data-testid="review-status-submit"]').click();
    await page.waitForLoadState('networkidle');

    const st1 = queryDB(`SELECT review_status FROM exam_submissions WHERE id = ${targetSubId}`);
    expect(st1[0].review_status).toBe('reviewed');

    // Transition 2: reviewed -> finalized
    await page.goto(`/teacher/reports.php?exam_title=${encodeURIComponent(testState.exam.title)}`);
    await page.waitForLoadState('domcontentloaded');
    const btnSub102_t2 = page.locator(`button[onclick*='"id":${targetSubId}']`).first();
    await expect(btnSub102_t2).toBeVisible({ timeout: 10000 });
    await btnSub102_t2.click();
    await expect(modal).toBeVisible();
    await modal.locator('select[name="new_review_status"]').selectOption('finalized');
    await modal.locator('textarea[name="teacher_remarks"]').fill('Step 2: Finalized');
    await modal.locator('[data-testid="review-status-submit"]').click();
    await page.waitForLoadState('networkidle');

    const st2 = queryDB(`SELECT review_status FROM exam_submissions WHERE id = ${targetSubId}`);
    expect(st2[0].review_status).toBe('finalized');

    // Transition 3: finalized -> published
    await page.goto(`/teacher/reports.php?exam_title=${encodeURIComponent(testState.exam.title)}`);
    await page.waitForLoadState('domcontentloaded');
    const btnSub102_t3 = page.locator(`button[onclick*='"id":${targetSubId}']`).first();
    await expect(btnSub102_t3).toBeVisible({ timeout: 10000 });
    await btnSub102_t3.click();
    await expect(modal).toBeVisible();
    await modal.locator('select[name="new_review_status"]').selectOption('published');
    await modal.locator('textarea[name="teacher_remarks"]').fill('Step 3: Published');
    await modal.locator('[data-testid="review-status-submit"]').click();
    await page.waitForLoadState('networkidle');

    const st3 = queryDB(`SELECT review_status, published_at FROM exam_submissions WHERE id = ${targetSubId}`);
    expect(st3[0].review_status).toBe('published');
    expect(st3[0].published_at).not.toBeNull();

    // Verify transition history records
    const histRecords = queryDB(`SELECT previous_status, new_status FROM submission_status_history WHERE submission_id = ${targetSubId} ORDER BY id ASC`);
    expect(histRecords.length).toBeGreaterThanOrEqual(3);
    const last3 = histRecords.slice(-3);
    expect(last3[0].new_status).toBe('reviewed');
    expect(last3[1].new_status).toBe('finalized');
    expect(last3[2].new_status).toBe('published');
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 4: TASK 5 — Student Visibility After Publication
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 4: Student Visibility Changes After Publication', async ({ page }) => {
    // Submission #102 was published in Workflow 3
    const publishedSubId = testState.submissions.student_a_pending; // 102

    // Verify it's actually published in DB first
    const dbCheck = queryDB(`SELECT review_status FROM exam_submissions WHERE id = ${publishedSubId}`);
    expect(dbCheck[0].review_status).toBe('published');

    // 1. Student A Login
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.student_a.email);
    await page.fill('input[name="password"]', testState.student_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*student\/dashboard\.php/);

    // 2. PDF export of published submission MUST succeed
    const pdfStatus = await page.evaluate(async (subId) => {
      const res = await fetch(`/student/export_pdf.php?id=${subId}`);
      return res.status;
    }, publishedSubId);
    expect(pdfStatus).toBe(200);

    // 3. Dashboard shows exam title for published result
    await page.goto('/student/dashboard.php');
    await expect(page.locator('body')).toContainText('QA Civil Engineering Fundamentals Exam');

    // 4. DB verification of published values
    const pubDb = queryDB(`SELECT student_id, total_score, percentage, status, review_status FROM exam_submissions WHERE id = ${publishedSubId}`);
    expect(Number(pubDb[0].student_id)).toBe(testState.student_a.id);
    expect(parseFloat(pubDb[0].total_score)).toBe(2.0);
    expect(parseFloat(pubDb[0].percentage)).toBe(100.0);
    expect(pubDb[0].status).toBe('Pass');
    expect(pubDb[0].review_status).toBe('published');
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 5: TASK 6 — Student-to-Student IDOR Verification
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 5: Student-to-Student IDOR Block', async ({ page }) => {
    const studentA_subId = testState.submissions.student_a_published; // 100
    const studentB_subId = testState.submissions.student_b_published; // 101

    // 1. Login as Student A
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.student_a.email);
    await page.fill('input[name="password"]', testState.student_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*student\/dashboard\.php/);

    // 2. Student A accesses OWN published PDF -> 200 OK
    const ownStatus = await page.evaluate(async (subId) => {
      const res = await fetch(`/student/export_pdf.php?id=${subId}`);
      return res.status;
    }, studentA_subId);
    expect(ownStatus).toBe(200);

    // 3. Student A attempts Student B's published PDF -> 403 Forbidden
    const idorRes = await page.evaluate(async (subId) => {
      const res = await fetch(`/student/export_pdf.php?id=${subId}`);
      const text = await res.text();
      return { status: res.status, text: text };
    }, studentB_subId);

    expect(idorRes.status).toBe(403);
    expect(idorRes.text).toContain('403 Forbidden');
    expect(idorRes.text).not.toContain('QA Test Student Beta');

    // Note: PDF export route (/student/export_pdf.php?id=) is the single student-facing result endpoint.
    // No separate AJAX result API endpoint exists.

    // 4. Student A dashboard remains functional
    await page.goto('/student/dashboard.php');
    await expect(page.locator('body')).toContainText(/Student Portal|Dashboard|Welcome/i);
  });

  // ──────────────────────────────────────────────────────────────────────────
  // WORKFLOW 6: TASK 7 — Deterministic Analytics & Empty-State Verification
  // ──────────────────────────────────────────────────────────────────────────
  test('Workflow 6: Deterministic Analytics & Empty-State Verification', async ({ page }) => {
    // 1. Login as Teacher A
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.teacher_a.email);
    await page.fill('input[name="password"]', testState.teacher_a.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*teacher\/dashboard\.php/);

    // 2. Filter by QA Analytics Benchmark Exam — use direct URL to avoid onchange navigation race
    await page.goto(`/teacher/reports.php?exam_title=${encodeURIComponent(testState.analytics_exam.title)}`);
    await page.waitForLoadState('domcontentloaded');

    // 3. Assert exact deterministic analytics values
    // Seed: 90, 80, 70, 60 with threshold 75 -> Total=4, Pass=2, Fail=2, Rate=50%, Avg=75%, Max=90%
    const reportsText = await page.locator('body').innerText();

    expect(reportsText).toContain('50.0%'); // Pass Rate
    expect(reportsText).toContain('75.0%'); // Average

    // Verify via DB query for precise values since page may contain other numbers
    const analyticsDb = queryDB(`SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Pass' THEN 1 ELSE 0 END) as passed, SUM(CASE WHEN status = 'Fail' THEN 1 ELSE 0 END) as failed, AVG(percentage) as avg_pct, MAX(percentage) as max_pct FROM exam_submissions WHERE teacher_id = ${testState.teacher_a.id} AND exam_title = '${testState.analytics_exam.title}'`);
    expect(Number(analyticsDb[0].total)).toBe(4);
    expect(Number(analyticsDb[0].passed)).toBe(2);
    expect(Number(analyticsDb[0].failed)).toBe(2);
    expect(parseFloat(analyticsDb[0].avg_pct)).toBeCloseTo(75.0, 0);
    expect(parseFloat(analyticsDb[0].max_pct)).toBeCloseTo(90.0, 0);

    // 4. Empty-state verification — use direct URL to avoid onchange navigation race
    await page.goto('/teacher/reports.php?exam_title=NonExistentExamTitle');
    await page.waitForLoadState('domcontentloaded');
    const emptyText = await page.locator('body').innerText();
    expect(emptyText).toContain('0.0%');
    expect(emptyText).not.toContain('+4.2%');
    expect(emptyText).not.toContain('94.8%');

    // 5. Admin Dashboard - no fake analytics
    await page.goto('/logout.php');
    await page.goto('/index.php');
    await page.fill('input[name="email"]', testState.admin.email);
    await page.fill('input[name="password"]', testState.admin.password);
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*admin\/dashboard\.php/);

    const adminText = await page.locator('body').innerText();
    expect(adminText).not.toContain('+4.2%');
    expect(adminText).not.toContain('94.8%');
  });

});
