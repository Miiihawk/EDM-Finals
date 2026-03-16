<?php
require_once '../backend/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = $_POST['first_name'];   
    $last_name = $_POST['last_name']; 
    // $role = mysqli_real_escape_string($conn, $_POST['role']);
    $role = 'user'; // Default role is user, you can change this based on your requirements
    
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $check_query = "SELECT * FROM users WHERE username = '$username'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error = 'Username already exists';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users (username, password, first_name, last_name, role) VALUES ('$username', '$hashed_password', '$first_name', '$last_name', '$role')";
            
            if (mysqli_query($conn, $insert_query)) {
                $success = 'Account created successfully! <a href="login.php">Login now</a>';
            } else {
                $error = 'Error creating account';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link rel="icon" type="image/x-icon" href="images/logo.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="m-0 flex min-h-screen items-center justify-center bg-[#00594c] font-[Segoe_UI,Tahoma,Geneva,Verdana,sans-serif]">
    <div class="w-full max-w-[400px] p-5">
        <div class="rounded-xl bg-white p-10 shadow-[0_10px_40px_rgba(0,0,0,0.2)]">
            <div class="mb-5 text-center">
                <img src="images/logo.jpg" alt="Logo" class="mx-auto h-16 w-16 rounded-lg object-cover">
            </div>
            <h2 class="mb-[30px] text-center text-2xl font-semibold text-[#333]">Create Account</h2>
            
            <?php if ($error): ?>
                <div class="mb-5 rounded-md border-l-4 border-[#c33] bg-[#fee] px-3 py-3 text-sm text-[#c33]"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="mb-5 rounded-md border-l-4 border-[#3c3] bg-[#efe] px-3 py-3 text-sm text-[#3c3]"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-5">
                    <label for="username" class="mb-2 block text-sm font-medium text-[#555]">Username</label>
                    <input type="text" id="username" name="username" required class="w-full rounded-md border border-[#ddd] px-3 py-3 text-sm focus:border-[#667eea] focus:outline-none">
                </div>

                 <div class="mb-5">
                    <label for="first_name" class="mb-2 block text-sm font-medium text-[#555]">First Name</label>
                    <input type="text" id="first_name" name="first_name" required class="w-full rounded-md border border-[#ddd] px-3 py-3 text-sm focus:border-[#667eea] focus:outline-none">
                </div>

                 <div class="mb-5">
                    <label for="last_name" class="mb-2 block text-sm font-medium text-[#555]">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required class="w-full rounded-md border border-[#ddd] px-3 py-3 text-sm focus:border-[#667eea] focus:outline-none">
                </div>
                
                <div class="mb-5">
                    <label for="password" class="mb-2 block text-sm font-medium text-[#555]">Password</label>
                    <input type="password" id="password" name="password" required class="w-full rounded-md border border-[#ddd] px-3 py-3 text-sm focus:border-[#667eea] focus:outline-none">
                </div>
                
                <div class="mb-5">
                    <label for="confirm_password" class="mb-2 block text-sm font-medium text-[#555]">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required class="w-full rounded-md border border-[#ddd] px-3 py-3 text-sm focus:border-[#667eea] focus:outline-none">
                </div>
                
                <!-- <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div> -->
                
                <button type="submit" class="w-full rounded-md px-4 py-[14px] text-base font-semibold text-white transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-[0_4px_12px_rgba(102,126,234,0.4)]" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">Sign Up</button>
            </form>
            
            <p class="mt-5 text-center text-sm text-[#777]">Already have an account? <a href="login.php" class="font-semibold text-[#667eea] hover:underline">Login</a></p>
        </div>
    </div>
</body>
</html>
