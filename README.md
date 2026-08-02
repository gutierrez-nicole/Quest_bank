# 🎓 QuestBank: AI-Powered Automated Examination Management & Academic Performance Monitoring System

> **Target Institution:** Holy Cross College – Pampanga  
> **Target Department:** College of Engineering (Department of Civil Engineering - BSCE)  
> **Core Integration:** Groq Cloud AI Engine (`llama-3.3-70b-versatile` & `llama-3.2-11b-vision-preview`)  

---

## 📌 Project Overview

**QuestBank** is an intelligent, web-based examination management and optical evaluation portal tailored for academic civil engineering institutions. Built with a **ProMax Layered Architecture** (Repositories, Services, Controllers, and Frontend Modules), the system streamlines test item generation from instructional materials, automates optical/handwritten student answer sheet grading, computes performance analytics, tracks ISO/IEC 25010 Quality Model metrics, and predicts academically at-risk students using Artificial Intelligence.

---

## ✨ Key Features

## 🚀 Database Setup & Test Execution

### 1. Database Schema Migration
Run the idempotent schema migration script:
```bash
php database/migrate.php
```

### 2. Run Automated Unit Test Suites
Execute all Priority 1 capability unit test suites:
```bash
php tests/test_prompt1_extraction.php
php tests/test_prompt2_ai_generation.php
php tests/test_prompt3_ocr.php
php tests/test_prompt4_evaluation.php
php tests/test_prompt5_results_review.php
```

### 3. Run Playwright End-to-End Tests
Install Playwright dependencies and execute end-to-end tests:
```bash
npm install
npx playwright install chromium
npx playwright test
```

### 👨‍🏫 Faculty / Teacher Portal
* 📄 **Instructional Material Processing:** Upload lesson materials in PDF, DOCX, PPTX, or TXT format.
* 🤖 **AI Question Generator:** Automatically create Multiple Choice, True/False, Fill-in-the-Blank, Identification, Matching Type, and **Civil Engineering Problem Solving / Math Formulas**.
* ✍️ **AI Optical Answer Sheet Checker (OCR):** Evaluate scanned or uploaded images/PDFs of student test papers against answer keys with automated Vision OCR grading.
* ⚠️ **Academically At-Risk Student Early Warning:** Automated identification of students scoring below 75% average for targeted faculty tutorial intervention.
* 📊 **Performance Analytics & Native FPDF Export:** Comprehensive topic performance reports and printable PDF analytical exports.
* 💾 **System Backup & Restore:** Generate full SQL database snapshots and restore data directly from the portal.

### 🎓 Student Portal
* 📈 **Performance Dashboard:** View overall GPA, passing rates, exam completion history, and score analytics.
* 📄 **Official Examination Transcripts:** Export printable PDF transcripts of exam results and topic breakdowns.

### 🛠️ Administrator Console
* 👥 **User Account Management:** Complete CRUD management for Admin, Teacher, and Student credentials.
* 🏛️ **Departments & Curriculum Management:** Manage academic departments (DCE - Department of Civil Engineering), faculty heads, subjects catalog, and section rosters.
* 🏆 **ISO/IEC 25010 Quality Model Assessment:** Evaluate system acceptability across 9 software quality characteristics with automated 4-point Likert scale weighted mean calculation (`3.92 / 4.00`) and printable FPDF exports.
* 📋 **Global Activity Audit Log:** Real-time color-coded multi-role audit trail of all user activities and telemetry events.

---

## 🛠️ Technology Stack & Architecture

* **Architecture Pattern:** Enterprise Layered Architecture (Repositories, Business Services, Controllers, Asset Bundles)
* **Backend Engine:** Native PHP 8.0+ (Central Bootstrap Autoloader, PDO Singleton Pattern)
* **Database:** MySQL (`bankquest_db`) with SQL Schema repository
* **Frontend UI/UX:** HTML5, Tailwind CSS, FontAwesome 6, Chart.js, Google Fonts (*Plus Jakarta Sans*)
* **JavaScript Assets:** Modular JS Modules (`assets/js/global.js`, `admin-charts.js`, `file-uploader.js`)
* **AI & Vision Engine:** Groq Cloud Vision & LLM API (`app/services/GroqService.php`)
* **PDF Exporter:** Native FPDF 1.86 Engine (`app/fpdf.php`)
* **Security Layer:** Anti-CSRF Token Validation, PDO Prepared Statements, Session Fixation Regeneration (`session_regenerate_id`), Secure HTTP-Only Cookie Flags, Server-side MIME & Extension Verification, IDOR Ownership Binding

