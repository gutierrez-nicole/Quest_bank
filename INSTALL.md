# 🛠️ QuestBank — Installation Guide

This document provides step-by-step installation instructions for deploying **QuestBank** on local development workstations and staging servers.

---

## 📋 System Prerequisites

### 1. Web & Database Stack
- **PHP:** 8.0 or higher
- **Database:** MySQL 5.7+ or MariaDB 10.4+
- **Web Server:** Apache (with `mod_rewrite`), Nginx, or PHP CLI Built-in Web Server.

### 2. Required PHP Extensions
- `pdo_mysql` — Database connectivity
- `fileinfo` — MIME type validation & magic byte analysis
- `gd` / `imagick` — Image processing
- `json` — JSON payload handling
- `mbstring` — Multibyte string processing
- `curl` — Groq Cloud AI API communication
- `zip` — DOCX & PPTX container inspection

### 3. Command-Line System Utilities
- **poppler-utils** (`pdftotext`, `pdfimages`):
  - *Debian/Ubuntu:* `sudo apt-get install poppler-utils`
  - *macOS:* `brew install poppler`
- **tesseract-ocr** (Offline OCR fallback):
  - *Debian/Ubuntu:* `sudo apt-get install tesseract-ocr`
  - *macOS:* `brew install tesseract`

---

## 🚀 Step-by-Step Installation

### Step 1: Clone or Extract Repository
```bash
git clone https://github.com/gutierrez-nicole/Quest_bank.git
cd Quest_bank
```

### Step 2: Configure Environment Variables
Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```
Edit `.env` with your database credentials and API key:
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bankquest_db
DB_USER=root
DB_PASS=your_database_password

GROQ_API_KEY=gsk_your_groq_api_key_here
APP_ENV=development
```

### Step 3: Database Setup
1. Create the MySQL database:
   ```sql
   CREATE DATABASE bankquest_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import the baseline schema and seed dataset:
   ```bash
   mysql -u root -p bankquest_db < database/bankquest_db.sql
   ```
3. Run automated schema migrations:
   ```bash
   php database/migrate.php
   ```

### Step 4: Storage & Directory Permissions
Ensure upload and storage directories are writable by the web server user:
```bash
mkdir -p storage/tmp uploads/submissions uploads/exports teacher/uploads database/backups
chmod -R 775 storage uploads teacher/uploads database/backups
```

### Step 5: Launch Development Server
```bash
php -S localhost:8000
```
Access QuestBank at: [http://localhost:8000](http://localhost:8000)

---

## 🔑 Default Accounts & Access

| Role | Username / Email | Default Password |
|---|---|---|
| **Administrator** | `russel@questbank.edu.ph` | `Password123!` |
| **Faculty / Teacher** | `smith@questbank.edu.ph` | `Password123!` |
| **Student** | `nikol@gmail.com` | `Password123!` |

> **Security Note:** Upon initial login, change default passwords immediately.

---

## 🧪 Verification & Health Check

Verify installation status by accessing the Administrator Health Console:
- Path: `admin/health.php`
- Run Verifier Suite: `php database/run_epic22_verification_suite.php`
