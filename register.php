<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: /online-shopping-management/index.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';

$errors = [];
$successMessage = false;
$name     = '';
$nickname = '';
$email    = '';
$phone    = '';
$address  = '';
$gender   = 'male';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $gender   = $_POST['gender'] ?? 'male';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($name)) {
        $errors[] = ($currentLang === 'bn') ? 'আপনার পূর্ণ নাম প্রদান করুন।' : 'Please enter your full name.';
    }
    if (empty($nickname)) {
        $nickname = explode(' ', $name)[0]; // নিকনেম না দিলে নামের প্রথম অংশ ব্যবহার করবে
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = ($currentLang === 'bn') ? 'সঠিক ইমেইল অ্যাড্রেস প্রদান করুন।' : 'Please enter a valid email address.';
    }

    // বাংলাদেশি ফোন নম্বর ভ্যালিডেশন (১১ ডিজিট এবং 013, 014, 015, 016, 017, 018, 019 দিয়ে শুরু)
    if (empty($phone) || !preg_match('/^01[3-9]\d{8}$/', $phone)) {
        $errors[] = ($currentLang === 'bn') ? 'সঠিক ১১ ডিজিটের বাংলাদেশি মোবাইল নম্বর প্রদান করুন ' : 'Please enter a valid phone number.';
    }

    if (empty($password) || strlen($password) < 6) {
        $errors[] = ($currentLang === 'bn') ? 'পাসওয়ার্ড অন্তত ৬ অক্ষরের হতে হবে।' : 'Password must be at least 6 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = ($currentLang === 'bn') ? 'পাসওয়ার্ড দুটি মেলেনি।' : 'Passwords do not match.';
    }

    // Check Duplicate Email & Phone (আলাদা এবং নিরাপদ ডুপ্লিকেট চেক)
    if (empty($errors)) {
        // ১. users টেবিল থেকে ইমেইল চেক
        $chkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if ($chkEmail) {
            $chkEmail->bind_param("s", $email);
            $chkEmail->execute();
            if ($chkEmail->get_result()->num_rows > 0) {
                $errors[] = ($currentLang === 'bn') ? 'এই ইমেইল অ্যাড্রেসটি ইতিমধ্যে রেজিস্টার করা হয়েছে।' : 'This email address is already registered.';
            }
            $chkEmail->close();
        }

        // ২. customers টেবিল থেকে ফোন নম্বর চেক
        $chkPhone = $conn->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
        if ($chkPhone) {
            $chkPhone->bind_param("s", $phone);
            $chkPhone->execute();
            if ($chkPhone->get_result()->num_rows > 0) {
                $errors[] = ($currentLang === 'bn') ? 'এই ফোন নম্বরটি ইতিমধ্যে রেজিস্টার করা হয়েছে।' : 'This phone number is already registered.';
            }
            $chkPhone->close();
        }
    }

    // Profile Image Upload
    $imagePath = null;
    if (empty($errors) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
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
        }
    }

    // Insert to DB
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'customer';

        $conn->begin_transaction();

        try {
            // users table
            $stmtUser = $conn->prepare("INSERT INTO users (name, nickname, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if (!$stmtUser) throw new Exception($conn->error);
            $stmtUser->bind_param("sssss", $name, $nickname, $email, $hash, $role);
            if (!$stmtUser->execute()) throw new Exception($stmtUser->error);
            $userId = $stmtUser->insert_id;
            $stmtUser->close();

            // customers table
            $stmtCust = $conn->prepare("INSERT INTO customers (user_id, name, nickname, phone, address, gender, profile_image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmtCust) throw new Exception($conn->error);
            $stmtCust->bind_param("issssss", $userId, $name, $nickname, $phone, $address, $gender, $imagePath);
            if (!$stmtCust->execute()) throw new Exception($stmtCust->error);
            $stmtCust->close();

            $conn->commit();
            $successMessage = true;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Database Save Error: " . $e->getMessage();
        }
    }
}
?>

