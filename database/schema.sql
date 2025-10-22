-- Improved RoomU schema (based on provided schema)
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Departments
CREATE TABLE IF NOT EXISTS departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dept_name (name)
);

-- 2. Users (admins + instructors)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,          -- hashed via password_hash()
    role ENUM('admin','instructor') NOT NULL,
    department_id INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    INDEX idx_users_role_dept (role, department_id),
    INDEX idx_users_email (email)
);

-- 3. Buildings
CREATE TABLE IF NOT EXISTS buildings (
    building_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    address VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_building_name (name)
);

-- 4. Rooms
CREATE TABLE IF NOT EXISTS rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    room_name VARCHAR(50) NOT NULL,               -- e.g., "101" or "Room 101"
    status ENUM('available','occupied','maintenance') DEFAULT 'available',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(building_id) ON DELETE CASCADE,
    INDEX idx_rooms_status (status),
    INDEX idx_rooms_building (building_id),
    UNIQUE KEY unique_room_building (building_id, room_name)
);

-- 5. Courses
CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_course_name (name)
);

-- 6. Subjects
CREATE TABLE IF NOT EXISTS subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    instructor_id INT DEFAULT NULL,
    subject_code VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_subjects_code (subject_code),
    INDEX idx_subjects_instructor (instructor_id),
    UNIQUE KEY unique_subject_course (course_id, subject_code)
);

-- 7. Sections
CREATE TABLE IF NOT EXISTS sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    department_id INT NOT NULL,
    section_name VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    INDEX idx_sections_dept (department_id)
    
);

-- 8. Classes (schedules) -- supports weekly recurring classes (day_of_week)
CREATE TABLE IF NOT EXISTS classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    instructor_id INT NOT NULL,
    subject_id INT NOT NULL,
    section_id INT NOT NULL,                 
    schedule_start TIME NOT NULL,
    schedule_end TIME NOT NULL,
    checkin_grace_minutes INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
    INDEX idx_classes_instructor (instructor_id),
    INDEX idx_classes_times (schedule_start, schedule_end),
    CHECK (schedule_start < schedule_end)
);

-- 9. Checkins (room occupation records)
CREATE TABLE IF NOT EXISTS checkins (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    room_id INT NOT NULL,
    checkin_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    checkout_time DATETIME DEFAULT NULL,
    status ENUM('active','completed','expired') DEFAULT 'active',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    INDEX idx_checkins_status (status),
    INDEX idx_checkins_time (checkin_time),
    INDEX idx_checkins_class_room (class_id, room_id),
    UNIQUE KEY unique_active_checkin (class_id, room_id)  -- ensures same class+room not duplicated
);

-- 10. Password resets
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_resets_token (token),
    INDEX idx_resets_expires (expires_at)
);


-- 12. Audit logs
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    old_value JSON DEFAULT NULL,
    new_value JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action)
);

ALTER TABLE classes
  ADD COLUMN instructor_id INT NOT NULL AFTER class_id;


ALTER TABLE classes
  ADD CONSTRAINT fk_classes_instructor
  FOREIGN KEY (instructor_id) REFERENCES users(user_id)
  ON DELETE CASCADE;


  ALTER TABLE sections
ADD COLUMN instructor_id INT NOT NULL,
ADD FOREIGN KEY (instructor_id) REFERENCES users(user_id) ON DELETE CASCADE;