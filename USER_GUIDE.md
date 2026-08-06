# 📖 QuestBank — User Guide

Welcome to **QuestBank**, an intelligent examination management and optical evaluation portal designed for academic engineering institutions.

---

## 👩‍🎓 Student Portal (`student/`)

### 1. Dashboard Overview
Upon logging in, students are presented with their personal academic dashboard:
- **Performance Overview:** Total exams taken, average percentage, passing status summary.
- **Published Results:** List of finalized and published exam scores.
- **Topic Breakdown:** Performance metrics categorized by Civil Engineering topics.

### 2. Exporting Exam Transcripts
1. Navigate to **Published Results** on your dashboard.
2. Click **Export PDF** next to any published exam.
3. The system generates an official PDF transcript including itemized scores, subject details, and official institutional header.

---

## 👨‍🏫 Faculty / Teacher Portal (`teacher/`)

### 1. Lesson Material Management (`teacher/materials.php`)
- **Upload Materials:** Upload course materials in `.pdf`, `.docx`, `.pptx`, or `.txt` formats.
- **Text & Structure Extraction:** QuestBank automatically parses lesson contents, extracting text and mapping topics into the institutional lesson pool.

### 2. Question Bank Management (`teacher/question_bank.php`)
- **Browse & Filter:** Filter questions by Subject, Program, Year Level, Academic Period (Prelims, Midterms, Semi-Finals, Finals), and Bloom's Taxonomy Level.
- **Manual & AI Creation:** Add questions manually or generate question suites via the Groq AI Engine.
- **Supported Question Types:**
  1. Multiple Choice
  2. True or False
  3. Identification
  4. Civil Engineering Problem Solving

### 3. AI Exam Generator (`teacher/generate_exam.php`)
1. Select target **Subject**, **Program**, **Year Level**, and **Academic Period**.
2. Specify the question distribution (e.g. 10 Multiple Choice, 5 True/False, 5 Identification).
3. Select source lesson materials from single or multiple academic periods.
4. Click **Generate AI Exam**.
5. Review the draft exam, adjust item points if needed, and save to your active exam repository.

### 4. OCR Answer Sheet Evaluator & Camera Scanner (`teacher/upload_check.php`)
1. Select the target **Stored Exam** and **Enrolled Student**.
2. Choose your input method:
   - **Scan Using Camera:** Click **Scan Using Camera** to launch live device camera preview.
     - Position student answer sheet inside document framing box.
     - Ensure adequate lighting and avoid heavy shadows.
     - Click **Capture Answer Sheet** to take photo.
     - Review preview, image dimensions, and low-resolution warnings.
     - Adjust image if needed: **Rotate Left/Right**, **Crop Boundary**, or **Reset**.
     - Click **Use Photo & Attach** to confirm capture.
   - **Upload Image or PDF:** Click **Upload Image or PDF** to select a `.jpg`, `.png`, or `.pdf` file from your device storage.
3. Click **Process & Grade Server-Side**.
4. The system parses student answers via `OcrService`, evaluates answers against the answer key, computes awarded points, and saves the result as **Pending Review**.

### 5. Review & Score Overrides (`teacher/submissions.php`)
1. Open any submission in **Pending Review** status.
2. Inspect OCR answer extraction, item-level evaluations, and confidence warnings.
3. Use the **Override Score** feature to manually adjust points or correct handwriting evaluations (mandatory reason required).
4. Click **Publish Results** to release scores to student dashboards.
