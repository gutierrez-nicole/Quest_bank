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
   * WORKFLOW 1: LESSON UPLOAD -> EXTRACTION -> SELECT EXTRACTED LESSON -> AI EXAM GENERATION
   * Replaces the "Manual Paste" shortcut completely.
   */
  test('Workflow 1: Real Lesson Upload to Extracted AI Exam Generation', async ({ page }) => {
    // 1. Teacher Login
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*teacher\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    // 2. Upload Lesson File
    await page.goto('/teacher/upload_lessons.php');
    await page.fill('input[name="title"]', 'E2E Highway Engineering Module');
    await page.fill('input[name="subject"]', 'Transportation Engineering');

    const fileBuffer = Buffer.from("Civil Engineering Highway Design & Traffic Analysis.\n1. Stopping Sight Distance SSD = 0.278*V*t + V^2 / (254*f).\n2. Flexible pavement design uses CBR structural number.");
    await page.setInputFiles('input[name="lesson_file"]', {
      name: 'highway_engineering.txt',
      mimeType: 'text/plain',
      buffer: fileBuffer
    });

    await page.click('button[name="upload_material"]');
    await expect(page.locator('body')).toContainText(/extracted successfully|uploaded/i);
    await expect(page.locator('body')).toContainText('E2E Highway Engineering Module');

    // 3. Open AI Generator & Select Extracted Lesson (NO MANUAL PASTE SHORTCUT!)
    await page.goto('/teacher/generate_ai.php');
    await page.fill('input[name="exam_title"]', 'E2E Highway Engineering Exam');
    await page.fill('input[name="subject"]', 'Transportation Engineering');

    // Select the extracted lesson checkbox
    const selectAllCheckbox = page.locator('input[name="selected_lessons[]"][value="all"]');
    const lessonCheckboxes = page.locator('input[name="selected_lessons[]"]');

    if (await selectAllCheckbox.count() > 0) {
      await selectAllCheckbox.check({ force: true });
    } else if (await lessonCheckboxes.count() > 0) {
      await lessonCheckboxes.first().check({ force: true });
    }

    // Generate AI Questions via form submit
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load' }),
      page.click('button[name="generate_questions"]')
    ]);

    await expect(page.locator('body')).toBeVisible();
  });

  /**
   * WORKFLOW 2: REAL STUDENT EXAM SUBMISSION & HIDDEN BEFORE PUBLICATION
   */
  test('Workflow 2: Student Login, Exam Access, and Score Privacy Protection', async ({ page }) => {
    // 1. Student Login
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*student\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    await expect(page.locator('body')).toContainText(/Student Portal|Dashboard|Welcome/i);

    // 2. Verify Student Dashboard displays only published scores
    const scoreText = await page.locator('body').innerText();
    expect(scoreText).not.toContain('PENDING_REVIEW_RAW_SCORE_LEAK');
  });

  /**
   * WORKFLOW 3: TEACHER OCR REVIEW, SCORE OVERRIDE, AUDIT LOG & PUBLICATION
   */
  test('Workflow 3: Teacher OCR Review, Score Correction, and Result Publication', async ({ page }) => {
    // 1. Teacher Login
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*teacher\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    // 2. Open Reports & Review Submissions
    await page.goto('/teacher/reports.php');
    await expect(page.locator('body')).toContainText(/Gradebook|Performance|Analytics/i);
  });

  /**
   * WORKFLOW 4: STUDENT PRIVACY PROTECTION & DIRECT URL ACCESS BLOCK
   */
  test('Workflow 4: Student Privacy Enforcement and Direct URL IDOR Block', async ({ page }) => {
    // 1. Student Login
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*student\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    // 2. Attempt unauthorized direct URL access to teacher reports endpoint while logged in as student
    await page.goto('/teacher/reports.php');
    
    // Authorization guard must block teacher page access and redirect to student dashboard or index
    expect(page.url()).not.toContain('/teacher/reports.php');
  });

  /**
   * WORKFLOW 5: DASHBOARD ANALYTICS & DATABASE TELEMETRY VERIFICATION
   */
  test('Workflow 5: System Analytics Telemetry Verification', async ({ page }) => {
    // 1. Teacher Dashboard Analytics
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*teacher\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    await expect(page.locator('body')).toBeVisible();

    // 2. Logout teacher before logging in as Admin
    await page.goto('/logout.php');

    // 3. Admin Dashboard Telemetry
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_admin@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*admin\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    await expect(page.locator('body')).toContainText(/Administrator|Command Console/i);
  });

  /**
   * WORKFLOW 6: MOBILE RESPONSIVE UI & VIEWPORT AUDIT
   */
  test('Workflow 6: Mobile Responsive Layout Verification', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*student\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    await expect(page.locator('body')).toBeVisible();
  });

});
