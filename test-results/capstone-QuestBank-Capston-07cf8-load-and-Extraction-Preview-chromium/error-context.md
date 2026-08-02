# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: capstone.spec.js >> QuestBank Capstone Priority 1 Integration Suite >> Step 2 & 3: Lesson Upload and Extraction Preview
- Location: tests/capstone.spec.js:17:3

# Error details

```
Test timeout of 60000ms exceeded.
```

```
Error: page.waitForURL: Test timeout of 60000ms exceeded.
=========================== logs ===========================
waiting for navigation until "load"
  navigated to "http://127.0.0.1:8000/index.php"
============================================================
```

# Page snapshot

```yaml
- generic [active] [ref=f1e1]:
  - generic [ref=f1e2]:
    - generic [ref=f1e3]:
      - generic [ref=f1e4]:
        - generic [ref=f1e5]: 
        - generic [ref=f1e7]:
          - paragraph [ref=f1e8]: Holy Cross College - Pampanga
          - heading "QuestBank" [level=1] [ref=f1e9]
      - button "" [ref=f1e10] [cursor=pointer]: 
    - generic [ref=f1e12]:
      - text: AI Automated Examination System
      - heading "Empowering Civil Engineering & Academic Assessments with Intelligent AI." [level=2] [ref=f1e13]
      - paragraph [ref=f1e14]: Upload course materials, generate exam items, and grade handwritten student test papers using optical evaluation technology.
    - generic [ref=f1e15]: © 2026 QuestBank. All rights reserved.
  - generic [ref=f1e16]:
    - generic [ref=f1e18]:
      - generic [ref=f1e19]: 
      - text: Invalid email address or password.
    - generic [ref=f1e20]:
      - generic [ref=f1e21]:
        - text: Faculty & Student Gateway
        - heading "Sign in to Portal" [level=2] [ref=f1e22]
        - paragraph [ref=f1e23]: Provide your credentials to access examination dashboards.
      - generic [ref=f1e24]:
        - generic [ref=f1e25]:
          - text: Email Address
          - generic [ref=f1e26]:
            - generic [ref=f1e27]: 
            - textbox "you@questbank.edu.ph" [ref=f1e28]
        - generic [ref=f1e29]:
          - text: Password
          - generic [ref=f1e30]:
            - generic [ref=f1e31]: 
            - textbox "••••••••" [ref=f1e32]
        - generic [ref=f1e33]:
          - generic [ref=f1e34] [cursor=pointer]:
            - checkbox "Remember me" [ref=f1e35]
            - text: Remember me
          - link "Forgot password?" [ref=f1e36] [cursor=pointer]:
            - /url: javascript:void(0)
        - button " SIGN IN TO DASHBOARD" [ref=f1e37] [cursor=pointer]:
          - generic [ref=f1e38]: 
          - text: SIGN IN TO DASHBOARD
      - generic [ref=f1e39]:
        - text: Don't have an account yet?
        - button "Register here" [ref=f1e40] [cursor=pointer]
```

# Test source

