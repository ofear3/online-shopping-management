<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ভাষা প্রথমে ডিক্লেয়ার করে নেওয়া হলো যাতে পোস্ট রিকোয়েস্টে ওয়ার্নিং না আসে
$currentLang = $_SESSION['lang'] ?? 'en';

// চেক করা ইউজার লগইন করা আছে কিনা
if (!isset($_SESSION['user_id'])) {
    header('Location: /online-shopping-management/login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$userId = $_SESSION['user_id'];
$errors = [];
$successMessage = '';

// প্রোফাইল আপডেট ফর্ম সাবমিট হলে
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $gender   = $_POST['gender'] ?? 'male';

    if (empty($name)) {
        $errors[] = ($currentLang === 'bn') ? 'নাম খালি রাখা যাবে না।' : 'Name cannot be empty.';
    }

    if (empty($nickname)) {
        $nickname = explode(' ', $name)[0];
    }

    // নতুন প্রোফাইল ছবি আপলোড হ্যান্ডলিং
    $imagePath = $_SESSION['user_image'] ?? null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp    = $_FILES['profile_image']['tmp_name'];
        $fileName   = $_FILES['profile_image']['name'];
        $fileExt    = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($fileExt, $allowedExt, true)) {
            $newFileName = 'cust_' . time() . '_' . mt_rand(1000, 9999) . '.' . $fileExt;
            $uploadDir   = __DIR__ . '/uploads/';
            
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                $imagePath = 'uploads/' . $newFileName;
            }
        } else {
            $errors[] = ($currentLang === 'bn') ? 'শুধুমাত্র JPG, PNG বা WEBP ছবি আপলোড করা যাবে।' : 'Only JPG, PNG or WEBP images are allowed.';
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            // 1. Update users table
            $stmtUser = $conn->prepare("UPDATE users SET name = ?, nickname = ? WHERE id = ?");
            if (!$stmtUser) throw new Exception($conn->error);
            $stmtUser->bind_param("ssi", $name, $nickname, $userId);
            $stmtUser->execute();
            $stmtUser->close();

            // 2. Update or Insert customers table
            $chkCust = $conn->prepare("SELECT id FROM customers WHERE user_id = ? LIMIT 1");
            $chkCust->bind_param("i", $userId);
            $chkCust->execute();
            $custRes = $chkCust->get_result();
            $chkCust->close();

            if ($custRes->num_rows > 0) {
                $stmtCust = $conn->prepare("UPDATE customers SET name = ?, nickname = ?, phone = ?, address = ?, gender = ?, profile_image = ? WHERE user_id = ?");
                if (!$stmtCust) throw new Exception($conn->error);
                $stmtCust->bind_param("ssssssi", $name, $nickname, $phone, $address, $gender, $imagePath, $userId);
                $stmtCust->execute();
                $stmtCust->close();
            } else {
                $stmtCust = $conn->prepare("INSERT INTO customers (user_id, name, nickname, phone, address, gender, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if (!$stmtCust) throw new Exception($conn->error);
                $stmtCust->bind_param("issssss", $userId, $name, $nickname, $phone, $address, $gender, $imagePath);
                $stmtCust->execute();
                $stmtCust->close();
            }

            $conn->commit();

            // সেশন আপডেট করা যাতে হেডারে তাৎক্ষণিক পরিবর্তন দেখা যায়
            $_SESSION['user_name']     = $name;
            $_SESSION['user_nickname'] = $nickname;
            $_SESSION['user_gender']   = $gender;
            $_SESSION['user_image']    = $imagePath;

            $successMessage = ($currentLang === 'bn') ? 'প্রোফাইল সফলভাবে আপডেট করা হয়েছে!' : 'Profile updated successfully!';

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Update Error: " . $e->getMessage();
        }
    }
}

