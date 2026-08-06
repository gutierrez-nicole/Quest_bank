# ⚙️ QuestBank — Administrator Guide

This guide details system administration, user management, database operations, health monitoring, and security controls in **QuestBank Console** (`admin/`).

---

## 👥 1. User & Roster Management (`admin/academic.php`)

### Account Management
- **Create Accounts:** Add Administrator, Teacher, or Student accounts with assigned roles.
- **Mandatory Password Reset:** Toggle `Force Password Reset` on any account. Upon their next login, the user will be forced to set a new password before accessing their dashboard.
- **Session Control:** View active login sessions or force immediate session logout (`admin/sessions.php`).

### Roster & Roster Import (`admin/import.php`)
- Batch import student rosters using structured CSV templates.
- Automatic validation checks for duplicate usernames, emails, and student numbers.

---

## 🏛️ 2. Academic Structure Administration

- **School Years & Semesters:** Create and activate academic school years and semesters.
- **Departments & Subjects:** Manage institutional course offerings.
- **Section Schedules (`admin/schedules.php`):** Assign sections, teachers, room assignments, and exam schedules.

---

## 💾 3. Database Backup & Restore (`admin/backup.php`)

### Creating Backups
- Click **Create New Backup** to generate a timestamped SQL dump containing schema, data, and system settings.
- View computed **SHA-256 checksums**, table counts, and download links.

### Restoring Backups
1. Click **Restore** next to any valid QuestBank SQL backup.
2. Review the SHA-256 checksum and pre-restore warning.
3. Type the explicit confirmation phrase **`RESTORE`**.
4. QuestBank automatically generates a fresh **Safety Backup** (`qb_safety_backup_*.sql`) of the current database before applying the restore.

---

## 🩺 4. Health & Storage Console (`admin/health.php` & `admin/files.php`)

### System Diagnostics & Deployment Checklist
- Inspect connection status, storage permissions, PHP extensions, OCR CLI engines, Groq AI API key, and active academic setup.
- The **Deployment Readiness Checklist** computes overall `PASS`, `WARNING`, or `FAIL` status.

### Storage Management & Orphaned Cleanup (`admin/files.php`)
- Monitor storage allocation across Lesson Materials, Scanned Submissions (including camera captures), Backups, and Temporary files.
- Run **Orphaned File Cleanup** to scan disk files against active database records. Boundary protections restrict deletion to approved upload directories (`uploads/ocr_sheets/`, `teacher/uploads/`).

---

## 🔐 5. System Settings & Maintenance Mode (`admin/settings.php`)

- **Maintenance Mode:** Toggle system maintenance `ON` or `OFF`.
  - *Maintenance ON:* Blocks Student and Teacher logins with a clear maintenance notice while preserving Administrator access.
- **Groq AI Key Configuration:** Manage the active Groq Cloud API key securely.
