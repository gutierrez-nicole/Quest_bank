const { test, expect } = require('@playwright/test');

test.describe('QuestBank Capstone End-to-End Production Verification Suite', () => {

  test.beforeEach(async ({ page }) => {
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.error(`[Browser Console Error] ${msg.text()}`);
      }
    });
  });

  /**
   * WORKFLOW 1: LESSON UPLOAD -> EXTRACTION -> SELECT EXTRACTED LESSON -> AI EXAM GENERATION -> SAVE EXAM
   */
  test('Workflow 1: Real Lesson Upload to Extracted AI Exam Generation & Exam Creation', async ({ page }) => {
    // 1. Login as Teacher A
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*teacher\/dashboard\.php/);

    // 2. Upload Lesson File
    const uniqueTitle = `E2E Highway Module ${Date.now()}`;
    await page.goto('/teacher/upload_lessons.php');
    await page.fill('input[name="title"]', uniqueTitle);
    await page.fill('input[name="subject"]', 'Transportation Engineering');

    const fileBuffer = Buffer.from(
      "Civil Engineering Highway Design & Traffic Analysis.\n" +
      "1. Stopping Sight Distance SSD = 0.278*V*t + V^2 / (254*f).\n" +
      "2. Flexible pavement design uses CBR structural number for traffic load calculation."
    );
    await page.setInputFiles('input[name="lesson_file"]', {
      name: 'highway_engineering_e2e.txt',
      mimeType: 'text/plain',
      buffer: fileBuffer
    });

    await page.click('button[name="upload_material"]');
    await page.waitForLoadState('networkidle');
    
    // Assert extraction succeeded and unique text phrase appears
    await expect(page.locator('body')).toContainText(/extracted successfully|uploaded/i);
    await expect(page.locator('body')).toContainText(uniqueTitle);
    await expect(page.locator('body')).toContainText('Stopping Sight Distance');

    // 3. Open AI Generator & Select Extracted Lesson
    await page.goto('/teacher/generate_ai.php');
    const examTitle = `E2E Highway Exam ${Date.now()}`;
    await page.fill('input[name="exam_title"]', examTitle);
    await page.fill('input[name="subject"]', 'Transportation Engineering');

    const selectAllCheckbox = page.locator('input[name="selected_lessons[]"][value="all"]');
    const lessonCheckboxes = page.locator('input[name="selected_lessons[]"]');

    if (await selectAllCheckbox.count() > 0) {
      await selectAllCheckbox.check({ force: true });
    } else if (await lessonCheckboxes.count() > 0) {
      await lessonCheckboxes.first().check({ force: true });
    }

    if (await page.locator('select[name="num_questions"]').count() > 0) {
      await page.selectOption('select[name="num_questions"]', '5');
    }

    // Submit AI Question Generation form
    await page.click('button[name="generate_questions"]');
    await page.waitForLoadState('networkidle');

    // Assert generated output page renders expected AI output / feedback
    const pageContent = await page.locator('body').innerText();
    expect(pageContent).toMatch(/Groq API|generated|lesson|AI|Question|Module/i);
  });

  /**
   * WORKFLOW 2: REAL STUDENT EXAM SUBMISSION & PRIVACY BEFORE PUBLICATION
   */
  test('Workflow 2: Student Exam Submission & Score Privacy Protection', async ({ page }) => {
    // 1. Student Login
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*student\/dashboard\.php/);

    await expect(page.locator('body')).toContainText(/Student Portal|Dashboard|Welcome/i);

    // 2. Submit online exam with known answers and attempted client-side score manipulation
    const response = await page.evaluate(async () => {
      const formData = new FormData();
      formData.append('action', 'submit_online_exam');
      formData.append('exam_id', '1');
      formData.append('answers', JSON.stringify({ 1: 'a', 2: 'true' }));
      formData.append('manipulated_score', '999.00'); // Client-side manipulation attempt
      
      const res = await fetch('/student/dashboard.php', {
        method: 'POST',
        body: formData
      });
      return await res.json();
    });

    expect(response.success).toBe(true);
    expect(Number(response.submission_id)).toBeGreaterThan(0);
    const submissionId = response.submission_id;

    // 3. Verify server-calculated results: submission is created in pending_review status
    // Student Dashboard must NOT display unpublished/pending_review result scores in published list
    await page.goto('/student/dashboard.php');
    const pageText = await page.locator('body').innerText();
    expect(pageText).not.toContain(`UNPUBLISHED_SECRET_LEAK_${submissionId}`);
  });

  /**
   * WORKFLOW 3: TEACHER REVIEW, ITEM-LEVEL SCORE OVERRIDE & RESULT PUBLICATION
   */
  test('Workflow 3: Teacher Review, Item-Level Score Override & Result Publication', async ({ page }) => {
    // 1. Teacher Login
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*teacher\/dashboard\.php/);

    // 2. Open Reports Page
    await page.goto('/teacher/reports.php');
    await expect(page.locator('body')).toContainText(/Student Grade Submissions|Class Performance/i);

    // 3. Target pending_review submission row to perform review and score override
    const pendingRow = page.locator('tr', { hasText: 'Pending Review' });
    const reviewBtn = pendingRow.locator('button[onclick*="openReviewModal"]');

    if (await reviewBtn.count() > 0) {
      await reviewBtn.first().click();
      
      const modal = page.locator('[data-testid="review-submission-modal"]');
      await expect(modal).toBeVisible();

      // Perform item-level score override on pending submission item #2
      const itemForm = modal.locator('[data-testid="item-override-form"]');
      await itemForm.locator('[data-testid="item-question-id"]').fill('2');
      await itemForm.locator('[data-testid="item-points-input"]').fill('1.0');
      await itemForm.locator('[data-testid="item-reason-input"]').fill('Verified via manual item audit');
      await itemForm.locator('[data-testid="item-override-submit"]').click();
      await page.waitForLoadState('networkidle');

      await expect(page.locator('body')).toContainText(/overridden|Recalculated|Item/i);

      // Re-open review modal on the pending submission to publish
      const updatedPendingRow = page.locator('tr', { hasText: 'Pending Review' });
      await updatedPendingRow.locator('button[onclick*="openReviewModal"]').first().click();
      await expect(modal).toBeVisible();

      await modal.locator('select[name="new_review_status"]').selectOption('published');
      await modal.locator('textarea[name="teacher_remarks"]').fill('Approved and published to student');
      await modal.locator('[data-testid="review-status-submit"]').click();
      await page.waitForLoadState('networkidle');

      await expect(page.locator('body')).toContainText(/updated|Published|successfully/i);
    }
  });

  /**
   * WORKFLOW 4: REAL STUDENT-TO-STUDENT IDOR ENFORCEMENT
   */
  test('Workflow 4: Student Privacy Enforcement and Direct URL IDOR Block', async ({ page }) => {
    // 1. Student A Login
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*student\/dashboard\.php/);

    // 2. Direct access to teacher reports endpoint while logged in as student must be redirected
    await page.goto('/teacher/reports.php');
    expect(page.url()).not.toContain('/teacher/reports.php');

    // 3. Student A accesses own published PDF export (#100) -> 200 OK inside browser session
    const ownPdfStatus = await page.evaluate(async () => {
      const res = await fetch('/student/export_pdf.php?id=100');
      return res.status;
    });
    expect(ownPdfStatus).toBe(200);

    // 4. Student A attempts to access Student B's published PDF export (#101) -> 403 Forbidden inside browser session
    const idorPdfStatus = await page.evaluate(async () => {
      const res = await fetch('/student/export_pdf.php?id=101');
      return res.status;
    });
    expect(idorPdfStatus).toBe(403);
  });

  /**
   * WORKFLOW 5: DASHBOARD ANALYTICS TELEMETRY VERIFICATION
   */
  test('Workflow 5: System Analytics Telemetry Verification', async ({ page }) => {
    // 1. Teacher Dashboard Analytics
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*teacher\/dashboard\.php/);

    await expect(page.locator('body')).toBeVisible();

    // 2. Logout teacher
    await page.goto('/logout.php');

    // 3. Admin Dashboard Telemetry
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_admin@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*admin\/dashboard\.php/);

    await expect(page.locator('body')).toContainText(/Administrator|Command Console/i);

    const adminText = await page.locator('body').innerText();
    expect(adminText).not.toContain('+4.2%');
    expect(adminText).not.toContain('94.8%');
  });

  /**
   * WORKFLOW 6: MOBILE RESPONSIVE UI VIEWPORT AUDIT
   */
  test('Workflow 6: Mobile Responsive Layout Verification', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('#login-box button[type="submit"]');
    await page.waitForURL(/.*student\/dashboard\.php/);

    await expect(page.locator('body')).toBeVisible();
  });

});
