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
            echo "<p style='color:green;'>Registration Successful!</p>";
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
    <title>Registration Page</title>
</head>

<body>
    <form method="post">
        <input type="text" name="full_name" placeholder="Enter Name" required><br>
        <input type="email" name="email" placeholder="Enter Email" required><br>
        <input type="password" name="password_hash" placeholder="Enter Password" required><br>
        <select name="role" required>
            <option value="instructor" selected>Instructor</option>
        </select><br>

        <?php if ($department_id): ?>
            <input type="hidden" name="department_id" value="<?= htmlspecialchars($department_id) ?>">
            <p>Assigning to Department ID: <strong><?= htmlspecialchars($department_id) ?></strong></p>
        <?php endif; ?>

        <button name="register">Register</button>
        <a href="admin_management.php">Back</a>
    </form>

</body>

</html>