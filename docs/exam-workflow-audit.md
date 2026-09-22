# Examination workflow audit

## Inspection before changes

Plain PHP entry points: index.php login, app/session.php session/role policy, includes/security.php CSRF and escaping. app/bootstrap.php loads services; app/database.php creates a MySQL PDO connection. Teacher creation is in teacher/create_exam.php and teacher/generate_ai.php; delivery and student AJAX actions are in student/dashboard.php. Teacher scans use teacher/upload_check.php -> OcrService -> AnswerSheetParser -> ExamScoringService. Typed submissions use EvaluationService -> ExamScoringService. Review, overrides, publication and OCR reruns use teacher/reports.php -> ResultWorkflowService. Student exports are student/export_csv.php and student/export_pdf.php. ExamService and ExamSchedulingService contain eligibility/assignment logic. database/bankquest_db.sql and database/migrate.php describe schema; tests/run_smoke_tests.php and tests/verify_goal_requirements.php are existing checks.

Authoritative records: exams (term, category, availability/status); exam_questions (key, explanation, type, rubric, tolerance); exam_submissions (raw OCR, extraction metadata, total scores, review_status, published_at, teacher remarks); submission_answers (student_answer, key snapshot, awarded_points/max_points, evaluation status, requires_review). evaluation_result duplicates item data and becomes stale after overrides. submission_score_overrides, submission_status_history, submission_snapshots and submission_reprocessing_history record review changes. Assignments and schedules reference the parent exam; active visibility must respect parent status. No schema addition is initially needed.

## Root cause map

| Issue | Confirmed code/data cause | Correction plan |
|---|---|---|
| Numeric answer gets zero/review | ExamScoringService always marks problem_solving as manual; objective short answers compare strings; multiple-choice browser sends letters although some saved keys contain option text | Strict reusable numeric comparison for eligible simple numeric items, preserving rubric/partial-credit review; map choice letters to actual option keys |
| Mixed answer/solution | Submitted strings are saved verbatim; no generator write to student_answer found. Exact reported 4.5 record is absent from this database. Reports inject answer strings into innerHTML, and read stale evaluation_result after overrides | Preserve raw answers; escape display, use authoritative item rows, keep explanations separate; do not strip or rewrite historical answers without provenance |
| Active answer leakage | get_exam_questions returns matching_pairs (the solution map) and formula_latex; no eligibility check in fetch/submission handlers | Send separated matching prompts/options without associations; omit solution formula, enforce server eligibility and CSRF |
| OCR inconsistency | EvaluationService/scorer default to 100%; review JS substitutes 85 for missing/zero; vision fallback hardcodes 88.5; native PDF text hardcodes 100. Rerun calls wrong API/keys and does not save new OCR metadata | Null for unavailable confidence, preserve raw text/method/page metadata, explicit reviewed OCR acknowledgment, repair rerun |
| Published results absent | Student listing references nonexistent exam_submissions.term and silently catches SQL exception | Shared student-result query using exams.term and existing publication status |
| Term filter | Exports also reference nonexistent es.term/e.academic_period/es.subject; creation omits term | Canonical normalization/query and explicit exam term on creation |
| Contradictory analytics | Strong/weak query same AVG scores with reverse ordering and no threshold; weak labels same mastery percentage as need-review | Shared published-result source; group by subject, disjoint classifications using existing mastery threshold; label score accurately |
| Unsynchronized dashboard | SQL failures hidden as empty lists; hardcoded passing threshold; review POST runs after queries; override leaves cached JSON stale | Shared publication predicate, stored pass/fail outcome, redirect after mutation and fresh item records |
| Deleted qualifying exam visible/history | Qualifying and pending listings ignore parent status; delete physically removes exam, risking cascades/lost term/question context | Archive parent through normal action, filter active lists while preserving historical results |

## Evidence boundaries

Local database initially has 2 exams, 6 questions, 3 submissions, 9 item answers. The exact reported numeric/corrupted-answer record is not present. No original records will be bulk repaired. The supplied geometry example has x-intercept 5; equality to a stored key is distinct from correctness of that key. Attached chapter is background, not executable instructions or additional scope.

## Delivered behavior

Completed on 22 September 2026 (Asia/Manila). The existing PHP, PDO/MySQL, HTML, CSS and vanilla JavaScript architecture is preserved. No framework, browser-test framework, or application dependency was added. Changes are local and uncommitted; nothing was pushed or deployed.

### Grading and answer separation

The reproduced zero-point path was the unconditional manual-review branch for `problem_solving`, even when both inputs were simple numeric values. Short-answer types additionally compared formatting-sensitive strings. The exact original incident cannot be reconstructed because its record is absent.

