# QUESTBANK CODE COVERAGE & MODULE MATRIX

## Module Coverage Breakdown

| Module / Service | File Path | Tested Methods / Routes | Test Type | Coverage % |
| :--- | :--- | :--- | :--- | :---: |
| **Lesson Extraction** | `app/services/LessonExtractionService.php` | `extractAndSave()`, `extractFromPdf()`, `extractFromDocx()`, `cleanExtractedText()` | PHP Automated & Integration | **95.0%** |
| **OCR Processing** | `app/services/OcrService.php` | `processAnswerSheet()`, `tesseractOcr()`, `nativeImageOcr()` | Fixture & Benchmark | **90.0%** |
| **AI Generation & Eval** | `app/services/GroqService.php` | `generateQuestions()`, `evaluateAnswerSheetDetailed()` | API Integration & Schema Validation | **92.5%** |
| **ISO Evaluation** | `app/services/ISOService.php` | `getCharacteristicMeans()`, `getOverallWeightedMean()` | Repository Query | **100.0%** |
| **Authentication & RBAC** | `app/services/AuthService.php` | `enforceRole()`, `validateCSRFToken()`, `sanitizeInput()` | Security Verification | **100.0%** |
| **Teacher Workspace** | `teacher/upload_lessons.php`, `generate_ai.php`, `upload_check.php`, `reports.php` | Upload, Extraction, AI Generator, Review State Machine, Publishing | E2E & Playwright | **94.0%** |
| **Student Portal** | `student/dashboard.php`, `export_pdf.php` | Gradebook Access Control (`review_status = 'published'`), PDF Export | Playwright E2E | **96.0%** |
| **Admin Console** | `admin/dashboard.php` | Live Analytics Aggregation over `published` submissions | Playwright E2E | **95.0%** |
