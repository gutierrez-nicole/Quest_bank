# QUESTBANK — CAPSTONE SYSTEM ADMINISTRATION & OPERATIONS MANUAL

## 1. System Overview
QuestBank is an AI-powered Exam Generation, OCR Answer Sheet Grading, Academic Administration, and Security Management system built with PHP 8.x and MySQL/MariaDB.

---

## 2. Installation & Setup
1. **Requirements:** PHP 8.1+, MySQL 8.0+ / MariaDB 10.4+, Web Server (Apache/Nginx or Built-in PHP Server).
2. **Database Migration:**
   ```bash
   php database/migrate.php
   ```
3. **Verification Suite Execution:**
   ```bash
   php database/run_epic22_verification_suite.php
   ```

---

## 3. Operations & Maintenance

### 3.1 Database Backup & Restore
- **Backup Location:** `database/backups/` (protected via `.htaccess`).
- **Create Backup:** Navigate to `Admin -> Database Backup & Restore` and click **Create New Backup**.
- **Restore Backup:** Select any available backup file, click **Restore**, and confirm execution in the modal.

### 3.2 System Health & Diagnostics
- Navigate to `Admin -> System Health & Operations` (`admin/health.php`).
- Monitor DB connection, storage permissions, disk usage, Groq AI API key, and OCR engine status.
- Review the **Final Deployment Readiness Checklist** prior to production deployment.

### 3.3 Storage Management & Cleanup
- Use `Admin -> File Manager` (`admin/files.php`) or `Admin -> Health` to clean temporary preview files (`qb_batch_*.csv`) and unreferenced orphaned files safely.

### 3.4 Maintenance Mode
- Toggle maintenance mode under `Admin -> Settings` (`admin/settings.php`).
- When ON, students and teachers are prevented from logging in or registering, while administrators maintain active access.

---

## 4. Troubleshooting Common Issues
- **OCR Fallback Notice:** If Tesseract OCR CLI is not installed, the system automatically uses PHP GD image processing fallback scoring.
- **AI Question Generation Notice:** If the `GROQ_API_KEY` is missing or invalid, the system automatically engages deterministic offline mock AI question generators.
