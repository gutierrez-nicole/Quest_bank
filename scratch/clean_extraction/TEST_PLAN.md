# QUESTBANK QA AUTOMATION & CAPSTONE TEST PLAN

## 1. Executive Summary & Objective
This Test Plan defines the automated end-to-end verification strategy for **QuestBank (AI Automated Examination System)** at Holy Cross College – Pampanga (Civil Engineering Department). The goal is to verify complete software delivery across 15 core architectural phases without reliance on hardcoded fallbacks or simulated data.

## 2. Scope & Target Modules
- **Authentication & RBAC**: Admin, Teacher, and Student login gateways with CSRF and role enforcement.
- **Lesson Extraction Engine**: Text extraction from `.TXT`, `.DOCX`, `.PPTX`, and `.PDF` files using `LessonExtractionService.php`.
- **AI Question Pipeline**: Grounded question generation via Groq API (`llama-3.3-70b-versatile`) recorded with model metadata.
- **OCR Engine & Evaluation**: Optical Character Recognition on uploaded answer sheets (`OcrService.php`) and comparative AI answer grading (`GroqService.php`).
- **Result Review Workflow**: State machine transitions (`Draft` ➔ `Pending Review` ➔ `Reviewed` ➔ `Published` ➔ `Archived`) with strict student privacy controls.
- **Live Database Analytics**: Real SQL aggregations for ISO 25010 metrics, pass rates, and department statistics.

## 3. Test Tools & Environment
- **Runtime**: PHP 8.5.8 (CLI), MySQL 8.0 local server (`bankquest_db`).
- **E2E Automation**: Node.js v24, Playwright Chromium headless runner.
- **OCR & Extraction**: `pdftotext`, `Tesseract OCR` / Native Image Analyzer fallback.

## 4. Pass/Fail & Verdict Criteria
- **PASS**: 100% execution of core end-to-end workflows using real database persistence, verified student access control, and zero unhandled system exceptions.
- **CONDITIONAL PASS**: Core workflow and security pass 100%, but low-res or handwritten OCR triggers manual review flags as designed.
- **FAIL**: Workflow relies on simulated strings or hardcoded fallbacks.
