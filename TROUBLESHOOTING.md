# 🔍 QuestBank — Troubleshooting & Diagnostic Guide

This document provides resolutions for common runtime issues, database errors, OCR challenges, and system diagnostics in **QuestBank**.

---

## 🚨 Common Runtime Issues & Solutions

### 1. Database Connection Error (`500` / Connection Refused)
- **Symptom:** "Database Connection Error: SQLSTATE[HY000] [2002] Connection refused"
- **Cause:** MySQL service is offline or credentials in `.env` are incorrect.
- **Resolution:**
  1. Verify MySQL is running: `sudo service mysql status` or `brew services list`.
  2. Check host, port, username, and password in `.env`.
  3. Run baseline migration: `php database/migrate.php`.

### 2. OCR Low Confidence (<75%) or Extraction Warning
- **Symptom:** Submissions flagged with "Requires Review: Low OCR Confidence".
- **Cause:** Low resolution scan, shadows, skewed page alignment, or unreadable handwriting.
- **Resolution:**
  1. Ensure answer sheets are scanned at 300 DPI in clear lighting.
  2. Install Tesseract CLI locally for higher accuracy: `sudo apt-get install tesseract-ocr`.
  3. Use the Teacher Review modal (`teacher/submissions.php`) to inspect the scan and apply manual score overrides if needed.

### 3. Groq AI Rate Limits / Key Offline
- **Symptom:** AI exam generation fallback warning or API timeout.
- **Cause:** Groq Cloud API key is missing, invalid, or rate-limited.
- **Resolution:**
  1. Verify your key in `admin/settings.php` or `.env` (`GROQ_API_KEY=gsk_...`).
  2. QuestBank automatically falls back to deterministic offline question generation engines if the API is offline.

### 4. "Session ended by administrator" Message
- **Symptom:** User is logged out and redirected to login with a session notice.
- **Cause:** An administrator terminated active sessions via `admin/sessions.php` or the session expired.
- **Resolution:** Simply log in again with your credentials.

### 5. File Upload Permission Denied
- **Symptom:** Upload fails with "Failed to write file to disk" or "Permission Denied".
- **Cause:** Webserver process (`www-data` / `nginx` / `nobody`) lacks write permissions to storage folders.
- **Resolution:**
  ```bash
  chmod -R 775 storage uploads teacher/uploads database/backups
  ```

### 6. Database Restore Rollback & Safety Recovery
- **Symptom:** Database restore interrupted due to invalid SQL or lock contention.
- **Cause:** Concurrent restore attempt or corrupted SQL dump file.
- **Resolution:**
  1. QuestBank preserves your database prior to restore in a Safety Backup (`database/backups/qb_safety_backup_*.sql`).
  2. Access `admin/backup.php` and restore from the generated safety backup file.

### 7. Camera Scanner Permission / Non-HTTPS Access Error
- **Symptom:** "Camera permission was denied" or "Camera access requires an HTTPS connection or localhost origin".
- **Cause:** Browser camera permission was denied, another app is using the camera, or the site is accessed over insecure HTTP on a remote IP address.
- **Resolution:**
  1. Access the portal using an HTTPS domain (`https://questbank.edu.ph`) or `http://localhost`.
  2. Grant camera access in browser site settings (click lock icon in address bar -> Permissions -> Camera -> Allow).
  3. If physical camera hardware is absent, use the built-in fallback button **Upload Image or PDF** (`.jpg`, `.png`, `.pdf`).
