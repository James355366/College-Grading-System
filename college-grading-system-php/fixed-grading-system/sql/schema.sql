-- =====================================================================
-- College Grading System — MySQL Schema + Seed Data
-- Philippine CHED-standard grading (1.0–5.0 scale, 75% passing floor)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS college_grading_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE college_grading_system;

-- ---------------------------------------------------------------------
-- Admins
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin: username "admin", password "admin123"
-- (hash generated with password_hash('admin123', PASSWORD_DEFAULT))
INSERT INTO admins (username, password_hash, name) VALUES
('admin', '$2y$10$Pk65QATEvdHUYjeEjrsU7.up7dTiNg3sgyv0/KiYo6VJyyGMxrOSa', 'System Administrator');

-- ---------------------------------------------------------------------
-- Teachers
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS teachers;
CREATE TABLE teachers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL DEFAULT '',
  department VARCHAR(150) NOT NULL DEFAULT '',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default teacher: username "teacher", password "teacher123"
INSERT INTO teachers (username, password_hash, name, email, department, status) VALUES
('teacher', '$2y$10$nJlZkrzdd4n4DRTiEbIgte0RzbcrU1SdxNsX5B6g0lveSaGoJ.xca', 'Prof. Maria Santos', 'maria.santos@college.edu', 'College of Computer Studies', 'active');

-- ---------------------------------------------------------------------
-- Default grading weights (single row, id=1)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS default_weights;
CREATE TABLE default_weights (
  id INT PRIMARY KEY DEFAULT 1,
  class_standing DECIMAL(5,2) NOT NULL DEFAULT 20,
  major_exams DECIMAL(5,2) NOT NULL DEFAULT 40,
  performance_task DECIMAL(5,2) NOT NULL DEFAULT 20,
  activity_output DECIMAL(5,2) NOT NULL DEFAULT 20
) ENGINE=InnoDB;

INSERT INTO default_weights (id, class_standing, major_exams, performance_task, activity_output)
VALUES (1, 20, 40, 20, 20);

-- ---------------------------------------------------------------------
-- Classes
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS classes;
CREATE TABLE classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  section VARCHAR(100) NOT NULL,
  school_year VARCHAR(20) NOT NULL,
  semester VARCHAR(30) NOT NULL,
  teacher_id INT NOT NULL,
  class_standing DECIMAL(5,2) NOT NULL DEFAULT 20,
  major_exams DECIMAL(5,2) NOT NULL DEFAULT 40,
  performance_task DECIMAL(5,2) NOT NULL DEFAULT 20,
  activity_output DECIMAL(5,2) NOT NULL DEFAULT 20,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_classes_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Students (per-class roster + scores)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS students;
CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  student_id VARCHAR(50) NOT NULL,
  name VARCHAR(200) NOT NULL,
  quiz DECIMAL(5,2) NOT NULL DEFAULT 0,
  oral DECIMAL(5,2) NOT NULL DEFAULT 0,
  attendance DECIMAL(5,2) NOT NULL DEFAULT 0,
  prelim DECIMAL(5,2) NOT NULL DEFAULT 0,
  final_exam DECIMAL(5,2) NOT NULL DEFAULT 0,
  performance_task DECIMAL(5,2) NOT NULL DEFAULT 0,
  activity_output DECIMAL(5,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Audit logs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actor VARCHAR(150) NOT NULL,
  role ENUM('admin','teacher') NOT NULL,
  action VARCHAR(100) NOT NULL,
  details TEXT NOT NULL
) ENGINE=InnoDB;

INSERT INTO audit_logs (actor, role, action, details) VALUES
('system', 'admin', 'SYSTEM_INIT', 'College Grading System initialized with default data.');

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Seed sample classes + students (teacher_id = 1)
-- ---------------------------------------------------------------------
INSERT INTO classes (code, subject, section, school_year, semester, teacher_id, class_standing, major_exams, performance_task, activity_output)
VALUES
('BSIT-201', 'Data Structures and Algorithms', 'BSIT 2A', '2025-2026', '1st Semester', 1, 20, 40, 20, 20),
('BSIT-305', 'Web Systems and Technologies', 'BSIT 3B', '2025-2026', '1st Semester', 1, 20, 40, 20, 20);

INSERT INTO students (class_id, student_id, name, quiz, oral, attendance, prelim, final_exam, performance_task, activity_output) VALUES
(1, '2023-00123', 'Aguilar, Mariz S.', 88, 86, 95, 85, 84, 87, 88),
(1, '2023-00145', 'Bautista, John Carlo D.', 82, 90, 91, 76, 92, 90, 84),
(1, '2023-00167', 'Cruz, Angelica M.', 94, 82, 99, 94, 76, 84, 92),
(1, '2023-00189', 'Dela Peña, Rafael T.', 80, 94, 87, 88, 88, 93, 80),
(1, '2023-00201', 'Estrada, Nicole B.', 91, 78, 100, 70, 96, 79, 89),
(1, '2023-00223', 'Fernandez, Miguel A.', 78, 91, 90, 96, 72, 96, 96),
(2, '2023-00301', 'Garcia, Sophia L.', 87, 89, 92, 82, 82, 89, 91),
(2, '2023-00322', 'Hernandez, Josh P.', 92, 79, 98, 92, 74, 76, 83),
(2, '2023-00344', 'Ignacio, Kaye R.', 79, 92, 88, 78, 94, 91, 93),
(2, '2023-00366', 'Javier, Leo M.', 89, 84, 97, 92, 78, 85, 82),
(2, '2023-00388', 'Lopez, Andrea C.', 84, 87, 95, 74, 90, 97, 94);
