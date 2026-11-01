<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ডেটাবেজ কানেকশন রিকোয়ার করুন (যদি header.php এর আগেই কানেকশন প্রয়োজন হয়)
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /online-shopping-management/index.php');
    exit;
}

$errors = [];
$email = '';

// ১. সবার আগে পোস্ট রিকোয়েস্ট ও লগইন প্রসেস হ্যান্ডেল করুন
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $currentLang = $_SESSION['lang'] ?? 'en';

    if (empty($email)) {
        $errors[] = ($currentLang === 'bn') ? 'ইমেইল অ্যাড্রেস প্রদান করুন।' : 'Please enter your email address.';
    }
    if (empty($password)) {
        $errors[] = ($currentLang === 'bn') ? 'পাসওয়ার্ড প্রদান করুন।' : 'Please enter your password.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            SELECT u.id, u.name, u.nickname as user_nickname, u.email, u.password, u.role, c.gender, c.profile_image, c.nickname as cust_nickname
            FROM users u 
            LEFT JOIN customers c ON u.id = c.user_id 
            WHERE u.email = ? LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($user = $res->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    // হেডার পাঠানোর আগে সেশন রি জেনারেট ও রিডাইরেক্ট করা হলো
                    session_regenerate_id(true);
                    
                    $nickname = !empty($user['cust_nickname']) ? $user['cust_nickname'] : (!empty($user['user_nickname']) ? $user['user_nickname'] : $user['name']);

                    $_SESSION['user_id']       = $user['id'];
                    $_SESSION['user_name']     = $user['name'];
                    $_SESSION['user_nickname'] = $nickname;
                    $_SESSION['user_email']    = $user['email'];
                    $_SESSION['user_role']     = $user['role'] ?? 'customer';
                    $_SESSION['user_gender']   = $user['gender'] ?? 'male';
                    $_SESSION['user_image']    = $user['profile_image'] ?? null;

                    header('Location: /online-shopping-management/index.php');
                    exit;
                } else {
                    $errors[] = ($currentLang === 'bn') ? 'ইমেইল বা পাসওয়ার্ড সঠিক নয়।' : 'Invalid email or password.';
                }
            } else {
                $errors[] = ($currentLang === 'bn') ? 'কোনো অ্যাকাউন্ট পাওয়া যায়নি।' : 'No account found with this email.';
            }
            $stmt->close();
        }
    }
}

// ২. সমস্ত প্রসেসিং শেষ হওয়ার পর এখন হেডার ইনক্লুড করুন
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[82vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-50/50">
    <div class="w-full max-w-md space-y-8 bg-white p-8 md:p-10 rounded-3xl border border-slate-200/80 shadow-xl shadow-slate-200/50">
        
        <!-- Header / Logo -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-slate-900 via-slate-800 to-emerald-900 text-emerald-400 shadow-lg shadow-emerald-950/20 mb-1">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 2l2.4 5.2 5.6.8-4 4.1.9 5.7-4.9-2.6-4.9 2.6.9-5.7-4-4.1 5.6-.8L12 2z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-black tracking-tight text-slate-900 uppercase">
                NOVERA<span class="text-emerald-600">.</span>
            </h2>
            <p class="text-xs text-slate-500">
                <?= $currentLang === 'bn' ? 'আপনার অ্যাকাউন্টে লগইন করুন' : 'Sign in to access your orders and account settings' ?>
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1.5">
                <?php foreach ($errors as $err): ?>
                    <p class="flex items-center gap-2 font-medium">
                        <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?= e($err) ?>
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-5" action="" method="POST">
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    <?= $currentLang === 'bn' ? 'ইমেইল অ্যাড্রেস' : 'Email Address' ?>
                </label>
                <input type="email" name="email" required value="<?= e($email) ?>" placeholder="saif@example.com"
                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    <?= $currentLang === 'bn' ? 'পাসওয়ার্ড' : 'Password' ?>
                </label>
                <input type="password" name="password" required placeholder="••••••••"
                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
            </div>

            <button type="submit" class="w-full py-3.5 px-4 bg-slate-950 hover:bg-slate-850 text-white text-xs font-bold rounded-2xl shadow-lg transition transform active:scale-[0.99] flex items-center justify-center gap-2">
                <span><?= $currentLang === 'bn' ? 'লগইন করুন' : 'Sign In' ?></span>
                <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </form>

        <div class="text-center pt-2 border-t border-slate-100">
            <p class="text-xs text-slate-500">
                <?= $currentLang === 'bn' ? 'নতুন অ্যাকাউন্ট খুলতে চান?' : "Don't have an account?" ?>
                <a href="/online-shopping-management/register.php" class="font-bold text-emerald-600 hover:text-emerald-700 transition ml-1">
                    <?= $currentLang === 'bn' ? 'রেজিস্টার করুন' : 'Register now' ?>
                </a>
            </p>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>