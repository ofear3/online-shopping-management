<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$currentLang = $_SESSION['lang'] ?? 'en';

// সর্টিং ও ফিল্টারিং প্যারামিটার
$sortBy = $_GET['sort'] ?? 'highest_discount';
$categoryFilter = $_GET['category'] ?? 'all';

// বেস SQL Query (শুধুমাত্র ডিসকাউন্ট থাকা প্রোডাক্ট)
$sql = "SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.discount_percent > 0";

// ক্যাটাগরি ফিল্টারিং লজিক (Kids-এর জন্য child লজিকসহ)
if ($categoryFilter === 'men') {
    $sql .= " AND (LOWER(c.name) LIKE '%men%' AND LOWER(c.name) NOT LIKE '%women%')";
} elseif ($categoryFilter === 'women') {
    $sql .= " AND LOWER(c.name) LIKE '%women%'";
} elseif ($categoryFilter === 'kids') {
    $sql .= " AND (LOWER(c.name) LIKE '%kid%' OR LOWER(c.name) LIKE '%child%')";
}

// সর্টিং লজিক
if ($sortBy === 'price_low') {
    $sql .= " ORDER BY (p.price - (p.price * (p.discount_percent / 100))) ASC";
} elseif ($sortBy === 'price_high') {
    $sql .= " ORDER BY (p.price - (p.price * (p.discount_percent / 100))) DESC";
} elseif ($sortBy === 'newest') {
    $sql .= " ORDER BY p.id DESC";
} else {
    // ডিফল্ট: সর্বোচ্চ ডিসকাউন্ট সবার আগে
    $sql .= " ORDER BY p.discount_percent DESC, p.id DESC";
}

