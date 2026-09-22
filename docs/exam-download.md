# Exam PDF download

Added on 2026-09-22 at the user's request. Changes are local; no deployment is included.

- Saved exam cards and the saved-exam preview offer Download PDF.
- The printable exam page offers Download PDF alongside Print Now.
- The default download is a student paper, excluding answer keys, explanations and reference solution formulas.
- Teacher Answer Key Mode explicitly changes the download to a clearly labeled teacher answer-key PDF.
- The existing teacher ownership check protects both download modes.
- Uses the existing FPDF engine. No new dependencies or database changes.

Validation: all 16 dedicated download checks passed in the isolated audit database, including real HTTP PDF attachments and rejection of students and other teachers. Native browser verification confirmed the button, mode-dependent URL and successful download request. Both pages of each generated student and teacher sample were visually inspected. Modified PHP files passed syntax checks; git diff whitespace checks passed.

The PDF uses a simple printable layout. Common engineering symbols are converted to readable text notation (for example, m² to m^2 and σ to sigma); it is not a rich LaTeX or diagram renderer.

Automated evidence: `verification/exam-download.txt`. Regression test: `tests/verify_exam_download.php` (requires the existing isolated browser fixture).
