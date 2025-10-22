<?php
session_start();
include 'database/roomdb.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);

    if (empty($email) || empty($new_password)) {
        $message = 'Please fill in all fields.';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Hash the new password
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password
            $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $update->bind_param("ss", $hashed, $email);

            if ($update->execute()) {
                $message = '✅ Password has been updated successfully.';
            } else {
                $message = '❌ Failed to update password. Please try again.';
            }

            $update->close();
        } else {
            $message = '⚠️ Email not found in our records.';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white shadow-md rounded-lg p-8 w-full max-w-md">
        <h2 class="text-2xl font-semibold text-center mb-6 text-gray-700">Forgot Password</h2>

        <?php if ($message): ?>
            <div class="mb-4 text-center text-sm text-gray-700">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label for="email" class="block text-gray-600">Email</label>
                <input type="email" id="email" name="email" required
                    class="w-full p-2 border rounded-md focus:ring focus:ring-green-200">
            </div>

            <div>
                <label for="new_password" class="block text-gray-600">New Password</label>
                <input type="password" id="new_password" name="new_password" required
                    class="w-full p-2 border rounded-md focus:ring focus:ring-green-200">
            </div>

            <button type="submit"
                class="w-full bg-green-600 text-white py-2 rounded-md hover:bg-green-700 transition">
                Reset Password
            </button>

            <div class="text-center mt-4">
                <a href="login.php" class="text-blue-500 hover:underline text-sm">Back to Login</a>
            </div>
        </form>
    </div>

</body>

</html>