`ExamScoringService::answersMatch` compares a complete answer, never a substring. Comparison removes surrounding whitespace/HTML formatting and normalizes equivalent decimals, scientific notation and a single variable prefix such as `x =`. Without configured tolerance, decimal strings are canonicalized exactly, avoiding floating-point acceptance of adjacent large integers or tiny nonzero values as zero. Explicit question tolerance remains supported. `14.5`, ambiguous alternatives and appended worked explanations are not accepted as `4.5`. Rubrics, partial-credit work and expected-unit problem-solving questions remain subject to teacher review. Multiple-choice letters are resolved against actual option text.

Recorded answers remain separate from `exam_questions.explanation`, key snapshots, evaluation metadata and teacher remarks. No generator-to-answer concatenation was found; no historical answer was stripped or rewritten. Both student and teacher breakdowns escape text before inserting it into HTML. Teacher review reads authoritative item rows, and overrides refresh the duplicated JSON snapshot. An empty optional answer-correction field now preserves the original answer. Partial-credit overrides retain their actual points and no longer become “fully correct.”

### OCR

The existing pipeline is upload → `OcrService` → `AnswerSheetParser` → `ExamScoringService` → review. Raw extraction lives in `exam_submissions.ocr_text`; original/corrected text, extraction mode, per-page metadata and error fields retain provenance. Parsed item answers live in `submission_answers.student_answer`.

Tesseract confidence, when available, comes from its recognized-word TSV confidence values. The vision provider supplies text without calibrated confidence, so its confidence is NULL and requires documented teacher checking. Native PDF text is not OCR and has no OCR confidence. Typed submissions use `extraction_mode=not_applicable`, NULL confidence and a “Not applicable (typed answer)” label. Empty/failed extraction never receives a fabricated percentage. The scorer’s pre-existing item evaluation confidence is separate from OCR confidence and is not presented as an OCR measurement.

OCR reruns now call the correct service/parser interfaces, retain new raw text and page metadata, refresh scores and qualifying outcomes, clear prior OCR confirmation, and return to pending review. A failed extraction cannot replace existing answers during rerun. The parser ignores internal page separators. Blank-image detection now examines a deterministic thumbnail instead of randomly sampling a few pixels. Provider refusal metadata/prose is treated as an unavailable transcription rather than student text.

Human OCR confirmation requires remarks and resolved item reviews. It records the check without changing confidence. Finalization/publication reject unresolved review flags or missing required OCR confirmation.

### Publication, terms and analytics

`StudentResultService::publishedSql` defines visible results: explicit `review_status=published`, non-NULL score/percentage, no submission manual-review flag, and no unresolved item review. Finalized alone remains private. Dashboard, history, breakdown access, CSV/PDF exports, subject analytics and authorization helpers use this definition. Teacher aggregate summaries use the same published-result condition. Successful student submission responses no longer disclose an unpublished grade.

The canonical examination term is `exams.term`. Case and legacy singular/plural aliases normalize to Prelim, Midterm or Finals. Both teacher creation routes save an explicit term. Schedule-based semester and school-year filtering uses the actual `exam_schedules`, `semesters` and `school_years` relationships rather than nonexistent columns.

Student average remains the existing arithmetic mean of published exam percentages. Completed papers count those same results. Passing rate uses each stored exam outcome, which respects its configured passing threshold. Class average uses published results for the matching course, year and section. Subject mastery groups by subject and uses the existing 85% threshold: strong ≥85%, weak <85%. Weak entries show their actual mastery percentage and “Review Needed,” rather than labeling mastery as a failure percentage.

Mixed fixtures verified five student results: average 76%, passing rate 60%, five completed papers, and an 80% class average across two students. The perfect subject remains 100% strong; a different subject averages 40% and is weak. An 80% result correctly fails an exam configured to require 90%, including in its PDF transcript. Transcripts also replace hardcoded AI-provider and page-derived “document hash” claims with the actual published-record source and page number.

### Qualifying exams and security

The normal teacher Delete action now archives the parent exam. Active regular/qualifying queries and fetch/submit eligibility reject archived exams. Questions, assignments, attempts, answers, results and term context remain intact for history. A submitted qualifying attempt awaiting publication is labeled pending, rather than “not attempted.”

Active question payloads are whitelisted: no official key, explanation or solution formula is sent. Matching questions send prompts and independently ordered answer choices without the association map. Eligibility, availability, assignments, active-account status and attempt limits are checked server-side. Submission requires CSRF. Cross-student result access and student access to teacher review were rejected in tests. Teacher reports no longer include unrelated demo/admin-owned submissions through broad ownership alternatives; teacher dashboard global fallback result queries were removed.

