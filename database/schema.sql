-- Enable InnoDB for foreign keys (if not already)
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Departments Table (for instructors and sections)
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    INDEX idx_dept_name (name)
);

-- Sample inserts
-- INSERT INTO departments (name) VALUES ('Information Technology'), ('Business Administration');

-- 2. Users Table (admin/instructor only; no guests)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,  -- Hashed via PHP password_hash
    role ENUM('admin', 'instructor') NOT NULL,
    department_id INT DEFAULT NULL,  -- Required for instructors
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    INDEX idx_users_role_dept (role, department_id),
    INDEX idx_users_email (email)
);

-- Sample inserts (hash passwords in PHP)
-- INSERT INTO users (full_name, email, password, role, department_id) VALUES ('System Admin', 'admin@phinmaed.com', '$2y$10$hash', 'admin', NULL);
-- INSERT INTO users (full_name, email, password, role, department_id) VALUES ('Jane Instructor', 'jane@phinmaed.com', '$2y$10$hash', 'instructor', 1);

-- 3. Buildings Table
CREATE TABLE buildings (
    building_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    INDEX idx_building_name (name)
);

-- Sample
-- INSERT INTO buildings (name) VALUES ('Main Building');

-- 4. Rooms Table (statuses for dashboards)
CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    room_name VARCHAR(50) NOT NULL,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(building_id) ON DELETE CASCADE,
    INDEX idx_rooms_status (status),
    INDEX idx_rooms_building (building_id),
    UNIQUE KEY unique_room_building (building_id, room_name)
);

-- Sample
-- INSERT INTO rooms (building_id, room_name, capacity) VALUES (1, '101', 40);

-- 5. Courses Table (CRUD by admin/instructor)
CREATE TABLE courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,  -- e.g., 'BS Information Technology'
    INDEX idx_course_name (name)
);

-- Sample
-- INSERT INTO courses (name) VALUES ('BS Information Technology');

-- 6. Subjects Table (instructor-managed under courses; added instructor_id for ownership)
CREATE TABLE subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    instructor_id INT DEFAULT NULL,  -- Who created/manages this subject (for flow's manage_class)
    subject_code VARCHAR(50) NOT NULL,  -- e.g., 'IT101'
    subject_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES users(user_id) ON DELETE SET NULL,  -- Optional; soft-delete on instructor removal
    INDEX idx_subjects_code (subject_code),
    INDEX idx_subjects_instructor (instructor_id),
    UNIQUE KEY unique_subject_course (course_id, subject_code)
);

-- Sample
-- INSERT INTO subjects (course_id, instructor_id, subject_code, subject_name) VALUES (1, 2, 'IT101', 'Intro to Programming');

-- 7. Sections Table (defined by course + dept + year + block; for class sections like 'BSIT2-05')
CREATE TABLE sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    department_id INT NOT NULL,  -- Ties to instructor's dept
    year_level VARCHAR(10) NOT NULL,  -- e.g., '2'
    block_no VARCHAR(10) NOT NULL,  -- e.g., '05'
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    INDEX idx_sections_dept (department_id),
    UNIQUE KEY unique_section_course_dept (course_id, department_id, year_level, block_no)
);

-- Sample (e.g., BSIT 2-05 in IT dept)
-- INSERT INTO sections (course_id, department_id, year_level, block_no) VALUES (1, 1, '2', '05');

-- 8. Classes Table (instructor's schedules; grace for check-in)
CREATE TABLE classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    instructor_id INT NOT NULL,
    subject_id INT NOT NULL,
    section_id INT NOT NULL,
    schedule_start TIME NOT NULL,  -- e.g., '10:30:00'
    schedule_end TIME NOT NULL,    -- e.g., '12:00:00'
    checkin_grace_minutes INT DEFAULT 5,  -- Early allowance for check-in
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
    INDEX idx_classes_instructor (instructor_id),
    INDEX idx_classes_times (schedule_start, schedule_end),
    CHECK (schedule_start < schedule_end)
);

-- Sample
-- INSERT INTO classes (instructor_id, subject_id, section_id, schedule_start, schedule_end) VALUES (2, 1, 1, '10:30:00', '12:00:00');

-- 9. Checkins Table (for occupations; active for current classes)
CREATE TABLE checkins (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    room_id INT NOT NULL,
    checkin_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    checkout_time DATETIME DEFAULT NULL,
    status ENUM('active', 'completed', 'expired') DEFAULT 'active',  -- Active until end time
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    INDEX idx_checkins_status (status),
    INDEX idx_checkins_time (checkin_time),
    INDEX idx_checkins_class_room (class_id, room_id),
    INDEX idx_checkins_instructor (class_id),  -- For instructor status queries
    UNIQUE KEY unique_active_checkin (class_id, room_id)
);

-- Sample
-- INSERT INTO checkins (class_id, room_id) VALUES (1, 1);

-- 10. Password Resets Table
CREATE TABLE password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_resets_token (token),
    INDEX idx_resets_expires (expires_at)
);

-- 11. System Settings Table (e.g., default grace)
CREATE TABLE system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO system_settings (setting_key, setting_value, description) 
VALUES 
    ('timezone', 'Asia/Manila', 'System default timezone'),
    ('default_checkin_grace_minutes', '5', 'Default early allowance for check-ins');

-- 12. Audit Logs Table (for actions like deletions, check-ins)
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,  -- e.g., 'instructor_deleted', 'checkin_created'
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    old_value JSON DEFAULT NULL,
    new_value JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action)
);

-- End of schema.