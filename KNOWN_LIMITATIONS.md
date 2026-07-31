# QUESTBANK KNOWN LIMITATIONS & ARCHITECTURAL DISCLOSURE

## 1. Optical Character Recognition (OCR) Scope & Behavior

### Printed Text vs. Handwriting
- **Standard Printed Text & PDFs**: Clear printed fonts and digital PDFs achieve high recognition rates (**92.0% – 94.5%** accuracy) with high confidence (**88% – 90%**).
- **Handwritten Fonts & Low-Resolution Scans**: Non-standard cursive handwriting or low-resolution scans (<150 DPI) result in lower OCR confidence (<75%).

### Defensive Manual Review Workflow
- Rather than outputting incorrect automated scores, the system is designed with a safety mechanism: when OCR confidence falls below 75% or image quality is poor, the system flags the submission with `suggested_manual_review = true`.
- Submissions with this flag are placed in `review_status = 'pending_review'` for teacher verification and manual score/remark override in `teacher/reports.php`.

## 2. External Service Dependencies & Rate Limits

- **Groq Cloud AI API**: AI question generation and comparative answer evaluation rely on Groq API quota limits (`llama-3.3-70b-versatile`). Exponential backoff and retry handling are implemented in `GroqService.php`.
- **System Extraction CLI**: For maximum PDF extraction accuracy, `pdftotext` and `tesseract` CLI utilities are recommended on host server. When unavailable, native PHP stream decoders and GD image analyzers act as fallbacks.
