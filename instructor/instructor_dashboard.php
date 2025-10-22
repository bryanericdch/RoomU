<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('location: /logout.php');
    exit();
}
include '../database/roomdb.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode'])) {

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
}

// ===========================
// Fetch buildings with room status counts
// ===========================
$buildings = [];
$result = $conn->query("
    SELECT b.building_id, b.name,
           SUM(CASE WHEN r.status = 'available' THEN 1 ELSE 0 END) AS available,
           SUM(CASE WHEN r.status = 'occupied' THEN 1 ELSE 0 END) AS occupied,
           SUM(CASE WHEN r.status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance
    FROM buildings b
    LEFT JOIN rooms r ON r.building_id = b.building_id
    GROUP BY b.building_id
    ORDER BY b.name
");

$roomsByBuilding = [];
while ($row = $result->fetch_assoc()) {
    $buildings[] = $row;

    // Fetch rooms for this building
    $roomsResult = $conn->query("SELECT * FROM rooms WHERE building_id = {$row['building_id']} ORDER BY room_name");
    $roomsByBuilding[$row['building_id']] = $roomsResult->fetch_all(MYSQLI_ASSOC);
}

// ===========================
// Fetch classes with section, subject_code, instructor info
// ===========================
$userId = $_SESSION['user_id']; // current instructor

$classesResult = $conn->query("
    SELECT 
        ci.room_id,
        c.class_id,
        s.section_name,
        sub.subject_code,
        u.full_name AS instructor_name,
        c.schedule_start,
        c.schedule_end
    FROM classes c
    INNER JOIN checkins ci ON ci.class_id = c.class_id AND ci.status = 'active'
    INNER JOIN sections s ON c.section_id = s.section_id
    INNER JOIN subjects sub ON c.subject_id = sub.subject_id
    INNER JOIN users u ON c.instructor_id = u.user_id
    WHERE ci.room_id IS NOT NULL
");

// Initialize classesByRoom array
$classesByRoom = [];
while ($row = $classesResult->fetch_assoc()) {
    if ($row['room_id']) { // only include if assigned to a room
        // Add to array instead of overwriting
        $classesByRoom[$row['room_id']][] = [
            'section_name' => $row['section_name'] ?? 'Section N/A',
            'subject_code' => $row['subject_code'] ?? 'N/A',
            'instructor_name' => $row['instructor_name'] ?? 'Instructor N/A',
            'schedule_start' => $row['schedule_start'],
            'schedule_end' => $row['schedule_end'],
        ];
    }
}

// --- Fetch instructor info ---
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$full_name = $user['full_name'];
$email = $user['email'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/output.css">
    <title>RoomU</title>
</head>

<body class="h-screen flex flex-col">

    <header class="bg-roomu-green h-[80px] w-full flex justify-between relative flex-none">
        <!--Logo-->
        <section class="w-[190px] h-full flex items-center justify-center ml-3">
            <img src="/assets/img/phinma_white.png">
        </section>

        <!--Date and Time-->
        <section class="absolute left-89 h-full flex items-center w-[300px] justify-between">
            <div class="text-roomu-white font-bold text-[36px] whitespace-nowrap" id="current-time">11:11 AM</div>
            <div class="text-roomu-white flex flex-col font-bold text-[16px]">
                <div id="current-day">Sunday</div>
                <div id="current-date">January 07, 2025</div>
            </div>
        </section>



        <!--Profile-->
        <section id="profile-btn" class="flex items-center justify-between mr-[30px] w-[160px] cursor-pointer select-none">
            <div class="text-roomu-white text-base font-normal"><?php echo ($full_name); ?></div>
            <div class="bg-roomu-white rounded-full w-[50px] h-[50px] flex center-div">
                <img class="w-[30px] h-[30px]" src="/assets/icons/admin_icon.svg" alt="profile">
            </div>
        </section>

        <div id="logout-bar" class="fixed top-0 right-0 h-full w-[400px] bg-body shadow-xl transform translate-x-full transition-transform duration-300 ease-in-out z-50">
            <div class="p-6 flex flex-col h-full">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-roomu-black">Account</h2>
                    <button id="close-logout" class="text-gray-500 hover:text-roomu-green text-2xl font-bold cursor-pointer">&times;</button>
                </div>

                <div class="mb-4">
                    <div class="font-medium text-roomu-black"><?php echo ($full_name); ?></div>
                    <div id="account-email" class="text-sm text-gray-500"><?php echo ($email); ?></div>
                </div>

                <div class="flex flex-col gap-3 mt-4">
                    <button id="open-change-password" class="w-full bg-roomu-green text-roomu-white py-2 px-3 rounded-md hover:bg-hover-roomu-green">Change Password</button>
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
            </div>
        </div>
    </header>

    <main class="flex flex-1 overflow-hidden">
        <!--Side Navigation-->
        <nav class="w-[200px] h-full flex-none flex flex-col justify-around items-end ">
            <div>
                <a href="/instructor/instructor_dashboard.php" class="nav-item bg-roomu-green text-roomu-white">
                    <div class="nav-icon"><img src="/assets/icons/dashboard_icon.png"></div>
                    <p>Dashboard</p>
                </a>
                <a href="/instructor/instructor_classes.php" class="nav-item">
                    <div class="nav-icon"><img src="/assets/icons/instructors_icon.svg" class="w-[24px] h-[24px]"></div>
                    <p>Classes</p>
                </a>
            </div>
            <img src="/assets/img/upang.png" class="w-auto ">
        </nav>

        <main class="flex w-full h-full justify-center">
            <!--Buildings Section-->
            <section class="flex w-[40%] h-full items-center justify-center">
                <div id="buildings-section" class="shadow-container rounded-[10px] w-[90%] h-[80%] flex items-stretch justify-start p-[20px]">
                    <ul class="space-y-3 w-full" role="list">
                        <?php foreach ($buildings as $b): ?>
                            <li data-building-id="<?= $b['building_id'] ?>" data-action="select-building" class="p-3 bg-gray-50 rounded-md hover:bg-hover-roomu-green cursor-pointer flex justify-between items-center w-full transition duration-200 ease-in-out">
                                <a href="#" class="text-lg font-medium text-roomu-black flex-1"><?= htmlspecialchars($b['name']) ?></a>
                                <div class="flex items-center space-x-2 ml-4">
                                    <div class="flex items-center"><span class="w-3 h-3 bg-green-500 rounded-full"></span><span class="ml-1 text-xs text-green-600 font-medium"><?= $b['available'] ?></span></div>
                                    <div class="flex items-center"><span class="w-3 h-3 bg-red-500 rounded-full"></span><span class="ml-1 text-xs text-red-600 font-medium"><?= $b['occupied'] ?></span></div>
                                    <div class="flex items-center"><span class="w-3 h-3 bg-yellow-500 rounded-full"></span><span class="ml-1 text-xs text-yellow-600 font-medium"><?= $b['maintenance'] ?></span></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>

            <!--Rooms Section-->
            <section class="w-[40%] h-full items-center justify-center flex flex-col">
                <div id="rooms-section" class="col-span-1 shadow-container rounded-lg p-6 w-full h-[80%] flex flex-col overflow-hidden">
                    <h2 id="rooms-heading" class="text-xl font-bold text-roomu-black mb-4">Rooms</h2>
                    <ul class="space-y-2 overflow-y-auto flex-1" role="list" id="rooms-list">
                        <li class="text-roomu-black italic">Select a building to view rooms</li>
                    </ul>
                </div>
            </section>
        </main>
    </main>

    <script>
        const buildingsData = [
            <?php foreach ($buildings as $b): ?> {
                    building_id: <?= $b['building_id'] ?>,
                    name: "<?= addslashes($b['name']) ?>",
                    available: <?= $b['available'] ?>,
                    occupied: <?= $b['occupied'] ?>,
                    maintenance: <?= $b['maintenance'] ?>,
                    rooms: [
                        <?php
                        if (!empty($roomsByBuilding[$b['building_id']])) {
                            foreach ($roomsByBuilding[$b['building_id']] as $r) {
                                echo "{ room_id: {$r['room_id']}, room_name: '" . addslashes($r['room_name']) . "', status: '{$r['status']}' },";
                            }
                        }
                        ?>
                    ]
                },
            <?php endforeach; ?>
        ];

        // Pass PHP classesByRoom array to JS
        const classesByRoom = <?= json_encode($classesByRoom) ?>;
    </script>


    <script src="/assets/js/clock.js"></script>
    <script src="/assets/js/instructor_dashboard.js"></script>
    <script src="/assets/js/logout.js"></script>
    <script src="/assets/js/instructor_checkin.js"></script>
    <script src="/assets/js/instructor_passwordchange.js"></script>

</body>

</html>