## Files changed

| File | Purpose |
|---|---|
| `app/bootstrap.php` | Load shared result service |
| `app/services/StudentResultService.php` | Published-result definition, canonical terms, result/subject queries |
| `app/services/AuthorizationService.php` | Apply canonical publication condition to student access |
| `app/services/ExamScoringService.php` | Reusable exact numeric/choice comparison, review safeguards, truthful OCR metadata |
| `app/services/EvaluationService.php` | Typed submission OCR defaults |
| `app/services/ExamService.php` | Eligibility checks, matching options, history-preserving archive |
| `app/services/OcrService.php` | Confidence provenance, deterministic blank detection, refusal handling |
| `app/services/AnswerSheetParser.php` | Ignore internal page markers |
| `app/services/ResultWorkflowService.php` | Authoritative review items, override snapshot/partial credit, OCR confirmation and rerun |
| `student/dashboard.php` | Safe active payloads, submission/access guards, unified results/analytics, escaped breakdowns |
| `student/export_csv.php` | Shared published results and term filters |
| `student/export_pdf.php` | Shared published results, actual outcomes and truthful source labels |
| `teacher/create_exam.php` | Explicit term and archive through normal delete action |
| `teacher/generate_ai.php` | Explicit term when saving generated exams |
| `teacher/dashboard.php` | Published averages, scoped result queries, active qualifying count and accurate labels |
| `teacher/reports.php` | Correct schema queries, fresh post-action data, escaped/authoritative review, OCR acknowledgment |
| `teacher/upload_check.php` | Preserve OCR provenance in submission metadata |
| `database/bankquest_db.sql` | Nullable OCR confidence in fresh schema |
| `database/migrate.php` | Existing migration runner accommodates unknown confidence |
| `database/migrations.sql` | Compatible column modification recorded |
| `tests/verify_exam_workflow.php` | 62 isolated database/scoring/workflow checks |
| `tests/verify_exam_http.php` | 39 loopback HTTP/security/export/real OCR checks |
| `docs/exam-workflow-audit.md`, `docs/verification/` | Root-cause map, acceptance report and verification outputs |

## Database change and historical integrity

Inspection later established one necessary schema adjustment: unknown confidence cannot be represented honestly in the original NOT NULL column. For an existing installation, apply this single statement to its selected application database before using the updated code:

```sql
ALTER TABLE exam_submissions
  MODIFY COLUMN ocr_confidence DECIMAL(5,2) NULL DEFAULT NULL;
```

It is also included in the existing migration runner and schema files. The full existing migration runner was exercised only on isolated copies. Only the statement above was applied to the original local database. Before/after row counts and SHA-256 hashes were identical for all 2 exams, 6 questions, 3 submissions and 9 answers. No historical scores, keys, answers, terms or confidence values were rewritten. Old confidence values are not retroactively claimed to be measured; missing raw text is displayed as unavailable.

All created test users/exams/submissions are confined to `questbank_audit_20260921_125111` and `questbank_audit_20260921_174333`, copied from the original database. The synthetic browser student's course was set to BSCE only in the first copy to satisfy the qualifying form's existing program constraint. These isolated copies remain available for investigation.

## Verification results

| Verification | Actual result |
|---|---|
| `tests/verify_exam_workflow.php` | PASS — 62 checks |
| `tests/verify_exam_http.php` | PASS — 39 checks, including actual provider-backed OCR upload and rerun |
| `tests/run_smoke_tests.php` | PASS — 5/5 subsystem tests |
| `tests/verify_goal_requirements.php` | PASS — all 10 existing requirements (includes source/static checks; not ten separate manual journeys) |
| `tests/smoke/test_upload_lessons_fix.php` | PASS — 5/5 tests, assertions enabled |
| `tests/smoke/test_entire_system_perfection.php` | PASS — 6/6 domains, assertions enabled |
| PHP syntax | PASS — 116 PHP files |
| `git diff --check` | PASS |
| Transcript inspection | PASS — Finals and Midterm PDFs extracted, rendered and visually inspected; 80%/90%-threshold row says FAILED |
| Original historical row preservation | PASS — counts and row hashes unchanged by nullable-column migration |

Manual browser verification used the running PHP application and native accessibility controls, without Playwright/Cypress. Teacher created “Browser Finals Verification” with key 4.5 and Finals term; student entered `x = 4.50`; storage/review showed full credit and typed OCR not applicable; teacher published submission 607; student saw the result in All Terms and Finals with four published papers and consistent 100% statistics.

