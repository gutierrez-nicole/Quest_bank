# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: capstone.spec.js >> QuestBank Capstone Priority 1 Integration Suite >> Step 4 & 5: AI Question Generation & Exam Creation
- Location: tests/capstone.spec.js:42:3

# Error details

```
Test timeout of 60000ms exceeded.
```

```
Error: page.click: Test timeout of 60000ms exceeded.
Call log:
  - waiting for locator('input[name="input_source"][value="manual"]')
    - locator resolved to <input type="radio" value="manual" class="hidden" name="input_source" onclick="toggleInputSource('manual')"/>
  - attempting click action
    2 × waiting for element to be visible, enabled and stable
      - element is not visible
    - retrying click action
    - waiting 20ms
    2 × waiting for element to be visible, enabled and stable
      - element is not visible
    - retrying click action
      - waiting 100ms
    112 × waiting for element to be visible, enabled and stable
        - element is not visible
      - retrying click action
        - waiting 500ms

```

# Page snapshot

```yaml
- generic [ref=f2e1]:
  - complementary [ref=f2e2]:
    - generic [ref=f2e3]:
      - generic [ref=f2e4]: 
      - navigation [ref=f2e8]:
        - link "" [ref=f2e9] [cursor=pointer]:
          - /url: dashboard.php
        - link "" [ref=f2e11] [cursor=pointer]:
          - /url: create_exam.php
        - link "" [ref=f2e13] [cursor=pointer]:
          - /url: generate_ai.php
        - link "" [ref=f2e15] [cursor=pointer]:
          - /url: upload_check.php
        - link "" [ref=f2e17] [cursor=pointer]:
          - /url: upload_lessons.php
        - link "" [ref=f2e19] [cursor=pointer]:
          - /url: manage_students.php
        - link "" [ref=f2e21] [cursor=pointer]:
          - /url: reports.php
        - link "" [ref=f2e23] [cursor=pointer]:
          - /url: backup.php
        - link "" [ref=f2e25] [cursor=pointer]:
          - /url: profile_settings.php
      - link "" [ref=f2e28] [cursor=pointer]:
        - /url: ../logout.php
  - main [ref=f2e30]:
    - generic [ref=f2e31]:
      - generic [ref=f2e32]:
        - heading " Civil Engineering AI Item Generator" [level=2] [ref=f2e33]:
          - generic [ref=f2e34]: 
          - text: Civil Engineering AI Item Generator
        - paragraph [ref=f2e35]: Generate specialized test items from course materials for Civil Engineering disciplines.
      - generic [ref=f2e36]: PR
    - generic [ref=f2e40]:
      - generic [ref=f2e41]:
        - generic [ref=f2e42]:
          - heading " 1. Lesson & Branch Setup" [level=3] [ref=f2e43]:
            - generic [ref=f2e44]: 
            - text: 1. Lesson & Branch Setup
          - generic [ref=f2e45]: Groq Llama-3.3
        - generic [ref=f2e46]:
          - generic [ref=f2e47]:
            - text: Exam Title
            - generic [ref=f2e48]:
              - generic [ref=f2e49]: 
              - textbox "e.g. Reinforced Concrete Design Quiz 1" [ref=f2e50]: Structural Analysis Exam
          - generic [ref=f2e51]:
            - text: Subject Name
            - generic [ref=f2e52]:
              - generic [ref=f2e53]: 
              - textbox "e.g. Structural Theory" [active] [ref=f2e54]: Structural Engineering
          - generic [ref=f2e55]:
            - text: Content Input Source
            - generic [ref=f2e56]:
              - generic [ref=f2e57] [cursor=pointer]:
                - generic [ref=f2e58]: 
                - text: Extracted Lessons
              - generic [ref=f2e59] [cursor=pointer]:
                - generic [ref=f2e60]: 
                - text: Manual Paste
          - generic [ref=f2e61]:
            - generic [ref=f2e62]:
              - generic [ref=f2e63]: Select Extracted Lessons
              - generic [ref=f2e64]: 11 Available
            - generic [ref=f2e65]:
              - generic [ref=f2e66] [cursor=pointer]:
                - checkbox "Select All Module Lessons" [ref=f2e67]
                - generic [ref=f2e68]: Select All Module Lessons
              - generic [ref=f2e69] [cursor=pointer]:
                - generic [ref=f2e70]:
                  - checkbox "Structural Mechanics Lesson 15 words" [ref=f2e71]
                  - generic [ref=f2e72]: Structural Mechanics Lesson
                - generic [ref=f2e73]: 15 words
              - generic [ref=f2e74] [cursor=pointer]:
                - generic [ref=f2e75]:
                  - checkbox "Structural Mechanics Lesson 15 words" [ref=f2e76]
                  - generic [ref=f2e77]: Structural Mechanics Lesson
                - generic [ref=f2e78]: 15 words
              - generic [ref=f2e79] [cursor=pointer]:
                - generic [ref=f2e80]:
                  - checkbox "Structural Mechanics Lesson 15 words" [ref=f2e81]
                  - generic [ref=f2e82]: Structural Mechanics Lesson
                - generic [ref=f2e83]: 15 words
              - generic [ref=f2e84] [cursor=pointer]:
                - generic [ref=f2e85]:
                  - checkbox "Structural Mechanics Lesson 15 words" [ref=f2e86]
                  - generic [ref=f2e87]: Structural Mechanics Lesson
                - generic [ref=f2e88]: 15 words
              - generic [ref=f2e89] [cursor=pointer]:
                - generic [ref=f2e90]:
                  - checkbox "Structural Mechanics Lesson 15 words" [ref=f2e91]
                  - generic [ref=f2e92]: Structural Mechanics Lesson
                - generic [ref=f2e93]: 15 words
              - generic [ref=f2e94] [cursor=pointer]:
                - generic [ref=f2e95]:
                  - checkbox "Concrete Beams 25 words" [ref=f2e96]
                  - generic [ref=f2e97]: Concrete Beams
                - generic [ref=f2e98]: 25 words
              - generic [ref=f2e99] [cursor=pointer]:
                - generic [ref=f2e100]:
                  - checkbox "Concrete Beams 25 words" [ref=f2e101]
                  - generic [ref=f2e102]: Concrete Beams
                - generic [ref=f2e103]: 25 words
              - generic [ref=f2e104] [cursor=pointer]:
                - generic [ref=f2e105]:
                  - checkbox "Valid PDF Lesson 5 words" [ref=f2e106]
                  - generic [ref=f2e107]: Valid PDF Lesson
                - generic [ref=f2e108]: 5 words
              - generic [ref=f2e109] [cursor=pointer]:
                - generic [ref=f2e110]:
                  - checkbox "Valid PPTX Lesson 9 words" [ref=f2e111]
                  - generic [ref=f2e112]: Valid PPTX Lesson
                - generic [ref=f2e113]: 9 words
              - generic [ref=f2e114] [cursor=pointer]:
                - generic [ref=f2e115]:
                  - checkbox "Valid DOCX Lesson 13 words" [ref=f2e116]
                  - generic [ref=f2e117]: Valid DOCX Lesson
                - generic [ref=f2e118]: 13 words
              - generic [ref=f2e119] [cursor=pointer]:
                - generic [ref=f2e120]:
                  - checkbox "Valid TXT Lesson 19 words" [ref=f2e121]
                  - generic [ref=f2e122]: Valid TXT Lesson
                - generic [ref=f2e123]: 19 words
          - generic [ref=f2e124]:
            - generic [ref=f2e125]:
              - text: Difficulty Level
              - combobox [ref=f2e126]:
                - option "Easy"
                - option "Medium" [selected]
                - option "Hard / Advanced"
                - option "Mixed Difficulty"
            - generic [ref=f2e127]:
              - text: Number of Items
              - combobox [ref=f2e128]:
                - option "5 Questions" [selected]
                - option "10 Questions"
                - option "15 Questions"
                - option "20 Questions"
                - option "25 Questions"
                - option "30 Questions"
                - option "50 Questions"
          - generic [ref=f2e129]:
            - text: Civil Engineering Specialization
            - combobox [ref=f2e130]:
              - option "🏗️ Structural Engineering (Beams, Columns, Steel & Reinforced Concrete Design)" [selected]
              - option "🧪 Geotechnical Engineering (Soil Mechanics, Foundations & Earth Structures)"
              - option "🚧 Construction Engineering & Management (Project Planning, Estimating & Site Control)"
              - option "🌿 Environmental Engineering (Water Resources, Wastewater & Hydrology)"
              - option "🛣️ Transportation Engineering (Pavements, Highway Design & Traffic Flow)"
          - generic [ref=f2e131]:
            - text: Question Format / Type
            - combobox [ref=f2e132]:
              - option "Multiple Choice (Options A-D)" [selected]
              - option "True or False"
              - option "Identification"
              - option "Fill-in-the-Blank"
              - option "Matching Type"
              - option "Problem Solving"
              - option "Math Formula (LaTeX)"
          - button " Generate AI Test Items" [ref=f2e133] [cursor=pointer]:
            - generic [ref=f2e134]: 
            - text: Generate AI Test Items
      - generic [ref=f2e136]:
        - generic [ref=f2e137]: 
        - heading "Ready to Generate Civil Engineering Exams" [level=3] [ref=f2e139]
        - paragraph [ref=f2e140]: Fill out the form on the left with your lesson content and select your Civil Engineering specialization branch.
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
  22  |       page.waitForURL(/.*teacher\/dashboard\.php/),
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
> 56  |     await page.click('text=Manual Paste');
      |                ^ Error: page.click: Test timeout of 60000ms exceeded.
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