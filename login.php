<?php
session_start();
include 'database/roomdb.php';

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $check = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['role'] = $row['role'];

            if ($_SESSION['role'] === 'admin') {
                header('location: admin/admin_dashboard.php');
            } elseif ($_SESSION['role'] === 'instructor') {
                header('location: instructor/instructor_dashboard.php');
            }
            exit();
        }
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
    <title>RoomU</title>
</head>

<body class="flex h-screen">

    <!--Left Section-->
    <section class="login-image-section shadow-image-login">
        <img class="w-[18.75em] h-[13%] mt-[16px] ml-[16px]" src="/assets/img/phinma_white.png">
        <div class="login-roomu-logo">
            <p class=" text-roomu-white text-2xl font-extrabold m-0 select-none">RoomU <span
                    class="text-2xl font-medium">| Classroom
                    Availability and
                    Scheduling System</span></p>
        </div>
    </section>


    <!--Forms Section-->
    <section class="form h-screen">
        <div class=" w-[350px] mt-[10px]">
            <img src="/assets/img/upang.png">
        </div>


        <div class="flex flex-col w-[350px] h-full mt-[30px] items-center  relative">

            <div class="w-[90%] h-[75.5%]  absolute top-20">
                <form method="post" class="flex flex-col justify-between h-[200px]">

                    <div class="flex flex-col font-inter  text-roomu-black mb-[20px]">
                        <label class="font-semibold text-[24px] ml-[5px] select-none" for="email">Email</label>
                        <input
                            class="bg-roomu-white h-[54px] rounded-[15px] px-[20px] focus:outline-0 select-none shadow-input-box"
                            type="email" id="email" name="email" required>
                    </div>

                    <div class="flex flex-col font-inter  text-roomu-black">
                        <label class="font-semibold text-[24px] ml-[5px] select-none" for="password">Password</label>
                        <input class="bg-roomu-white h-[54px] rounded-[15px] px-[20px] focus:outline-0 shadow-input-box"
                            type="password" id="password" name="password" required>
                    </div>

                    <div class="flex justify-end font-inter text-roomu-black  text-[15px] mt-[5px]">
                        <a href="forgot_password.php"> Forgot password? </a>
                    </div>

                    <div class="w-auto flex justify-center mt-[30px] text-roomu-white font-semibold text-[30px]">
                        <input class="login-button" type="submit" value="Login" name="login">
                    </div>
                    <div class="w-auto flex justify-center mt-[30px] text-roomu-white font-semibold text-[30px]">
                        <a href="/student/student_dashboard.php" class="bg-roomu-black login-button">Login as Student</a>
                    </div>

                </form>
            </div>
        </div>
    </section>

</body>

</html>