---

## 📂 Project Directory Structure

```text
Quest_bank/
├── app/                        # Application Core Architecture
│   ├── bootstrap.php           #  - Central Autoloader & System Bootstrap
│   ├── config/config.php       #  - Global Configuration & Groq API Credentials
│   ├── database.php            #  - Central PDO Connection Singleton (getDBConnection())
│   ├── session.php             #  - Security Sessions & Role Middleware (requireRole())
│   ├── fpdf.php                #  - Native FPDF Engine (v1.86)
│   ├── repositories/           #  - Data Access Layer (RAW SQL PDO Queries)
│   │   ├── UserRepository.php
│   │   ├── DepartmentRepository.php
│   │   ├── ISORepository.php
│   │   └── ActivityLogRepository.php
│   └── services/               #  - Business Logic & Service Layer
│       ├── AuthService.php
│       ├── DepartmentService.php
│       ├── ISOService.php
│       ├── ExamService.php
│       ├── StudentService.php
│       └── GroqService.php
│
├── assets/                     # Frontend Static Assets
│   └── js/                     #  - Modular JavaScript Asset Files
│       ├── global.js           #    * Shared UI Modal Controls & Popover Handlers
│       ├── admin-charts.js     #    * Chart.js Data Visualizations
│       └── file-uploader.js    #    * Drag & Drop File Zone & Image Previews
│
├── database/                   # Database Backups & Initial SQL Schema Dump
│   └── bankquest_db.sql        #  - Main System MySQL Database Dump
│
├── pdf/                        # PDF Document Assets
│   └── QuestBank_50Percent_Completion_Report.pdf
│
├── includes/                   # Layout Navigation & Security Utilities
│   ├── security.php            #  - Anti-CSRF & Input Sanitization Helpers
│   ├── admin_sidebar.php       #  - Administrator Navigation Sidebar
│   ├── teacher_sidebar.php     #  - Faculty Navigation Sidebar
│   └── student_sidebar.php     #  - Student Navigation Sidebar
│
├── admin/                      # Administrator Console Pages
├── teacher/                    # Faculty & Exam Creator Modules
├── student/                    # Student Portal & Transcript Exporter
├── index.php                   # Authentication Gateway (Login & Register)
├── logout.php                  # Session Termination
└── README.md                   # System Documentation
```

---

## 🚀 Local Development Setup Guide

### Prerequisites
* **PHP 8.0+**
* **MySQL 5.7+** or **MariaDB**
* Local Web Server Environment (**XAMPP**, **MAMP**, or **Homebrew PHP/MySQL**)

### Installation Steps

1. **Clone / Open Repository:**
   ```bash
   cd /Users/loyd/Quest_bank
   ```

2. **Database Setup:**
   * Start your MySQL service.
   * Create a database named `bankquest_db`:
     ```sql
     CREATE DATABASE bankquest_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```
   * Import the system SQL schema file (`database/bankquest_db.sql`).

3. **Configure Environment Settings:**
   * Edit `app/config/config.php`:
     ```php
     define('DB_HOST', '127.0.0.1');
     define('DB_NAME', 'bankquest_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('GROQ_API_KEY', 'your_groq_api_key_here'); // Obtain free key from console.groq.com
     ```

4. **Launch Application:**
   * Run via PHP built-in web server:
     ```bash
     php -S localhost:8000
     ```
   * Open your browser and navigate to `http://localhost:8000`.

---

## 🔒 Security & Code Standards

* **CSRF Mitigation:** Anti-CSRF token verification (`validateCSRFToken()`) on all HTTP POST handlers.
* **SQLi Prevention:** 100% Parameterized prepared statements across all Repositories and Services.
* **XSS Safeguards:** UTF-8 context-aware HTML escaping (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
* **Session Hardening:** Automatic session ID regeneration on authentication (`session_regenerate_id(true)`), `HttpOnly` and `SameSite=Lax` cookie flags.
* **File Upload Defense:** Server-side `finfo_file` MIME type verification, extension whitelisting, and strict 10MB size limits.