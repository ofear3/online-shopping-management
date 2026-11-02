<?php
declare(strict_types=1);

// ১. সেশন ও ডাটাবেজ লোড
session_name('admin_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$dbConfigPath = __DIR__ . '/../config/db.php';
if (file_exists($dbConfigPath)) {
    require_once $dbConfigPath;
} else {
    http_response_code(500);
    exit('Critical Error: Database configuration file not found at config/db.php.');
}

// ২. সেশন সিকিউরিটি চেক
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$currentLang = $_SESSION['lang'] ?? 'en';

// ৩. ইনভেন্টরি ফর্ম প্রসেসিং (HTML আউটপুটের আগেই সম্পন্ন হবে)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action A: Quick Stock Restock
    if ($action === 'restock_inventory') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $import_qty = (int)($_POST['import_qty'] ?? 0);

        if ($product_id > 0 && $import_qty > 0) {
            $stmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $import_qty, $product_id);
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = "Stock Restocked Successfully (+{$import_qty} pcs)";
                    header('Location: inventory.php');
                    exit;
                }
                $stmt->close();
            }
        }
    }

    // Action B: Size & Discount Offer Update
    if ($action === 'manage_clothing_offer') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $discount_percent = (int)($_POST['discount_percent'] ?? 0);
        $size = trim($_POST['size'] ?? 'Free Size');

        if ($product_id > 0) {
            $stmt = $conn->prepare("UPDATE products SET discount_percent = ?, size = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("isi", $discount_percent, $size, $product_id);
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = "Clothing Size & Clearance Offer Updated!";
                    header('Location: inventory.php');
                    exit;
                }
                $stmt->close();
            }
        }
    }
}

// ৪. সার্চ এবং ডাটা ফেচিং
$search = trim($_GET['search'] ?? '');
$cat_id = (int)($_GET['category_id'] ?? 0);

