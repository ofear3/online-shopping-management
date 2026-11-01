<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$currentLang = $_SESSION['lang'] ?? 'en';
$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productId <= 0) {
    header('Location: /online-shopping-management/shop.php');
    exit;
}

// প্রোডাক্ট ডাটাবেজ থেকে ফেচ করা (ক্যাটাগরি বা ডিসকাউন্ট পার্সেন্ট সহ)
$stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? LIMIT 1");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: /online-shopping-management/shop.php');
    exit;
}

$price = (float) ($product['price'] ?? 0);
$discountPercent = (float) ($product['discount_percent'] ?? 0);
$hasDiscount = ($discountPercent > 0);
$finalPrice = $hasDiscount ? ($price - ($price * ($discountPercent / 100))) : $price;

$categoryName = strtolower($product['category_name'] ?? $product['category'] ?? '');

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[85vh] py-6 sm:py-12 px-3 sm:px-6 lg:px-8 bg-slate-50/50 flex items-center justify-center">
    <div
        class="max-w-5xl w-full bg-white rounded-2xl sm:rounded-3xl border border-slate-200/80 shadow-xl overflow-hidden p-5 sm:p-10">

        <!-- Breadcrumb -->
        <nav
            class="mb-5 text-[11px] sm:text-xs font-bold text-slate-400 flex items-center gap-1.5 overflow-x-auto whitespace-nowrap pb-1">
            <a href="/online-shopping-management/index.php" class="hover:text-slate-800 transition">Home</a>
            <span>/</span>
            <a href="/online-shopping-management/shop.php" class="hover:text-slate-800 transition">Shop</a>
            <span>/</span>
            <span
                class="text-emerald-600 uppercase"><?= e($product['category_name'] ?? $product['category'] ?? 'Product') ?></span>
        </nav>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-10 items-start">

            <!-- Left: Product Image Showcase -->
            <div
                class="relative h-80 sm:h-[420px] md:h-[450px] bg-slate-100 rounded-2xl sm:rounded-3xl overflow-hidden shadow-inner border border-slate-100">
                <?php if (!empty($product['image'])): ?>
                    <img src="/online-shopping-management/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>"
                        class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-slate-400 font-bold text-xs uppercase">
                        NOVERA Luxury
                    </div>
                <?php endif; ?>

                <?php if ($hasDiscount): ?>
                    <span
                        class="absolute top-4 right-4 px-3 py-1.5 bg-rose-600 text-white text-xs font-black rounded-xl shadow-md animate-pulse">
                        -<?= round($discountPercent) ?><?= $currentLang === 'bn' ? '% ছাড়' : '% OFF' ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Right: Product Info & Cart Form -->
            <div class="space-y-5 sm:space-y-6 flex flex-col justify-between">

                <div class="space-y-3">
                    <span
                        class="px-3 py-1 bg-slate-100 text-slate-700 font-bold text-[10px] rounded-lg uppercase tracking-wider inline-block">
                        <?= e($product['category_name'] ?? $product['category'] ?? 'Luxury Apparel') ?>
                    </span>
                    <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-snug">
                        <?= e($product['name']) ?>
                    </h1>

                    <!-- Price (BDT ৳) -->
                    <div class="flex items-center gap-3 pt-1">
                        <?php if ($hasDiscount): ?>
                            <span
                                class="text-2xl sm:text-3xl font-black text-rose-600">৳<?= number_format($finalPrice, 0) ?></span>
                            <span
                                class="text-sm sm:text-base font-semibold text-slate-400 line-through">৳<?= number_format($price, 0) ?></span>
                        <?php else: ?>
                            <span
                                class="text-2xl sm:text-3xl font-black text-slate-900">৳<?= number_format($price, 0) ?></span>
                        <?php endif; ?>
                    </div>

                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed pt-2 border-t border-slate-100">
                        <?= e($product['description'] ?? 'No description available for this luxury apparel item.') ?>
                    </p>
                </div>

                <!-- Add to Cart Form (Redirects to cart.php as requested) -->
                <!-- Add to Cart Form -->
                <form action="/online-shopping-management/cart.php" method="POST"
                    class="space-y-5 pt-4 border-t border-slate-100">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">

                    <!-- Size Selection -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            <?= $currentLang === 'bn' ? 'সাইজ নির্বাচন করুন' : 'Select Size' ?>
                        </label>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <?php
                            $sizes = ['S', 'M', 'L', 'XL', 'XXL'];
                            if (strpos($categoryName, 'kid') !== false) {
                                $sizes = ['2-3Y', '4-5Y', '6-7Y', '8-9Y'];
                            } elseif (strpos($categoryName, 'acc') !== false || strpos($categoryName, 'shoe') !== false) {
                                $sizes = ['Standard', 'Free Size'];
                            }

                            foreach ($sizes as $index => $size):
                                ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="size" value="<?= $size ?>" <?= $index === 1 ? 'checked' : '' ?>
                                        class="peer sr-only">
                                    <div
                                        class="px-4 py-2.5 rounded-xl border-2 border-slate-200 flex items-center justify-center text-xs font-bold text-slate-700 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-700 transition">
                                        <?= $size ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            <?= $currentLang === 'bn' ? 'পরিমাণ' : 'Quantity' ?>
                        </label>
                        <div class="flex items-center w-32 bg-slate-50 border border-slate-200 rounded-2xl p-1">
                            <button type="button" onclick="this.parentNode.querySelector('input').stepDown()"
                                class="w-9 h-9 flex items-center justify-center font-bold text-slate-600 hover:bg-white rounded-xl transition cursor-pointer">-</button>
                            <input type="number" name="quantity" value="1" min="1" max="10"
                                class="w-full text-center bg-transparent text-xs font-bold text-slate-800 focus:outline-none">
                            <button type="button" onclick="this.parentNode.querySelector('input').stepUp()"
                                class="w-9 h-9 flex items-center justify-center font-bold text-slate-600 hover:bg-white rounded-xl transition cursor-pointer">+</button>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row gap-3 pt-2">
                        <button type="submit"
                            class="flex-1 py-3.5 sm:py-4 px-6 bg-slate-950 hover:bg-slate-850 active:scale-[0.99] text-white text-xs font-bold rounded-2xl shadow-lg transition flex items-center justify-center gap-2 cursor-pointer">
                            <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span><?= $currentLang === 'bn' ? 'কার্টে যুক্ত করুন' : 'Add to Cart' ?></span>
                        </button>
                        <a href="/online-shopping-management/shop.php"
                            class="py-3.5 sm:py-4 px-6 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-2xl transition text-center flex items-center justify-center">
                            <?= $currentLang === 'bn' ? 'শপে ফিরে যান' : 'Back to Shop' ?>
                        </a>
                    </div>
                </form>

            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>