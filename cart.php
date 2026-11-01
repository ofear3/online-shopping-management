<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$currentLang = $_SESSION['lang'] ?? 'en';

// কার্ট ইনিশিয়ালাইজেশন
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ১. কার্ট অ্যাকশন প্রসেসিং
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    // Add to Cart
    if ($action === 'add' || $productId > 0) {
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $size     = trim($_POST['size'] ?? 'M');

        if ($productId > 0) {
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($product) {
                $cartKey = $productId . '_' . $size;
                
                if (isset($_SESSION['cart'][$cartKey])) {
                    $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
                } else {
                    $price = (float)$product['price'];
                    $disc  = (float)($product['discount_percent'] ?? 0);
                    $final = $disc > 0 ? ($price - ($price * ($disc / 100))) : $price;

                    $_SESSION['cart'][$cartKey] = [
                        'id'       => $product['id'],
                        'name'     => $product['name'],
                        'price'    => $final,
                        'original' => $price,
                        'image'    => $product['image'] ?? '',
                        'size'     => $size,
                        'quantity' => $quantity
                    ];
                }
            }
        }
        header("Location: /online-shopping-management/cart.php");
        exit;
    }

    // Update Quantity
    if ($action === 'update') {
        $cartKey = $_POST['cart_key'] ?? '';
        $newQty  = (int)($_POST['quantity'] ?? 1);

        if (isset($_SESSION['cart'][$cartKey])) {
            if ($newQty > 0) {
                $_SESSION['cart'][$cartKey]['quantity'] = $newQty;
            } else {
                unset($_SESSION['cart'][$cartKey]);
            }
        }
        header("Location: /online-shopping-management/cart.php");
        exit;
    }

    // Remove Item
    if ($action === 'remove') {
        $cartKey = $_POST['cart_key'] ?? '';
        if (isset($_SESSION['cart'][$cartKey])) {
            unset($_SESSION['cart'][$cartKey]);
        }
        header("Location: /online-shopping-management/cart.php");
        exit;
    }
}

// হিসাব-নিকাশ
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += ($item['price'] * $item['quantity']);
}

