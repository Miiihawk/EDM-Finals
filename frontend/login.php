<?php
require_once '../backend/config.php';
require_once '../backend/create_accounts.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'User not found';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="icon" type="image/x-icon" href="images/logo.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="m-0 flex min-h-screen items-center justify-center bg-[#00594c] font-[Segoe_UI,Tahoma,Geneva,Verdana,sans-serif]">
    <div class="w-full max-w-[400px] p-5">
        <div class="rounded-xl bg-white p-10 shadow-[0_10px_40px_rgba(0,0,0,0.2)]">
            <div class="mb-5 text-center">
                <img src="images/logo.jpg" alt="Logo" class="mx-auto h-16 w-16 rounded-lg object-cover">
            </div>
            <h2 class="mb-[30px] text-center text-2xl font-semibold text-[#333]">Login</h2>
            
            <?php if ($error): ?>
                <div class="mb-5 rounded-md border-l-4 border-[#c33] bg-[#fee] px-3 py-3 text-sm text-[#c33]"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-5">
                    <label for="username" class="mb-2 block text-sm font-medium text-[#555]">Username</label>
                    <input type="text" id="username" name="username" required class="w-full rounded-md border border-[#ddd] px-3 py-3 text-sm focus:border-[#667eea] focus:outline-none">
                </div>
                
                <div class="mb-5">
                    <label for="password" class="mb-2 block text-sm font-medium text-[#555]">Password</label>
                    <input type="password" id="password" name="password" required class="w-full rounded-md border border-[#ddd] px-3 py-3 text-sm focus:border-[#667eea] focus:outline-none">
                </div>
                
                <button type="submit" class="w-full rounded-md px-4 py-[14px] text-base font-semibold text-white transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-[0_4px_12px_rgba(102,126,234,0.4)]" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">Login</button>
            </form>
            
            <p class="mt-5 text-center text-sm text-[#777]">Don't have an account? <a href="signup.php" class="font-semibold text-[#667eea] hover:underline">Sign up</a></p>
        </div>
    </div>
</body>
</html>
