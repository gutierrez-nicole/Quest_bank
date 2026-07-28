# 🎓 QuestBank: AI-Powered Automated Examination Management & Academic Performance Monitoring System

> **Target Institution:** Holy Cross College – Pampanga  
> **Target Department:** College of Engineering (Civil Engineering Program)  
> **Core Integration:** Groq Cloud AI API (`llama3-8b-8192` & `llama-3.2-11b-vision-preview`)  

---

## 📌 Project Overview

**QuestBank** is an intelligent, web-based examination management and optical evaluation portal tailored for academic institutions. The system streamlines test item generation from instructional materials, automates optical/handwritten student answer sheet grading, computes performance analytics, and predicts academically at-risk students using Artificial Intelligence.

---

## ✨ Key Features

### 👨‍🏫 Faculty / Teacher Portal
* 📄 **Instructional Material Processing:** Upload lesson materials in PDF, DOCX, PPTX, or TXT format.
* 🤖 **AI Question Generator:** Automatically create multiple-choice, true/false, fill-in-the-blank, identification, and **Civil Engineering problem-solving formula questions**.
* ✍️ **AI Optical Answer Sheet Checker (OCR):** Evaluate scanned or uploaded images/PDFs of student test papers against teacher answer keys with automated score calculation.
* 📊 **Performance Analytics & At-Risk Prediction:** Track student passing rates and identify struggling/at-risk students early per grading term (Prelim, Midterm, Finals).
* 💾 **System Backup & Restore:** Generate full SQL database snapshots and restore data directly from the portal.

### 🎓 Student Portal
* 📈 **Performance Dashboard:** View overall GPA, passing rates, exam completion history, and score analytics.
* 📄 **Official Examination Transcripts:** Export printable PDF transcripts of exam results and topic breakdowns.

### 🛠️ Administrator Console
* 👥 **User Account Management:** Add, edit, and oversee Admin, Teacher, and Student credentials.
* 📚 **Curriculum & Roster Management:** Manage subjects catalog, departmental details, and student section rosters.
* 📋 **Global Activity Audit Log:** Real-time audit trail of all exam submissions and system activities.

---

## 🛠️ Technology Stack

* **Backend Engine:** Native PHP (PDO Architecture with Singleton Connection Pattern)
* **Database:** MySQL (`bankquest_db`)
* **Frontend UI/UX:** HTML5, Tailwind CSS (via CDN), FontAwesome 6, Chart.js, Google Fonts (*Plus Jakarta Sans*)
* **AI & Vision Engine:** Groq Cloud API (`https://api.groq.com/openai/v1/chat/completions`)
* **Security Layer:** Anti-CSRF Token Validation (`hash_equals`), PDO Prepared Statements, Password Hashing (`PASSWORD_BCRYPT`)

---

## 📂 Project Architecture

```text
Quest_bank/
├── app/                        # Core Application Backend
│   ├── config/config.php       #  - Global Constants (DB credentials, Groq API settings)
│   ├── database.php            #  - Central PDO Singleton Connection Helper (getDBConnection())
│   ├── session.php             #  - Session & Role Authorization (requireRole())
│   └── services/
│       └── GroqService.php     #  - Encapsulated Groq Cloud AI API Service
│
├── includes/                   # Security & UI Component Partials
│   ├── security.php            #  - Anti-CSRF Token Generator & Input Sanitization
│   ├── admin_sidebar.php       #  - Admin Navigation Partial
│   └── teacher_sidebar.php     #  - Faculty Navigation Partial
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
* Local Server Environment (**XAMPP**, **MAMP**, or **Homebrew PHP/MySQL**)

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
   * Import the system SQL schema file (`bankquest_db.sql`).

3. **Configure Environment Settings:**
   * Edit `app/config/config.php`:
     ```php
     define('DB_HOST', '127.0.0.1');
     define('DB_NAME', 'bankquest_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('GROQ_API_KEY', 'your_groq_api_key_here'); // Obtain free key from console.groq.com
     ```

4. **Launch Local Web Server:**
   ```bash
   php -S localhost:8000
   ```

5. **Access Application:**
   Open your browser and navigate to: `http://localhost:8000`

---

## 🛡️ Security & ISO/IEC 25010 Compliance

This system adheres to the **ISO/IEC 25010 Software Quality Model** standards:
* **Functional Suitability:** Complete coverage of exam creation, automated grading, and academic analytics.
* **Security:** CSRF token verification on all POST forms and SQL injection prevention via PDO prepared statements.
* **Maintainability:** Modular architecture separating configuration, session handling, AI services, and UI templates.

---

&copy; QuestBank System. All rights reserved.