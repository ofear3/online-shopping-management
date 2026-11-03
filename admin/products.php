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

// ৩. প্রোডাক্ট ফর্ম প্রসেসিং (Add, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action A: Add New Product
    if ($action === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $size = trim($_POST['size'] ?? 'Free Size');
        $description = trim($_POST['description'] ?? '');

        if (!empty($name) && $price > 0) {
            $stmt = $conn->prepare("INSERT INTO products (name, category_id, price, stock, size, description) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sidiss", $name, $category_id, $price, $stock, $size, $description);
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = "Product Added Successfully!";
                    header('Location: products.php');
                    exit;
                }
                $stmt->close();
            }
        }
    }

    // Action B: Delete Product
    if ($action === 'delete_product') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $product_id);
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = "Product Deleted Successfully!";
                    header('Location: products.php');
                    exit;
                }
                $stmt->close();
            }
        }
    }
}

// ৪. ক্যাটাগরি এবং প্রোডাক্ট লিস্ট ফেচিং
$categories_res = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$products_list = $conn->query("
    SELECT p.*, c.name AS category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC
");

$totalProductsCount = ($products_list) ? $products_list->num_rows : 0;

// ৫. লজিক শেষ হওয়ার পর হেডার এবং HTML রেন্ডার শুরু
require_once __DIR__ . '/header.php';
?>

<div class="p-6 md:p-8 space-y-8">

    <!-- Page Header Banner (Matching Other Pages) -->
    <div class="bg-gradient-to-r from-zinc-950 via-zinc-900 to-indigo-950 rounded-3xl p-6 md:p-10 text-white shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6 border border-zinc-800/80">
        <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="space-y-2 text-center md:text-left relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 font-bold text-[11px] rounded-full uppercase tracking-wider">
                👕 Store Catalog
            </span>
            <h1 class="text-2xl md:text-4xl font-black tracking-tight">Product Management</h1>
            <p class="text-xs md:text-sm text-zinc-400">Add, update, or remove clothing items and manage your store catalog seamlessly.</p>
        </div>
        <div class="flex items-center gap-4 relative z-10">
            <div class="px-5 py-3 rounded-2xl bg-zinc-900/90 border border-zinc-800 text-center shadow-inner">
                <span class="block text-[10px] text-zinc-400 uppercase tracking-widest font-bold">Total Products</span>
                <span class="text-2xl font-black text-indigo-400"><?= $totalProductsCount ?></span>
            </div>
            <button type="button" onclick="openAddProductModal()" class="px-5 py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-2xl transition shadow-lg shadow-indigo-600/30 cursor-pointer flex items-center gap-2">
                <span>+ Add Product</span>
            </button>
        </div>
    </div>

    <!-- Products Table Card with Inner Scroll -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-zinc-800 bg-zinc-950/40 flex items-center justify-between sticky top-0 z-10 backdrop-blur-md">
            <h3 class="text-xs font-bold text-zinc-300 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                All Store Products
            </h3>
            <span class="text-[11px] text-zinc-400">Catalog Inventory</span>
        </div>

        <!-- Inner Scrollable Container -->
        <div class="max-h-[580px] overflow-y-auto">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="sticky top-0 z-10 bg-zinc-950">
                        <tr class="border-b border-zinc-800 text-zinc-400 uppercase tracking-wider text-[11px] font-bold">
                            <th class="py-3.5 px-4 bg-zinc-950/90">Product Name</th>
                            <th class="py-3.5 px-4 bg-zinc-950/90">Category</th>
                            <th class="py-3.5 px-4 bg-zinc-950/90">Price</th>
                            <th class="py-3.5 px-4 bg-zinc-950/90">Stock</th>
                            <th class="py-3.5 px-4 bg-zinc-950/90">Size</th>
                            <th class="py-3.5 px-4 bg-zinc-950/90 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-zinc-300 font-medium">
                        <?php if ($products_list && $products_list->num_rows > 0): ?>
                            <?php while ($item = $products_list->fetch_assoc()): ?>
                                <tr class="hover:bg-zinc-800/40 transition">
                                    <td class="py-3.5 px-4 font-bold text-white">
                                        <?= e($item['name']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-zinc-400">
                                        <?= e($item['category_name'] ?? 'General') ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-bold text-emerald-400">
                                        ৳<?= number_format((float)$item['price'], 2) ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-bold text-white">
                                        <?= (int)$item['stock'] ?> <span class="text-[11px] text-zinc-400 font-normal">pcs</span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2.5 py-1 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-300 font-bold text-[10px]">
                                            <?= e($item['size'] ?? 'Free Size') ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <form method="POST" action="products.php" onsubmit="return confirm('Are you sure you want to delete this product?');" class="inline">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 font-bold text-[11px] rounded-xl transition cursor-pointer">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-16 text-center text-zinc-500 text-xs font-medium">No products found in the catalog.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div id="addProductModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md hidden">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-sm font-bold text-white">Add New Product</h3>
            <button onclick="closeAddProductModal()" class="text-zinc-500 hover:text-zinc-300 font-bold cursor-pointer">✕</button>
        </div>

        <form method="POST" action="products.php" class="space-y-4">
            <input type="hidden" name="action" value="add_product">

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Product Name</label>
                <input type="text" name="name" required placeholder="e.g. Premium Cotton Shirt" class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Category</label>
                <select name="category_id" class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500 cursor-pointer">
                    <option value="0">Select Category</option>
                    <?php if ($categories_res): ?>
                        <?php while ($c = $categories_res->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Price (৳)</label>
                    <input type="number" step="0.01" name="price" required placeholder="e.g. 1200" class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Initial Stock (pcs)</label>
                    <input type="number" name="stock" required value="10" class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Size Variant (S, M, L, XL, etc.)</label>
                <input type="text" name="size" placeholder="e.g. L or Free Size" class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Description</label>
                <textarea name="description" rows="2" placeholder="Product details..." class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                <button type="button" onclick="closeAddProductModal()" class="px-4 py-2 text-xs font-medium bg-zinc-800 text-zinc-300 rounded-xl cursor-pointer">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-md cursor-pointer">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddProductModal() {
        document.getElementById('addProductModal').classList.remove('hidden');
    }
    function closeAddProductModal() {
        document.getElementById('addProductModal').classList.add('hidden');
    }
</script>

</main>
</body>
</html>