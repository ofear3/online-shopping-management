<?php
declare(strict_types=1);

// ১. হেডার ফাইল ইনক্লুড করা
require_once __DIR__ . '/header.php';

// ২. ইনভেন্টরি ফর্ম প্রসেসিং
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action A: Quick Stock Restock / Import
    if ($action === 'restock_inventory') {
        $product_id  = (int)($_POST['product_id'] ?? 0);
        $import_qty  = (int)($_POST['import_qty'] ?? 0);

        if ($product_id > 0 && $import_qty > 0) {
            $stmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $import_qty, $product_id);
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = "Stock Restocked Successfully (+{$import_qty} pcs)";
                    echo "<script>window.location.href='inventory.php';</script>";
                    exit;
                }
                $stmt->close();
            }
        }
    }

    // Action B: Size & Discount Offer Update
    if ($action === 'manage_clothing_offer') {
        $product_id       = (int)($_POST['product_id'] ?? 0);
        $discount_percent = (int)($_POST['discount_percent'] ?? 0);
        $size             = trim($_POST['size'] ?? 'Free Size');

        if ($product_id > 0) {
            $stmt = $conn->prepare("UPDATE products SET discount_percent = ?, size = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("isi", $discount_percent, $size, $product_id);
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = "Clothing Size & Clearance Offer Updated!";
                    echo "<script>window.location.href='inventory.php';</script>";
                    exit;
                }
                $stmt->close();
            }
        }
    }
}

// ৩. সার্চ এবং ফিল্টার হ্যান্ডলিং (FIXED)
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

// ৪. ইনভেন্টরি ওভারভিউ সামারি স্ট্যাটস
$total_items    = 0;
$low_stock_cnt  = 0;
$out_of_stock   = 0;