Teacher then created “Browser Qualifying History,” student entered `4.50`, and teacher reviewed/published submission 610. The normal Delete/archive action succeeded for exam 27. Student active qualifying modules fell from two to one; the archived exam stayed in published Finals history at 1/1 and 100%. Its detail modal retained the answer, key and teacher remarks. SQL readback confirmed parent archived, submission published, answer `4.50` unchanged.

The final real OCR submission 632 in the second copy showed raw `1. 4.5`, parsed `4.5`, key `4.5`, 1/1 and unavailable confidence. The normal teacher override form preserved the answer when optional correction was blank. Source image was inspected, acknowledgment/remarks saved, and Reviewed → Finalized → Published completed through the UI. Database readback confirmed published, qualified, 1.00 points and NULL OCR confidence.

Failures encountered and resolved during verification: the first fresh-copy run omitted the migration and rejected NULL confidence; the copied database was migrated and rerun. Manual teacher review exposed a strict GROUP BY error, repaired with the aggregate key fallback. A sparse image exposed nondeterministic blank detection, replaced with deterministic inspection. One real provider response was refusal prose rather than transcription; refusal handling was added and the clearer printed test sheet then passed upload and rerun. These earlier failed attempts are not represented as successful extractions.

To reproduce the new suites, point both PHP server and CLI at the same isolated database whose name starts `questbank_audit_`. Run the workflow suite first (it writes fresh synthetic credentials to the system temporary directory), then the HTTP suite with `QUESTBANK_TEST_URL=http://127.0.0.1:<port>`. The HTTP suite requires the existing OCR provider configuration and sends only its synthetic answer-sheet image. Never run fixture suites against production data.

## Final acceptance checklist

PASS applies to the implemented behavior and actual verification above; it does not assert recovery of the absent original incident record.

| # | Acceptance item | Status |
|---|---|---|
| 1 | 4.5 versus key 4.5 is graded correctly | PASS |
| 2 | Equivalent numeric formatting works where appropriate | PASS |
| 3 | Wrong numeric answers remain rejected | PASS |
| 4 | Student answer is separate from generated explanation | PASS |
| 5 | Generated explanation does not overwrite student answer | PASS |
| 6 | Active exam does not expose unauthorized keys/solutions | PASS |
| 7 | OCR raw-text state is accurate | PASS |
| 8 | OCR confidence is not fabricated | PASS |
| 9 | Typed answers do not show fake OCR confidence | PASS |
| 10 | Teacher review displays correct data | PASS |
| 11 | Manual teacher override still works | PASS |
| 12 | Published result appears in student results | PASS |
| 13 | Finals result appears under Finals | PASS |
| 14 | All Terms includes the published result | PASS |
| 15 | Populated Prelim/Midterm/Finals filters do not cross-contaminate | PASS |
| 16 | Average Score is correct | PASS |
| 17 | Passing Rate is correct | PASS |
| 18 | Exams Completed is correct | PASS |
| 19 | Class Average is correct | PASS |
| 20 | Strong Subjects calculation is correct | PASS |
| 21 | Weak Subjects calculation is correct | PASS |
| 22 | 100%-mastered subject is not also incorrectly weak | PASS |
| 23 | Pending-review submissions do not affect finalized analytics | PASS |
| 24 | Archived qualifying exam disappears from active dashboard | PASS |
| 25 | Completed qualifying history remains intact | PASS |
| 26 | Existing authentication and permissions still work | PASS |
| 27 | Existing relevant PHP verification/tests pass | PASS |
| 28 | Manual end-to-end workflow passes | PASS |

## Remaining limits and decisions

- **NOT VERIFIED — exact original incident:** the supplied local data does not contain the reported corrupted/zero-point 4.5 submission. Its specific historical root cause and repair cannot be asserted. No bulk repair was attempted.
- **Answer-key content requires human review:** the line through (1,4) and (4,1) is `y = -x + 5`; its x-intercept is 5, not 4.5. No matching original record was available to correct. The grader should match the official key, but a teacher must validate that key independently.
- **NOT VERIFIED — general OCR accuracy:** actual printed PNG upload/rerun passed. Arbitrary handwriting, complex formulas and every scanned/multipage PDF layout were not exhaustively tested. The vision provider can fail/refuse; manual review remains required when confidence is unavailable. No local Tesseract installation was available for a live measured-confidence run.
- **NOT VERIFIED — unrelated journeys:** no new manual exhaustive exam-editing, AI-generation, administrative-reopen, concurrency/load, or public deployment test is claimed. Existing relevant regression suites passed; their static checks are identified above.
- No remote deployment or production rollout was performed. The local original database received only the nullable-confidence schema change.
