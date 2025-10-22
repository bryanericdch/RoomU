<?php
session_start();
include '../database/roomdb.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('location: /logout.php');
    exit();
}
// 🟩 Get department ID from URL if present
$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;

if (isset($_POST['register'])) {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password_hash'], PASSWORD_DEFAULT);
    $department_id = $_POST['department_id'] ?? null; // keep association from hidden field

    $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo "<p style='color:red;'>Email already exists.</p>";
    } else {
        // 🟦 Include department_id if it's for an instructor
        if ($role === 'instructor' && $department_id) {
            $reg = $conn->prepare("INSERT INTO users (full_name, email, role, password_hash, department_id) VALUES (?, ?, ?, ?, ?)");
            $reg->bind_param("ssssi", $name, $email, $role, $password, $department_id);
        } else {
            $reg = $conn->prepare("INSERT INTO users (full_name, email, role, password_hash) VALUES (?, ?, ?, ?)");
            $reg->bind_param("ssss", $name, $email, $role, $password);
        }

        if ($reg->execute()) {
            header("Location: admin_management.php?success=1");
            exit();
        } else {
            echo "<p style='color:red;'>Registration Failed.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/output.css">
    <title>Registration Page</title>
</head>

<body class="flex h-screen max-w-full items-center justify-center bg-roomu-green">
    <form method="post" class="w-[500px] rounded-md flex flex-col px-8 py-10 justify-between bg-roomu-white">
        <label for="name" class="font-semibold text-[24px] ml-[5px] select-none ">Name</label>
        <input type="text" name="full_name" id="name" required class="bg-white h-[54px] rounded-[15px] px-[20px] focus:outline-0 select-none shadow-input-box"><br>

        <label for="email" class="font-semibold text-[24px] ml-[5px] select-none ">Email</label>
        <input type="email" name="email" id="email" class="bg-white h-[54px] rounded-[15px] px-[20px] focus:outline-0 select-none shadow-input-box" required><br>

        <label for="pass" class="font-semibold text-[24px] ml-[5px] select-none ">Password</label>
        <input type="password" name="password_hash" id="pass" required class="bg-white h-[54px] rounded-[15px] px-[20px] focus:outline-0 select-none shadow-input-box"><br>
        <select name="role" required class="hidden">
            <option value="instructor" selected>Instructor</option>
        </select><br>

        <?php if ($department_id): ?>
            <input type="hidden" name="department_id" value="<?= htmlspecialchars($department_id) ?>">
            <p class="hidden">Assigning to Department ID: <strong><?= htmlspecialchars($department_id) ?></strong></p>
        <?php endif; ?>

        <button name="register" class="bg-roomu-green text-roomu-white text-2xl py-4 rounded-md cursor-pointer hover:bg-hover-roomu-green font-semibold">Register</button>
        <a href="admin_management.php" class="mt-3">Back</a>
    </form>
</body>

</html>