// ডাটাবেজ থেকে লেটেস্ট ইউজার ও কাস্টমার ডাটা ফেচ করা
$stmtData = $conn->prepare("
    SELECT u.name, u.nickname as user_nickname, u.email, c.phone, c.address, c.gender, c.profile_image, c.nickname as cust_nickname
    FROM users u 
    LEFT JOIN customers c ON u.id = c.user_id 
    WHERE u.id = ? LIMIT 1
");
$stmtData->bind_param("i", $userId);
$stmtData->execute();
$userData = $stmtData->get_result()->fetch_assoc();
$stmtData->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[85vh] py-12 px-4 sm:px-6 lg:px-8 bg-slate-50/50 flex items-center justify-center">
    <div class="w-full max-w-2xl bg-white p-8 sm:p-10 rounded-3xl border border-slate-200/80 shadow-xl shadow-slate-200/50 space-y-8">
        
        <!-- Header Profile Info -->
        <div class="flex flex-col sm:flex-row items-center gap-5 pb-6 border-b border-slate-100 text-center sm:text-left">
            <div class="relative w-20 h-20 rounded-2xl bg-slate-950 text-white font-bold flex items-center justify-center text-2xl shadow-md overflow-hidden border-2 border-slate-200 shrink-0">
                <?php if (!empty($userData['profile_image'])): ?>
                    <img src="/online-shopping-management/<?= e($userData['profile_image']) ?>" alt="Profile" class="w-full h-full object-cover">
                <?php else: ?>
                    <?php if (($userData['gender'] ?? 'male') === 'female'): ?>
                        <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <?php else: ?>
                        <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.654 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?php endif; ?>
                <?php endif; ?>
                <span class="absolute bottom-1 right-1 w-3.5 h-3.5 bg-emerald-500 border-2 border-white rounded-full"></span>
            </div>

            <div class="space-y-1">
                <h1 class="text-2xl font-black text-slate-900">
                    <?= e($userData['cust_nickname'] ?? $userData['user_nickname'] ?? $userData['name']) ?>
                </h1>
                <p class="text-xs font-medium text-slate-500"><?= e($userData['email']) ?></p>
                <span class="inline-block px-3 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-lg border border-emerald-200">
                    <?= $currentLang === 'bn' ? 'ভেরিফাইড কাস্টমার' : 'Verified Customer' ?>
                </span>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (!empty($successMessage)): ?>
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <?= e($successMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
                <?php foreach ($errors as $err): ?>
                    <p class="font-medium"><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Full Name -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'পূর্ণ নাম' : 'Full Name' ?>
                    </label>
                    <input type="text" name="name" required value="<?= e($userData['name'] ?? '') ?>"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                </div>

                <!-- Nickname -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'ডাকনাম (Nickname)' : 'Nickname (AI / Header)' ?>
                    </label>
                    <input type="text" name="nickname" value="<?= e($userData['cust_nickname'] ?? $userData['user_nickname'] ?? '') ?>"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Phone -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'ফোন নম্বর' : 'Phone Number' ?>
                    </label>
                    <input type="tel" name="phone" value="<?= e($userData['phone'] ?? '') ?>" placeholder="017xxxxxxxx"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                </div>

                <!-- Gender -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'জেন্ডার' : 'Gender' ?>
                    </label>
                    <select name="gender" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                        <option value="male" <?= ($userData['gender'] ?? '') === 'male' ? 'selected' : '' ?>><?= $currentLang === 'bn' ? 'পুরুষ (Male)' : 'Male' ?></option>
                        <option value="female" <?= ($userData['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= $currentLang === 'bn' ? 'মহিলা (Female)' : 'Female' ?></option>
                    </select>
                </div>
            </div>

            <!-- Profile Image Upload -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    <?= $currentLang === 'bn' ? 'প্রোফাইল ছবি পরিবর্তন করুন' : 'Update Profile Picture' ?>
                </label>
                <input type="file" name="profile_image" accept="image/*"
                       class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-950 file:text-white hover:file:bg-slate-800 transition">
            </div>

            <!-- Address -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    <?= $currentLang === 'bn' ? 'ডেলিভারি ঠিকানা (Address)' : 'Delivery Address' ?>
                </label>
                <textarea name="address" rows="3" placeholder="<?= $currentLang === 'bn' ? 'আপনার পূর্ণ ঠিকানা লিখুন...' : 'Enter your complete address...' ?>"
                          class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"><?= e($userData['address'] ?? '') ?></textarea>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full py-4 px-4 bg-slate-950 hover:bg-slate-850 text-white text-xs font-bold rounded-2xl shadow-lg transition transform active:scale-[0.99] flex items-center justify-center gap-2">
                <span><?= $currentLang === 'bn' ? 'প্রোফাইল আপডেট করুন' : 'Save Changes' ?></span>
                <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </button>

        </form>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>