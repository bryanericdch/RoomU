<?php
session_start();
include '../database/roomdb.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('location: /logout.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$mode = $_POST['mode'] ?? '';

// Fetch user info
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$full_name = $user['full_name'];
$email = $user['email'];

// Fetch departments with instructor count
$departments = [];
$deptStmt = $conn->prepare("
    SELECT d.department_id, d.name, COUNT(u.user_id) AS instructor_count
    FROM departments d
    LEFT JOIN users u ON u.department_id = d.department_id AND LOWER(u.role) = 'instructor'
    GROUP BY d.department_id
    ORDER BY d.name ASC
");
$deptStmt->execute();
$deptResult = $deptStmt->get_result();
while ($row = $deptResult->fetch_assoc()) {
    $departments[] = $row;
}

// Fetch instructors
$instructors = [];
$instructorStmt = $conn->prepare("
    SELECT u.user_id, u.full_name, u.email, u.department_id, u.is_active,
           u.last_login, d.name AS department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE LOWER(u.role) = 'instructor'
    ORDER BY u.full_name ASC
");
$instructorStmt->execute();
$instructorResult = $instructorStmt->get_result();
while ($row = $instructorResult->fetch_assoc()) {
    $instructors[] = $row;
}
// Fetch all classes with room, section, and subject info via checkins
$classesResult = $conn->query("
    SELECT 
        c.class_id,
        c.instructor_id,
        c.schedule_start,
        c.schedule_end,
        s.section_name,
        sub.subject_code,
        r.room_name
    FROM classes c
    LEFT JOIN sections s ON c.section_id = s.section_id
    LEFT JOIN subjects sub ON c.subject_id = sub.subject_id
    LEFT JOIN checkins ci ON ci.class_id = c.class_id AND ci.status = 'active'
    LEFT JOIN rooms r ON ci.room_id = r.room_id
    ORDER BY c.instructor_id, r.room_name
");


// Map classes to instructors
$classesByInstructor = [];
while ($row = $classesResult->fetch_assoc()) {
    $inst_id = $row['instructor_id'];
    if (!isset($classesByInstructor[$inst_id])) $classesByInstructor[$inst_id] = [];
    $classesByInstructor[$inst_id][] = [
        'room_name' => $row['room_name'] ?? '-',
        'section_name' => $row['section_name'] ?? '-',
        'subject_code' => $row['subject_code'] ?? '-',
        'schedule_start' => $row['schedule_start'],
        'schedule_end' => $row['schedule_end']
    ];
}

// --- CHANGE PASSWORD ---
if ($mode === 'change_password') {
    try {
        $current = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (!$current || !$new_pass || !$confirm_pass) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit();
        }

        if ($new_pass !== $confirm_pass) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
            exit();
        }

        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id=?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($current, $user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Current password incorrect']);
            exit();
        }

        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash=?, updated_at=CURRENT_TIMESTAMP WHERE user_id=?");
        $stmt->bind_param("si", $hash, $_SESSION['user_id']);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
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
    <title>RoomU &vert; Administrator</title>
</head>

<body class="h-screen flex flex-col">

    <header class="bg-roomu-green h-[80px] w-full
    flex justify-between relative flex-none">
        <!--Logo-->
        <section class="w-[190px] h-full flex items-center justify-center ml-3">
            <img src="/assets/img/phinma_white.png">
        </section>

        <!--Date and Time-->
        <section class="absolute left-89 w-[300px] h-full
        flex items-center justify-between">
            <div class="text-roomu-white font-bold text-[36px]" id="current-time">
                11:11 AM<!-- <?php echo date('g:i A', strtotime('Asia/Manila')); ?> -->
            </div>
            <div class="text-roomu-white flex  flex-col font-bold text-[16px]">
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
                        class="w-full bg-roomu-green text-roomu-white py-2 px-3 rounded-md hover:bg-hover-roomu-green">Change
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


    <main class="flex flex-1 overflow-hidden">
        <!--Side Navigation-->
        <nav class="w-[200px] h-full flex-none flex flex-col justify-around items-end ">
            <div>
                <a href="/admin/admin_dashboard.php" class="nav-item ">
                    <div class="nav-icon"><img src="/assets/icons/dashboard_icon.png"></div>
                    <p>Dashboard</p>
                </a>

                <a href="/admin/admin_instructors.php" class="nav-item bg-roomu-green text-roomu-white">
                    <div class="nav-icon"><img src="/assets/icons/instructors_icon.svg" class="w-[24px] h-[24px]"></div>
                    <p>Instructors</p>
                </a>

                <a href="/admin/admin_management.php" class="nav-item">
                    <div class="nav-icon"><img src="/assets/icons/management_icon.svg" class="w-[24px] h-[24px]"></div>
                    <p>Management</p>
                </a>
            </div>

            <img src="/assets/img/upang.png" class="w-auto ">
        </nav>


        <!--Main Content-->
        <section class="flex w-full h-full">
            <section class="flex w-[20%] h-full items-center justify-end mr-10 ml-10">

                <!--Departments Section-->
                <div id="departments-section"
                    class="shadow-container rounded-[10px] w-full h-[80%] flex items-stretch justify-start p-[20px]">
                    <ul class="space-y-3 w-full" role="list">
                        <?php if (count($departments) > 0): ?>
                            <?php foreach ($departments as $dept): ?>
                                <li class="p-3 bg-gray-50 rounded-md hover:bg-hover-roomu-green cursor-pointer flex justify-between items-center w-full transition duration-200 ease-in-out"
                                    data-department-id="<?= $dept['department_id'] ?>" data-action="select-department">

                                    <a href="#" class="text-lg font-medium flex-1">
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </a>

                                    <div class="flex items-center space-x-2 ml-4">
                                        <div class="flex items-center">
                                            <span class="w-3 h-3"><img src="/assets/icons/instructors_icon.svg"></span>
                                            <span class="ml-1 text-xs font-medium"><?= $dept['instructor_count'] ?></span>

                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="p-3 italic text-gray-500">No departments found</li>
                        <?php endif; ?>
                    </ul>
                </div>

            </section>

            <!--Instructors Section-->
            <section class="w-[70%] h-full items-center justify-around flex flex-col">
                <div id="instructors-section"
                    class="col-span-1 shadow-container rounded-lg p-6 w-full h-[80%] flex flex-col overflow-hidden">
                    <div class="flex justify-between text-roomu-black font-bold border-b border-gray-200 pb-2 mb-3">
                        <div class="w-1/4">Instructors</div>
                        <div class="w-1/5">Room No.</div>
                        <div class="w-2/1">Class</div>
                        <div class="w-1/4 text-center">Schedule</div>
                    </div>

                    <ul id="instructors-list" class="space-y-2 overflow-y-auto flex-1">
                        <?php if (count($instructors) > 0): ?>
                            <?php foreach ($instructors as $instr): ?>
                                <li class="flex flex-col bg-gray-50 rounded-md p-3 hover:bg-hover-roomu-green cursor-pointer">
                                    <div class="flex justify-between w-full font-bold">
                                        <div class="w-1/4"><?= htmlspecialchars($instr['full_name']) ?></div>
                                        <div class="w-1/4 text-center">Room</div>
                                        <div class="w-1/4">Class</div>
                                        <div class="w-1/4 text-center">Schedule</div>
                                    </div>

                                    <?php
                                    $instrClasses = $classesByInstructor[$instr['user_id']] ?? [];
                                    if (count($instrClasses) > 0):
                                        foreach ($instrClasses as $cls):
                                    ?>
                                            <div class="flex justify-between w-full mt-1 text-sm">
                                                <div class="w-1/4">&nbsp;</div> <!-- empty for name column -->
                                                <div class="w-1/4 text-center"><?= htmlspecialchars($cls['room_name']) ?></div>
                                                <div class="w-1/4"><?= htmlspecialchars($cls['section_name'] . ' / ' . $cls['subject_code']) ?></div>
                                                <div class="w-1/4 text-center">
                                                    <?= date('g:i A', strtotime($cls['schedule_start'])) ?> -
                                                    <?= date('g:i A', strtotime($cls['schedule_end'])) ?>
                                                </div>
                                            </div>
                                        <?php
                                        endforeach;
                                    else:
                                        ?>
                                        <div class="flex justify-between w-full mt-1 text-sm">
                                            <div class="w-1/4">&nbsp;</div> <!-- empty for name column -->
                                            <div class="w-1/4 text-center italic text-gray-500">-</div> <!-- room column -->
                                            <div class="w-1/4 text-center italic text-gray-500">No classes assigned</div> <!-- class column -->
                                            <div class="w-1/4 text-center">&nbsp;</div> <!-- schedule column -->
                                        </div>
                                    <?php endif; ?>

                                </li>
                            <?php endforeach; ?>

                        <?php endif; ?>
                    </ul>
                </div>
            </section>


        </section>


    </main>




    <script src="/assets/js/clock.js"></script>
    <script src="/assets/js/logout.js"></script>
    <script src="/assets/js/admin_changepassword.js"></script>

</body>

</html>