$where_clauses = [];
if (!empty($search)) {
    $safe_search = $conn->real_escape_string($search);
    $where_clauses[] = "(p.name LIKE '%{$safe_search}%' OR p.size LIKE '%{$safe_search}%')";
}
if ($cat_id > 0) {
    $where_clauses[] = "p.category_id = {$cat_id}";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$total_items = 0;
$low_stock_cnt = 0;
$out_of_stock = 0;

$stat_res = $conn->query("
    SELECT 
        COUNT(*) AS total_items,
        SUM(CASE WHEN stock <= 5 THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS out_stock
    FROM products
");

if ($stat_res && $stats = $stat_res->fetch_assoc()) {
    $total_items = (int)($stats['total_items'] ?? 0);
    $low_stock_cnt = (int)($stats['low_stock'] ?? 0);
    $out_of_stock = (int)($stats['out_stock'] ?? 0);
}

$query = "
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    {$where_sql}
    ORDER BY 
        CASE 
            WHEN p.stock <= 0 THEN 1
            WHEN p.stock <= 5 THEN 2
            ELSE 3
        END ASC, p.id DESC
";
$products_list = $conn->query($query);
$categories_res = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");

// ৫. লজিক শেষ হওয়ার পর হেডার এবং HTML রেন্ডার শুরু
require_once __DIR__ . '/header.php';
?>

<div class="p-6 md:p-8 space-y-6 max-w-full overflow-hidden flex flex-col h-[calc(100vh-65px)]">

    <!-- Page Header Banner -->
    <div class="bg-gradient-to-r from-zinc-950 via-zinc-900 to-indigo-950 rounded-3xl p-5 md:p-6 text-white shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-4 border border-zinc-800/80 flex-shrink-0">
        <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="space-y-1 text-center md:text-left relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 font-bold text-[10px] rounded-full uppercase tracking-wider">
                📦 Inventory & Stock
            </span>
            <h1 class="text-xl md:text-3xl font-black tracking-tight">
                <?= $currentLang === 'bn' ? 'ইনভেন্টরি ও স্টক কন্ট্রোল' : 'Clothing Inventory & Stock Control' ?>
            </h1>
            <p class="text-xs text-zinc-400">
                <?= $currentLang === 'bn' ? 'অ্যাপারেল স্টক লেভেল, সাইজ ভ্যারিয়েন্ট, লো-স্টক অ্যালার্ট এবং প্রমোশনাল সেলস ট্র্যাক করুন।' : 'Track apparel stock levels, size variants, low-stock reorder alerts, and promotional sales.' ?>
            </p>
        </div>
        <div class="px-4 py-2 rounded-2xl bg-zinc-900/90 border border-zinc-800 text-center shadow-inner relative z-10 flex-shrink-0">
            <span class="block text-[9px] text-zinc-400 uppercase tracking-widest font-bold">Total Catalog</span>
            <span class="text-xl font-black text-indigo-400"><?= $total_items ?></span>
        </div>
    </div>

    <!-- Summary Widgets -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 flex-shrink-0">
        <div class="p-3.5 bg-zinc-900 border border-zinc-800/80 rounded-2xl flex items-center justify-between shadow-lg">
            <div>
                <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Total Apparel Catalog</p>
                <h3 class="text-xl font-black text-zinc-100 mt-0.5"><?= $total_items ?> <span class="text-xs font-normal text-zinc-500">Designs</span></h3>
            </div>
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold text-sm">👔</div>
        </div>

        <div class="p-3.5 bg-zinc-900 border border-zinc-800/80 rounded-2xl flex items-center justify-between shadow-lg">
            <div>
                <p class="text-[10px] font-bold text-amber-400 uppercase tracking-wider">Low Stock (≤ 5 pcs)</p>
                <h3 class="text-xl font-black text-amber-400 mt-0.5"><?= $low_stock_cnt ?> <span class="text-xs font-normal text-zinc-500">Items</span></h3>
            </div>
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center font-bold text-sm">⚠️</div>
        </div>

        <div class="p-3.5 bg-zinc-900 border border-zinc-800/80 rounded-2xl flex items-center justify-between shadow-lg">
            <div>
                <p class="text-[10px] font-bold text-rose-400 uppercase tracking-wider">Out of Stock (Stock 0)</p>
                <h3 class="text-xl font-black text-rose-400 mt-0.5"><?= $out_of_stock ?> <span class="text-xs font-normal text-zinc-500">Items</span></h3>
            </div>
            <div class="w-9 h-9 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center font-bold text-sm">🚨</div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <form method="GET" action="inventory.php" class="flex flex-wrap items-center gap-3 bg-zinc-900 p-3 rounded-2xl border border-zinc-800/80 shadow-lg flex-shrink-0">
        <div class="flex-1 min-w-[200px]">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search dress name or size (e.g., T-Shirt, XL, Denim)..." class="w-full bg-zinc-950 border border-zinc-800 text-xs text-zinc-200 rounded-xl px-3.5 py-2 focus:outline-none focus:border-indigo-500">
        </div>

        <div class="w-full sm:w-48">
            <select name="category_id" class="w-full bg-zinc-950 border border-zinc-800 text-xs text-zinc-200 rounded-xl px-3.5 py-2 focus:outline-none focus:border-indigo-500 cursor-pointer">
                <option value="0">All Categories</option>
                <?php if ($categories_res): ?>
                    <?php while ($c = $categories_res->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $cat_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>

        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition shadow-md shadow-indigo-600/20 cursor-pointer">
            Filter
        </button>
        
        <?php if (!empty($search) || $cat_id > 0): ?>
            <a href="inventory.php" class="px-3 py-2 text-zinc-400 hover:text-zinc-200 text-xs transition font-semibold">Reset Search</a>
        <?php endif; ?>
    </form>

    <!-- Table Card with Inner Scroll ONLY -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl overflow-hidden shadow-xl flex-1 flex flex-col min-h-0">
        <div class="p-3.5 border-b border-zinc-800 bg-zinc-950/40 flex items-center justify-between flex-shrink-0">
            <h3 class="text-xs font-bold text-zinc-300 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                Stock Inventory
            </h3>
        </div>

        <!-- Inner Scrollable Container ONLY -->
        <div class="flex-1 overflow-y-auto min-h-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="sticky top-0 z-10 bg-zinc-950">
                        <tr class="border-b border-zinc-800 text-zinc-400 uppercase tracking-wider text-[11px] font-bold">
                            <th class="py-3.5 px-4 bg-zinc-950">Item & Category</th>
                            <th class="py-3.5 px-4 bg-zinc-950">Size Variant</th>
                            <th class="py-3.5 px-4 bg-zinc-950">Stock Quantity</th>
                            <th class="py-3.5 px-4 bg-zinc-950">Re-order Status</th>
                            <th class="py-3.5 px-4 bg-zinc-950">Clearance Sale</th>
                            <th class="py-3.5 px-4 bg-zinc-950 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300 font-medium">
                        <?php if ($products_list && $products_list->num_rows > 0): ?>
                            <?php while ($item = $products_list->fetch_assoc()): ?>
                                <tr class="hover:bg-zinc-800/40 transition">
                                    <td class="py-3.5 px-4">
                                        <p class="font-bold text-white"><?= e($item['name']) ?></p>
                                        <p class="text-[11px] text-zinc-400"><?= e($item['category_name'] ?? 'General Outfit') ?> | Price: <span class="text-emerald-400">৳<?= number_format((float)$item['price'], 2) ?></span></p>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <span class="px-2.5 py-1 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-300 font-bold text-[10px]">
                                            <?= e($item['size'] ?? 'Free Size') ?>
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-4 font-bold text-white">
                                        <span class="text-sm"><?= (int)$item['stock'] ?></span> <span class="text-[11px] text-zinc-400 font-normal">pcs</span>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <?php if ($item['stock'] <= 0): ?>
                                            <span class="px-2.5 py-1 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-400 font-bold text-[10px] inline-flex items-center gap-1">
                                                🚨 Sold Out! Re-order Needed
                                            </span>
                                        <?php elseif ($item['stock'] <= 5): ?>
                                            <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 font-semibold text-[10px] inline-flex items-center gap-1">
                                                ⚠️ Low Stock! Restock Soon
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-medium text-[10px] inline-flex items-center gap-1">
                                                ✅ In Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <?php if ((int)$item['discount_percent'] > 0): ?>
                                            <span class="px-2.5 py-1 rounded-lg bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 font-bold text-[10px]">
                                                🏷️ <?= (int)$item['discount_percent'] ?>% OFF
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[11px] text-zinc-500">Regular Price</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="py-3.5 px-4 text-right space-x-1.5">
                                        <button type="button" 
                                                onclick="openRestockModal(<?= $item['id'] ?>, '<?= e(addslashes($item['name'])) ?>')" 
                                                class="px-2.5 py-1.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-bold text-[11px] rounded-xl border border-zinc-700 transition cursor-pointer">
                                            + Add Stock
                                        </button>

                                        <button type="button" 
                                                onclick="openOfferModal(<?= $item['id'] ?>, '<?= e(addslashes($item['name'])) ?>', '<?= e(addslashes($item['size'] ?? 'Free Size')) ?>', <?= (int)$item['discount_percent'] ?>)" 
                                                class="px-2.5 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 font-bold text-[11px] rounded-xl transition cursor-pointer">
                                            🏷️ Size & Offer
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center text-zinc-500 text-xs">No clothing items matched your search query.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Restock Modal -->
<div id="restockModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md hidden">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-sm font-bold text-white">Add New Cloth Stock</h3>
            <button onclick="closeRestockModal()" class="text-zinc-500 hover:text-zinc-300 font-bold cursor-pointer">✕</button>
        </div>

        <form method="POST" action="inventory.php" class="space-y-4">
            <input type="hidden" name="action" value="restock_inventory">
            <input type="hidden" id="restock_prod_id" name="product_id" value="">

            <div>
                <p class="text-xs text-zinc-400">Dress Name:</p>
                <p id="restock_prod_name" class="text-sm font-bold text-white mt-0.5"></p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Quantity to Add (pcs)</label>
                <input type="number" name="import_qty" min="1" value="10" required class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                <button type="button" onclick="closeRestockModal()" class="px-4 py-2 text-xs font-medium bg-zinc-800 text-zinc-300 rounded-xl cursor-pointer">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-md cursor-pointer">Confirm Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Size & Offer Settings Modal -->
<div id="offerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md hidden">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-sm font-bold text-white">Update Size & Discount</h3>
            <button onclick="closeOfferModal()" class="text-zinc-500 hover:text-zinc-300 font-bold cursor-pointer">✕</button>
        </div>

        <form method="POST" action="inventory.php" class="space-y-4">
            <input type="hidden" name="action" value="manage_clothing_offer">
            <input type="hidden" id="offer_prod_id" name="product_id" value="">

            <div>
                <p class="text-xs text-zinc-400">Dress Name:</p>
                <p id="offer_prod_name" class="text-sm font-bold text-white mt-0.5"></p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Cloth Size (S, M, L, XL, XXL)</label>
                <input type="text" id="offer_size" name="size" placeholder="e.g. M, L, XL" class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Clearance Discount (% OFF)</label>
                <input type="number" id="offer_disc_percent" name="discount_percent" min="0" max="90" placeholder="e.g. 20" class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                <button type="button" onclick="closeOfferModal()" class="px-4 py-2 text-xs font-medium bg-zinc-800 text-zinc-300 rounded-xl cursor-pointer">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold bg-amber-500 hover:bg-amber-400 text-zinc-950 rounded-xl shadow-md cursor-pointer">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRestockModal(id, name) {
        document.getElementById('restock_prod_id').value = id;
        document.getElementById('restock_prod_name').innerText = name;
        document.getElementById('restockModal').classList.remove('hidden');
    }
    function closeRestockModal() { document.getElementById('restockModal').classList.add('hidden'); }

    function openOfferModal(id, name, size, discount) {
        document.getElementById('offer_prod_id').value = id;
        document.getElementById('offer_prod_name').innerText = name;
        document.getElementById('offer_size').value = size;
        document.getElementById('offer_disc_percent').value = discount;
        document.getElementById('offerModal').classList.remove('hidden');
    }
    function closeOfferModal() { document.getElementById('offerModal').classList.add('hidden'); }
</script>

</main>
</body>
</html>