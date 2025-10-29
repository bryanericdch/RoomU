<?php
session_start();
include '../database/roomdb.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('location: /logout.php');
    exit();
}
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

$id = intval($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode'])) {

    $mode = $_POST['mode'];
    $type = $_POST['type'] ?? '';

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


    // --- INSTRUCTORS CRUD ---
    if ($mode === 'fetch_instructors' && isset($_POST['department_id'])) {
        $department_id = intval($_POST['department_id']);
        $stmt = $conn->prepare("SELECT user_id, full_name, email FROM users WHERE role='instructor' AND department_id = ?");
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'items' => $users]);
        exit();
    }

    // --- TOGGLE MAINTENANCE MODE ---
    if ($mode === 'maintenance_room' && $id) {
        $stmt = $conn->prepare("SELECT status FROM rooms WHERE room_id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $newStatus = ($row['status'] === 'maintenance') ? 'available' : 'maintenance';

            $stmtUpdate = $conn->prepare("UPDATE rooms SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE room_id = ?");
            $stmtUpdate->bind_param("si", $newStatus, $id);
            $stmtUpdate->execute();

            echo json_encode(['success' => true, 'status' => $newStatus]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Room not found']);
        }
        exit();
    }

    if ($mode === 'edit_instructor' && isset($_POST['full_name'], $_POST['id'])) {
        $full_name = trim($_POST['full_name']);
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $stmt->bind_param("si", $full_name, $id);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows > 0]);
        exit();
    }

    if ($mode === 'delete_instructor' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role='instructor'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows > 0]);
        exit();
    }

    // --- ROOMS CRUD ---
    if (in_array($mode, ['fetch_rooms', 'add_room', 'edit_room', 'delete_room'])) {
        $building_id = intval($_POST['building_id'] ?? 0);
        $room_id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($mode === 'fetch_rooms' && $building_id) {
            $stmt = $conn->prepare("SELECT room_id AS id, room_name AS name, status FROM rooms WHERE building_id = ?");
            $stmt->bind_param("i", $building_id);
            $stmt->execute();
            $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'items' => $rooms]);
            exit();
        }

        if ($mode === 'add_room' && $building_id && $name) {
            $stmt = $conn->prepare("INSERT INTO rooms (building_id, room_name, status) VALUES (?, ?, 'available')");
            $stmt->bind_param("is", $building_id, $name);
            $stmt->execute();
            echo json_encode(['success' => $stmt->affected_rows > 0]);
            exit();
        }

        if ($mode === 'edit_room' && $room_id && $name) {
            $stmt = $conn->prepare("UPDATE rooms SET room_name = ?, updated_at = CURRENT_TIMESTAMP WHERE room_id = ?");
            $stmt->bind_param("si", $name, $room_id);
            $stmt->execute();
            echo json_encode(['success' => $stmt->affected_rows > 0]);
            exit();
        }

        if ($mode === 'delete_room' && $room_id) {
            $stmt = $conn->prepare("DELETE FROM rooms WHERE room_id = ?");
            $stmt->bind_param("i", $room_id);
            $stmt->execute();
            echo json_encode(['success' => $stmt->affected_rows > 0]);
            exit();
        }
    }

    // --- ADD or EDIT Building/Department ---
    if ($mode === 'add' || $mode === 'edit') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Name required']);
            exit;
        }

        if ($type === 'buildings') {
            if ($mode === 'add') {
                $stmt = $conn->prepare("INSERT INTO buildings (name) VALUES (?)");
                $stmt->bind_param("s", $name);
                $ok = $stmt->execute();
                echo json_encode(['success' => $ok, 'message' => $ok ? 'Building added successfully' : 'Failed to add building']);
                exit;
            } elseif ($mode === 'edit') {
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE buildings SET name=? WHERE building_id=?");
                $stmt->bind_param("si", $name, $id);
                $ok = $stmt->execute();
                echo json_encode(['success' => $ok, 'message' => $ok ? 'Building updated successfully' : 'Failed to update building']);
                exit;
            }
        }

        if ($type === 'departments') {
            if ($mode === 'add') {
                $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
                $stmt->bind_param("s", $name);
                $ok = $stmt->execute();
                echo json_encode(['success' => $ok, 'message' => $ok ? 'Department added successfully' : 'Failed to add department']);
                exit;
            } elseif ($mode === 'edit') {
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE departments SET name=? WHERE department_id=?");
                $stmt->bind_param("si", $name, $id);
                $ok = $stmt->execute();
                echo json_encode(['success' => $ok, 'message' => $ok ? 'Department updated successfully' : 'Failed to update department']);
                exit;
            }
        }
    }

    // --- FETCH List ---
    if ($mode === 'fetch') {
        if ($type === 'buildings') {
            $res = $conn->query("SELECT building_id AS id, name FROM buildings ORDER BY name");
            $items = $res->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'items' => $items]);
            exit;
        }

        if ($type === 'departments') {
            $res = $conn->query("SELECT department_id AS id, name FROM departments ORDER BY name");
            $items = $res->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'items' => $items]);
            exit;
        }
    }

    // --- DELETE ---
    if ($mode === 'delete') {
        $id = intval($_POST['id']);
        if ($type === 'buildings') {
            $ok = $conn->query("DELETE FROM buildings WHERE building_id = $id");
            echo json_encode(['success' => $ok]);
            exit;
        }
        if ($type === 'departments') {
            $ok = $conn->query("DELETE FROM departments WHERE department_id = $id");
            echo json_encode(['success' => $ok]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// --- Fetch admin info ---
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$full_name = $user['full_name'];
$email = $user['email'];

// Fetch buildings & departments
$buildings = $conn->query("SELECT building_id, name FROM buildings ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$departments = $conn->query("SELECT department_id, name FROM departments ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// --- Auto-update Room Status based on current time ---
date_default_timezone_set('Asia/Manila');
$currentTime = date("H:i:s");

$rooms = $conn->query("SELECT room_id, status FROM rooms");

while ($room = $rooms->fetch_assoc()) {
    $roomId = $room['room_id'];

    if ($room['status'] === 'maintenance') continue;

    $stmt = $conn->prepare("
        SELECT c.schedule_start, c.schedule_end
        FROM checkins ch
        INNER JOIN classes c ON c.class_id = ch.class_id
        WHERE ch.room_id = ? AND ch.status = 'active'
    ");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();

    $isOccupied = false;

    while ($class = $result->fetch_assoc()) {
        $startTime = strtotime($class['schedule_start']);
        $endTime   = strtotime($class['schedule_end']);
        $current   = strtotime($currentTime);

        if ($current >= $startTime && $current <= $endTime) {
            $isOccupied = true;
            break;
        }
    }

    $newStatus = $isOccupied ? 'occupied' : 'available';

    if ($room['status'] !== $newStatus) {
        $stmtUpdate = $conn->prepare("UPDATE rooms SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE room_id = ?");
        $stmtUpdate->bind_param("si", $newStatus, $roomId);
        $stmtUpdate->execute();
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
                        class="w-full bg-red-500 text-white py-2 px-3 rounded-md hover:opacity-90 text-center">Logout</a>
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

                <a href="/admin/admin_instructors.php" class="nav-item">
                    <div class="nav-icon"><img src="/assets/icons/instructors_icon.svg" class="w-[24px] h-[24px]"></div>
                    <p>Instructors</p>
                </a>

                <a href="/admin/admin_management.php" class="nav-item bg-roomu-green text-roomu-white">
                    <div class="nav-icon"><img src="/assets/icons/management_icon.svg" class="w-[24px] h-[24px]"></div>
                    <p>Management</p>
                </a>
            </div>

            <img src="/assets/img/upang.png" class="w-auto ">
        </nav>

        <!--Main Content-->
        <section class="flex flex-col items-center gap-6 p-6 w-full h-full">

            <!--Selection: Building/Department-->
            <section class="bg-roomu-white shadow-md rounded-lg  p-4 flex justify-between items-center">
                <div class="flex gap-4">
                    <button id="btn-buildings"
                        class="tab-btn active-tab px-4 py-2 rounded-md font-semibold text-roomu-green border border-roomu-green hover:bg-roomu-green hover:text-white transition cursor-pointer">Buildings</button>
                    <button id="btn-departments"
                        class="tab-btn px-4 py-2 rounded-md font-semibold text-roomu-green border border-roomu-green hover:bg-roomu-green hover:text-white transition cursor-pointer">Departments</button>
                    <button id="add-btn"
                        class="bg-roomu-green text-white px-4 py-2 rounded-md hover:bg-hover-roomu-green transition cursor-pointer">
                        + Add New
                    </button>
                </div>
            </section>

            <div class="flex gap-6">
                <!--List Buildings/Departments-->
                <section
                    class="flex flex-col bg-roomu-white shadow-md rounded-lg p-4 w-[400px] h-[60vh] overflow-y-auto">
                    <h2 id="list-title" class="text-lg font-semibold mb-3">Buildings</h2>

                    <ul id="list-container" class="space-y-2">
                        <?php foreach ($buildings as $b): ?>
                            <li data-id="<?php echo $b['building_id']; ?>" class="p-3 border rounded-md hover:bg-hover-roomu-green cursor-pointer flex justify-between items-center">
                                <span><?php echo htmlspecialchars($b['name']); ?></span>
                                <div class="flex gap-2">
                                    <button class="edit-btn text-blue-500 hover:underline">Edit</button>
                                    <button class="remove-btn text-red-500 hover:underline">Remove</button>
                                </div>
                            </li>

                        <?php endforeach; ?>


                        <!-- Sample building items 
                        <li
                            class="p-3 border rounded-md hover:bg-gray-50 cursor-pointer transition flex justify-between items-center">
                            <span>PTC</span>
                            <div class="flex gap-2">
                                <button class="text-blue-500 hover:underline">Edit</button>
                                <button class="text-red-500 hover:underline">Remove</button>
                            </div>
                        </li>
                        -->
                    </ul>
                </section>



                <!-- List Rooms/Instructors -->
                <section class="bg-roomu-white shadow-md rounded-lg p-4 w-[500px] h-[60vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-3">
                        <h2 id="details-title" class="text-lg font-semibold">Select A Building</h2>
                        <button id="add-sub-btn"
                            class="bg-roomu-green text-white px-4 py-2 rounded-md hover:bg-hover-roomu-green transition cursor-pointer">
                            + Add Room
                        </button>
                    </div>

                    <ul id="details-container" class="space-y-2">
                        <!-- Rooms will be dynamically inserted here by JavaScript -->
                    </ul>
                </section>

            </div>

            <!--Modal-->
            <section id="modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                <div class="bg-roomu-white rounded-lg p-6 w-[400px] relative shadow-lg">
                    <h2 id="modal-title" class="text-lg font-semibold mb-4">Add New Building</h2>

                    <!--Form-->
                    <form id="modal-form" class="flex flex-col gap-3">
                        <input type="text" name="name" placeholder="Building Name" class="border p-2 rounded-md"
                            required>

                        <div class="flex justify-end gap-2 mt-4">
                            <button type="button" id="cancel-btn"
                                class="border px-3 py-1 rounded-md hover:bg-gray-100 transition cursor-pointer">Cancel</button>
                            <button type="submit"
                                class="bg-roomu-green text-white px-3 py-1 rounded-md hover:bg-hover-roomu-green transition cursor-pointer">Save</button>
                        </div>
                    </form>
                    <button id="close-modal"
                        class="absolute top-2 right-3 text-gray-500 hover:text-gray-800">&times;</button>

                </div>
            </section>

        </section>


    </main>



    <script src="/assets/js/clock.js"></script>
    <script src="/assets/js/logout.js"></script>
    <script src="/assets/js/admin_management1.js"></script>
    <script src="/assets/js/admin_changepassword.js"></script>



</body>

</html>