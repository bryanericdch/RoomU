<?php
session_start();

// ==========================
// === SESSION & ACCESS =====
// ==========================
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['instructor', 'admin'])) {
    header('location: /logout.php');
    exit();
}

include '../database/roomdb.php';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode']) && $_POST['mode'] === 'change_password') {
    header('Content-Type: application/json');

    $userId = $_SESSION['user_id'];
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if ($new !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    // Fetch current password hash
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($storedHash);
    $stmt->fetch();
    $stmt->close();

    if (!$storedHash || !password_verify($current, $storedHash)) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    // Hash new password
    $newHash = password_hash($new, PASSWORD_DEFAULT);

    // Update database
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->bind_param("si", $newHash, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }

    $stmt->close();
    exit;
}

date_default_timezone_set('Asia/Manila');

// Get current time
$currentTime = date('H:i:s');

// 1️⃣ Set rooms to occupied if class is ongoing
$conn->query("
    UPDATE rooms r
    JOIN checkins ci ON r.room_id = ci.room_id
    JOIN classes c ON ci.class_id = c.class_id
    SET r.status = 'occupied'
    WHERE '$currentTime' BETWEEN c.schedule_start AND c.schedule_end
      AND r.status != 'maintenance'
");

// 2️⃣ Set rooms to available if class hasn't started yet
$conn->query("
    UPDATE rooms r
    JOIN checkins ci ON r.room_id = ci.room_id
    JOIN classes c ON ci.class_id = c.class_id
    SET r.status = 'available'
    WHERE '$currentTime' < c.schedule_start
      AND r.status != 'maintenance'
");

// 3️⃣ Set rooms to available if class has ended
$conn->query("
    UPDATE rooms r
    JOIN checkins ci ON r.room_id = ci.room_id
    JOIN classes c ON ci.class_id = c.class_id
    SET r.status = 'available'
    WHERE '$currentTime' > c.schedule_end
      AND r.status != 'maintenance'

    
");
$conn->query("
    DELETE classes, checkins
    FROM classes 
    LEFT JOIN checkins ON classes.class_id = checkins.class_id
    WHERE TIME('$currentTime') > classes.schedule_end 
    AND DATE(classes.created_at) = CURDATE()
");
$conn->query("
    DELETE FROM subjects 
    WHERE NOT EXISTS (
        SELECT 1 
        FROM classes 
        WHERE classes.subject_id = subjects.subject_id
    )
");


// 4️⃣ Reset maintenance rooms after midnight
if ($currentTime >= '00:00:00' && $currentTime <= '00:10:00') {
    $conn->query("UPDATE rooms SET status = 'available' WHERE status = 'maintenance'");
}


// ==========================
// === FETCH CLASSES ========
// ==========================
if (isset($_GET['action']) && $_GET['action'] === 'fetch_classes') {
    header('Content-Type: application/json');

    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    $is_admin = ($_SESSION['role'] ?? '') === 'admin';

    if ($is_admin) {
        $stmt = $conn->prepare("
            SELECT c.class_id, s.subject_code, c.schedule_start, c.schedule_end, 
                   r.room_id, r.room_name, r.status
            FROM classes c
            JOIN subjects s ON c.subject_id = s.subject_id
            JOIN checkins ci ON ci.class_id = c.class_id
            JOIN rooms r ON r.room_id = ci.room_id
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT c.class_id, s.subject_code, c.schedule_start, c.schedule_end, 
                   r.room_id, r.room_name, r.status
            FROM classes c
            JOIN subjects s ON c.subject_id = s.subject_id
            JOIN checkins ci ON ci.class_id = c.class_id
            JOIN rooms r ON r.room_id = ci.room_id
            WHERE c.instructor_id = ?
        ");
        $stmt->bind_param('i', $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $classes = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['classes' => $classes]);
    exit;
}

// ==========================
// === COURSE HANDLING ======
// ==========================

// Add course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_course') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Course name is required']);
        exit;
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);

    $stmt = $conn->prepare("INSERT INTO courses (name, owner_id, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param('si', $name, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok]);
    exit;
}

// Edit course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_course') {
    header('Content-Type: application/json');
    $course_id = intval($_POST['course_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    if ($course_id <= 0 || $name === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE courses SET name = ? WHERE course_id = ?");
    $stmt->bind_param('si', $name, $course_id);
    $success = $stmt->execute();
    echo json_encode(['success' => $success, 'message' => $success ? 'Course updated successfully.' : 'Database error.']);
    $stmt->close();
    exit;
}

// Delete course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_course') {
    header('Content-Type: application/json');
    $course_id = intval($_POST['course_id'] ?? 0);
    if ($course_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid course ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
    $stmt->bind_param('i', $course_id);
    $success = $stmt->execute();
    echo json_encode(['success' => $success, 'message' => $success ? 'Course deleted successfully.' : 'Database error.']);
    $stmt->close();
    exit;
}

// Fetch all courses
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['fetch'] ?? '') === 'courses') {
    header('Content-Type: application/json');

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $is_admin = ($_SESSION['role'] ?? '') === 'admin';

    if ($is_admin) {
        $stmt = $conn->prepare("
            SELECT c.course_id, c.name, u.full_name as owner_name 
            FROM courses c
            LEFT JOIN users u ON c.owner_id = u.user_id 
            ORDER BY c.name ASC
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT course_id, name 
            FROM courses 
            WHERE owner_id = ? 
            ORDER BY name ASC
        ");
        $stmt->bind_param('i', $user_id);
    }

    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode($courses);
    exit;
}

// ==========================
// === SECTION HANDLING =====
// ==========================

// Fetch sections for a course
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['fetch'] ?? '') === 'sections') {
    $course_id = (int)($_GET['course_id'] ?? 0);
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $is_admin = ($_SESSION['role'] ?? '') === 'admin';

    $sql = "
        SELECT s.* 
        FROM sections s
        JOIN courses c ON s.course_id = c.course_id
        WHERE s.course_id = ? " .
        (!$is_admin ? "AND c.owner_id = ?" : "");

    $stmt = $conn->prepare($sql);

    if ($is_admin) {
        $stmt->bind_param('i', $course_id);
    } else {
        $stmt->bind_param('ii', $course_id, $user_id);
    }

    $stmt->execute();
    $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode($sections);
    exit;
}

// Add section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_section') {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $section_name = trim($_POST['section_name'] ?? '');
    $user_id = (int)($_SESSION['user_id'] ?? 0);

    // Get instructor's department_id
    $stmt = $conn->prepare("SELECT department_id FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $department_id = $user['department_id'];
    $stmt->close();

    if ($section_name === '') {
        echo json_encode(['success' => false, 'message' => 'Section name is required']);
        exit;
    }

    if (!$department_id) {
        echo json_encode(['success' => false, 'message' => 'Instructor must be assigned to a department']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO sections (course_id, section_name, instructor_id, department_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('isii', $course_id, $section_name, $user_id, $department_id);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok]);
    exit;
}

// Edit section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_section') {
    header('Content-Type: application/json');
    $section_id = intval($_POST['section_id'] ?? 0);
    $section_name = trim($_POST['section_name'] ?? '');
    if ($section_id <= 0 || $section_name === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE sections SET section_name = ? WHERE section_id = ?");
    $stmt->bind_param('si', $section_name, $section_id);
    $success = $stmt->execute();
    echo json_encode(['success' => $success, 'message' => $success ? 'Section updated successfully.' : 'Database error.']);
    $stmt->close();
    exit;
}

// Delete section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_section') {
    header('Content-Type: application/json');
    $section_id = intval($_POST['section_id'] ?? 0);
    if ($section_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid section ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM sections WHERE section_id = ?");
    $stmt->bind_param('i', $section_id);
    $success = $stmt->execute();
    echo json_encode(['success' => $success, 'message' => $success ? 'Section deleted successfully.' : 'Database error.']);
    $stmt->close();
    exit;
}

// ==========================
// === CLASS HANDLING =======
// ==========================

// Add class
// Add class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_class') {
    header('Content-Type: application/json');

    $section_id = intval($_POST['section_id'] ?? 0);
    $subject_code = trim($_POST['subject_code'] ?? '');
    $schedule_start = $_POST['schedule_start'] ?? '';
    $schedule_end = $_POST['schedule_end'] ?? '';
    $checkin_grace_minutes = intval($_POST['checkin_grace_minutes'] ?? 5);
    $room_id = intval($_POST['room_id'] ?? 0);

    if (!$section_id || !$subject_code || !$schedule_start || !$schedule_end || !$room_id) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // Use session user_id as instructor_id
    $instructor_id = $_SESSION['user_id'];

    // Get course_id from section
    $stmt = $conn->prepare("SELECT course_id FROM sections WHERE section_id=?");
    $stmt->bind_param('i', $section_id);
    $stmt->execute();
    $section = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$section) {
        echo json_encode(['success' => false, 'message' => 'Invalid section selected.']);
        exit;
    }
    $course_id = $section['course_id'];

    // Check if subject already exists for this course
    $stmtCheck = $conn->prepare("SELECT subject_id FROM subjects WHERE course_id=? AND subject_code=?");
    $stmtCheck->bind_param('is', $course_id, $subject_code);
    $stmtCheck->execute();
    $existing = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if ($existing) {
        // Subject already exists, reuse it
        $subject_id = $existing['subject_id'];
    } else {
        // Insert new subject
        $stmt = $conn->prepare("INSERT INTO subjects (course_id, instructor_id, subject_code) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $course_id, $instructor_id, $subject_code);
        $stmt->execute();
        $subject_id = $stmt->insert_id;
        $stmt->close();
    }

    // Insert class
    $stmt = $conn->prepare("INSERT INTO classes (instructor_id, section_id, subject_id, schedule_start, schedule_end, checkin_grace_minutes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiissi', $instructor_id, $section_id, $subject_id, $schedule_start, $schedule_end, $checkin_grace_minutes);
    $ok = $stmt->execute();
    $class_id = $stmt->insert_id;
    $stmt->close();

    // Insert checkin
    $stmt = $conn->prepare("INSERT INTO checkins (class_id, room_id, status) VALUES (?, ?, 'active')");
    $stmt->bind_param('ii', $class_id, $room_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Class added successfully.' : 'Failed to add class.']);
    exit;
}

// Edit class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_class') {
    header('Content-Type: application/json');

    $class_id = intval($_POST['class_id'] ?? 0);
    $subject_code = trim($_POST['subject_code'] ?? '');
    $schedule_start = $_POST['schedule_start'] ?? '';
    $schedule_end = $_POST['schedule_end'] ?? '';
    $checkin_grace_minutes = intval($_POST['checkin_grace_minutes'] ?? 5);
    $room_id = intval($_POST['room_id'] ?? 0);

    if (!$class_id || !$subject_code || !$schedule_start || !$schedule_end || !$room_id) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $is_admin = ($_SESSION['role'] ?? '') === 'admin';

    // Ownership check
    $stmtCheck = $conn->prepare("SELECT instructor_id, subject_id FROM classes WHERE class_id = ?");
    $stmtCheck->bind_param('i', $class_id);
    $stmtCheck->execute();
    $res = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'Class not found.']);
        exit;
    }

    if (!$is_admin && ((int)$res['instructor_id'] !== $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    $subject_id = $res['subject_id'];

    // Update subject code
    $stmt = $conn->prepare("UPDATE subjects SET subject_code=? WHERE subject_id=?");
    $stmt->bind_param('si', $subject_code, $subject_id);
    $stmt->execute();
    $stmt->close();

    // Update class schedule & checkin grace
    $stmt = $conn->prepare("UPDATE classes SET schedule_start=?, schedule_end=?, checkin_grace_minutes=? WHERE class_id=?");
    $stmt->bind_param('ssii', $schedule_start, $schedule_end, $checkin_grace_minutes, $class_id);
    $ok = $stmt->execute();
    $stmt->close();

    // Update room in checkins table
    $stmt = $conn->prepare("UPDATE checkins SET room_id=? WHERE class_id=? AND status='active'");
    $stmt->bind_param('ii', $room_id, $class_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Class updated successfully.' : 'Failed to update class.']);
    exit;
}


// Fetch classes for section
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['fetch'] ?? '') === 'classes') {
    header('Content-Type: application/json');
    $section_id = intval($_GET['section_id'] ?? 0);

    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    $is_admin = ($_SESSION['role'] ?? '') === 'admin';

    if ($is_admin) {
        $stmt = $conn->prepare("
            SELECT c.class_id, s.subject_code, c.schedule_start, c.schedule_end, c.checkin_grace_minutes,
                   r.room_id, r.room_name, r.status
            FROM classes c
            JOIN subjects s ON c.subject_id = s.subject_id
            LEFT JOIN checkins ch ON ch.class_id = c.class_id AND ch.status='active'
            LEFT JOIN rooms r ON r.room_id = ch.room_id
            WHERE c.section_id = ?
        ");
        $stmt->bind_param('i', $section_id);
    } else {
        $stmt = $conn->prepare("
            SELECT c.class_id, s.subject_code, c.schedule_start, c.schedule_end, c.checkin_grace_minutes,
                   r.room_id, r.room_name, r.status
            FROM classes c
            JOIN subjects s ON c.subject_id = s.subject_id
            LEFT JOIN checkins ch ON ch.class_id = c.class_id AND ch.status='active'
            LEFT JOIN rooms r ON r.room_id = ch.room_id
            WHERE c.section_id = ? AND c.instructor_id = ?
        ");
        $stmt->bind_param('ii', $section_id, $user_id);
    }

    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
    exit;
}

// Delete class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_class') {
    header('Content-Type: application/json');
    $class_id = intval($_POST['class_id'] ?? 0);
    if ($class_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid class ID.']);
        exit;
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $is_admin = ($_SESSION['role'] ?? '') === 'admin';

    // Ownership check
    $stmtOwner = $conn->prepare("SELECT instructor_id FROM classes WHERE class_id = ?");
    $stmtOwner->bind_param('i', $class_id);
    $stmtOwner->execute();
    $owner = $stmtOwner->get_result()->fetch_assoc();
    $stmtOwner->close();

    if (!$owner) {
        echo json_encode(['success' => false, 'message' => 'Class not found.']);
        exit;
    }

    if (!$is_admin && ((int)$owner['instructor_id'] !== $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    // proceed with existing delete logic
    // Get subject_id and room_id
    $stmt = $conn->prepare("SELECT subject_id, room_id FROM classes c LEFT JOIN checkins ch ON c.class_id = ch.class_id AND ch.status='active' WHERE c.class_id = ?");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'Class not found.']);
        exit;
    }
    $subject_id = $res['subject_id'];
    $room_id = $res['room_id'];

    // Delete checkins
    $stmt = $conn->prepare("DELETE FROM checkins WHERE class_id = ?");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $stmt->close();

    // Delete class
    $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
    $stmt->bind_param('i', $class_id);
    $success = $stmt->execute();
    $stmt->close();

    // Revert room status to available
    if ($room_id) {
        $stmt = $conn->prepare("UPDATE rooms SET status='available' WHERE room_id = ?");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $stmt->close();
    }

    // Delete subject if no other classes exist
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM classes WHERE subject_id = ?");
    $stmt->bind_param('i', $subject_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($count == 0) {
        $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
        $stmt->bind_param('i', $subject_id);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => $success, 'message' => $success ? 'Class (and subject if unused) deleted successfully.' : 'Database error.']);
    exit;
}

// ==========================
// === ROOM STATUS HANDLING ==
// ==========================
if (isset($_POST['action']) && $_POST['action'] === 'update_room_status') {
    header('Content-Type: application/json');
    $room_id = $_POST['room_id'] ?? null;
    $status = $_POST['status'] ?? null;

    if (!$room_id || !in_array($status, ['available', 'maintenance'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE room_id = ?");
    $stmt->bind_param('si', $status, $room_id);
    $ok = $stmt->execute();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Room status updated.' : 'Failed to update room status']);
    $stmt->close();
    exit;
}

// Fetch available rooms with check-in status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['fetch'] ?? '') === 'available_rooms') {
    header('Content-Type: application/json');

    $query = "
        SELECT r.room_id, r.room_name,
               CASE WHEN EXISTS (
                   SELECT 1 FROM checkins c
                   WHERE c.room_id = r.room_id AND c.status = 'active'
               ) THEN 'checkedin'
               ELSE 'available'
               END AS status
        FROM rooms r
        ORDER BY r.room_name ASC
    ";

    $result = $conn->query($query);
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}

// NEW: Fetch buildings list
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['fetch'] ?? '') === 'buildings') {
    header('Content-Type: application/json');
    $result = $conn->query("SELECT building_id, name FROM buildings ORDER BY name ASC");
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}

// NEW: Fetch rooms for a specific building (includes active-checkin status)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['fetch'] ?? '') === 'rooms_by_building') {
    header('Content-Type: application/json');
    $building_id = intval($_GET['building_id'] ?? 0);
    if ($building_id <= 0) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT r.room_id, r.room_name,
               CASE WHEN EXISTS (
                   SELECT 1 FROM checkins c WHERE c.room_id = r.room_id AND c.status = 'active'
               ) THEN 'checkedin' ELSE r.status END AS status
        FROM rooms r
        WHERE r.building_id = ?
        ORDER BY r.room_name ASC
    ");
    $stmt->bind_param('i', $building_id);
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode($rooms);
    exit;
}

// --- Fetch instructor info ---
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$full_name = $user['full_name'];
$email = $user['email'];


// ==========================
// === CLASS STATUS HANDLING
// ==========================
if (isset($_POST['action']) && $_POST['action'] === 'update_class_status') {
    header('Content-Type: application/json');
    $class_id = intval($_POST['class_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (!$class_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing class_id or status']);
        exit;
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $is_admin = ($_SESSION['role'] ?? '') === 'admin';

    // Ownership check
    $stmtOwner = $conn->prepare("SELECT instructor_id FROM classes WHERE class_id = ?");
    $stmtOwner->bind_param('i', $class_id);
    $stmtOwner->execute();
    $owner = $stmtOwner->get_result()->fetch_assoc();
    $stmtOwner->close();

    if (!$owner) {
        echo json_encode(['success' => false, 'message' => 'Class not found.']);
        exit;
    }

    if (!$is_admin && ((int)$owner['instructor_id'] !== $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    // Get associated room_id
    $stmt = $conn->prepare("SELECT room_id FROM checkins WHERE class_id = ? AND status='active' LIMIT 1");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res || !$res['room_id']) {
        echo json_encode(['success' => false, 'message' => 'No active checkin found for this class.']);
        exit;
    }
    $room_id = $res['room_id'];

    // Update room status
    $stmt2 = $conn->prepare("UPDATE rooms SET status = ? WHERE room_id = ?");
    $stmt2->bind_param('si', $status, $room_id);
    $ok = $stmt2->execute();
    $stmt2->close();

    // If maintenance, reset class schedule
    if ($status === 'maintenance') {
        $stmt = $conn->prepare("UPDATE classes SET schedule_start='00:00:01', schedule_end='00:00:02' WHERE class_id=?");
        $stmt->bind_param('i', $class_id);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => $ok, 'room_id' => $room_id]);
    exit;
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/output.css">
    <title>RoomU</title>
</head>

<body class="h-screen flex flex-col">

    <header class="bg-roomu-green h-[80px] w-full flex justify-between relative flex-none">

        <!--Logo-->
        <section class="w-[190px]  h-full flex items-center justify-center ml-3">
            <img src="/assets/img/phinma_white.png">
        </section>

        <!--Date and  Time-->
        <section class="absolute left-89 w-[300px] h-full flex items-center justify-between">
            <div class="text-roomu-white font-bold text-[36px]" id="current-time">
                11:11 AM <!-- <?php echo date('g:i A', strtotime('Asia/Manila')); ?> -->
            </div>
            <div class="text-roomu-white flex flex-col font-bold text-[16px]">
                <div id="current-day">Sunday</div> <!--<?php echo date('l', strtotime('Asia/Manila')); ?>-->
                <div id="current-date">January 07, 2025</div>
                <!--<?php echo date('F j, Y', strtotime('Asia/Manila')); ?>-->
            </div>
        </section>

        <!--Profile-->
        <section id="profile-btn"
            class="flex items-center justify-between mr-[30px] w-[160px] cursor-pointer select-none">
            <div class="text-roomu-white text-base font-normal"><?php echo ($full_name); ?></div>
            <div class="bg-roomu-white rounded-full w-[50px] h-[50px] flex center-div">
                <img class="w-[30px] h-[30px]" src="/assets/icons/admin_icon.svg" alt="profile">
            </div>

        </section>

        <div id="logout-bar"
            class="fixed top-0 right-0 h-full w-[400px] bg-body shadow-xl transform translate-x-full transition-transform duration-300 ease-in-out z-50">
            <div class="p-6 flex flex-col h-full">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-roomu-black">Account</h2>
                    <button id="close-logout"
                        class="text-gray-500 hover:text-roomu-green text-2xl font-bold cursor-pointer">&times;</button>
                </div>

                <div class="mb-4">
                    <div class="font-medium text-roomu-black"><?php echo ($full_name); ?></div>
                    <div id="account-email" class="text-sm text-gray-500"><?php echo ($email); ?></div>
                </div>
                <div class="flex flex-col gap-3 mt-4">
                    <button id="open-change-password"
                        class="w-full bg-roomu-green text-roomu-white py-2 px-3 rounded-md hover:bg-hover-roomu-green cursor-pointer">Change
                        Password</button>
                    <a href="/logout.php" id="logout-btn"
                        class="w-full bg-red-500 text-white py-2 px-3 rounded-md hover:opacity-90">Logout</a>
                </div>

                <form id="change-password-form" class="mt-6 space-y-3 hidden" autocomplete="off">
                    <input name="current_password" id="current_password" type="password" placeholder="Current password"
                        class="w-full border p-2 rounded" required>
                    <input name="new_password" id="new_password" type="password" placeholder="New password"
                        class="w-full border p-2 rounded" required>
                    <input name="confirm_password" id="confirm_password" type="password" placeholder="Confirm new password"
                        class="w-full border p-2 rounded" required>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="cancel-change" class="px-3 py-1 border rounded">Cancel</button>
                        <button type="submit" class="px-3 py-1 bg-roomu-green text-white rounded">Save</button>
                    </div>
                </form>

                <div id="logout-msg" class="text-sm text-red-600 mt-4 hidden"></div>
            </div>
        </div>

    </header>

    <!--Main Content-->
    <main class="flex flex-1 overflow-hidden">
        <!--Side Navigation-->
        <nav class="w-[200px] h-full flex-none flex flex-col justify-around items-end ">
            <div>
                <a href="/instructor/instructor_dashboard.php" class="nav-item ">
                    <div class="nav-icon"><img src="/assets/icons/dashboard_icon.png"></div>
                    <p>Dashboard</p>
                </a>

                <a href="/instructor/instructor_classes.php" class="nav-item bg-roomu-green text-roomu-white">
                    <div class="nav-icon "><img src="/assets/icons/instructors_icon.svg" class="w-[24px] h-[24px]">
                    </div>
                    <p>Classes</p>
                </a>
            </div>
            <img src="/assets/img/upang.png" class="w-auto ">
        </nav>



        <!--Main Content-->
        <section class="flex flex-1 p-6 gap-6">
            <!--Courses-->
            <div class="w-1/4 bg-roomu-white p-4 rounded shadow">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold">Courses</h3>
                    <button id="add-course"
                        class="bg-roomu-green text-white px-2 py-1 rounded cursor-pointer hover:bg-hover-roomu-green">+
                        Add</button>
                </div>
                <ul id="courses" class="space-y-2 overflow-auto"></ul>
            </div>

            <div class="w-1/4 bg-roomu-white p-4 rounded shadow">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold">Sections</h3>
                    <button id="add-section"
                        class="bg-roomu-green text-white px-2 py-1 rounded cursor-pointer hover:bg-hover-roomu-green"
                        disabled>+ Add</button>
                </div>
                <ul id="sections" class="space-y-2 overflow-auto"></ul>
            </div>

            <div class="flex-1 bg-roomu-white p-4 rounded shadow">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold">Classes</h3>
                    <button id="add-class"
                        class="bg-roomu-green text-white px-2 py-1 rounded cursor-pointer hover:bg-hover-roomu-green"
                        disabled>+ Add</button>
                </div>
                <ul id="classes" class="space-y-2 overflow-auto"></ul>
            </div>

        </section>

        <!--modal for add/edit class-->
        <section id="modal" class="fixed inset-0 hidden items-center justify-center bg-black/40 z-50">
            <div class="bg-white p-6 rounded w-[420px]">
                <h4 id="modal-title" class="font-bold mb-3">Add Class</h4>
                <form id="modal-form" class="space-y-3">
                    <div>
                        <label class="block text-sm">Subject code</label>
                        <input id="subject_code" name="subject_code" required class="w-full shadow-container p-2 rounded" />
                    </div>
                    <!-- Room selector -->
                    <label class="block text-sm font-medium mt-2">Building</label>
                    <select id="building_id" name="building_id" class="w-full border rounded p-2 mb-2">
                        <option value="">Loading buildings...</option>
                    </select>

                    <label class="block text-sm font-medium">Room</label>
                    <select id="room_id" name="room_id" class="w-full border rounded p-2">
                        <option value="">Select building first</option>
                    </select>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm">Start time</label>
                            <input id="start_time" name="schedule_start" type="time" required class="w-full shadow-container p-2 rounded" />
                        </div>
                        <div>
                            <label class="block text-sm">End time</label>
                            <input id="end_time" name="schedule_end" type="time" required class="w-full shadow-container p-2 rounded" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="modal-cancel"
                            class="px-3 py-1 border rounded cursor-pointer ">Cancel</button>
                        <button type="submit"
                            class="px-3 py-1 bg-roomu-green text-white rounded cursor-pointer hover:bg-hover-roomu-green">Save</button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Course modal -->
        <section id="modal-course" class="fixed inset-0 hidden items-center justify-center bg-black/40 z-50">
            <div class="bg-white p-6 rounded w-[420px]">
                <h4 id="modal-course-title" class="font-bold mb-3">Add Course</h4>
                <form id="modal-course-form" class="space-y-3">
                    <div>
                        <label class="block text-sm">Course name</label>
                        <input id="course-name" name="name" required class="w-full shadow-container p-2 rounded" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="modal-course-cancel"
                            class="px-3 py-1 border rounded cursor-pointer ">Cancel</button>
                        <button type="submit"
                            class="px-3 py-1 bg-roomu-green text-white rounded cursor-pointer hover:bg-hover-roomu-green">Save</button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Section modal -->
        <section id="modal-section" class="fixed inset-0 hidden items-center justify-center bg-black/40 z-50">
            <div class="bg-white p-6 rounded w-[420px]">
                <h4 id="modal-section-title" class="font-bold mb-3">Add Section</h4>
                <form id="modal-section-form" class="space-y-3">
                    <div>
                        <label class="block text-sm">Section name</label>
                        <input id="section-name" name="section_name" required class="w-full shadow-container p-2 rounded" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="modal-section-cancel"
                            class="px-3 py-1 border rounded cursor-pointer">Cancel</button>
                        <button type="submit"
                            class="px-3 py-1 bg-roomu-green text-white rounded cursor-pointer hover:bg-hover-roomu-green">Save</button>
                    </div>
                </form>
            </div>
        </section>



    </main>


    <script src="/assets/js/clock.js"></script>
    <script src="/assets/js/logout.js"></script>
    <script src="/assets/js/instructor_classes.js"></script>
    <script src="/assets/js/instructor_passwordchange.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const buildingSelect = document.getElementById('building_id');
            const roomSelect = document.getElementById('room_id');
            const addClassBtn = document.getElementById('add-class');

            function escapeHtml(str) {
                return String(str || '').replace(/[&<>"']/g, function(s) {
                    return ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    })[s];
                });
            }

            function loadBuildings(selectedBuildingId = null) {
                fetch('/instructor/instructor_classes.php?fetch=buildings')
                    .then(res => res.json())
                    .then(data => {
                        if (!buildingSelect) return;
                        buildingSelect.innerHTML = '<option value=\"\">Select building</option>';
                        data.forEach(b => {
                            const opt = document.createElement('option');
                            opt.value = b.building_id;
                            opt.textContent = b.name;
                            buildingSelect.appendChild(opt);
                        });
                        if (selectedBuildingId) buildingSelect.value = selectedBuildingId;
                        const bid = buildingSelect.value || (data[0] && data[0].building_id);
                        if (bid) loadRooms(bid);
                    })
                    .catch(() => {
                        if (buildingSelect) buildingSelect.innerHTML = '<option value=\"\">Failed to load</option>';
                    });
            }

            function loadRooms(buildingId, selectedRoomId = null) {
                if (!roomSelect) return;
                roomSelect.innerHTML = '<option value=\"\">Loading rooms...</option>';
                fetch('/instructor/instructor_classes.php?fetch=rooms_by_building&building_id=' + encodeURIComponent(buildingId))
                    .then(res => res.json())
                    .then(data => {
                        roomSelect.innerHTML = '<option value=\"\">Select room</option>';
                        data.forEach(rm => {
                            const opt = document.createElement('option');
                            opt.value = rm.room_id;
                            opt.textContent = `${rm.room_name} (${rm.status})`;
                            roomSelect.appendChild(opt);
                        });
                        if (selectedRoomId) roomSelect.value = selectedRoomId;
                    })
                    .catch(() => {
                        roomSelect.innerHTML = '<option value=\"\">Failed to load rooms</option>';
                    });
            }

            if (buildingSelect) {
                buildingSelect.addEventListener('change', function() {
                    const bid = this.value;
                    if (bid) loadRooms(bid);
                    else roomSelect.innerHTML = '<option value=\"\">Select building first</option>';
                });
            }

            // When opening add-class modal, load buildings (existing modal open code should still run)
            if (addClassBtn) {
                addClassBtn.addEventListener('click', function() {
                    loadBuildings();
                });
            }

            // Optionally, if modal is opened for edit you should call loadBuildings(existingBuildingId) then set room
        });
    </script>

</body>

</html>