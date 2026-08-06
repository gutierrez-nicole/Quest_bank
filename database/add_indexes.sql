SET @db = DATABASE();

-- ============================================================
-- QuestBank Database Architecture Migration
-- Indexes, Foreign Keys, Constraints & Integrity Rules
-- ============================================================

-- Performance Indexes
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_created ON activity_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_submissions_student ON exam_submissions(student_id);
CREATE INDEX IF NOT EXISTS idx_submissions_perc ON exam_submissions(percentage);
CREATE INDEX IF NOT EXISTS idx_submissions_teacher_term ON exam_submissions(teacher_id, term);
CREATE INDEX IF NOT EXISTS idx_requests_teacher_status ON student_requests(teacher_id, status);
CREATE INDEX IF NOT EXISTS idx_requests_student ON student_requests(student_id);

-- Unique Constraints
ALTER TABLE departments ADD UNIQUE INDEX idx_dept_code (dept_code);

-- NOT NULL + DEFAULT Constraints
ALTER TABLE exam_submissions MODIFY correct_count INT NOT NULL DEFAULT 0;
ALTER TABLE exam_submissions MODIFY wrong_count INT NOT NULL DEFAULT 0;
ALTER TABLE exam_submissions MODIFY total_score INT NOT NULL DEFAULT 0;
ALTER TABLE exam_submissions MODIFY total_items INT NOT NULL DEFAULT 0;
ALTER TABLE exam_submissions MODIFY percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00;

-- Expanded question_type column
ALTER TABLE exam_questions MODIFY question_type VARCHAR(50) NOT NULL DEFAULT 'multiple_choice';

-- Foreign Key Constraints (Referential Integrity)
ALTER TABLE activity_logs ADD CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE exam_submissions ADD CONSTRAINT fk_submissions_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE student_requests ADD CONSTRAINT fk_requests_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE student_requests ADD CONSTRAINT fk_requests_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE;