```ts
  1   | const { test, expect } = require('@playwright/test');
  2   | 
  3   | test.describe('QuestBank Capstone Priority 1 Integration Suite', () => {
  4   | 
  5   |   test('Step 1: Teacher Login & Dashboard Access', async ({ page }) => {
  6   |     await page.goto('/index.php');
  7   |     await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
  8   |     await page.fill('input[name="password"]', 'Password123!');
  9   |     await Promise.all([
  10  |       page.waitForURL(/.*teacher\/dashboard\.php/),
  11  |       page.click('#login-box button[type="submit"]')
  12  |     ]);
  13  | 
  14  |     await expect(page.locator('body')).toContainText(/Dashboard/i);
  15  |   });
  16  | 
  17  |   test('Step 2 & 3: Lesson Upload and Extraction Preview', async ({ page }) => {
  18  |     await page.goto('/index.php');
  19  |     await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
  20  |     await page.fill('input[name="password"]', 'Password123!');
  21  |     await Promise.all([
> 22  |       page.waitForURL(/.*teacher\/dashboard\.php/),
      |            ^ Error: page.waitForURL: Test timeout of 60000ms exceeded.
  23  |       page.click('#login-box button[type="submit"]')
  24  |     ]);
  25  | 
  26  |     await page.goto('/teacher/upload_lessons.php');
  27  |     await page.fill('input[name="title"]', 'Structural Mechanics Lesson');
  28  |     await page.fill('input[name="subject"]', 'Structural Engineering');
  29  | 
  30  |     const fileBuffer = Buffer.from("Civil Engineering Structural Mechanics Lesson.\n1. Bending moment M = w*L^2/8.\n2. Shear force V = w*L/2.");
  31  |     await page.setInputFiles('input[name="lesson_file"]', {
  32  |       name: 'structural_mechanics.txt',
  33  |       mimeType: 'text/plain',
  34  |       buffer: fileBuffer
  35  |     });
  36  | 
  37  |     await page.click('button[name="upload_material"]');
  38  |     await expect(page.locator('body')).toContainText(/extracted successfully|uploaded/i);
  39  |     await expect(page.locator('body')).toContainText('Structural Mechanics Lesson');
  40  |   });
  41  | 
  42  |   test('Step 4 & 5: AI Question Generation & Exam Creation', async ({ page }) => {
  43  |     await page.goto('/index.php');
  44  |     await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
  45  |     await page.fill('input[name="password"]', 'Password123!');
  46  |     await Promise.all([
  47  |       page.waitForURL(/.*teacher\/dashboard\.php/),
  48  |       page.click('#login-box button[type="submit"]')
  49  |     ]);
  50  | 
  51  |     await page.goto('/teacher/generate_ai.php');
  52  |     await page.fill('input[name="exam_title"]', 'Structural Analysis Exam');
  53  |     await page.fill('input[name="subject"]', 'Structural Engineering');
  54  |     
  55  |     // Click styled label for Manual Paste
  56  |     await page.click('text=Manual Paste');
  57  |     await page.fill('textarea[name="lesson_text"]', 'Structural Analysis Lesson Content. 1. Concrete beams resist bending moment. 2. Steel reinforcement provides tensile resistance.');
  58  | 
  59  |     await page.click('button[name="generate_questions"]');
  60  |     await expect(page.locator('body')).toContainText(/generated|questions|saved|item/i);
  61  |   });
  62  | 
  63  |   test('Step 6 & 7: Student Exam Submission & Score Hidden While Pending', async ({ page }) => {
  64  |     await page.goto('/index.php');
  65  |     await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
  66  |     await page.fill('input[name="password"]', 'Password123!');
  67  |     await Promise.all([
  68  |       page.waitForURL(/.*student\/dashboard\.php/),
  69  |       page.click('#login-box button[type="submit"]')
  70  |     ]);
  71  | 
  72  |     await expect(page.locator('body')).toContainText(/Student Portal|Dashboard|Welcome/i);
  73  |   });
  74  | 
  75  |   test('Step 8 & 9: Teacher Review & Final Result Generation', async ({ page }) => {
  76  |     await page.goto('/index.php');
  77  |     await page.fill('input[name="email"]', 'qa_teacher_a@questbank.test');
  78  |     await page.fill('input[name="password"]', 'Password123!');
  79  |     await Promise.all([
  80  |       page.waitForURL(/.*teacher\/dashboard\.php/),
  81  |       page.click('#login-box button[type="submit"]')
  82  |     ]);
  83  | 
  84  |     await page.goto('/teacher/reports.php');
  85  |     await expect(page.locator('body')).toContainText(/Gradebook|Performance/i);
  86  |   });
  87  | 
  88  |   test('Step 10 & 11: Student Result View & Student Privacy', async ({ page }) => {
  89  |     await page.goto('/index.php');
  90  |     await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
  91  |     await page.fill('input[name="password"]', 'Password123!');
  92  |     await Promise.all([
  93  |       page.waitForURL(/.*student\/dashboard\.php/),
  94  |       page.click('#login-box button[type="submit"]')
  95  |     ]);
  96  | 
  97  |     await expect(page.locator('body')).toContainText(/Student Portal|Dashboard|Welcome/i);
  98  |   });
  99  | 
  100 |   test('Step 12: Mobile Responsive Result Page View', async ({ page }) => {
  101 |     await page.setViewportSize({ width: 375, height: 812 });
  102 |     await page.goto('/index.php');
  103 |     await page.fill('input[name="email"]', 'qa_student_a@questbank.test');
  104 |     await page.fill('input[name="password"]', 'Password123!');
  105 |     await Promise.all([
  106 |       page.waitForURL(/.*student\/dashboard\.php/),
  107 |       page.click('#login-box button[type="submit"]')
  108 |     ]);
  109 | 
  110 |     await expect(page.locator('body')).toBeVisible();
  111 |   });
  112 | 
  113 | });
  114 | 
```