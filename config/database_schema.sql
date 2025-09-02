-- Exam Scheduling System Database Schema
-- Created: September 2, 2025

CREATE DATABASE IF NOT EXISTS exam_scheduling;
USE exam_scheduling;

-- Roles table
CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    permissions TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20),
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- Departments table
CREATE TABLE departments (
    department_id INT PRIMARY KEY AUTO_INCREMENT,
    department_name VARCHAR(100) NOT NULL,
    department_code VARCHAR(10) NOT NULL UNIQUE,
    head_of_department VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Academic sessions table
CREATE TABLE academic_sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    session_name VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN DEFAULT FALSE,
    status ENUM('Active', 'Inactive', 'Archived') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Exam periods table
CREATE TABLE exam_periods (
    exam_period_id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    period_name VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    registration_deadline DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(session_id)
);

-- Courses table
CREATE TABLE courses (
    course_id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_title VARCHAR(200) NOT NULL,
    credit_units INT NOT NULL,
    department_id INT NOT NULL,
    semester VARCHAR(20) NOT NULL,
    academic_level VARCHAR(20) NOT NULL,
    course_type ENUM('Core', 'Elective') DEFAULT 'Core',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

-- Students table
CREATE TABLE students (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    matric_number VARCHAR(9) NOT NULL UNIQUE,
    department_id INT NOT NULL,
    academic_level VARCHAR(20) NOT NULL,
    current_semester VARCHAR(20) NOT NULL,
    entry_year YEAR NOT NULL,
    status ENUM('Active', 'Graduated', 'Suspended') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

-- Lecturers table
CREATE TABLE lecturers (
    lecturer_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    staff_id VARCHAR(50) NOT NULL UNIQUE,
    department_id INT NOT NULL,
    designation VARCHAR(100),
    specialization TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

-- Lecturer course assignments table (many-to-many relationship)
CREATE TABLE lecturer_course_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    lecturer_id INT NOT NULL,
    course_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (assigned_by) REFERENCES users(user_id),
    UNIQUE KEY unique_lecturer_course (lecturer_id, course_id)
);

-- Venues table
CREATE TABLE venues (
    venue_id INT PRIMARY KEY AUTO_INCREMENT,
    venue_name VARCHAR(100) NOT NULL,
    venue_code VARCHAR(20) NOT NULL UNIQUE,
    capacity INT NOT NULL,
    venue_type ENUM('Hall', 'Classroom', 'Laboratory') DEFAULT 'Classroom',
    facilities TEXT,
    is_available BOOLEAN DEFAULT TRUE,
    location TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Examinations table
CREATE TABLE examinations (
    exam_id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    exam_period_id INT NOT NULL,
    exam_type ENUM('CA', 'Final', 'Makeup') NOT NULL,
    duration_minutes INT NOT NULL,
    total_marks INT NOT NULL,
    instructions TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (exam_period_id) REFERENCES exam_periods(exam_period_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    UNIQUE KEY unique_course_exam (course_id, exam_period_id, exam_type)
);

-- Exam invigilator assignments table (multiple invigilators per exam)
CREATE TABLE exam_invigilator_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    role_type ENUM('Chief', 'Assistant') DEFAULT 'Assistant',
    assigned_by INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES examinations(exam_id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id),
    FOREIGN KEY (assigned_by) REFERENCES users(user_id),
    UNIQUE KEY unique_exam_lecturer (exam_id, lecturer_id)
);

-- Exam schedules table (supports multiple venues per exam)
CREATE TABLE exam_schedules (
    schedule_id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    venue_id INT NOT NULL,
    exam_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    capacity_allocated INT NOT NULL,
    students_assigned INT DEFAULT 0,
    status ENUM('Scheduled', 'Ongoing', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES examinations(exam_id),
    FOREIGN KEY (venue_id) REFERENCES venues(venue_id),
    UNIQUE KEY unique_exam_venue (exam_id, venue_id)
);

-- Student course enrollments table
CREATE TABLE student_course_enrollments (
    enrollment_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    exam_period_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    status ENUM('Registered', 'Withdrawn') DEFAULT 'Registered',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id),
    FOREIGN KEY (exam_period_id) REFERENCES exam_periods(exam_period_id),
    UNIQUE KEY unique_enrollment (student_id, course_id, exam_period_id)
);

-- Exam registrations table
CREATE TABLE exam_registrations (
    registration_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    registration_date DATE NOT NULL,
    status ENUM('Registered', 'Present', 'Absent') DEFAULT 'Registered',
    seat_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (exam_id) REFERENCES examinations(exam_id),
    UNIQUE KEY unique_exam_registration (student_id, exam_id)
);

-- Lecturer invigilator assignments table (lecturers can be assigned to invigilate exams)
CREATE TABLE lecturer_invigilator_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    schedule_id INT NOT NULL,
    lecturer_id INT NOT NULL,
    role_type ENUM('Chief', 'Assistant') DEFAULT 'Assistant',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES exam_schedules(schedule_id),
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id),
    UNIQUE KEY unique_lecturer_schedule (lecturer_id, schedule_id)
);

-- Student venue assignments table (tracks which venue each student is assigned to for an exam)
CREATE TABLE student_venue_assignments (
    assignment_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    schedule_id INT NOT NULL,
    seat_number VARCHAR(10),
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (schedule_id) REFERENCES exam_schedules(schedule_id),
    UNIQUE KEY unique_student_schedule (student_id, schedule_id)
);

-- Insert default roles
INSERT INTO roles (role_name, description) VALUES 
('Admin', 'System Administrator with full access'),
('Student', 'Student with access to view schedules and register for exams'),
('Lecturer', 'Lecturer with access to course management and invigilation duties');

-- Insert sample departments
INSERT INTO departments (department_name, department_code, head_of_department) VALUES 
('Computer Science', 'CS', 'Dr. John Smith'),
('Mathematics', 'MATH', 'Dr. Jane Doe'),
('Physics', 'PHY', 'Dr. Bob Johnson'),
('Chemistry', 'CHEM', 'Dr. Alice Brown');

-- Insert sample academic session
INSERT INTO academic_sessions (session_name, start_date, end_date, is_current) VALUES 
('2024/2025', '2024-09-01', '2025-08-31', TRUE);

-- Insert sample exam period
INSERT INTO exam_periods (session_id, period_name, start_date, end_date, registration_deadline) VALUES 
(1, 'First Semester', '2024-12-01', '2024-12-20', '2024-11-15');

-- Insert sample venues
INSERT INTO venues (venue_name, venue_code, capacity, venue_type, location) VALUES 
('Main Hall', 'MH001', 200, 'Hall', 'Ground Floor, Main Building'),
('Computer Lab 1', 'CL001', 30, 'Laboratory', 'First Floor, CS Building'),
('Classroom A', 'CA001', 50, 'Classroom', 'Second Floor, Academic Block'),
('Classroom B', 'CB001', 45, 'Classroom', 'Second Floor, Academic Block');

-- Insert sample courses
INSERT INTO courses (course_code, course_title, credit_units, department_id, semester, academic_level, course_type) VALUES 
('CS101', 'Introduction to Programming', 3, 1, 'First', '100', 'Core'),
('CS201', 'Data Structures', 3, 1, 'First', '200', 'Core'),
('MATH101', 'Calculus I', 3, 2, 'First', '100', 'Core'),
('PHY101', 'General Physics I', 3, 3, 'First', '100', 'Core');

-- Default admin user will be created by setup.php with proper password hash
