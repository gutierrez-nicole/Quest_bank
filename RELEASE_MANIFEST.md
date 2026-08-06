# 📜 QuestBank — Release Candidate 1 (RC1) Release Manifest

---

## 📌 Release Metadata

- **Official Release Version:** `v2.2-RC1` (`2.2.0-rc1`)
- **Build Date & Timestamp:** 2026-08-06 15:52:30 UTC+8
- **Target Institution:** Holy Cross College – Pampanga (Department of Civil Engineering - BSCE)

---

## 📦 Release Packages & Checksums

### 1. Developer Package
- **Filename:** `QuestBank-v2.2-RC1-Developer.zip`
- **SHA-256 Checksum:** `4fdad47ab617403260b991653c4bcde216b5a8ec89a7364dd465908aa6cce941`
- **Contents:** Full production PHP source, database migrations, clean seed dataset, 22/22 verifier scripts, test runner framework, Playwright E2E suite, fixtures, developer documentation.

### 2. Client / Production Package
- **Filename:** `QuestBank-v2.2-RC1-Production.zip`
- **SHA-256 Checksum:** `d302bfe8b4619ef892f0bbccd2a69644cd94921ebc5dbd9ced68f91a91cd21df`
- **Contents:** Production PHP source, web assets, canonical clean database dump (`database/bankquest_db.sql`), migrations, deployment guides, user/admin manuals.
- **Excluded Items:**
  - `tests/` directory
  - Playwright E2E specs (`tests/epic22_e2e_complete.spec.js`) and config (`playwright.config.js`)
  - `package.json` & `package-lock.json`
  - `database/verify_*.php` scripts
  - `database/run_epic22_verification_suite.php` & `database/epic22_verifiers.json`
  - `database/verification_archive/`
  - Test runner helpers & E2E seeders
  - `TESTING.md`
  - `app/testing_bootstrap.php`

---

## 📊 Codebase & Repository Inventory

- **Total Production PHP Files:** 132 files
- **Production Services (`app/services/`):** 26 service classes
- **Administrator Console Pages (`admin/`):** 22 pages
- **Faculty / Teacher Portal Pages (`teacher/`):** 12 pages
- **Student Portal Pages (`student/`):** 3 pages
- **Documentation Suite:** 9 markdown documents (`README.md`, `INSTALL.md`, `DEPLOYMENT.md`, `USER_GUIDE.md`, `ADMIN_GUIDE.md`, `TROUBLESHOOTING.md`, `CHANGELOG.md`, `DOCUMENTATION.md`, `RELEASE_MANIFEST.md`)
- **Authoritative Test Verifiers:** 24 verifiers (22 authoritative + 2 helper suites)

---

## 🔒 Security & Password Protection Rules

1. **Migration Password Safety:** `database/migrate.php` uses `ON DUPLICATE KEY UPDATE id = id` for demo user seeding. Existing user passwords, profile details, and `force_password_reset` flags are never overwritten during migration runs.
2. **First-Time Login:** Initial demo accounts require a password change on first login (`force_password_reset = 1`). Default initial password is `Password123!`.

---

## ⚠️ Known System & OCR Limitations

1. **Low-Resolution Scans:** Answer sheet phone photos below 150 DPI or with high skew flag submissions with low OCR confidence (<75%), placing them in `pending_review` status for teacher verification.
2. **Groq API Rate Limits:** Free-tier Groq API keys may experience rate limits during concurrent bulk OCR requests. Local Tesseract CLI fallback is active.
