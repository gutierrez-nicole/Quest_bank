SET @db = DATABASE();

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_exams_teacher ON exams(teacher_id);
CREATE INDEX IF NOT EXISTS idx_exam_q_exam ON exam_questions(exam_id);
CREATE INDEX IF NOT EXISTS idx_submissions_teacher ON exam_submissions(teacher_id);
CREATE INDEX IF NOT EXISTS idx_submissions_student ON exam_submissions(student_id);
CREATE INDEX IF NOT EXISTS idx_submissions_perc ON exam_submissions(percentage);
CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_created ON activity_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_students_teacher ON students(teacher_id);
CREATE INDEX IF NOT EXISTS idx_requests_teacher_status ON student_requests(teacher_id, status);
