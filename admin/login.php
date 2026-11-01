<?php
// ১. সেশন চালু না থাকলে সেশন স্টার্ট করা (বাধ্যতামূলক)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ইতিমধ্যে অ্যাডমিন হিসেবে লগইন করা থাকলে ড্যাশবোর্ডে পাঠাবে
if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'admin') {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        // ডাইরেক্ট হার্ডকোডেড চেক: কোনো ডাটাবেজ লাগবে না
        if ($email === 'admin' && $password === 'admin') {
            // সেশন ডাটা সেট করা (header.php এর সাথে সামঞ্জস্য রেখে)
            $_SESSION['user_id']   = "admin";
            $_SESSION['user_name'] = "admin";
            $_SESSION['username']  = "System Admin"; // header.php এ $userName পড়ার জন্য
            $_SESSION['user_role'] = 'admin';

            // ডানপাশের ওপরের সবুজ পপআপ বক্সে দেখানোর জন্য সেশন মেসেজ
            $_SESSION['toast_message'] = "System Admin Successfully Logged In";

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password! (Use admin / admin)";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - OnlineShop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-zinc-950 text-zinc-200 font-sans antialiased flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-zinc-900 border border-zinc-800/80 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
        
        <div class="text-center space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold flex items-center justify-center text-base mx-auto shadow-sm">
                OS
            </div>
            <h1 class="text-xl font-bold text-zinc-100 tracking-tight">Admin Portal Login</h1>
            <p class="text-xs text-zinc-400">Sign in to manage storefront operations</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-medium text-center">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off" class="space-y-4">
            
            <!-- Browser auto-fill popup bypass dummy fields -->
            <input type="text" name="fake_usernamenameremembered" class="hidden" style="display:none">
            <input type="password" name="fake_passwordremembered" class="hidden" style="display:none">

            <div>
                <label for="email" class="block text-xs font-semibold text-zinc-300 mb-1.5">Username / Email</label>
                <!-- type="email" থেকে পরিবর্তন করে type="text" করা হয়েছে যাতে শুধু admin লেখা যায় -->
                <input type="text" 
                       id="email" 
                       name="email" 
                       required 
                       autocomplete="off"
                       placeholder="admin"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-100 text-xs focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-zinc-300 mb-1.5">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       autocomplete="new-password"
                       placeholder="••••••••"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-zinc-950 border border-zinc-800 text-zinc-100 text-xs focus:outline-none focus:border-emerald-500/60 focus:ring-1 focus:ring-emerald-500/60 transition">
            </div>

            <button type="submit" 
                    class="w-full py-2.5 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-bold text-xs transition shadow-lg shadow-emerald-500/10 cursor-pointer">
                Sign In to Dashboard
            </button>
        </form>

        <div class="text-center pt-2 border-t border-zinc-800/60">
            <a href="../index.php" class="text-[11px] text-zinc-400 hover:text-zinc-200 transition">← Back to main storefront</a>
        </div>

    </div>

</body>
</html>