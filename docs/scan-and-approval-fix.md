# Scan results and student approval — 2026-09-23

## Changes

The OCR submit handler disabled the submit button before native submission, although the server action depended on that button's name. The native DataTransfer path could therefore submit files without triggering processing. The form now has a stable hidden action field and one multipart fetch path. Only the feedback and result panel are updated, keeping the page's JavaScript and selections alive. A second submit is ignored while processing.

The existing result summary now sits beside the scanner on wide screens and below it on narrower screens. The panel identifies the exam, student, saved submission ID, initial score, item evaluations and Pending Review state. OCR requiring verification is explicitly flagged. Reports and publication rules remain in place.

Oversized POST bodies now return a visible 413 explanation. Individual upload errors reject the whole batch instead of silently omitting pages. Processing/save exceptions are logged and produce a safe user-facing error. Selected pages survive recoverable AJAX errors; success clears them. Interrupted connections advise checking Reports before retrying.

New public registrations select a teacher and create a pending account plus roster request. New teacher-created accounts also require acceptance. Pending/inactive accounts cannot log in or continue through an existing authenticated session. Accepting an owned pending request adds the student to the roster and activates a pending account in one transaction. Requests without a section require the teacher to choose an owned section. Another teacher cannot accept the request. Existing active accounts are not retroactively made pending.

## Verification

- 62 existing workflow checks passed in an isolated audit database.
- 39 existing HTTP checks passed, including real image OCR and stored scoring.
- 22 focused scan/approval HTTP checks passed, including three 2.55 MB pages, saved Reports entry, pending-result privacy, 9 MB request rejection, partial-batch rejection, registration/login/acceptance and cross-teacher rejection.
- Four client submit-handler scenarios passed: success, upload-limit error, network failure and expired session. These execute the real handler with mocked browser interfaces; they are unit tests, not browser end-to-end evidence.
- Changed PHP files passed syntax checks; git diff whitespace checks passed.
- Native browser inspected the desktop scanner/placeholder panel and exam/student selection. Physical phone capture and the customer's server were not verified. The browser viewport override did not visibly change the screenshot dimensions, so mobile rendering is not marked verified.

The customer's exact server-side cause remains unconfirmed without its logs. These changes fix the identified code defect and reproduced upload-limit failure paths; they do not establish which one occurred on the customer's installation.

## Use and installation

Update the application files on the machine serving QuestBank. This change needs no new package or schema migration on the current schema. Ensure the prior OCR-confidence migration is already applied. Configure PHP upload_max_filesize and post_max_size for the intended page sizes; the request limit must exceed the total batch size plus multipart overhead. The verification server used 3M per file and 8M per request, not a production configuration recommendation.

Teacher: select exam/student, capture or upload pages, click Process & Grade, inspect the result panel, then use Go to Reports for Review. Student: register with the correct teacher, wait for that teacher to accept in Student Roster, then sign in. Existing accounts retain their current status.

No production grades or accounts were changed. Changes have not been deployed to the customer's server.