$stat_res = $conn->query("
    SELECT 
        COUNT(*) AS total_items,
        SUM(CASE WHEN stock <= 5 THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS out_stock
    FROM products
");

if ($stat_res && $stats = $stat_res->fetch_assoc()) {
    $total_items   = (int)($stats['total_items'] ?? 0);
    $low_stock_cnt = (int)($stats['low_stock'] ?? 0);
    $out_of_stock  = (int)($stats['out_stock'] ?? 0);
}

// ৫. সার্চ কোয়েরিসহ প্রোডাক্ট ফেচ
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

// ক্যাটাগরি ফিল্টার অপশনের জন্য ক্যাটাগরি লিস্ট
$categories_res = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
?>

<div class="p-6 md:p-8 space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-zinc-100 tracking-tight">Clothing Inventory & Stock Control</h1>
            <p class="text-xs text-zinc-400 mt-1">Track apparel stock levels, size variants, low-stock reorder alerts, and promotional sales</p>
        </div>
    </div>

    <!-- Summary Widgets -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 bg-zinc-900 border border-zinc-800/80 rounded-2xl flex items-center justify-between shadow-lg">
            <div>
                <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Total Apparel Catalog</p>
                <h3 class="text-2xl font-black text-zinc-100 mt-1"><?= $total_items ?> <span class="text-xs font-normal text-zinc-500">Designs</span></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold text-sm">👔</div>
        </div>

        <div class="p-4 bg-zinc-900 border border-zinc-800/80 rounded-2xl flex items-center justify-between shadow-lg">
            <div>
                <p class="text-[10px] font-bold text-amber-400 uppercase tracking-wider">Low Stock (≤ 5 pcs)</p>
                <h3 class="text-2xl font-black text-amber-400 mt-1"><?= $low_stock_cnt ?> <span class="text-xs font-normal text-zinc-500">Items</span></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center font-bold text-sm">⚠️</div>
        </div>

        <div class="p-4 bg-zinc-900 border border-zinc-800/80 rounded-2xl flex items-center justify-between shadow-lg">
            <div>
                <p class="text-[10px] font-bold text-rose-400 uppercase tracking-wider">Out of Stock (Stock 0)</p>
                <h3 class="text-2xl font-black text-rose-400 mt-1"><?= $out_of_stock ?> <span class="text-xs font-normal text-zinc-500">Items</span></h3>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center font-bold text-sm">🚨</div>
        </div>
    </div>

    <!-- Search & Filter Bar (Fix Working Form GET Submission) -->
    <form method="GET" action="inventory.php" class="flex flex-wrap items-center gap-3 bg-zinc-900 p-4 rounded-2xl border border-zinc-800/80">
        <div class="flex-1 min-w-[200px]">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search dress name or size (e.g., T-Shirt, XL, Denim)..." class="w-full bg-zinc-950 border border-zinc-800 text-xs text-zinc-200 rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-emerald-500/60">
        </div>

        <div class="w-full sm:w-48">
            <select name="category_id" class="w-full bg-zinc-950 border border-zinc-800 text-xs text-zinc-200 rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-emerald-500/60 cursor-pointer">
                <option value="0">All Categories</option>
                <?php if ($categories_res): ?>
                    <?php while ($c = $categories_res->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $cat_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>

        <button type="submit" class="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 text-xs font-bold rounded-xl transition cursor-pointer">
            Filter
        </button>
        
        <?php if (!empty($search) || $cat_id > 0): ?>
            <a href="inventory.php" class="px-3 py-2.5 text-zinc-400 hover:text-zinc-200 text-xs transition">Reset Search</a>
        <?php endif; ?>
    </form>

    <!-- Table Card -->
    <div class="bg-zinc-900 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-zinc-800/80 flex items-center justify-between bg-zinc-950/40">
            <h2 class="text-xs font-bold text-zinc-200 uppercase tracking-wider">Garment Stock Inventory</h2>
            <span class="text-[11px] text-zinc-500 font-medium">Sorted by Restock Priority</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-zinc-950/80 border-b border-zinc-800/80 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                        <th class="py-3.5 px-4">Item & Category</th>
                        <th class="py-3.5 px-4">Size Variant</th>
                        <th class="py-3.5 px-4">Stock Quantity</th>
                        <th class="py-3.5 px-4">Re-order Status</th>
                        <th class="py-3.5 px-4">Clearance Sale</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/50 text-xs">
                    <?php if ($products_list && $products_list->num_rows > 0): ?>
                        <?php while ($item = $products_list->fetch_assoc()): ?>
                            <tr class="hover:bg-zinc-800/30 transition">
                                <td class="py-3.5 px-4">
                                    <p class="font-bold text-zinc-100"><?= e($item['name']) ?></p>
                                    <p class="text-[10px] text-zinc-500"><?= e($item['category_name'] ?? 'General Outfit') ?> | Price: $<?= number_format((float)$item['price'], 2) ?></p>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-1 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-300 font-bold text-[10px]">
                                        <?= e($item['size'] ?? 'Free Size') ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 font-bold text-zinc-200">
                                    <span class="text-sm"><?= (int)$item['stock'] ?></span> <span class="text-[10px] text-zinc-500 font-normal">pcs</span>
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
                                        <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-bold text-[10px]">
                                            🏷️ <?= (int)$item['discount_percent'] ?>% OFF
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[11px] text-zinc-600">Regular Price</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-right space-x-1.5">
                                    <button type="button" 
                                            onclick="openRestockModal(<?= $item['id'] ?>, '<?= e(addslashes($item['name'])) ?>')" 
                                            class="px-2.5 py-1.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-semibold text-[11px] rounded-lg border border-zinc-700 transition cursor-pointer">
                                        + Add Stock
                                    </button>

                                    <button type="button" 
                                            onclick="openOfferModal(<?= $item['id'] ?>, '<?= e(addslashes($item['name'])) ?>', '<?= e(addslashes($item['size'] ?? 'Free Size')) ?>', <?= (int)$item['discount_percent'] ?>)" 
                                            class="px-2.5 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 font-semibold text-[11px] rounded-lg transition cursor-pointer">
                                        🏷️ Size & Offer
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-zinc-500 text-xs">No clothing items matched your search query.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Restock Modal -->
<div id="restockModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md hidden">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-sm font-bold text-zinc-100">Add New Cloth Stock</h3>
            <button onclick="closeRestockModal()" class="text-zinc-500 hover:text-zinc-300">✕</button>
        </div>

        <form method="POST" action="inventory.php" class="space-y-4">
            <input type="hidden" name="action" value="restock_inventory">
            <input type="hidden" id="restock_prod_id" name="product_id" value="">

            <div>
                <p class="text-xs text-zinc-400">Dress Name:</p>
                <p id="restock_prod_name" class="text-sm font-bold text-zinc-100 mt-0.5"></p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Quantity to Add (pcs)</label>
                <input type="number" name="import_qty" min="1" value="10" required class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-emerald-500">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                <button type="button" onclick="closeRestockModal()" class="px-4 py-2 text-xs font-medium bg-zinc-800 text-zinc-300 rounded-xl">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold bg-emerald-500 text-zinc-950 rounded-xl">Confirm Stock</button>
            </div>
        </form>
    </div>
</div>

<!-- Size & Offer Settings Modal -->
<div id="offerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md hidden">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-sm font-bold text-zinc-100">Update Size & Discount</h3>
            <button onclick="closeOfferModal()" class="text-zinc-500 hover:text-zinc-300">✕</button>
        </div>

        <form method="POST" action="inventory.php" class="space-y-4">
            <input type="hidden" name="action" value="manage_clothing_offer">
            <input type="hidden" id="offer_prod_id" name="product_id" value="">

            <div>
                <p class="text-xs text-zinc-400">Dress Name:</p>
                <p id="offer_prod_name" class="text-sm font-bold text-zinc-100 mt-0.5"></p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Cloth Size (S, M, L, XL, XXL)</label>
                <input type="text" id="offer_size" name="size" placeholder="e.g. M, L, XL" class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Clearance Discount (% OFF)</label>
                <input type="number" id="offer_disc_percent" name="discount_percent" min="0" max="90" placeholder="e.g. 20" class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-emerald-500">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                <button type="button" onclick="closeOfferModal()" class="px-4 py-2 text-xs font-medium bg-zinc-800 text-zinc-300 rounded-xl">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold bg-amber-500 text-zinc-950 rounded-xl">Save Settings</button>
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
    function closeRestockModal() {
        document.getElementById('restockModal').classList.add('hidden');
    }

    function openOfferModal(id, name, size, discount) {
        document.getElementById('offer_prod_id').value = id;
        document.getElementById('offer_prod_name').innerText = name;
        document.getElementById('offer_size').value = size;
        document.getElementById('offer_disc_percent').value = discount;
        document.getElementById('offerModal').classList.remove('hidden');
    }
    function closeOfferModal() {
        document.getElementById('offerModal').classList.add('hidden');
    }
</script>

</main>
</body>
</html>