$shipping   = ($subtotal > 0) ? 100.00 : 0.00; // ১০০ টাকা শিপিং চার্জ
$vat        = $subtotal * 0.05; // ৫% ভ্যাট
$grandTotal = $subtotal > 0 ? ($subtotal + $shipping + $vat) : 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-[85vh] py-8 sm:py-12 px-3 sm:px-6 lg:px-8 bg-slate-50/60">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-emerald-950 rounded-2xl sm:rounded-3xl p-6 sm:p-10 text-white shadow-2xl relative overflow-hidden flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="space-y-2 text-center sm:text-left">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 font-bold text-[10px] sm:text-[11px] rounded-full uppercase tracking-wider">
                    🛒 NOVERA Shopping Bag
                </span>
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight">
                    <?= $currentLang === 'bn' ? 'আপনার শপিং কার্ট' : 'Your Shopping Cart' ?>
                </h1>
            </div>
            <a href="/online-shopping-management/shop.php" class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-2xl border border-white/20 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span><?= $currentLang === 'bn' ? 'আরও কেনাকাটা করুন' : 'Continue Shopping' ?></span>
            </a>
        </div>

        <?php if (empty($_SESSION['cart'])): ?>
            <!-- Empty Cart -->
            <div class="bg-white rounded-3xl p-10 sm:p-16 border border-slate-200/80 shadow-xl text-center space-y-5 max-w-2xl mx-auto">
                <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto shadow-inner">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <div class="space-y-2">
                    <h2 class="text-xl sm:text-2xl font-black text-slate-900">
                        <?= $currentLang === 'bn' ? 'আপনার কার্টটি খালি রয়েছে!' : 'Your Cart is Currently Empty!' ?>
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 leading-relaxed max-w-md mx-auto">
                        <?= $currentLang === 'bn' ? 'নোভেরা এক্সক্লুসিভ কালেকশন থেকে আপনার পছন্দের পোশাক কার্টে যোগ করুন।' : 'Explore our high-end luxury catalog and discover tailored apparel crafted just for you.' ?>
                    </p>
                </div>
                <div class="pt-2">
                    <a href="/online-shopping-management/shop.php" class="inline-flex py-3.5 px-8 bg-slate-950 hover:bg-slate-850 text-white text-xs font-bold rounded-2xl shadow-lg transition">
                        <?= $currentLang === 'bn' ? 'শপ পেজে যান' : 'Explore Shop' ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Items Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Items List -->
                <div class="lg:col-span-7 space-y-4">
                    <div class="bg-white rounded-3xl p-5 sm:p-8 border border-slate-200/80 shadow-xl space-y-4">
                        <h2 class="text-base font-black text-slate-900 uppercase tracking-tight border-b border-slate-100 pb-4">
                            <?= $currentLang === 'bn' ? 'কার্টে থাকা আইটেম (' . count($_SESSION['cart']) . ')' : 'Cart Items (' . count($_SESSION['cart']) . ')' ?>
                        </h2>

                        <div class="divide-y divide-slate-100 space-y-4">
                            <?php foreach ($_SESSION['cart'] as $key => $item): ?>
                                <div class="pt-4 first:pt-0 flex flex-col sm:flex-row items-center justify-between gap-4">
                                    
                                    <div class="flex items-center gap-4 w-full sm:w-auto">
                                        <div class="w-20 h-24 bg-slate-100 rounded-2xl overflow-hidden shrink-0 border border-slate-100">
                                            <?php if (!empty($item['image'])): ?>
                                                <img src="/online-shopping-management/<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-[10px] font-bold text-slate-400">NOVERA</div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="space-y-1">
                                            <h3 class="font-bold text-xs sm:text-sm text-slate-900 line-clamp-1"><?= e($item['name']) ?></h3>
                                            <span class="inline-block px-2.5 py-0.5 bg-slate-100 text-slate-700 font-extrabold text-[10px] rounded-lg">
                                                Size: <?= e($item['size']) ?>
                                            </span>
                                            <div class="text-xs font-black text-rose-600 pt-1">
                                                ৳<?= number_format($item['price'], 0) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                                        <!-- Quantity -->
                                        <form action="/online-shopping-management/cart.php" method="POST" class="flex items-center bg-slate-50 border border-slate-200 rounded-2xl p-1">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="cart_key" value="<?= e($key) ?>">
                                            
                                            <button type="submit" name="quantity" value="<?= $item['quantity'] - 1 ?>" class="w-8 h-8 flex items-center justify-center font-bold text-slate-600 hover:bg-white rounded-xl transition cursor-pointer">-</button>
                                            <span class="w-8 text-center text-xs font-bold text-slate-800"><?= $item['quantity'] ?></span>
                                            <button type="submit" name="quantity" value="<?= $item['quantity'] + 1 ?>" class="w-8 h-8 flex items-center justify-center font-bold text-slate-600 hover:bg-white rounded-xl transition cursor-pointer">+</button>
                                        </form>

                                        <!-- Total -->
                                        <div class="text-right min-w-[70px]">
                                            <span class="text-xs sm:text-sm font-black text-slate-900">৳<?= number_format($item['price'] * $item['quantity'], 0) ?></span>
                                        </div>

                                        <!-- Remove -->
                                        <form action="/online-shopping-management/cart.php" method="POST">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="cart_key" value="<?= e($key) ?>">
                                            <button type="submit" class="w-9 h-9 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl flex items-center justify-center transition cursor-pointer" title="Remove Item">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xl space-y-6">
                        <h2 class="text-base font-black text-slate-900 uppercase tracking-tight border-b border-slate-100 pb-4">
                            <?= $currentLang === 'bn' ? 'অর্ডার সারসংক্ষেপ' : 'Order Summary' ?>
                        </h2>

                        <div class="space-y-3 text-xs">
                            <div class="flex items-center justify-between text-slate-600">
                                <span><?= $currentLang === 'bn' ? 'সাবটোটাল' : 'Subtotal' ?></span>
                                <span class="font-bold text-slate-900">৳<?= number_format($subtotal, 0) ?></span>
                            </div>
                            <div class="flex items-center justify-between text-slate-600">
                                <span><?= $currentLang === 'bn' ? 'শিপিং চার্জ' : 'Shipping Fee' ?></span>
                                <span class="font-bold text-slate-900">৳<?= number_format($shipping, 0) ?></span>
                            </div>
                            <div class="flex items-center justify-between text-slate-600">
                                <span><?= $currentLang === 'bn' ? 'ভ্যাট (৫%)' : 'Estimated VAT (5%)' ?></span>
                                <span class="font-bold text-slate-900">৳<?= number_format($vat, 0) ?></span>
                            </div>

                            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                                <span class="text-sm font-black text-slate-900"><?= $currentLang === 'bn' ? 'সর্বমোট' : 'Grand Total' ?></span>
                                <span class="text-lg font-black text-rose-600">৳<?= number_format($grandTotal, 0) ?></span>
                            </div>
                        </div>

                        <!-- Checkout Button -->
                        <div class="pt-2">
                            <a href="/online-shopping-management/checkout.php" class="w-full py-4 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white text-xs font-black rounded-2xl shadow-lg transition flex items-center justify-center gap-2 text-center block">
                                <span><?= $currentLang === 'bn' ? 'চেকআউটে এগিয়ে যান' : 'Proceed to Checkout' ?></span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>