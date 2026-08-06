# 🎓 QuestBank: Automated Examination Management & Performance Monitoring System

> **Target Institution:** Holy Cross College – Pampanga  
> **Target Department:** College of Engineering (Department of Civil Engineering - BSCE)  
> **AI Vision & Evaluation Engine:** Groq Cloud AI API (`llama-3.3-70b-versatile` & `llama-3.2-11b-vision-preview`)

---

## 📌 System Overview

**QuestBank** is an intelligent, web-based examination management and optical evaluation portal designed for academic civil engineering institutions. The system provides end-to-end capabilities:
- **Instructional Material Extraction:** Upload and extract content from PDF, DOCX, PPTX, and TXT lesson files.
- **AI Exam Generation:** Automatically generate Multiple Choice, True/False, Identification, and Civil Engineering Problem Solving questions.
- **Optical Answer Sheet Checker (OCR):** Evaluate scanned student answer sheets via Groq Vision OCR and automated scoring logic.
- **Review & Publication Workflow:** Faculty score review, item-level overrides, status state transitions (`draft` $\rightarrow$ `pending_review` $\rightarrow$ `reviewed` $\rightarrow$ `finalized` $\rightarrow$ `published`), and complete audit history.
- **Student Performance Analytics:** Grade tracking, passing rate metrics, radar topic breakdown, and FPDF transcript exports.
- **ISO/IEC 25010 Evaluation:** Quality assessment tool for institutional evaluation.

---

## 📋 Production Requirements

### 1. Web & Database Server
- **PHP:** 8.0 or higher
- **Database:** MySQL 5.7+ or MariaDB 10.4+
- **Web Server:** Apache (with `mod_rewrite`), Nginx, or PHP CLI Server for development.

### 2. Required PHP Extensions
- `pdo_mysql` (Database connectivity)
- `fileinfo` (MIME validation & magic-byte verification)
- `gd` / `imagick` (Image processing)
- `json` (API payload handling)
- `mbstring` (Multibyte text processing)
- `curl` (Groq API communication)
- `zip` (DOCX & PPTX container inspection)

### 3. Required Command-Line Dependencies (for PDF Extraction & OCR)
- **pdftotext / pdfimages** (`poppler-utils`) — Required for PDF text & image extraction.
  - *Ubuntu/Debian:* `sudo apt-get install poppler-utils`
  - *macOS:* `brew install poppler`
- **tesseract** — Required as local fallback OCR engine when Vision API is offline.
  - *Ubuntu/Debian:* `sudo apt-get install tesseract-ocr`
  - *macOS:* `brew install tesseract`

---

## 🚀 Installation & Environment Setup

### Step 1: Database Setup
1. Create the MySQL database:
   ```sql
   CREATE DATABASE bankquest_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import the base database schema:
   ```bash
   mysql -u root -p bankquest_db < database/bankquest_db.sql
   ```
3. Run the idempotent database migration script to apply all table enhancements:
   ```bash
   php database/migrate.php
   ```

### Step 2: Configure Environment Variables
Copy `.env.example` to `.env` and fill in your environment settings:
```bash
cp .env.example .env
```
Edit `.env`:
```ini
DB_HOST=127.0.0.1
DB_NAME=bankquest_db
DB_USER=root
DB_PASS=your_db_password
GROQ_API_KEY=your_groq_api_key
```

Alternatively, configure constants directly in `app/config/config.php`.

### Step 3: Set Upload Directory Permissions
Ensure write permissions for document uploads:
```bash
chmod 775 teacher/uploads uploads
```

### Step 4: Launch Web Application
Run using Apache/Nginx or built-in PHP web server:
```bash
php -S localhost:8000
```
Access the application at `http://localhost:8000`.

---

## 🔑 Login & Initial Setup Process

1. **Initial Credentials:** Upon running `database/migrate.php`, administrative and default accounts are populated in the database.
2. **First Login:**
   - Access `http://localhost:8000` to reach the login gateway.
   - Login as Administrator or Faculty to manage departments, subjects, and student rosters.
3. **Password Update:** Immediately change default account passwords via **Profile Settings**.

---

## 💡 Production Usage Guide

### Faculty / Teacher Portal (`teacher/`)
- **Upload Lessons:** Upload course materials (`.pdf`, `.docx`, `.pptx`, `.txt`). The extraction engine parses text into structured material.
- **AI Exam Generator:** Select extracted materials, set question count, difficulty, and question types to generate AI exams.
- **OCR Answer Checker:** Upload scanned answer sheets (`.pdf`, `.png`, `.jpg`). The system evaluates answers and calculates total scores.
- **Reports & Review:** Inspect OCR confidence scores, apply item-level score overrides with mandatory reason logging, and advance submission statuses through publication.

### Student Portal (`student/`)
- **Dashboard:** View published exam results, average scores, and topic performance.
- **Export Transcripts:** Download official FPDF transcripts of published exams.

### Administrator Console (`admin/`)
- **User & Roster Management:** Create and manage Teacher, Student, and Admin accounts.
- **Departments & Subjects:** Manage institutional course offerings.
- **Activity Audit Logs:** Inspect real-time multi-role action audit logs.

---

## 🔒 Deployment & Security Notes

- **CSRF Protection:** All forms include anti-CSRF token verification (`validateCSRFToken()`).
- **SQL Injection Prevention:** 100% prepared PDO statements throughout database repositories and services.
- **MIME & Extension Security:** File uploads undergo extension whitelisting, double extension blocking, and magic byte validation via `FileValidationService`.
- **Student Privacy & IDOR Prevention:** Student access is restricted strictly to their own results (`student_id = session.user_id`) and published submissions (`review_status = 'published'`).

---

## 🧪 Testing & Regression Smoke Suite

QuestBank includes a lightweight, zero-dependency PHP maintainer smoke test suite for validating core system functionality:

```bash
# Run Maintainer Smoke Test Suite:
php tests/run_smoke_tests.php
```

The smoke suite validates:
- **Auth & Authorization:** Multi-role demo accounts, password hashing, and forced reset flags.
- **Exam Scoring Engine:** Server-side answer evaluation and point calculations.
- **Results Privacy:** Publication workflow boundaries and student data isolation.
- **Database Migrations:** Migration execution safety and password preservation.

---

## ⚠️ Known OCR & System Limitations

1. **Low Quality Scans:** Extremely low resolution or heavily skewed phone photographs may result in lower OCR confidence scores (<75%), automatically flagging the submission for teacher review.
2. **Handwritten Identification:** Complex cursive handwriting on identification questions may require teacher review via the item-level score override modal.
3. **Groq API Rate Limits:** Free-tier Groq API keys may experience rate limits during concurrent bulk OCR requests. Local Tesseract CLI fallback is recommended for high-volume environments.