<div class="min-h-[85vh] flex items-center justify-center py-10 px-4 sm:px-6 lg:px-8 bg-slate-50/50 relative">
    <div class="w-full max-w-lg space-y-6 bg-white p-6 sm:p-10 rounded-3xl border border-slate-200/80 shadow-xl shadow-slate-200/50">
        
        <!-- Header / Novera Logo -->
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
                <?= $currentLang === 'bn' ? 'আপনার ব্যক্তিগত তথ্য দিয়ে শপিং অ্যাকাউন্ট তৈরি করুন' : 'Setup your account for a personalized luxury experience' ?>
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
                <?php foreach ($errors as $err): ?>
                    <p class="flex items-center gap-2 font-medium">
                        <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?= e($err) ?>
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="space-y-4" action="" method="POST" enctype="multipart/form-data">
            
            <!-- Full Name & Nick Name Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'পূর্ণ নাম' : 'Full Name' ?>
                    </label>
                    <input type="text" name="name" required value="<?= e($name) ?>" placeholder="Mohammad Saif"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'ডাকনাম (Nick Name)' : 'Nick Name (AI / Header)' ?>
                    </label>
                    <input type="text" name="nickname" value="<?= e($nickname) ?>" placeholder="Saif"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                </div>
            </div>

            <!-- Email & Phone Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'ইমেইল অ্যাড্রেস' : 'Email Address' ?>
                    </label>
                    <input type="email" name="email" required value="<?= e($email) ?>" placeholder="saif@example.com"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'ফোন নম্বর (১১ ডিজিট)' : 'Phone Number (11 Digits)' ?>
                    </label>
                    <input type="tel" name="phone" required maxlength="11" value="<?= e($phone) ?>" placeholder="017xxxxxxxx"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                </div>
            </div>

            <!-- Gender & Profile Image -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <!-- Custom Soft Rounded Gender Dropdown -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'জেন্ডার' : 'Gender' ?>
                    </label>
                    
                    <div class="relative inline-block w-full text-left" id="customGenderDropdown">
                        <button type="button" id="genderDropdownBtn" 
                                class="w-full flex items-center justify-between px-4 py-3 bg-slate-50 hover:bg-slate-100/80 text-slate-800 rounded-2xl text-xs font-bold border border-slate-200/90 shadow-2xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition cursor-pointer">
                            <span id="selectedGenderText" class="font-bold text-slate-800">
                                <?php if (($gender ?? 'male') === 'female'): ?>
                                    <?= $currentLang === 'bn' ? 'নারী (Female)' : 'Female' ?>
                                <?php else: ?>
                                    <?= $currentLang === 'bn' ? 'পুরুষ (Male)' : 'Male' ?>
                                <?php endif; ?>
                            </span>
                            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" id="genderArrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <input type="hidden" name="gender" id="genderInputValue" value="<?= e($gender ?? 'male') ?>">

                        <div id="genderDropdownMenu" 
                             class="hidden absolute left-0 mt-2 w-full bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-50 space-y-0.5 transition-all">
                            <button type="button" onclick="selectGender('male', '<?= $currentLang === 'bn' ? 'পুরুষ (Male)' : 'Male' ?>')"
                                    class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition rounded-xl">
                                <?= $currentLang === 'bn' ? 'পুরুষ (Male)' : 'Male' ?>
                            </button>
                            <button type="button" onclick="selectGender('female', '<?= $currentLang === 'bn' ? 'নারী (Female)' : 'Female' ?>')"
                                    class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition rounded-xl">
                                <?= $currentLang === 'bn' ? 'নারী (Female)' : 'Female' ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Profile Image Upload -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'প্রোফাইল ছবি' : 'Profile Image' ?>
                    </label>
                    <input type="file" name="profile_image" accept="image/*"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-950 file:text-white hover:file:bg-slate-800 transition cursor-pointer">
                </div>

            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const btn = document.getElementById("genderDropdownBtn");
                    const menu = document.getElementById("genderDropdownMenu");
                    const arrow = document.getElementById("genderArrow");

                    if (btn && menu) {
                        btn.addEventListener("click", function(e) {
                            e.stopPropagation();
                            menu.classList.toggle("hidden");
                            if (arrow) arrow.classList.toggle("rotate-180");
                        });

                        document.addEventListener("click", function(e) {
                            if (!menu.contains(e.target) && !btn.contains(e.target)) {
                                menu.classList.add("hidden");
                                if (arrow) arrow.classList.remove("rotate-180");
                            }
                        });
                    }
                });

                function selectGender(val, labelText) {
                    document.getElementById('genderInputValue').value = val;
                    document.getElementById('selectedGenderText').textContent = labelText;
                    document.getElementById('genderDropdownMenu').classList.add('hidden');
                    document.getElementById('genderArrow').classList.remove('rotate-180');
                }
            </script>

            <!-- Address -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    <?= $currentLang === 'bn' ? 'ডেলিভারি ঠিকানা' : 'Delivery Address' ?>
                </label>
                <textarea name="address" rows="2" placeholder="<?= $currentLang === 'bn' ? 'বাসা নং, রোড, এলাকা, শহর' : 'House, Road, Area, City' ?>"
                          class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"><?= e($address) ?></textarea>
            </div>

            <!-- Passwords -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'পাসওয়ার্ড' : 'Password' ?>
                    </label>
                    <input type="password" name="password" required placeholder="••••••••"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <?= $currentLang === 'bn' ? 'পাসওয়ার্ড নিশ্চিত করুন' : 'Confirm Password' ?>
                    </label>
                    <input type="password" name="confirm_password" required placeholder="••••••••"
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                </div>
            </div>

            <button type="submit" class="w-full py-3.5 px-4 bg-slate-950 hover:bg-slate-850 text-white text-xs font-bold rounded-2xl shadow-lg transition transform active:scale-[0.99] flex items-center justify-center gap-2 mt-2">
                <span><?= $currentLang === 'bn' ? 'অ্যাকাউন্ট তৈরি করুন' : 'Register Account' ?></span>
                <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </form>

        <div class="text-center pt-2 border-t border-slate-100">
            <p class="text-xs text-slate-500">
                <?= $currentLang === 'bn' ? 'ইতিমধ্যে অ্যাকাউন্ট আছে?' : 'Already have an account?' ?>
                <a href="/online-shopping-management/login.php" class="font-bold text-emerald-600 hover:text-emerald-700 transition ml-1">
                    <?= $currentLang === 'bn' ? 'লগইন করুন' : 'Sign In' ?>
                </a>
            </p>
        </div>

    </div>

    <!-- SUCCESS POPUP MODAL -->
    <?php if ($successMessage): ?>
    <div id="successModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-sm px-4">
        <div class="bg-white w-full max-w-md p-8 rounded-3xl shadow-2xl border border-slate-100 text-center space-y-5 animate-in fade-in zoom-in duration-200">
            
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto shadow-inner">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>

            <div class="space-y-1.5">
                <h3 class="text-xl font-bold text-slate-900">
                    <?= $currentLang === 'bn' ? 'রেজিস্ট্রেশন সফল হয়েছে!' : 'Registration Successful!' ?>
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    <?= $currentLang === 'bn' ? 'আপনার NOVERA অ্যাকাউন্ট সফলভাবে তৈরি হয়েছে। এখন লগইন করুন।' : 'Your NOVERA account has been created successfully. Please sign in.' ?>
                </p>
            </div>

            <div class="pt-2 flex flex-col gap-2">
                <a href="/online-shopping-management/login.php" class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-2xl shadow-lg transition flex items-center justify-center gap-2">
                    <span><?= $currentLang === 'bn' ? 'এখনই লগইন করুন' : 'Sign In Now' ?></span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>

        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>