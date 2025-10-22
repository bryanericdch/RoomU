<?php
session_start();
include '../database/roomdb.php';

// --- Fetch all classes first ---
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
    LEFT JOIN checkins ci ON ci.class_id = c.class_id AND ci.status = 'active'
    LEFT JOIN sections s ON c.section_id = s.section_id
    LEFT JOIN subjects sub ON c.subject_id = sub.subject_id
    LEFT JOIN users u ON c.instructor_id = u.user_id
");

$classesByRoom = [];
while ($row = $classesResult->fetch_assoc()) {
    if ($row['room_id']) {
        $classesByRoom[$row['room_id']][] = [
            'section_name' => $row['section_name'] ?? 'Section N/A',
            'subject_code' => $row['subject_code'] ?? 'N/A',
            'instructor_name' => $row['instructor_name'] ?? 'Instructor N/A',
            'schedule_start' => $row['schedule_start'],
            'schedule_end' => $row['schedule_end'],
        ];
    }
}

// --- Fetch rooms grouped by building ---
$roomsResult = $conn->query("SELECT * FROM rooms ORDER BY room_name");
$roomsByBuilding = [];
while ($row = $roomsResult->fetch_assoc()) {
    $roomsByBuilding[$row['building_id']][] = $row;
}

// --- Fetch buildings with room status counts ---
$buildings = [];
$result = $conn->query("
    SELECT b.building_id, b.name
    FROM buildings b
    ORDER BY b.name
");

date_default_timezone_set('Asia/Manila');
$currentTime = date('H:i:s');

$updatedBuildings = [];
while ($row = $result->fetch_assoc()) {
    $roomsResult = $conn->query("SELECT * FROM rooms WHERE building_id = {$row['building_id']} ORDER BY room_name");
    $rooms = $roomsResult->fetch_all(MYSQLI_ASSOC);

    foreach ($rooms as &$room) {
        $roomId = $room['room_id'];
        $status = 'available'; // default status

        // Check if any class is ongoing in this room
        if (!empty($classesByRoom[$roomId])) {
            foreach ($classesByRoom[$roomId] as $class) {
                if ($currentTime >= $class['schedule_start'] && $currentTime < $class['schedule_end']) {
                    $status = 'occupied';
                    break;
                }
            }
        }

        // Update the database only if status has changed
        if ($room['status'] !== $status) {
            $conn->query("UPDATE rooms SET status = '$status' WHERE room_id = $roomId");
        }

        $room['status'] = $status;
    }


    unset($room);

    $available = $occupied = $maintenance = 0;
    foreach ($rooms as $room) {
        if ($room['status'] === 'available') $available++;
        elseif ($room['status'] === 'occupied') $occupied++;
        elseif ($room['status'] === 'maintenance') $maintenance++;
    }

    $row['rooms'] = $rooms;
    $row['available'] = $available;
    $row['occupied'] = $occupied;
    $row['maintenance'] = $maintenance;

    $updatedBuildings[] = $row;
}

$buildings = $updatedBuildings;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/output.css">
    <title>RoomU | Student</title>
</head>

<body class="h-screen flex flex-col">

    <header class="bg-roomu-green h-[80px] w-full flex justify-between relative flex-none">
        <!-- Logo -->
        <section class="w-[190px] h-full flex items-center justify-center ml-3">
            <img src="/assets/img/phinma_white.png">
        </section>

        <!-- Date and Time -->
        <section class="absolute left-89 w-[300px] h-full flex items-center justify-between">
            <div class="text-roomu-white font-bold text-[36px]" id="current-time">11:11 AM</div>
            <div class="text-roomu-white flex flex-col font-bold text-[16px]">
                <div id="current-day">Sunday</div>
                <div id="current-date">January 07, 2025</div>
            </div>
        </section>

        <!-- Profile -->
        <section id="profile-btn" class="flex items-center justify-between mr-[30px] w-[160px] cursor-pointer select-none">
            <div class="text-roomu-white text-base font-normal">Student</div>
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
                    <div class="font-medium text-roomu-black">Student</div>
                    <div id="account-email" class="text-sm text-gray-500">Student@example.com</div>
                </div>
                <div class="flex flex-col gap-3 mt-4">
                    <a id="logout-btn" href="/login.php" class="w-full bg-red-500 text-white py-2 px-3 rounded-md hover:opacity-90">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <section class="flex flex-1 overflow-hidden">
        <!--Side Navigation-->
        <nav class="w-[200px] h-full flex-none flex flex-col justify-around items-end ">
            <div>
                <a href="#" class="nav-item bg-roomu-green text-roomu-white">
                    <div class="nav-icon"><img src="/assets/icons/dashboard_icon.png"></div>
                    <p>Dashboard</p>
                </a>
            </div>
            <img src="/assets/img/upang.png" class="w-auto ">
        </nav>

        <!--Main Content-->
        <main class="flex w-full h-full justify-center">
            <!--Buildings Section-->
            <section class="flex w-[40%] h-full items-center justify-center">
                <div id="buildings-section" class="shadow-container rounded-[10px] w-[90%] h-[80%] flex items-stretch justify-start p-[20px]">
                    <ul class="space-y-3 w-full" role="list">
                        <?php foreach ($buildings as $b): ?>
                            <li data-building-id="<?= $b['building_id'] ?>" data-action="select-building"
                                class="p-3 bg-gray-50 rounded-md hover:bg-hover-roomu-green cursor-pointer flex justify-between items-center w-full transition duration-200 ease-in-out">
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

            <!-- Sidebar -->
            <div id="details-sidebar" class="fixed top-0 right-0 h-full w-[400px] bg-body shadow-xl transform translate-x-full transition-transform duration-300 ease-in-out z-50">
                <div class="p-6 flex flex-col h-full">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-roomu-black">Room Details</h2>
                        <button id="close-sidebar" class="text-gray-500 hover:text-roomu-green text-2xl font-bold cursor-pointer">&times;</button>
                    </div>
                    <div id="sidebar-content" class="overflow-y-auto flex-1 space-y-4 text-roomu-black">
                        <p class="italic text-roomu-black">Select a room to view details.</p>
                    </div>
                </div>
            </div>
            <div id="sidebar-overlay" class="fixed inset-0 bg-roomu-black opacity-25 hidden z-40"></div>
        </main>
    </section>

    <script>
        const buildingsData = [
            <?php foreach ($buildings as $b): ?> {
                    building_id: <?= $b['building_id'] ?>,
                    name: "<?= addslashes($b['name']) ?>",
                    available: <?= $b['available'] ?>,
                    occupied: <?= $b['occupied'] ?>,
                    maintenance: <?= $b['maintenance'] ?>,
                    rooms: [
                        <?php foreach ($b['rooms'] as $r): ?> {
                                room_id: <?= $r['room_id'] ?>,
                                room_name: "<?= addslashes($r['room_name']) ?>",
                                status: "<?= $r['status'] ?>"
                            },
                        <?php endforeach; ?>
                    ]
                },
            <?php endforeach; ?>
        ];

        const classesByRoom = <?= json_encode($classesByRoom) ?>;
    </script>

    <script src="/assets/js/clock.js"></script>
    <script src="/assets/js/logout.js"></script>
    <script src="/assets/js/student_dashboard.js"></script>
</body>

</html>