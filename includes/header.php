<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

if (!function_exists('e')) {
    function e(mixed $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// ভাষা পরিবর্তনের লজিক
if (isset($_GET['lang'])) {
    $lang = strtolower($_GET['lang']);
    if (in_array($lang, ['en', 'bn'], true)) {
        $_SESSION['lang'] =$lang;
    }
}

$currentLang =$_SESSION['lang'] ?? 'en';

$trans = [
    'en' => [
        'ticker1'    => 'Free Shipping on Orders Over $50 | Code: NOVERA2026',
        'ticker2'    => 'New Summer Luxury Collection 2026 Available Now',
        'home'       => 'Home',
        'categories' => 'Categories',
        'child'      => 'Kids Collection',
        'men'        => 'Men Collection',
        'women'      => 'Women Collection',
        'ai_assist'  => 'AI Stylist',
        'sale'       => 'Sale',
        'contact'    => 'Contact',
        'search_ph'  => 'Search NOVERA fashion...',
        'my_orders'  => 'My Orders',
        'my_profile' => 'My Profile',
        'logout'     => 'Logout',
        'login'      => 'Sign In',
        'register'   => 'Register',
    ],
    'bn' => [
        'ticker1'    => '১৫০০ টাকার কেনাকাটায় ফ্রি ডেলিভারি | প্রোমো কোড: NOVERA2026',
        'ticker2'    => 'NOVERA-র নতুন প্রিমিয়াম সামার কালেকশন ২০২৬ এসে গেছে',
        'home'       => 'হোম',
        'categories' => 'ক্যাটাগরি',
        'child'      => 'বাচ্চাদের পোশাক',
        'men'        => 'পুরুষদের পোশাক',
        'women'      => 'নারীদের পোশাক',
        'ai_assist'  => 'এআই স্টাইলিস্ট',
        'sale'       => 'অফার',
        'contact'    => 'যোগাযোগ',
        'search_ph'  => 'পোশাক বা কালেকশন খুঁজুন...',
        'my_orders'  => 'আমার অর্ডারসমূহ',
        'my_profile' => 'আমার প্রোফাইল',
        'logout'     => 'লগআউট',
        'login'      => 'লগইন',
        'register'   => 'রেজিস্টার',
    ]
];

$t = $trans[$currentLang];

$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" class="h-full bg-slate-50 text-slate-800">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOVERA | Luxury Apparel</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: <?= $currentLang === 'bn' ? "'Hind Siliguri', sans-serif" : "'Inter', sans-serif" ?>;
        }
        .marquee-container {
            width: 100%;
            overflow: hidden;
            white-space: nowrap;
            position: relative;
        }
        .marquee-content {
            display: inline-block;
            white-space: nowrap;
            will-change: transform;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-slate-50/80 text-slate-800 antialiased">

    <!-- Top Announcement Bar -->
    <div class="bg-slate-950 text-slate-200 py-2 border-b border-slate-800/80 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <div id="tickerContainer" class="marquee-container flex-1 mr-4 h-5 flex items-center">
                <div id="tickerTrack" class="marquee-content font-medium tracking-wide flex items-center gap-12 text-slate-300">
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <?= $t['ticker1'] ?>
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l2.4 5.2 5.6.8-4 4.1.9 5.7-4.9-2.6-4.9 2.6.9-5.7-4-4.1 5.6-.8L12 2z"/></svg>
                        <?= $t['ticker2'] ?>
                    </span>
                </div>
            </div>

            <!-- Language Switcher -->
            <div class="flex items-center gap-1 shrink-0 bg-slate-950 pl-3 border-l border-slate-800 font-bold">
                <a href="?lang=en" class="px-2 py-0.5 rounded transition <?= $currentLang === 'en' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' ?>">EN</a>
                <span class="text-slate-800">|</span>
                <a href="?lang=bn" class="px-2 py-0.5 rounded transition <?= $currentLang === 'bn' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' ?>">বাংলা</a>
            </div>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="bg-white/95 backdrop-blur-xl border-b border-slate-200/80 sticky top-0 z-40 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20 gap-4 md:gap-8">
                
                <!-- Brand Logo -->
                <a href="/online-shopping-management/index.php" class="flex items-center gap-3 shrink-0 group">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-slate-950 via-slate-900 to-emerald-950 text-emerald-400 flex items-center justify-center shadow-md shadow-slate-900/10 group-hover:scale-105 transition duration-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 2l2.4 5.2 5.6.8-4 4.1.9 5.7-4.9-2.6-4.9 2.6.9-5.7-4-4.1 5.6-.8L12 2z"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-black tracking-wider text-slate-900 uppercase">NOVERA<span class="text-emerald-600">.</span></span>
                </a>

                <!-- Clean & Spacious Menu Links -->
                <nav class="hidden lg:flex items-center gap-8 text-sm font-semibold text-slate-700">
                    <a href="/online-shopping-management/index.php" class="hover:text-emerald-600 transition"><?= $t['home'] ?></a>
                    
                    <!-- Categories Dropdown -->
                    <div class="relative group py-6">
                        <button class="flex items-center gap-1.5 hover:text-emerald-600 transition focus:outline-none">
                            <span><?= $t['categories'] ?></span>
                            <svg class="w-3.5 h-3.5 text-slate-400 group-hover:rotate-180 transition duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="absolute left-0 top-full w-52 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-2xl shadow-xl py-2 hidden group-hover:block transition-all z-50">
                            <a href="/online-shopping-management/shop.php?category=men" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-emerald-600 transition">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                <?= $t['men'] ?>
                            </a>
                            <a href="/online-shopping-management/shop.php?category=women" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-emerald-600 transition">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                <?= $t['women'] ?>
                            </a>
                            <a href="/online-shopping-management/shop.php?category=child" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-emerald-600 transition">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <?= $t['child'] ?>
                            </a>
                        </div>
                    </div>

                    <!-- AI Stylist Link -->
                    <a href="/online-shopping-management/ai-assist.php" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-indigo-50 border border-indigo-200/80 text-indigo-700 hover:bg-indigo-100 transition font-bold text-xs shadow-2xs">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <?= $t['ai_assist'] ?>
                    </a>

                    <a href="/online-shopping-management/sale.php" class="text-rose-600 hover:text-rose-700 font-bold transition"><?= $t['sale'] ?></a>
                    <a href="/online-shopping-management/contact.php" class="hover:text-emerald-600 transition"><?= $t['contact'] ?></a>
                </nav>

                <!-- Expanded Professional Search Bar -->
                <div class="hidden sm:flex flex-1 max-w-sm mx-2 md:mx-4">
                    <form action="/online-shopping-management/shop.php" method="GET" class="w-full relative">
                        <input type="text" name="search" placeholder="<?= $t['search_ph'] ?>" 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-100/90 border border-slate-200/80 rounded-2xl text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition shadow-2xs">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </form>
                </div>

                <!-- Right Actions: Cart & Hover User Profile -->
                <div class="flex items-center gap-3 shrink-0">
                    
                    <!-- Shopping Cart -->
                    <a href="/online-shopping-management/cart.php" class="relative p-2.5 text-slate-700 hover:text-slate-900 hover:bg-slate-100 rounded-2xl transition" title="Shopping Cart">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <?php if ($cartCount > 0): ?>
                            <span class="absolute top-1 right-1 bg-emerald-600 text-white font-bold text-[10px] w-5 h-5 rounded-full flex items-center justify-center border-2 border-white shadow-xs">
                                <?= $cartCount ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- Profile Dropdown (Hover State) -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="relative group py-6">
                            <button class="flex items-center gap-2.5 p-1 rounded-2xl hover:bg-slate-100/80 transition focus:outline-none">
                                <div class="relative w-9 h-9 rounded-xl bg-slate-950 text-white font-bold flex items-center justify-center text-xs shadow-xs overflow-hidden border border-slate-200">
                                    <?php if (!empty($_SESSION['user_image'])): ?>
                                        <img src="/online-shopping-management/<?= e($_SESSION['user_image']) ?>" alt="Profile" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php if (isset($_SESSION['user_gender']) &&$_SESSION['user_gender'] === 'female'): ?>
                                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        <?php else: ?>
                                            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.654 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Online Indicator Green Badge -->
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                                </div>

                                <span class="hidden md:inline-block text-xs font-bold text-slate-800">
                                    <?= e($_SESSION['user_nickname'] ?? $_SESSION['user_name'] ?? 'Account') ?>
                                </span>
                                <svg class="w-3.5 h-3.5 text-slate-400 group-hover:rotate-180 transition duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <!-- Hover Dropdown Container -->
                            <div class="absolute right-0 top-full w-52 bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-2xl shadow-xl py-2 hidden group-hover:block transition-all z-50">
                                <div class="px-4 py-2 border-b border-slate-100">
                                    <p class="text-[10px] text-slate-400 uppercase font-bold"><?= $currentLang === 'bn' ? 'সাইন ইন করা আছেন' : 'Signed in as' ?></p>
                                    <p class="text-xs font-bold text-slate-800 truncate"><?= e($_SESSION['user_nickname'] ?? $_SESSION['user_name']) ?></p>
                                </div>

                                <!-- My Orders -->
                                <a href="/online-shopping-management/my-orders.php" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-emerald-600 transition">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    <?= $t['my_orders'] ?>
                                </a>

                                <!-- My Profile -->
                                <a href="/online-shopping-management/profile.php" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-emerald-600 transition">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <?= $t['my_profile'] ?>
                                </a>

                                <hr class="my-1 border-slate-100">

                                <!-- Logout -->
                                <a href="/online-shopping-management/logout.php" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 transition">
                                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    <?= $t['logout'] ?>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-2">
                            <a href="/online-shopping-management/login.php" class="px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 rounded-xl transition">
                                <?= $t['login'] ?>
                            </a>
                            <a href="/online-shopping-management/register.php" class="px-3.5 py-2 text-xs font-bold text-white bg-slate-950 hover:bg-slate-800 rounded-xl transition shadow-xs">
                                <?= $t['register'] ?>
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </header>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById("tickerContainer");
            const track = document.getElementById("tickerTrack");
            if (!container || !track) return;

            const speed = 0.85; 
            let isHovered = false;
            let animationFrameId;

            let savedPos = parseFloat(localStorage.getItem("novera_ticker_pos"));
            const containerWidth = container.offsetWidth;
            const trackWidth = track.offsetWidth;

            let posX = !isNaN(savedPos) ? savedPos : containerWidth;

            function animate() {
                if (!isHovered) {
                    posX -= speed;
                    if (posX < -trackWidth) {
                        posX = containerWidth;
                    }
                    track.style.transform = `translateX(${posX}px)`;
                    localStorage.setItem("novera_ticker_pos", posX);
                }
                animationFrameId = requestAnimationFrame(animate);
            }

            container.addEventListener("mouseenter", () => isHovered = true);
            container.addEventListener("mouseleave", () => isHovered = false);

            animationFrameId = requestAnimationFrame(animate);
        });
    </script>

    <main class="flex-1">