<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$currentLang = $_SESSION['lang'] ?? 'en';

$errors = [];

// ১. রিলোড প্রটেকশন (PRG Pattern)
$successMessage = false;
if (isset($_SESSION['contact_success']) && $_SESSION['contact_success'] === true) {
    $successMessage = true;
    unset($_SESSION['contact_success']); // একবার দেখানোর পর সেশন ক্লিয়ার
}

$name    = $_SESSION['user_name'] ?? '';
$email   = $_SESSION['user_email'] ?? '';
$subject = '';
$message = '';

// ২. ফর্ম সাবমিশন প্রসেসিং ও ডাটাবেজ সেভ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name)) {
        $errors[] = ($currentLang === 'bn') ? 'আপনার নাম প্রদান করুন।' : 'Please enter your name.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = ($currentLang === 'bn') ? 'সঠিক ইমেইল অ্যাড্রেস প্রদান করুন।' : 'Please enter a valid email address.';
    }
    if (empty($subject)) {
        $errors[] = ($currentLang === 'bn') ? 'মেসেজের বিষয় (Subject) নির্বাচন করুন।' : 'Please enter a subject.';
    }
    if (empty($message)) {
        $errors[] = ($currentLang === 'bn') ? 'আপনার মেসেজটি লিখুন।' : 'Please enter your message.';
    }

    if (empty($errors)) {
        // user_messages টেবিলে ডাটা সেভ করার জন্য Prepared Statement
        $stmt = $conn->prepare("INSERT INTO user_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("ssss", $name, $email, $subject, $message);
            if ($stmt->execute()) {
                $stmt->close();
                $_SESSION['contact_success'] = true;
                header("Location: contact.php");
                exit;
            } else {
                $errors[] = "Database Insert Error: " . $stmt->error;
                $stmt->close();
            }
        } else {
            $errors[] = "Database Prepare Error: " . $conn->error;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Custom CSS Animations & Mobile Overflow Protections -->
<style>
    html {
        overflow-y: scroll;
        overflow-x: hidden;
    }

    body {
        max-width: 100vw;
        overflow-x: hidden;
    }

    /* স্মুথ রিয়েলিস্টিক ডানা ঝাপটানো */
    @keyframes realWingFlap {
        0%, 100% {
            transform: rotate(0deg) scaleY(1);
        }
        50% {
            transform: rotate(-32deg) scaleY(0.7) translateY(-3px);
        }
    }

    /* মোবাইল ও ডেস্কটপ ফ্রেন্ডলি পাখি আগমন অ্যানিমেশন */
    @keyframes birdArriveSmooth {
        0% {
            transform: translate(-120px, -35px) scale(0.4);
            opacity: 0;
        }
        70% {
            transform: translate(-12px, -6px) scale(1.05);
            opacity: 1;
        }
        100% {
            transform: translate(-6px, -2px) scale(1);
            opacity: 1;
        }
    }

    /* চিঠি মুখে করে আকাশে উড়ে যাওয়া */
    @keyframes skyFlyAway {
        0% {
            transform: translate(0, 0) scale(1) rotate(0deg);
            opacity: 1;
        }
        25% {
            transform: translate(20px, -15px) scale(1.02) rotate(-4deg);
            opacity: 1;
        }
        100% {
            transform: translate(280px, -180px) scale(0.2) rotate(18deg);
            opacity: 0;
        }
    }

    .wing-left {
        animation: realWingFlap 0.18s ease-in-out infinite alternate;
        transform-origin: 32px 28px;
    }
    .wing-right {
        animation: realWingFlap 0.18s ease-in-out infinite alternate 0.05s;
        transform-origin: 28px 30px;
    }

    .pigeon-enter {
        animation: birdArriveSmooth 0.9s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }

    .animate-sky-fly {
        animation: skyFlyAway 2.2s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.96);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .smooth-modal-box {
        animation: modalFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
</style>

<div class="min-h-screen py-6 sm:py-10 px-3 sm:px-6 lg:px-8 bg-slate-50/60">
    <div class="max-w-7xl mx-auto space-y-6 sm:space-y-8">
        
        <!-- Hero Header (Fully Responsive Flex Layout) -->
        <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-emerald-950 rounded-2xl sm:rounded-3xl p-6 sm:p-8 md:p-12 text-white shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-3 max-w-xl z-10 text-center md:text-left">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 font-bold text-[10px] sm:text-[11px] rounded-full uppercase tracking-wider">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    NOVERA Concierge & Support
                </span>
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight">
                    <?= $currentLang === 'bn' ? 'আমাদের সাথে যোগাযোগ করুন' : 'We Are Here to Assist You' ?>
                </h1>
                <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                    <?= $currentLang === 'bn' ? 'যেকোনো জিজ্ঞাসা, অর্ডার আপডেট বা সহায়তার জন্য আমাদের কনসিয়ার্জ টিমের সাথে যোগাযোগ করুন।' : 'Have a question regarding your order, sizing, or custom inquiries? Reach out to our dedicated support concierge.' ?>
                </p>
            </div>

            <div class="z-10 bg-white/10 backdrop-blur-md px-5 py-3.5 rounded-2xl border border-white/10 text-center shadow-inner shrink-0 w-full md:w-auto">
                <span class="block text-lg sm:text-xl font-black text-emerald-400">24 / 7</span>
                <span class="text-[10px] uppercase tracking-widest text-slate-300 font-bold"><?= $currentLang === 'bn' ? 'অনলাইন সাপোর্ট' : 'Online Assistance' ?></span>
            </div>
        </div>

        <!-- 2 Column Responsive Grid Section -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-stretch">
            
            <!-- Left Column: Support Channels & Google Map -->
            <div class="lg:col-span-6 space-y-6 flex flex-col justify-between">
                <div class="bg-white rounded-2xl sm:rounded-3xl p-5 sm:p-6 md:p-8 border border-slate-200/80 shadow-xs space-y-6 h-full flex flex-col justify-between">
                    <div>
                        <h2 class="text-xs sm:text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2 mb-4 sm:mb-6">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            <?= $currentLang === 'bn' ? 'সাপোর্ট ও লোকেশন' : 'Support Channels & Location' ?>
                        </h2>

                        <div class="space-y-3.5 text-xs">
                            <div class="flex items-start gap-3 sm:gap-4 p-3.5 sm:p-4 rounded-2xl bg-slate-50 border border-slate-100 hover:border-emerald-200 transition">
                                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-bold text-slate-800 text-xs truncate"><?= $currentLang === 'bn' ? 'কাস্টমার কেয়ার হেল্পলাইন' : 'Customer Care Hotline' ?></h3>
                                    <p class="text-slate-500 mt-0.5 break-all">+880 1700-000000 / +880 1800-000000</p>
                                    <span class="text-[10px] text-emerald-600 font-bold block mt-1"><?= $currentLang === 'bn' ? 'সকাল ১০:০০ - রাত ১০:০০ (প্রতিদিন)' : '10:00 AM - 10:00 PM (Daily)' ?></span>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 sm:gap-4 p-3.5 sm:p-4 rounded-2xl bg-slate-50 border border-slate-100 hover:border-emerald-200 transition">
                                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-bold text-slate-800 text-xs truncate"><?= $currentLang === 'bn' ? 'ইমেইল সাপোর্ট' : 'Official Email Support' ?></h3>
                                    <p class="text-slate-500 mt-0.5 break-all">support@novera.com / info@novera.com</p>
                                    <span class="text-[10px] text-slate-400 block mt-1"><?= $currentLang === 'bn' ? '২৪ ঘণ্টার মধ্যে উত্তর প্রদান করা হয়' : 'Responses within 24 business hours' ?></span>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 sm:gap-4 p-3.5 sm:p-4 rounded-2xl bg-slate-50 border border-slate-100 hover:border-emerald-200 transition">
                                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-bold text-slate-800 text-xs truncate"><?= $currentLang === 'bn' ? 'ফ্ল্যাগশিপ শো-রুম (রাপা প্লাজা)' : 'Flagship Store (Rapa Plaza)' ?></h3>
                                    <p class="text-slate-500 mt-0.5 leading-relaxed"><?= $currentLang === 'bn' ? 'রাপা প্লাজা, রোড ২৭ (পুরাতন), ধানমন্ডি, ঢাকা-১২০৯' : 'Rapa Plaza, Road 27 (Old), Dhanmondi, Dhaka-1209' ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Google Map Embed -->
                        <div class="mt-5 rounded-2xl overflow-hidden border border-slate-200/80 shadow-inner h-48 sm:h-56 relative">
                            <iframe 
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3651.902347963283!2d90.37084917680197!3d23.750965389653775!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3755bfb6c9b36b2f%3A0x6b1076b1f09cbb58!2sRapa%20Plaza%2C%20Dhaka%201209!5e0!3m2!1sen!2sbd!4v1700000000000!5m2!1sen!2sbd" 
                                width="100%" 
                                height="100%" 
                                style="border:0;" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade"
                                class="w-full h-full object-cover">
                            </iframe>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Right Column: Form with Carrier Pigeon Animation -->
            <div class="lg:col-span-6">
                <div class="bg-white rounded-2xl sm:rounded-3xl p-5 sm:p-6 md:p-8 border border-slate-200/80 shadow-xl shadow-slate-200/30 space-y-4 sm:space-y-5 relative overflow-hidden">
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900 uppercase tracking-tight">
                            <?= $currentLang === 'bn' ? 'মেসেজ পাঠান' : 'Send Us a Message' ?>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            <?= $currentLang === 'bn' ? 'নিচের ফর্মে বার্তা লিখুন, আমরা দ্রুত উত্তর দেব।' : 'Fill out the form below for immediate support.' ?>
                        </p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
                            <?php foreach ($errors as $err): ?>
                                <p class="flex items-center gap-2 font-medium">
                                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <?= e($err) ?>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form id="contactForm" action="" method="POST" onsubmit="handleCarrierPigeonSubmit(event)" class="space-y-3.5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    <?= $currentLang === 'bn' ? 'আপনার নাম' : 'Your Name' ?>
                                </label>
                                <input type="text" name="name" required value="<?= e($name) ?>" placeholder="Mohammad Saif"
                                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    <?= $currentLang === 'bn' ? 'ইমেইল অ্যাড্রেস' : 'Email Address' ?>
                                </label>
                                <input type="email" name="email" required value="<?= e($email) ?>" placeholder="saif@example.com"
                                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                <?= $currentLang === 'bn' ? 'মেসেজের বিষয়' : 'Subject' ?>
                            </label>
                            <input type="text" name="subject" required value="<?= e($subject) ?>" placeholder="<?= $currentLang === 'bn' ? 'যেমন: অর্ডার স্টেটাস, রিটার্ন কোয়েরি' : 'e.g., Order Inquiry, Sizing Guide' ?>"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                <?= $currentLang === 'bn' ? 'বিস্তারিত বার্তা' : 'Message Details' ?>
                            </label>
                            <textarea name="message" rows="3" required placeholder="<?= $currentLang === 'bn' ? 'আপনার জিজ্ঞাসা লিখুন...' : 'Type your query here...' ?>"
                                      class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"><?= e($message) ?></textarea>
                        </div>

                        <!-- Carrier Pigeon Animation Stage Container -->
                        <div class="relative min-h-[48px] flex items-center justify-center pt-1">
                            <!-- Main Submit Button -->
                            <button type="submit" id="submitBtn" class="w-full py-3.5 px-4 bg-slate-950 hover:bg-slate-850 active:scale-[0.99] text-white text-xs font-extrabold rounded-2xl shadow-lg transition-all duration-300 flex items-center justify-center gap-2.5 cursor-pointer">
                                <span id="btnText"><?= $currentLang === 'bn' ? 'মেসেজ পাঠান' : 'Submit Message' ?></span>
                                <svg id="btnIcon" class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </button>

                            <!-- Envelope & Pigeon Animation Stage -->
                            <div id="envelopeStage" class="hidden absolute flex items-center justify-center w-full">
                                <div id="letterWrapper" class="relative flex items-center justify-center px-4 sm:px-6 py-2.5 bg-gradient-to-r from-amber-600 via-emerald-700 to-slate-900 text-white rounded-2xl shadow-2xl border border-amber-400/40 transition-all duration-300">
                                    
                                    <!-- Detailed Realistic Carrier Pigeon SVG -->
                                    <div id="pigeonBird" class="absolute -left-10 sm:-left-12 -top-3 opacity-0 pointer-events-none z-30">
                                        <svg class="w-10 h-10 sm:w-12 sm:h-12 text-slate-100 drop-shadow-xl" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M8 38 C4 42, 2 48, 4 52 C8 50, 12 44, 14 40 Z" fill="#94A3B8"/>
                                            <path d="M14 36 C 20 24, 34 22, 46 28 C 52 31, 56 30, 60 26 C 58 32, 54 36, 50 38 C 42 44, 26 46, 14 36 Z" fill="#F8FAFC"/>
                                            <path d="M14 36 C 22 28, 36 28, 46 32 C 38 42, 24 44, 14 36 Z" fill="#E2E8F0"/>
                                            <g class="wing-left">
                                                <path d="M28 30 C 20 12, 36 2, 44 18 C 38 24, 32 28, 28 30 Z" fill="#10B981"/>
                                                <path d="M30 26 C 24 14, 36 6, 42 18 Z" fill="#34D399"/>
                                            </g>
                                            <g class="wing-right">
                                                <path d="M24 32 C 16 16, 30 6, 38 20 C 32 26, 28 30, 24 32 Z" fill="#059669"/>
                                            </g>
                                            <circle cx="54" cy="27" r="5" fill="#F8FAFC"/>
                                            <circle cx="55.5" cy="26" r="1" fill="#0F172A"/>
                                            <path d="M58 27 L 64 30 L 57 31 Z" fill="#F59E0B"/>
                                        </svg>
                                    </div>

                                    <!-- Envelope Icon -->
                                    <svg class="w-5 h-5 text-amber-300 shrink-0 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>

                                    <span class="text-xs font-black tracking-wide text-white truncate"><?= $currentLang === 'bn' ? 'চিঠি প্রস্তুত হচ্ছে...' : 'Preparing Letter...' ?></span>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        </div>

    </div>

    <!-- RESPONSIVE SUCCESS POPUP MODAL -->
    <?php if ($successMessage): ?>
    <div id="successModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-sm p-4">
        <div class="bg-white w-full max-w-sm sm:max-w-md p-6 sm:p-8 rounded-3xl shadow-2xl border border-slate-100 text-center space-y-4 sm:space-y-5 smooth-modal-box">
            
            <div class="w-14 h-14 sm:w-16 sm:h-16 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto shadow-inner">
                <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>

            <div class="space-y-1.5">
                <h3 class="text-lg sm:text-xl font-bold text-slate-900">
                    <?= $currentLang === 'bn' ? 'মেসেজ সফলভাবে পাঠানো হয়েছে!' : 'Message Sent Successfully!' ?>
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    <?= $currentLang === 'bn' ? 'আপনার মেসেজটি আমাদের কনসিয়ার্জ টিম পেয়েছে। খুব শীঘ্রই আপনার ইমেইলে যোগাযোগ করা হবে।' : 'Thank you for reaching out. Our support concierge has received your message and will reply via email shortly.' ?>
                </p>
            </div>

            <div class="pt-2">
                <button type="button" onclick="document.getElementById('successModal').style.display='none';" class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-2xl shadow-lg transition flex items-center justify-center gap-2 cursor-pointer">
                    <span><?= $currentLang === 'bn' ? 'ঠিক আছে' : 'Got it' ?></span>
                </button>
            </div>

        </div>
    </div>
    <?php endif; ?>

</div>

<script>
    let isPigeonFlyingSubmitted = false;

    function handleCarrierPigeonSubmit(e) {
        if (isPigeonFlyingSubmitted) return;

        e.preventDefault();
        const btn = document.getElementById("submitBtn");
        const envelopeStage = document.getElementById("envelopeStage");
        const letterWrapper = document.getElementById("letterWrapper");
        const pigeonBird = document.getElementById("pigeonBird");
        const form = document.getElementById("contactForm");

        // Step 1: Collapse Button smoothly
        btn.style.opacity = '0';
        btn.style.transform = 'scale(0.8)';

        setTimeout(() => {
            btn.classList.add("hidden");
            envelopeStage.classList.remove("hidden");

            // Step 2: Pigeon arrives from left side
            pigeonBird.classList.add("pigeon-enter");

            // Step 3: Bird grabs the letter and flies into the sky
            setTimeout(() => {
                letterWrapper.classList.add("animate-sky-fly");

                // Step 4: Submit form to PHP backend to store data in user_messages table
                setTimeout(() => {
                    isPigeonFlyingSubmitted = true;
                    form.submit();
                }, 2100);

            }, 900);

        }, 300);
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>