$saleProducts = [];
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $saleProducts[] = $row;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8 bg-slate-50/60">
    <div class="max-w-7xl mx-auto space-y-8">

        <!-- Hero Festive Banner (Sales Motivation) -->
        <div
            class="relative bg-gradient-to-r from-rose-950 via-slate-900 to-amber-950 rounded-3xl p-8 md:p-12 text-white shadow-2xl overflow-hidden border border-rose-500/20">
            <!-- Background Glow Effects -->
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-rose-500/10 rounded-full blur-3xl pointer-events-none">
            </div>
            <div
                class="absolute -bottom-24 -left-24 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none">
            </div>

            <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
                <div class="space-y-3 text-center md:text-left max-w-xl">
                    <div
                        class="inline-flex items-center gap-2 px-4 py-1.5 bg-rose-500/20 border border-rose-400/30 text-rose-300 font-extrabold text-[11px] rounded-full uppercase tracking-wider shadow-inner">
                        <span class="animate-ping w-2 h-2 rounded-full bg-rose-400"></span>
                        🔥 <?= $currentLang === 'bn' ? 'সীমিত সময়ের মেগা অফার' : 'Limited Time Mega Sale' ?>
                    </div>

                    <h1 class="text-3xl md:text-5xl font-black tracking-tight text-white leading-tight">
                        <?= $currentLang === 'bn' ? 'বিশেষ ছাড় ও এক্সক্লুসিভ কালেকশন' : 'Exclusive Luxury Sale' ?>
                    </h1>

                    <p class="text-xs md:text-sm text-slate-300 leading-relaxed">
                        <?= $currentLang === 'bn' ? 'NOVERA-র প্রিমিয়াম পোশাকে পাচ্ছেন অবিশ্বাস্য মূল্যছাড়! আপনার পছন্দের পোশাকটি এখনই সেরা দামে লুফে নিন।' : 'Elevate your wardrobe with premium apparel at unbelievable prices. Grab your favorites before stock runs out!' ?>
                    </p>
                </div>

                <!-- Highlight Box -->
                <div
                    class="bg-white/10 backdrop-blur-md p-6 rounded-3xl border border-white/15 text-center shrink-0 shadow-xl space-y-2 min-w-[200px]">
                    <span class="text-3xl md:text-4xl font-black text-amber-400 block tracking-tight">
                        <?= count($saleProducts) ?>
                    </span>
                    <span class="text-[10px] uppercase tracking-widest text-rose-200 font-bold block">
                        <?= $currentLang === 'bn' ? 'টি অফার পোশাক' : 'Items On Sale' ?>
                    </span>
                    <div class="pt-2">
                        <span
                            class="inline-block px-3 py-1 bg-amber-400 text-slate-950 font-black text-[10px] rounded-xl shadow-xs">
                            <?= $currentLang === 'bn' ? 'সর্বোচ্চ ৫০% পর্যন্ত ছাড়' : 'Up to 50% OFF' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Tabs & Sort Bar Section -->
        <div class="flex flex-col lg:flex-row items-center justify-between gap-4 bg-white p-4 rounded-3xl border border-slate-200/80 shadow-xs">
            
            <!-- Category Filter Tabs (All Items, Men, Women, Kids) -->
            <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                <?php 
                $categoriesList = [
                    'all' => ['bn' => 'সকল আইটেম', 'en' => 'All Items'],
                    'men' => ['bn' => 'মেন (Men)', 'en' => 'Men'],
                    'women' => ['bn' => 'ওমেন (Women)', 'en' => 'Women'],
                    'kids' => ['bn' => 'কিডস (Kids)', 'en' => 'Kids']
                ];

                foreach ($categoriesList as $key => $label):
                    $isActiveCat = ($categoryFilter === $key);
                    $tabUrl = "?category={$key}&sort={$sortBy}";
                ?>
                    <a href="<?= $tabUrl ?>" 
                       class="px-4 py-2.5 rounded-2xl text-xs font-extrabold transition shadow-2xs <?= $isActiveCat ? 'bg-rose-600 text-white shadow-rose-600/20' : 'bg-slate-100/80 text-slate-700 hover:bg-slate-200' ?>">
                        <?= $label[$currentLang] ?? $label['en'] ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Custom Single-Line Sort Dropdown -->
            <div class="flex items-center gap-2.5 w-full lg:w-auto justify-end">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider shrink-0 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                    </svg>
                    <?= $currentLang === 'bn' ? 'সর্ট করুন:' : 'Sort By:' ?>
                </span>

                <div class="relative inline-block text-left w-[240px] sm:w-[270px] shrink-0" id="customSortDropdown">
                    <button type="button" id="sortDropdownBtn"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 bg-white hover:bg-slate-50 text-slate-800 rounded-2xl text-xs font-bold border border-slate-200/90 shadow-2xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition cursor-pointer">
                        <span class="whitespace-nowrap font-bold text-slate-800">
                            <?php
                            if ($sortBy === 'price_low') {
                                echo $currentLang === 'bn' ? '📉 দাম: কম থেকে বেশি' : '📉 Price: Low to High';
                            } elseif ($sortBy === 'price_high') {
                                echo $currentLang === 'bn' ? '📈 দাম: বেশি থেকে কম' : '📈 Price: High to Low';
                            } elseif ($sortBy === 'newest') {
                                echo $currentLang === 'bn' ? '✨ নতুন কালেকশন' : '✨ Newest Arrivals';
                            } else {
                                echo $currentLang === 'bn' ? '🔥 সর্বোচ্চ ছাড়' : '🔥 Highest Discount';
                            }
                            ?>
                        </span>
                        <svg class="w-4 h-4 text-slate-400 shrink-0 ml-1 transition-transform duration-200"
                            id="sortArrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Floating Rounded Options -->
                    <div id="sortDropdownMenu"
                        class="hidden absolute right-0 mt-2 w-full bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-50 space-y-0.5 transition-all">

                        <a href="?category=<?= $categoryFilter ?>&sort=highest_discount"
                            class="flex items-center gap-2 px-3.5 py-2.5 text-xs font-bold transition mx-1 rounded-xl whitespace-nowrap <?= $sortBy === 'highest_discount' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' ?>">
                            <span>🔥</span>
                            <span><?= $currentLang === 'bn' ? 'সর্বোচ্চ ছাড়' : 'Highest Discount' ?></span>
                        </a>

                        <a href="?category=<?= $categoryFilter ?>&sort=price_low"
                            class="flex items-center gap-2 px-3.5 py-2.5 text-xs font-bold transition mx-1 rounded-xl whitespace-nowrap <?= $sortBy === 'price_low' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' ?>">
                            <span>📉</span>
                            <span><?= $currentLang === 'bn' ? 'দাম: কম থেকে বেশি' : 'Price: Low to High' ?></span>
                        </a>

                        <a href="?category=<?= $categoryFilter ?>&sort=price_high"
                            class="flex items-center gap-2 px-3.5 py-2.5 text-xs font-bold transition mx-1 rounded-xl whitespace-nowrap <?= $sortBy === 'price_high' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' ?>">
                            <span>📈</span>
                            <span><?= $currentLang === 'bn' ? 'দাম: বেশি থেকে কম' : 'Price: High to Low' ?></span>
                        </a>

                        <a href="?category=<?= $categoryFilter ?>&sort=newest"
                            class="flex items-center gap-2 px-3.5 py-2.5 text-xs font-bold transition mx-1 rounded-xl whitespace-nowrap <?= $sortBy === 'newest' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' ?>">
                            <span>✨</span>
                            <span><?= $currentLang === 'bn' ? 'নতুন কালেকশন' : 'Newest Arrivals' ?></span>
                        </a>

                    </div>
                </div>
            </div>
        </div>

        <!-- Products Grid Area -->
        <?php if (empty($saleProducts)): ?>
            <div class="bg-white rounded-3xl p-16 text-center border border-slate-200/80 shadow-xs space-y-4">
                <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center mx-auto">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-800">
                    <?= $currentLang === 'bn' ? 'বর্তমানে কোনো ডিসকাউন্ট অফার নেই' : 'No Sale Items Available' ?>
                </h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    <?= $currentLang === 'bn' ? 'আমাদের নতুন কালেকশনের পোশাকগুলো ক্যাটালগ থেকে দেখে নিন।' : 'Check out our latest collections from the shop catalog.' ?>
                </p>
                <a href="/online-shopping-management/shop.php"
                    class="inline-block px-5 py-2.5 bg-slate-950 text-white rounded-2xl text-xs font-bold transition shadow-lg">
                    <?= $currentLang === 'bn' ? 'শপে যান' : 'Go to Shop' ?>
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($saleProducts as $item):
                    $originalPrice = (float) ($item['price'] ?? 0);
                    $discountPercent = (float) ($item['discount_percent'] ?? 0);

                    // Fixed calculation lines
                    $savedAmount = $originalPrice * ($discountPercent / 100);
                    $finalPrice = $originalPrice - $savedAmount;
                    ?>
                    <div
                        class="bg-white rounded-3xl border border-rose-100/80 overflow-hidden shadow-xs hover:shadow-2xl hover:border-rose-300 transition duration-300 flex flex-col justify-between group relative">

                        <!-- Top Offer Badge with Bounce Animation & Rose-Amber Gradient -->
                        <div
                            class="absolute top-3 left-3 z-20 bg-gradient-to-r from-rose-600 to-amber-600 text-white text-[11px] font-black px-3.5 py-1.5 rounded-2xl shadow-lg flex items-center gap-1.5 animate-bounce">
                            <span>🔥</span>
                            <span><?= round($discountPercent) ?>% OFF</span>
                        </div>

                        <!-- Image Display -->
                        <div class="relative h-72 bg-slate-100 overflow-hidden">
                            <a href="/online-shopping-management/product-details.php?id=<?= e((string) $item['id']) ?>">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="/online-shopping-management/<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>"
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                <?php else: ?>
                                    <div
                                        class="w-full h-full flex items-center justify-center text-slate-300 font-bold text-xs uppercase">
                                        NOVERA SALE
                                    </div>
                                <?php endif; ?>
                            </a>
                        </div>

                        <!-- Content Section -->
                        <div class="p-5 space-y-3 flex-1 flex flex-col justify-between">
                            <div class="space-y-1.5">
                                <span class="text-[10px] font-bold text-rose-500 uppercase tracking-widest block">
                                    <?= e($item['category_name'] ?? 'Limited Edition') ?>
                                </span>
                                <a href="/online-shopping-management/product-details.php?id=<?= e((string) $item['id']) ?>"
                                    class="block">
                                    <h3 class="font-bold text-slate-900 text-sm group-hover:text-rose-600 transition truncate">
                                        <?= e($item['name']) ?>
                                    </h3>
                                </a>
                                <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                    <?= e($item['description'] ?? '') ?>
                                </p>
                            </div>

                            <!-- Pricing & Saving Indicator -->
                            <div class="pt-3 flex items-center justify-between border-t border-slate-100">
                                <div>
                                    <span
                                        class="text-[10px] text-slate-400 font-bold uppercase block"><?= $currentLang === 'bn' ? 'অফার মূল্য' : 'Offer Price' ?></span>
                                    <div class="flex items-baseline gap-1.5">
                                        <span
                                            class="text-base font-black text-rose-600">৳<?= number_format($finalPrice, 0) ?></span>
                                        <span
                                            class="text-xs font-semibold text-slate-400 line-through">৳<?= number_format($originalPrice, 0) ?></span>
                                    </div>
                                    <p class="text-[10px] font-black text-emerald-600 mt-0.5">
                                        <?= $currentLang === 'bn' ? 'আপনার সাশ্রয় ৳' . number_format($savedAmount, 0) : 'You Save ৳' . number_format($savedAmount, 0) ?>
                                    </p>
                                </div>

                                <a href="/online-shopping-management/product-details.php?id=<?= e((string) $item['id']) ?>"
                                    class="px-3.5 py-2.5 bg-rose-600 hover:bg-rose-700 active:scale-95 text-white font-extrabold rounded-2xl text-xs transition flex items-center gap-1.5 shadow-md shadow-rose-600/20">
                                    <span><?= $currentLang === 'bn' ? 'অফার নিন' : 'Grab Offer' ?></span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </a>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const btn = document.getElementById("sortDropdownBtn");
        const menu = document.getElementById("sortDropdownMenu");
        const arrow = document.getElementById("sortArrow");

        if (btn && menu) {
            btn.addEventListener("click", function (e) {
                e.stopPropagation();
                menu.classList.toggle("hidden");
                if (arrow) arrow.classList.toggle("rotate-180");
            });

            document.addEventListener("click", function (e) {
                if (!menu.contains(e.target) && !btn.contains(e.target)) {
                    menu.classList.add("hidden");
                    if (arrow) arrow.classList.remove("rotation-180");
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>