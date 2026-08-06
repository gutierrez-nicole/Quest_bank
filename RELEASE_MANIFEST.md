# 📜 QuestBank — Release Candidate 1 (RC1) Release Manifest

---

## 📌 Release Metadata

- **Official Release Version:** `v2.2-RC1` (`2.2.0-rc1`)
- **Baseline Git Tag:** `v2.2.0-rc1-full-dev`
- **Target Institution:** Holy Cross College – Pampanga (Department of Civil Engineering - BSCE)

---

## 📊 Codebase & Repository Inventory

- **Production Core Services (`app/services/`):** 26 service classes
- **Administrator Console Pages (`admin/`):** 22 pages
- **Faculty / Teacher Portal Pages (`teacher/`):** 12 pages
- **Student Portal Pages (`student/`):** 3 pages
- **Documentation Suite:** 9 markdown documents (`README.md`, `INSTALL.md`, `DEPLOYMENT.md`, `USER_GUIDE.md`, `ADMIN_GUIDE.md`, `TROUBLESHOOTING.md`, `CHANGELOG.md`, `DOCUMENTATION.md`, `RELEASE_MANIFEST.md`)

---

## 📦 Packaging Guidelines

Binary ZIP release packages (`QuestBank-v2.2-RC1-Developer.zip` and `QuestBank-v2.2-RC1-Production.zip`) are generated on demand and excluded from Git tracking.

### Developer Archive Generation:
```bash
zip -r release-output/QuestBank-v2.2-RC1-Developer.zip . -x "*.git*" -x "*node_modules*" -x "*vendor*" -x "*.DS_Store" -x "*.log"
```

### Production Archive Generation:
```bash
zip -r release-output/QuestBank-v2.2-RC1-Production.zip . -x "*.git*" -x "*node_modules*" -x "*vendor*" -x "*.DS_Store" -x "*.log" -x "tests/*" -x "playwright.config.js" -x "package.json" -x "package-lock.json"
```

---

## 🔒 Security & Password Protection Rules

1. **Migration Password Safety:** `database/migrate.php` preserves existing user passwords and profiles during migration runs.
2. **First-Time Login:** Initial demo accounts require a password change on first login (`force_password_reset = 1`). Default initial password is `Password123!`.

---

## ⚠️ Known System & OCR Limitations

1. **Low-Resolution Scans:** Answer sheet phone photos below 150 DPI or with high skew flag submissions with low OCR confidence (<75%), placing them in `pending_review` status for teacher verification.
2. **Groq API Rate Limits:** Free-tier Groq API keys may experience rate limits during concurrent bulk OCR requests. Local Tesseract CLI fallback is active.
