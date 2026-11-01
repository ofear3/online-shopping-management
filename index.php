<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$currentLang = $_SESSION['lang'] ?? 'en';

// ১. ডাটাবেজ থেকে ক্যাটাগরি ফেচ করা (সর্বোচ্চ ৬টি বা ৩টি সেন্টার্ড ডিসপ্লে)
$categories = [];
$catQuery = $conn->query("SELECT id, name FROM categories ORDER BY name ASC LIMIT 6");
if ($catQuery) {
    while ($row = $catQuery->fetch_assoc()) {
        $categories[] = $row;
    }
}

// ২. ফিচার্ড/লেটেস্ট আর্ভালস (সর্বোচ্চ ৮টি প্রোডাক্ট = ঠিক ২ সারির গ্রিড)
$latestProducts = [];
$latQuery = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 8");
if ($latQuery) {
    while ($row = $latQuery->fetch_assoc()) {
        $latestProducts[] = $row;
    }
}

// ৩. স্পেশাল ডিসকাউন্ট অফার প্রোডাক্ট (সর্বোচ্চ ৮টি প্রোডাক্ট = ঠিক ২ সারির গ্রিড)
$saleProducts = [];
$saleQuery = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.discount_percent > 0 ORDER BY p.discount_percent DESC LIMIT 8");
if ($saleQuery) {
    while ($row = $saleQuery->fetch_assoc()) {
        $saleProducts[] = $row;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen space-y-12 sm:space-y-20 pb-16 bg-slate-50/60 overflow-x-hidden">

    <!-- 1. HERO BANNER SECTION -->
    <section class="pt-4 sm:pt-8 px-3 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div
            class="relative rounded-[2.5rem] overflow-hidden shadow-2xl border border-slate-800/80 bg-slate-950 min-h-[550px] sm:min-h-[620px] flex items-center justify-center p-6 sm:p-12 md:p-16">

            <!-- Background Full-Width Cinematic YouTube Video Showcase -->
            <div class="absolute inset-0 w-full h-full overflow-hidden pointer-events-none">
                <iframe id="noveraHeroVideo"
                    src="https://www.youtube.com/embed/FxZCZid9Z-4?enablejsapi=1&autoplay=1&mute=1&loop=1&playlist=FxZCZid9Z-4&controls=0&showinfo=0&rel=0&iv_load_policy=3&modestbranding=1&playsinline=1"
                    class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[180%] h-[180%] sm:w-[130%] sm:h-[130%] object-cover opacity-60 scale-110"
                    allow="autoplay; encrypted-media" title="NOVERA Luxury Showcase">
                </iframe>
            </div>

            <!-- Multi-Layered Premium Dark & Emerald Gradient Overlays for High Text Contrast -->
            <div
                class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/80 to-slate-950/40 pointer-events-none">
            </div>
            <div
                class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-emerald-950/40 via-transparent to-slate-950/90 pointer-events-none">
            </div>

            <!-- Ambient Glow Elements -->
            <div
                class="absolute -top-24 -right-24 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none">
            </div>
            <div
                class="absolute -bottom-24 -left-24 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl pointer-events-none">
            </div>

            <!-- Main Content Glassmorphism Center Box -->
            <div class="relative z-10 max-w-3xl mx-auto text-center space-y-6 sm:space-y-8">

                <!-- Live Status Badge -->
                <div
                    class="inline-flex items-center gap-2 px-4 py-1.5 bg-slate-900/80 border border-emerald-500/30 text-emerald-300 font-bold text-[11px] sm:text-xs rounded-full uppercase tracking-widest shadow-xl backdrop-blur-md mx-auto">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <?= $currentLang === 'bn' ? 'নোভেরা লাক্সারি কালেকশন ২০২৬' : 'NOVERA Luxury Showcase 2026' ?>
                </div>

                <!-- Main Heading -->
                <h1 class="text-3xl sm:text-5xl md:text-6xl font-black tracking-tight text-white leading-[1.15]">
                    <?= $currentLang === 'bn' ? 'অভিজাত পোশাকে <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-300 via-amber-400 to-emerald-400">অনন্য সৌন্দর্য</span>' : 'Elegance Redefined In <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-300 via-amber-400 to-emerald-400">Every Thread</span>' ?>
                </h1>

                <!-- Subtitle -->
                <p class="text-xs sm:text-sm md:text-base text-slate-300 leading-relaxed max-w-xl mx-auto font-normal">
                    <?= $currentLang === 'bn' ? 'প্রিমিয়াম মানের সেরা সব পোশাক নিয়ে আসছে NOVERA। আধুনিক ফ্যাশন ও উচ্চমানের ফেব্রিকের এক অপূর্ব মেলবন্ধন।' : 'Discover tailored perfection and timeless apparel crafted for ultimate comfort and sophistication.' ?>
                </p>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3.5 pt-2">
                    <a href="shop.php"
                        class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-amber-500 to-amber-400 hover:from-amber-400 hover:to-amber-300 active:scale-95 text-slate-950 font-black rounded-2xl text-xs transition duration-200 shadow-xl shadow-amber-500/20 text-center flex items-center justify-center gap-2 cursor-pointer">
                        <span><?= $currentLang === 'bn' ? 'শপ ঘুরে দেখুন' : 'Explore Catalog' ?></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>

                    <a href="sale.php"
                        class="w-full sm:w-auto px-8 py-4 bg-slate-900/80 hover:bg-slate-850 active:scale-95 text-white font-bold rounded-2xl text-xs border border-slate-700/80 hover:border-slate-600 transition duration-200 text-center flex items-center justify-center gap-2 backdrop-blur-md cursor-pointer">
                        <span>🔥 <?= $currentLang === 'bn' ? 'বিশেষ অফারসমূহ' : 'Special Offers' ?></span>
                    </a>
                </div>

                <!-- Floating Mini Trust Metrics Bar -->
                <div
                    class="grid grid-cols-3 gap-3 sm:gap-6 pt-6 border-t border-slate-800/80 max-w-lg mx-auto text-center">
                    <div class="bg-slate-900/40 backdrop-blur-md p-3 rounded-2xl border border-white/5">
                        <span class="block text-lg sm:text-xl font-black text-amber-400">100%</span>
                        <span
                            class="text-[9px] sm:text-[10px] text-slate-400 uppercase tracking-wider font-bold">Original
                            Fabric</span>
                    </div>
                    <div class="bg-slate-900/40 backdrop-blur-md p-3 rounded-2xl border border-white/5">
                        <span class="block text-lg sm:text-xl font-black text-emerald-400">24/7</span>
                        <span class="text-[9px] sm:text-[10px] text-slate-400 uppercase tracking-wider font-bold">Fast
                            Support</span>
                    </div>
                    <div class="bg-slate-900/40 backdrop-blur-md p-3 rounded-2xl border border-white/5">
                        <span class="block text-lg sm:text-xl font-black text-indigo-400">64</span>
                        <span
                            class="text-[9px] sm:text-[10px] text-slate-400 uppercase tracking-wider font-bold">Districts</span>
                    </div>
                </div>

            </div>

            <!-- Floating Sound Toggle Pill Button (Transparent & Sleek on Mobile/Desktop) -->
<div class="absolute bottom-4 right-4 sm:bottom-6 sm:right-6 z-30 pointer-events-auto">
    <button type="button" id="soundToggleBtn" onclick="toggleHeroSound()"
        class="px-3.5 py-2 sm:px-4 sm:py-2.5 bg-transparent hover:bg-slate-950/70 text-white/60 hover:text-white rounded-2xl text-[11px] sm:text-xs font-black border border-transparent hover:border-white/25 transition-all duration-300 flex items-center gap-1.5 backdrop-blur-none hover:backdrop-blur-md cursor-pointer group shadow-none hover:shadow-2xl">
        <svg id="soundIcon"
            class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-emerald-400 group-hover:scale-110 transition-transform"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
        </svg>
        <span id="soundText" class="tracking-wide">Sound Off</span>
    </button>
</div>

        </div>
    </section>

    <!-- YouTube IFrame API Script for Sound Control -->
    <script>
        let isVideoMuted = true;

        function toggleHeroSound() {
            const iframe = document.getElementById('noveraHeroVideo');
            const soundText = document.getElementById('soundText');
            const soundIcon = document.getElementById('soundIcon');

            if (isVideoMuted) {
                iframe.contentWindow.postMessage('{"event":"command","func":"unMute","args":""}', '*');
                soundText.textContent = "Sound On";
                soundIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.900a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />';
                isVideoMuted = false;
            } else {
                iframe.contentWindow.postMessage('{"event":"command","func":"mute","args":""}', '*');
                soundText.textContent = "Sound Off";
                soundIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />';
                isVideoMuted = true;
            }
        }
    </script>

    <!-- 2. CATEGORY HIGHLIGHTS (CENTERED ALIGNMENT & PREMUM CARDS) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        <!-- Centered Header -->
        <div class="text-center space-y-2 max-w-lg mx-auto">
            <span class="text-[10px] sm:text-xs font-black text-emerald-600 uppercase tracking-widest block">
                <?= $currentLang === 'bn' ? 'পছন্দের কালেকশন বেছে নিন' : 'Explore Collections' ?>
            </span>
            <h2 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">
                <?= $currentLang === 'bn' ? 'ব্রাউজ ক্যাটাগরি' : 'Browse Categories' ?>
            </h2>
            <p class="text-xs text-slate-500 leading-relaxed">
                <?= $currentLang === 'bn' ? 'আপনার রুচি ও শৈলী অনুযায়ী চমৎকার পোশাক খুঁজে নিন' : 'Find apparel tailored to your style' ?>
            </p>
        </div>

        <!-- Perfectly Centered Categories Grid -->
        <div class="flex flex-wrap justify-center items-center gap-4 sm:gap-6 max-w-5xl mx-auto">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat):
                    $catKey = strtolower($cat['name']);
                    ?>
                    <a href="shop.php?category=<?= urlencode($catKey) ?>"
                        class="w-[calc(50%-8px)] sm:w-44 bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs hover:border-emerald-500 hover:shadow-xl hover:-translate-y-1 transition duration-300 text-center space-y-3 group shrink-0">
                        <div
                            class="w-13 h-13 bg-emerald-50 group-hover:bg-emerald-600 text-emerald-600 group-hover:text-white rounded-2xl flex items-center justify-center mx-auto transition duration-300 shadow-inner">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 11V7a4 4 0 00-8 0v4M5 11h14l1 12H4L5 11z" />
                            </svg>
                        </div>
                        <h3 class="font-black text-slate-800 text-xs truncate group-hover:text-emerald-600 transition">
                            <?= e($cat['name']) ?>
                        </h3>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach (['Men' => 'men', 'Women' => 'women', 'Kids' => 'kids', 'Panjabi' => 'panjabi', 'Denim' => 'denim', 'Accessories' => 'accessories'] as $name => $slug): ?>
                    <a href="shop.php?category=<?= $slug ?>"
                        class="w-[calc(50%-8px)] sm:w-44 bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs hover:border-emerald-500 hover:shadow-xl hover:-translate-y-1 transition duration-300 text-center space-y-3 group shrink-0">
                        <div
                            class="w-13 h-13 bg-emerald-50 group-hover:bg-emerald-600 text-emerald-600 group-hover:text-white rounded-2xl flex items-center justify-center mx-auto transition duration-300 shadow-inner">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 11V7a4 4 0 00-8 0v4M5 11h14l1 12H4L5 11z" />
                            </svg>
                        </div>
                        <h3 class="font-black text-slate-800 text-xs truncate group-hover:text-emerald-600 transition">
                            <?= $name ?>
                        </h3>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- 3. NEW ARRIVALS (MAX 2 ROWS = 8 ITEMS MAX) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div
            class="bg-gradient-to-r from-slate-900 via-slate-950 to-emerald-950 rounded-3xl p-6 sm:p-10 text-white shadow-2xl relative overflow-hidden border border-slate-800">

            <div
                class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-white/10 pb-6">
                <div class="space-y-1">
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-500/20 text-amber-300 font-extrabold text-[10px] rounded-full uppercase tracking-wider border border-amber-400/30">
                        ✨ <?= $currentLang === 'bn' ? 'সদ্য আগত কালেকশন' : 'Brand New Season' ?>
                    </span>
                    <h2 class="text-xl sm:text-3xl font-black text-white tracking-tight uppercase">
                        <?= $currentLang === 'bn' ? 'নতুন অ্যারাইভালস' : 'Latest New Arrivals' ?>
                    </h2>
                </div>
                <a href="shop.php"
                    class="px-5 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs rounded-2xl shadow-lg transition shrink-0">
                    <?= $currentLang === 'bn' ? 'সব দেখুন' : 'View Full Catalog' ?>
                </a>
            </div>

            <?php if (!empty($latestProducts)): ?>
                <!-- Grid showing max 2 rows (4 columns x 2 rows = 8 cards) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mt-6">
                    <?php foreach ($latestProducts as $item):
                        $price = (float) ($item['price'] ?? 0);
                        $discountPercent = (float) ($item['discount_percent'] ?? 0);
                        $hasDiscount = ($discountPercent > 0);
                        $finalPrice = $hasDiscount ? ($price - ($price * ($discountPercent / 100))) : $price;
                        ?>
                        <div
                            class="bg-white text-slate-900 rounded-3xl p-4 space-y-3 shadow-xl border border-slate-100 flex flex-col justify-between group hover:-translate-y-1 transition duration-300">

                            <div class="relative h-56 bg-slate-100 rounded-2xl overflow-hidden">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>"
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-slate-300 font-bold text-xs">
                                        NOVERA</div>
                                <?php endif; ?>

                                <?php if ($hasDiscount): ?>
                                    <span
                                        class="absolute top-2.5 left-2.5 bg-rose-600 text-white text-[10px] font-black px-2.5 py-1 rounded-xl shadow-md">
                                        <?= round($discountPercent) ?>% OFF
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="absolute top-2.5 left-2.5 bg-slate-950 text-emerald-400 text-[10px] font-black px-2.5 py-1 rounded-xl shadow-md">
                                        NEW
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="space-y-1">
                                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">
                                    <?= e($item['category_name'] ?? 'Luxury') ?>
                                </span>
                                <h3
                                    class="font-black text-xs sm:text-sm text-slate-900 truncate group-hover:text-emerald-600 transition">
                                    <?= e($item['name']) ?>
                                </h3>
                                <div class="flex items-baseline gap-2 pt-1">
                                    <?php if ($hasDiscount): ?>
                                        <span class="text-sm font-black text-rose-600">৳<?= number_format($finalPrice, 0) ?></span>
                                        <span class="text-xs text-slate-400 line-through">৳<?= number_format($price, 0) ?></span>
                                    <?php else: ?>
                                        <span class="text-sm font-black text-slate-900">৳<?= number_format($price, 0) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <a href="product-details.php?id=<?= e((string) $item['id']) ?>"
                                class="w-full py-2.5 bg-slate-950 hover:bg-emerald-600 text-white text-xs font-extrabold rounded-2xl text-center transition block shadow-md">
                                <?= $currentLang === 'bn' ? 'বিস্তারিত দেখুন' : 'View Details' ?>
                            </a>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <!-- 4. LIMITED MEGA SALE SECTION (MAX 2 ROWS = 8 ITEMS MAX) -->
    <?php if (!empty($saleProducts)): ?>
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div
                class="bg-gradient-to-r from-rose-950 via-slate-900 to-amber-950 rounded-3xl p-6 sm:p-10 text-white shadow-2xl relative overflow-hidden border border-rose-500/20">

                <div
                    class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-white/10 pb-6">
                    <div class="space-y-1">
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-1 bg-rose-500/20 text-rose-300 font-extrabold text-[10px] rounded-full uppercase tracking-wider">
                            🔥 <?= $currentLang === 'bn' ? 'মেগা অফার' : 'Limited Time Offers' ?>
                        </span>
                        <h2 class="text-xl sm:text-3xl font-black text-white tracking-tight uppercase">
                            <?= $currentLang === 'bn' ? 'বিশেষ ছাড়ের পোশাক' : 'Exclusive Sale Deals' ?>
                        </h2>
                    </div>
                    <a href="sale.php"
                        class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-black rounded-2xl shadow-lg transition shrink-0">
                        <?= $currentLang === 'bn' ? 'সব অফার দেখুন' : 'Explore Sale Page' ?>
                    </a>
                </div>

                <!-- Grid showing max 2 rows (4 columns x 2 rows = 8 cards) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mt-6">
                    <?php foreach ($saleProducts as $item):
                        $origPrice = (float) ($item['price'] ?? 0);
                        $disc = (float) ($item['discount_percent'] ?? 0);
                        $saved = $origPrice * ($disc / 100);
                        $nowPrice = $origPrice - $saved;
                        ?>
                        <div
                            class="bg-white text-slate-900 rounded-3xl p-4 space-y-3 shadow-xl border border-rose-100 flex flex-col justify-between group hover:-translate-y-1 transition duration-300">
                            <div class="relative h-56 bg-slate-100 rounded-2xl overflow-hidden">
                                <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>"
                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                <span
                                    class="absolute top-2.5 left-2.5 bg-gradient-to-r from-rose-600 to-amber-600 text-white text-[10px] font-black px-3 py-1 rounded-xl shadow-md">
                                    <?= round($disc) ?>% OFF
                                </span>
                            </div>
                            <div class="space-y-1">
                                <h3 class="font-black text-xs sm:text-sm text-slate-900 truncate"><?= e($item['name']) ?></h3>
                                <div class="flex items-baseline gap-2 pt-1">
                                    <span class="text-sm font-black text-rose-600">৳<?= number_format($nowPrice, 0) ?></span>
                                    <span
                                        class="text-xs text-slate-400 line-through">৳<?= number_format($origPrice, 0) ?></span>
                                </div>
                                <p class="text-[10px] font-black text-emerald-600">
                                    <?= $currentLang === 'bn' ? 'সাশ্রয় ৳' . number_format($saved, 0) : 'Save ৳' . number_format($saved, 0) ?>
                                </p>
                            </div>
                            <a href="product-details.php?id=<?= e((string) $item['id']) ?>"
                                class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-black rounded-2xl text-center transition block shadow-md">
                                <?= $currentLang === 'bn' ? 'অফার নিন' : 'Grab Offer' ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </section>
    <?php endif; ?>

    <!-- 5. CUSTOMER REVIEWS & TESTIMONIALS SECTION -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div class="text-center space-y-1.5 max-w-md mx-auto">
            <span class="text-[10px] sm:text-xs font-black text-emerald-600 uppercase tracking-widest block">
                <?= $currentLang === 'bn' ? 'গ্রাহকদের মতামত' : 'Customer Feedback' ?>
            </span>
            <h2 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">
                <?= $currentLang === 'bn' ? 'আমাদের কাস্টমারদের রিভিউ' : 'What Our Clients Say' ?>
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-3 relative">
                <div class="flex items-center gap-1 text-amber-400">
                    ★ ★ ★ ★ ★
                </div>
                <p class="text-xs text-slate-600 leading-relaxed italic">
                    "NOVERA-র কাপড়ের কোয়ালিটি এক কথায় অসাধারণ! অর্ডারের পর খুব দ্রুত ডেলিভারি পেয়েছি। সাইজিং একদম
                    পারফেক্ট ছিল।"
                </p>
                <div class="pt-2 flex items-center gap-3 border-t border-slate-100">
                    <div
                        class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-700 font-black text-xs flex items-center justify-center">
                        SA
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-900">Saif Ahmed</h4>
                        <span class="text-[10px] text-emerald-600 font-semibold block">Verified Buyer</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-3 relative">
                <div class="flex items-center gap-1 text-amber-400">
                    ★ ★ ★ ★ ★
                </div>
                <p class="text-xs text-slate-600 leading-relaxed italic">
                    "The fabric texture and finishing exceeded my expectations. Best luxury online shopping platform in
                    Bangladesh!"
                </p>
                <div class="pt-2 flex items-center gap-3 border-t border-slate-100">
                    <div
                        class="w-9 h-9 rounded-full bg-amber-100 text-amber-700 font-black text-xs flex items-center justify-center">
                        MR
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-900">Mahmudur Rahman</h4>
                        <span class="text-[10px] text-emerald-600 font-semibold block">Verified Buyer</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-3 relative">
                <div class="flex items-center gap-1 text-amber-400">
                    ★ ★ ★ ★ ★
                </div>
                <p class="text-xs text-slate-600 leading-relaxed italic">
                    "কাস্টমার সাপোর্ট অনেক সাহায্যকারী ছিল। সাইজ এক্সচেঞ্জ করার প্রসেস খুবই স্মুথ ছিল। খুব পছন্দ হয়েছে।"
                </p>
                <div class="pt-2 flex items-center gap-3 border-t border-slate-100">
                    <div
                        class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 font-black text-xs flex items-center justify-center">
                        NZ
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-900">Nusrat Zahan</h4>
                        <span class="text-[10px] text-emerald-600 font-semibold block">Verified Buyer</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. TRUST BADGES / SERVICE GUARANTEE -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div
            class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center gap-4 p-2">
                <div
                    class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-xs sm:text-sm text-slate-900">
                        <?= $currentLang === 'bn' ? '১০০% অরিজিনাল ফেব্রিক' : '100% Original Apparel' ?>
                    </h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        <?= $currentLang === 'bn' ? 'সর্বোচ্চ কোয়ালিটি ও স্থায়িত্বের নিশ্চয়তা' : 'Guaranteed quality fabric and comfort' ?>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-2 border-t sm:border-t-0 sm:border-l border-slate-100">
                <div
                    class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-xs sm:text-sm text-slate-900">
                        <?= $currentLang === 'bn' ? 'দ্রুত হোম ডেলিভারি' : 'Express Delivery' ?>
                    </h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        <?= $currentLang === 'bn' ? 'সারাদেশে দ্রুততম সময়ে ডেলিভারি' : 'Fast shipping across Bangladesh' ?>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-2 border-t sm:border-t-0 sm:border-l border-slate-100">
                <div
                    class="w-12 h-12 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-xs sm:text-sm text-slate-900">
                        <?= $currentLang === 'bn' ? '২৪/৭ কাস্টমার সাপোর্ট' : 'Dedicated Support' ?>
                    </h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        <?= $currentLang === 'bn' ? 'যেকোনো সহায়তায় আমাদের কনসিয়ার্জ টিম' : 'Assistance whenever you need' ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>