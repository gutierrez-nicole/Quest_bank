# QUESTBANK TEST CASES SPECIFICATION

## Test Case Matrix

### TC-01: Real PDF Lesson Upload & Content Extraction
- **Module**: `LessonExtractionService.php` / `teacher/upload_lessons.php`
- **Preconditions**: Teacher logged in.
- **Steps**:
  1. Upload `sample_lesson.pdf`.
  2. Invoke `LessonExtractionService::extractAndSave($materialId)`.
  3. Verify status in `lesson_materials`.
- **Expected Outcome**: `processing_status = 'completed'`, extracted text stored in `lesson_text`, word count > 0.
- **Pass/Fail**: PASS.

### TC-02: AI Question Generation from Uploaded Lesson
- **Module**: `GroqService.php` / `teacher/generate_ai.php`
- **Preconditions**: Extracted lesson text exists in database.
- **Steps**:
  1. Select uploaded lesson from dropdown.
  2. Specify 5 questions and difficulty.
  3. Trigger generation.
- **Expected Outcome**: Groq API returns 5 grounded question items with JSON schema and usage metadata.
- **Pass/Fail**: PASS.

### TC-03: Exam Creation & Extended Question Types Persistence
- **Module**: `teacher/create_exam.php`
- **Preconditions**: Questions generated.
- **Steps**:
  1. Save exam with 7 question types (`multiple_choice`, `true_false`, `identification`, `fill_in_the_blank`, `matching_type`, `problem_solving`, `math_formula`).
  2. Inspect `exams` and `exam_questions` tables.
- **Expected Outcome**: Exam record created with total items and 7 associated `exam_questions` rows.
- **Pass/Fail**: PASS.

### TC-04: Real OCR Scanning & Comparative AI Answer Evaluation
- **Module**: `OcrService.php` / `upload_check.php`
- **Preconditions**: Answer sheet image uploaded.
- **Steps**:
  1. Scan `printed_clear.png` via `OcrService::processAnswerSheet()`.
  2. Grade extracted text against Master Key via `GroqService::evaluateAnswerSheetDetailed()`.
- **Expected Outcome**: Extracted text contains answers, confidence score computed, evaluation breakdown generated.
- **Pass/Fail**: PASS.

### TC-05: Result Review Workflow & Student Access Control
- **Module**: `teacher/reports.php` / `student/dashboard.php`
- **Preconditions**: Answer sheet evaluated.
- **Steps**:
  1. Submission saved with `review_status = 'pending_review'`.
  2. Query student dashboard for Student A.
  3. Teacher clicks Publish in `reports.php`.
  4. Query student dashboard again.
- **Expected Outcome**: Invisible to student while `pending_review`. Visible exclusively to Student A once `published`.
- **Pass/Fail**: PASS.

### TC-06: Security Verification (XSS, CSRF, SQLi, IDOR)
- **Module**: Security Helper & Database Core
- **Preconditions**: System online.
- **Steps**:
  1. Submit XSS string `<script>alert('XSS')</script>`.
  2. Validate CSRF token check on POST.
  3. Submit SQL injection string `1' OR '1'='1`.
  4. Attempt cross-student query.
- **Expected Outcome**: HTML special characters sanitized, invalid CSRF blocked, SQL injection safely bound by PDO, cross-student query returns 0 rows.
- **Pass/Fail**: PASS.
