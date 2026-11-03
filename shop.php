<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$currentLang = $_SESSION['lang'] ?? 'en';

// ফিল্টার ও সার্চ প্যারামিটার নেওয়া
$selectedCategory = $_GET['category'] ?? 'all';
$searchQuery      = trim($_GET['search'] ?? '');
$sortBy           = $_GET['sort'] ?? 'newest';

// কুয়েরি বিল্ড করা
$whereClauses = [];
$params = [];
$types = '';

// ক্যাটাগরি ফিল্টার
if ($selectedCategory !== 'all') {
    if (is_numeric($selectedCategory)) {
        $whereClauses[] = "category_id = ?";
        $params[] = (int)$selectedCategory;
        $types .= 'i';
    } else {
        $whereClauses[] = "category_id IN (SELECT id FROM categories WHERE LOWER(name) LIKE ?)";
        $params[] = '%' . strtolower($selectedCategory) . '%';
        $types .= 's';
    }
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(name LIKE ? OR description LIKE ?)";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

$sql = "SELECT * FROM products";
if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

// সর্টিং লজিক
if ($sortBy === 'price_low') {
    $sql .= " ORDER BY price ASC";
} elseif ($sortBy === 'price_high') {
    $sql .= " ORDER BY price DESC";
} else {
    $sql .= " ORDER BY id DESC";
}

$products = [];
$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8 bg-slate-50/60">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Hero Showcase Banner -->
        <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-emerald-950 rounded-3xl p-8 md:p-12 text-white shadow-xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-3 max-w-xl z-10 text-center md:text-left">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 font-bold text-[11px] rounded-full uppercase tracking-wider">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    NOVERA Showcase 2026
                </span>
                <h1 class="text-3xl md:text-4xl font-black tracking-tight">
                    <?= $currentLang === 'bn' ? 'প্রিমিয়াম পোশাক কালেকশন' : 'Exclusive Fashion Collection' ?>
                </h1>
                <p class="text-xs md:text-sm text-slate-300 leading-relaxed">
                    <?= $currentLang === 'bn' ? 'আপনার রুচিসম্মত পোশাক বেছে নিন NOVERA-র এক্সক্লুসিভ কালেকশন থেকে।' : 'Handcrafted apparel designed for elegance, high quality, and comfort.' ?>
                </p>
            </div>
            
            <div class="z-10 bg-white/10 backdrop-blur-md px-6 py-4 rounded-2xl border border-white/10 text-center shadow-inner shrink-0">
                <span class="block text-2xl font-black text-emerald-400"><?= count($products) ?></span>
                <span class="text-[10px] uppercase tracking-widest text-slate-300 font-bold"><?= $currentLang === 'bn' ? 'টি আইটেম উপলব্ধ' : 'Items Available' ?></span>
            </div>
        </div>

        <!-- Filter & Custom Soft Rounded Sort Bar -->
        <div class="flex flex-col md:flex-row items-center justify-between gap-4 bg-white p-4 rounded-3xl border border-slate-200/80 shadow-xs">
            
            <!-- Category Tabs -->
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                <?php
                $dbCategories = [];
                $catRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
                if ($catRes) {
                    while ($cRow = $catRes->fetch_assoc()) {
                        $dbCategories[strtolower($cRow['name'])] = $cRow['name'];
                    }
                }
                
                if (empty($dbCategories)) {
                    $dbCategories = ['men' => 'Men', 'women' => 'Women', 'child' => 'Kids'];
                }
                ?>

                <a href="?category=all<?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>&sort=<?= $sortBy ?>"
                   class="px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-1.5 <?= $selectedCategory === 'all' ? 'bg-slate-950 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    <?= $currentLang === 'bn' ? 'সকল পোশাক' : 'All Items' ?>
                </a>

                <?php foreach ($dbCategories as $catKey => $catLabel):
                    $isActive = ($selectedCategory === $catKey || $selectedCategory === strtolower($catLabel));
                ?>
                    <a href="?category=<?= urlencode($catKey) ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>&sort=<?= $sortBy ?>"
                       class="px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-1.5 <?= $isActive ? 'bg-slate-950 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                        <?= htmlspecialchars($catLabel) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Sort Dropdown -->
            <div class="flex items-center gap-2.5 w-full md:w-auto justify-end">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider shrink-0 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                    <?= $currentLang === 'bn' ? 'সর্ট করুন:' : 'Sort By:' ?>
                </span>

                <div class="relative inline-block text-left w-[270px] shrink-0" id="customSortDropdown">
                    <button type="button" id="sortDropdownBtn" 
                            class="w-full flex items-center justify-between px-3.5 py-2.5 bg-white hover:bg-slate-50 text-slate-800 rounded-2xl text-xs font-bold border border-slate-200/90 shadow-2xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition cursor-pointer">
                        <span class="whitespace-nowrap font-bold text-slate-800">
                            <?php 
                                if ($sortBy === 'price_low') {
                                    echo $currentLang === 'bn' ? '📉 দাম: কম থেকে বেশি' : '📉 Price: Low to High';
                                } elseif ($sortBy === 'price_high') {
                                    echo $currentLang === 'bn' ? '📈 দাম: বেশি থেকে কম' : '📈 Price: High to Low';
                                } else {
                                    echo $currentLang === 'bn' ? '✨ নতুন কালেকশন' : '✨ Newest Arrivals';
                                }
                            ?>
                        </span>
                        <svg class="w-4 h-4 text-slate-400 shrink-0 ml-1 transition-transform duration-200" id="sortArrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="sortDropdownMenu" 
                         class="hidden absolute right-0 mt-2 w-full bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-50 space-y-0.5 transition-all">
                        <a href="?category=<?= htmlspecialchars($selectedCategory) ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>&sort=newest" 
                           class="flex items-center gap-2 px-3.5 py-2.5 text-xs font-bold transition mx-1 rounded-xl whitespace-nowrap <?= $sortBy === 'newest' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' ?>">
                            <span class="shrink-0">✨</span>
                            <span><?= $currentLang === 'bn' ? 'নতুন কালেকশন' : 'Newest Arrivals' ?></span>
                        </a>
                        <a href="?category=<?= htmlspecialchars($selectedCategory) ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>&sort=price_low" 
                           class="flex items-center gap-2 px-3.5 py-2.5 text-xs font-bold transition mx-1 rounded-xl whitespace-nowrap <?= $sortBy === 'price_low' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' ?>">
                            <span class="shrink-0">📉</span>
                            <span><?= $currentLang === 'bn' ? 'দাম: কম থেকে বেশি' : 'Price: Low to High' ?></span>
                        </a>
                        <a href="?category=<?= htmlspecialchars($selectedCategory) ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>&sort=price_high" 
                           class="flex items-center gap-2 px-3.5 py-2.5 text-xs font-bold transition mx-1 rounded-xl whitespace-nowrap <?= $sortBy === 'price_high' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' ?>">
                            <span class="shrink-0">📈</span>
                            <span><?= $currentLang === 'bn' ? 'দাম: বেশি থেকে কম' : 'Price: High to Low' ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Grid -->
        <?php if (empty($products)): ?>
            <div class="bg-white rounded-3xl p-16 text-center border border-slate-200/80 shadow-xs space-y-4">
                <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-800">
                    <?= $currentLang === 'bn' ? 'কোনো প্রোডাক্ট পাওয়া যায়নি' : 'No Products Found' ?>
                </h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                    <?= $currentLang === 'bn' ? 'আপনার অনুসন্ধানের সাথে মেলে এমন কোনো পোশাক এই মুহূর্তে নেই।' : 'We could not find any products matching your criteria.' ?>
                </p>
                <a href="shop.php" class="inline-block px-5 py-2.5 bg-slate-950 text-white rounded-2xl text-xs font-bold transition">
                    <?= $currentLang === 'bn' ? 'সব প্রোডাক্ট দেখুন' : 'View All Products' ?>
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($products as $item): 
                    $price = (float)($item['price'] ?? 0);
                    $discountPercent = isset($item['discount_percent']) ? (float)$item['discount_percent'] : 0;
                    $hasDiscount = ($discountPercent > 0);
                    
                    // সঠিক ম্যাথমেটিক্যাল ক্যালকুলেশন
                    $savedAmount = $hasDiscount ? ($price * ($discountPercent / 100)) : 0;
                    $finalPrice = $hasDiscount ? ($price - $savedAmount) : $price;

                    // ইমেজের সঠিক পাথ হ্যান্ডেল করা
                    $rawImage = trim($item['image'] ?? '');
                    $rawImage = ltrim($rawImage, '/');
                    $rawImage = preg_replace('/^(\.\.\/)+/', '', $rawImage);
                    
                    $cleanedImage = str_replace(['online-shopping-management/', 'aatpoure/'], '', $rawImage);
                    $imagePath = !empty($rawImage) ? '/online-shopping-management/' . ltrim($cleanedImage, '/') : '';
                    $fullServerPath = $_SERVER['DOCUMENT_ROOT'] . '/online-shopping-management/' . ltrim($cleanedImage, '/');
                ?>
                    <div class="bg-white rounded-3xl border border-slate-200/80 overflow-hidden shadow-xs hover:shadow-xl transition duration-300 flex flex-col justify-between group">
                        
                        <!-- Image Area (Fixed for Landscape/Portrait without cutting) -->
                        <div class="relative h-72 bg-slate-900/5 overflow-hidden flex items-center justify-center p-3">
                            <a href="product-details.php?id=<?= htmlspecialchars((string)$item['id']) ?>" class="w-full h-full flex items-center justify-center">
                                <?php if (!empty($rawImage) && file_exists($fullServerPath)): ?>
                                    <img src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['name']) ?>" 
                                         class="w-full h-full object-contain object-center group-hover:scale-105 transition duration-500 drop-shadow-sm">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-slate-400 font-bold text-xs uppercase">
                                        NOVERA
                                    </div>
                                <?php endif; ?>
                            </a>

                            <!-- Discount Tag Badge -->
                            <?php if ($hasDiscount): ?>
                                <div class="absolute top-3 left-3 bg-rose-600 text-white text-[10px] font-black px-3 py-1 rounded-xl shadow-md flex items-center gap-1 animate-pulse z-10">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    <span><?= round($discountPercent) ?>% OFF</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Content Area -->
                        <div class="p-5 space-y-3 flex-1 flex flex-col justify-between">
                            <div class="space-y-1.5">
                                <a href="product-details.php?id=<?= htmlspecialchars((string)$item['id']) ?>" class="block">
                                    <h3 class="font-bold text-slate-900 text-sm group-hover:text-amber-600 transition truncate">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </h3>
                                </a>
                                <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                                    <?= htmlspecialchars($item['description'] ?? '') ?>
                                </p>
                            </div>

                            <!-- Pricing in BDT (৳) & Saved Amount -->
                            <div class="pt-3 flex items-center justify-between border-t border-slate-100">
                                <div>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase block"><?= $currentLang === 'bn' ? 'মূল্য' : 'Price' ?></span>
                                    <div class="flex items-baseline gap-1.5">
                                        <?php if ($hasDiscount): ?>
                                            <span class="text-base font-black text-rose-600">৳<?= number_format($finalPrice, 0) ?></span>
                                            <span class="text-xs font-semibold text-slate-400 line-through">৳<?= number_format($price, 0) ?></span>
                                        <?php else: ?>
                                            <span class="text-base font-black text-slate-900">৳<?= number_format($price, 0) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($hasDiscount): ?>
                                        <p class="text-[10px] font-extrabold text-emerald-600 mt-0.5">
                                            <?= $currentLang === 'bn' ? 'সাশ্রয় ৳' . number_format($savedAmount, 0) : 'Save ৳' . number_format($savedAmount, 0) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <a href="product-details.php?id=<?= htmlspecialchars((string)$item['id']) ?>" 
                                   class="px-3.5 py-2.5 bg-amber-500 hover:bg-amber-600 active:scale-95 text-slate-950 font-black rounded-xl text-xs transition flex items-center gap-1.5 shadow-md shadow-amber-500/20">
                                    <span><?= $currentLang === 'bn' ? 'বিস্তারিত' : 'View' ?></span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
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
    document.addEventListener("DOMContentLoaded", function() {
        const btn = document.getElementById("sortDropdownBtn");
        const menu = document.getElementById("sortDropdownMenu");
        const arrow = document.getElementById("sortArrow");

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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>