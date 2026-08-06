# 📜 QuestBank — Release Changelog

All notable changes and architectural milestones for **QuestBank** are documented in this file.

---

## [v2.2-RC1] — 2026-08-06 (Release Candidate 1)

### Fixed & Hardened
- **Storage Boundaries:** Restricted temporary file cleanup to dedicated `storage/tmp/` root with safe prefix checking for legacy system temp files.
- **Backup Security:** Implemented reusable `BackupService::isValidBackupFilename()` validator rejecting dotfiles (`.htaccess`), non-SQL extensions, and path traversal attempts across download, restore, delete, and list operations.
- **Health Diagnostics:** Implemented truthful OCR capability detection (Tesseract CLI vs. GD/Imagick fallback with directory write validation) and accurate Groq AI key/testing mode diagnostics.
- **Error Page Standard:** Integrated `renderErrorPage(403/404)` across export handlers, authorization checks, and missing resource endpoints.
- **Database Cleanup & Seed:** Prepared clean baseline demo dataset with preserved demo accounts (Admin, Teacher, Student) and exported `database/bankquest_db.sql`.
- **Authoritative QA Certification:** 22/22 verifier scripts passing 100% cleanly in `php database/run_epic22_verification_suite.php`.

---

## [v2.2-P5] — 2026-08-06 (Operational Readiness & Security)

### Added
- **Session Management:** Admin session console (`admin/sessions.php`), active session tracking, and forced logout enforcement.
- **Mandatory Password Reset:** `force_password_reset = 1` policy enforcement and dedicated `force_password_reset.php` workflow.
- **Database Backup & Restore Safety:** Automated pre-restore safety backups (`qb_safety_backup_*.sql`), restore lock file, SHA-256 checksums, and mandatory `RESTORE` confirmation phrase.
- **Audit Actor Integrity:** Ensured missing or invalid actor IDs store `NULL` without false user attribution.
- **Deployment Checklist:** 11 explicit system health checks with overall `PASS`/`WARNING`/`FAIL` evaluation.

---

## [v2.2-P4] — 2026-08-06 (Academic Administration)

### Added
- **Academic Structure:** School Years, Semesters, Departments, Subjects, Sections, and Teacher Assignments.
- **Exam Scheduling:** Section exam schedule calendar (`admin/schedules.php`).
- **CSRF Protection:** Standardized CSRF token validation (`validateCSRFToken()`) across all administrative POST mutations.

---

## [v2.2-P3] — 2026-08-06 (OCR Answer Checker & Workflow)

### Added
- **Optical Answer Sheet Checker:** Groq Vision OCR answer sheet processing and automated grading.
- **Review & Publication Workflow:** Submission state transitions (`draft` $\rightarrow$ `pending_review` $\rightarrow$ `reviewed` $\rightarrow$ `published`).
- **Score Overrides:** Item-level score override modal with mandatory audit reason logging.
- **Student Performance & PDF Exports:** Dashboard topic breakdown, radar analytics, and FPDF transcript exports (`student/export_pdf.php`).

---

## [v2.2-P2] — 2026-08-06 (Cross-Period Question Pool & AI Generator)

### Added
- **Cross-Period Pool:** Multi-period lesson material coverage and question generation.
- **Question Bank:** Support for 4 canonical question types (Multiple Choice, True/False, Identification, Problem Solving).
- **AI Engine:** Groq Cloud AI Integration (`llama-3.3-70b-versatile`) with offline fallback generators.

---

## [v2.1.0] — 2026-08-04 (Initial Capstone Baseline)

- Baseline user authentication, database schema, and initial UI layout.
