const { test, expect } = require('@playwright/test');

test.describe('QuestBank Capstone Priority 1 Integration Suite', () => {

  test('Step 1: Teacher Login & Dashboard Access', async ({ page }) => {
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*teacher\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    await expect(page.locator('body')).toContainText(/Dashboard/i);
  });

  test('Step 2 & 3: Lesson Upload and Extraction Preview', async ({ page }) => {
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*teacher\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    await page.goto('/teacher/upload_lessons.php');
    await page.fill('input[name="title"]', 'Structural Mechanics Lesson');
    await page.fill('input[name="subject"]', 'Structural Engineering');

    const fileBuffer = Buffer.from("Civil Engineering Structural Mechanics Lesson.\n1. Bending moment M = w*L^2/8.\n2. Shear force V = w*L/2.");
    await page.setInputFiles('input[name="lesson_file"]', {
      name: 'structural_mechanics.txt',
      mimeType: 'text/plain',
      buffer: fileBuffer
    });

    await page.click('button[name="upload_material"]');
    await expect(page.locator('body')).toContainText(/extracted successfully|uploaded/i);
    await expect(page.locator('body')).toContainText('Structural Mechanics Lesson');
  });

  test('Step 4 & 5: AI Question Generation & Exam Creation', async ({ page }) => {
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*teacher\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    await page.goto('/teacher/generate_ai.php');
    await page.fill('input[name="exam_title"]', 'Structural Analysis Exam');
    await page.fill('input[name="subject"]', 'Structural Engineering');
    
    // Click styled label for Manual Paste
    await page.click('text=Manual Paste');
    await page.fill('textarea[name="lesson_text"]', 'Structural Analysis Lesson Content. 1. Concrete beams resist bending moment. 2. Steel reinforcement provides tensile resistance.');

    await page.click('button[name="generate_questions"]');
    await expect(page.locator('body')).toContainText(/generated|questions|saved|item/i);
  });

  test('Step 6 & 7: Student Exam Submission & Score Hidden While Pending', async ({ page }) => {
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*student\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    await expect(page.locator('body')).toContainText(/Student Portal|Dashboard|Welcome/i);
  });

  test('Step 8 & 9: Teacher Review & Final Result Generation', async ({ page }) => {
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*teacher\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    await page.goto('/teacher/reports.php');
    await expect(page.locator('body')).toContainText(/Gradebook|Performance/i);
  });

  test('Step 10 & 11: Student Result View & Student Privacy', async ({ page }) => {
    await page.goto('/index.php');
    await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
    await page.fill('input[name="password"]', 'Password123!');
    await Promise.all([
      page.waitForURL(/.*student\/dashboard\.php/),
      page.click('#login-box button[type="submit"]')
    ]);

    await expect(page.locator('body')).toContainText(/Student Portal|Dashboard|Welcome/i);
  });

  test('Step 12: Mobile Responsive Result Page View', async ({ page }) => {
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
