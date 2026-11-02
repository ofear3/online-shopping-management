<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Isolated session name to prevent user session conflict
session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Direct hardcoded credentials check (admin / admin)
    if ($username === 'admin' && $password === 'admin') {
        session_regenerate_id(true);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = 'admin';

        header("Location: dashboard.php");
        exit();
    } else {
        $error = 'Invalid username or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="NOVERA Admin Login">
    <title>Admin Login - NOVERA Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-zinc-950 text-zinc-100 flex items-center justify-center p-4 font-sans antialiased">

    <div class="max-w-md w-full bg-zinc-900/90 border border-zinc-800 rounded-3xl p-8 shadow-2xl backdrop-blur-xl space-y-6">
        <div class="text-center space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-black flex items-center justify-center text-lg mx-auto shadow-inner">
                NV
            </div>
            <h1 class="text-2xl font-black text-white tracking-wider">NOVERA Admin</h1>
            <p class="text-xs text-zinc-400">Enter your credentials to access the administrative dashboard</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-bold p-3.5 rounded-2xl text-center shadow-lg">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-zinc-300 mb-1.5 uppercase tracking-wider">Username</label>
                <input type="text" name="username" required placeholder="admin"
                       class="w-full px-4 py-3 bg-zinc-950 border border-zinc-800 text-white rounded-2xl text-sm focus:outline-none focus:border-emerald-500 transition shadow-inner">
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-300 mb-1.5 uppercase tracking-wider">Password</label>
                <input type="password" name="password" required placeholder="••••••••"
                       class="w-full px-4 py-3 bg-zinc-950 border border-zinc-800 text-white rounded-2xl text-sm focus:outline-none focus:border-emerald-500 transition shadow-inner">
            </div>

            <button type="submit" 
                    class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold uppercase tracking-wider rounded-2xl transition shadow-lg shadow-emerald-600/20 active:scale-95 cursor-pointer">
                Sign In to Console
            </button>
        </form>
    </div>

</body>
</html>