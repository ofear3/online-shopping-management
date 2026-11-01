<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ১. হেডার ফাইল ইনক্লুড করা (DB Connection & Auth Check)
require_once __DIR__ . '/header.php';

$action = $_POST['action'] ?? '';
$modal_error = '';

// ২. ফর্ম সাবমিশন প্রসেসিং (Add, Edit, Delete Product & Manage Category)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Action A: Add New Product
    if ($action === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0.00);
        $stock = (int) ($_POST['stock'] ?? 0);
        $discount_percent = (int) ($_POST['discount_percent'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $image_path = '';

        if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileName = $_FILES['image']['name'];
            $fileError = $_FILES['image']['error'];

            if ($fileError === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

                if (in_array($ext, $allowed, true)) {
                    $uploadDir = __DIR__ . '/../uploads/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0777, true);
                    }

                    $newFileName = 'cloth_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $targetFile = $uploadDir . $newFileName;

                    if (@move_uploaded_file($fileTmpPath, $targetFile)) {
                        $image_path = 'uploads/' . $newFileName;
                    } else {
                        $modal_error = "Server Upload Error: Check directory permissions for uploads/ folder.";
                    }
                } else {
                    $modal_error = "Invalid file format! Allowed: JPG, PNG, WEBP, GIF, SVG.";
                }
            } else {
                $modal_error = "File upload failed with error code: " . $fileError;
            }
        }

        if (empty($modal_error) && !empty($name) && $category_id > 0 && $price >= 0) {
            $stmt = $conn->prepare("INSERT INTO products (category_id, name, description, price, stock, discount_percent, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issdiis", $category_id, $name, $description, $price, $stock, $discount_percent, $image_path);
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = "Outfit \"{$name}\" Added Successfully!";
                    echo "<script>window.location.href='products.php';</script>";
                    exit;
                } else {
                    $modal_error = "Database Error: " . $stmt->error;
                }
                $stmt->close();
            }
        } elseif (empty($modal_error)) {
            $modal_error = "Please fill in all mandatory fields (Title, Category, Price).";
        }
    }

    // Action B: Edit/Update Product
    if ($action === 'edit_product') {
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0.00);
        $stock = (int) ($_POST['stock'] ?? 0);
        $discount_percent = (int) ($_POST['discount_percent'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($product_id > 0 && !empty($name) && $category_id > 0 && $price >= 0) {
            if (isset($_FILES['image']) && !empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

                if (in_array($ext, $allowed, true)) {
                    $uploadDir = __DIR__ . '/../uploads/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0777, true);
                    }

                    $newFileName = 'cloth_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $targetFile = $uploadDir . $newFileName;

                    if (@move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                        $oldStmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
                        $oldStmt->bind_param("i", $product_id);
                        $oldStmt->execute();
                        $oldRes = $oldStmt->get_result()->fetch_assoc();
                        if ($oldRes && !empty($oldRes['image'])) {
                            $oldFile = __DIR__ . '/../' . $oldRes['image'];
                            if (file_exists($oldFile)) {
                                @unlink($oldFile);
                            }
                        }
                        $oldStmt->close();

                        $newImagePath = 'uploads/' . $newFileName;
                        $upImgStmt = $conn->prepare("UPDATE products SET image = ? WHERE id = ?");
                        $upImgStmt->bind_param("si", $newImagePath, $product_id);
                        $upImgStmt->execute();
                        $upImgStmt->close();
                    }
                }
            }

            $stmt = $conn->prepare("UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, discount_percent = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("issdiii", $category_id, $name, $description, $price, $stock, $discount_percent, $product_id);
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = "Outfit \"{$name}\" Updated Successfully!";
                    echo "<script>window.location.href='products.php';</script>";
                    exit;
                }
                $stmt->close();
            }
        }
    }

    // Action C: Delete Product
    if ($action === 'delete_product') {
        $prod_id = (int) ($_POST['product_id'] ?? 0);
        $prod_name = trim($_POST['product_name'] ?? '');

        if ($prod_id > 0) {
            $imgStmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
            if ($imgStmt) {
                $imgStmt->bind_param("i", $prod_id);
                $imgStmt->execute();
                $res = $imgStmt->get_result()->fetch_assoc();
                if ($res && !empty($res['image'])) {
                    $fileToDelete = __DIR__ . '/../' . $res['image'];
                    if (file_exists($fileToDelete)) {
                        @unlink($fileToDelete);
                    }
                }
                $imgStmt->close();
            }

            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $prod_id);
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = !empty($prod_name) ? "Outfit \"{$prod_name}\" Deleted!" : "Outfit Deleted Successfully!";
                    echo "<script>window.location.href='products.php';</script>";
                    exit;
                }
                $stmt->close();
            }
        }
    }

    // Action D: Add New Category (With Duplicate Check)
    if ($action === 'add_category') {
        $cat_name = trim($_POST['cat_name'] ?? '');
        if (!empty($cat_name)) {
            $checkStmt = $conn->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
            if ($checkStmt) {
                $checkStmt->bind_param("s", $cat_name);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult && $checkResult->num_rows > 0) {
                    $modal_error = "Category \"{$cat_name}\" already exists!";
                } else {
                    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                    if ($stmt) {
                        $stmt->bind_param("s", $cat_name);
                        if ($stmt->execute()) {
                            $_SESSION['toast_message'] = "Category \"{$cat_name}\" Added Successfully!";
                            echo "<script>window.location.href='products.php';</script>";
                            exit;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
            }
        } else {
            $modal_error = "Category name cannot be empty!";
        }
    }

    // Action E: Delete Category
    if ($action === 'delete_category') {
        $del_cat_id = (int) ($_POST['cat_id'] ?? 0);
        $del_cat_name = trim($_POST['cat_name'] ?? '');

        if ($del_cat_id > 0) {
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $del_cat_id);
                if ($stmt->execute()) {
                    $_SESSION['toast_message'] = !empty($del_cat_name) ? "Category \"{$del_cat_name}\" Removed!" : "Category Removed Successfully!";
                    echo "<script>window.location.href='products.php';</script>";
                    exit;
                }
                $stmt->close();
            }
        }
    }
}

// ৩. ডাটাবেজ থেকে ডাটা ফেচ
$categories = [];
$catQuery =$conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($catQuery) {
    while ($row =$catQuery->fetch_assoc()) {
        $categories[] =$row;
    }
}

$productsResult =$conn->query("
    SELECT p.*, c.name AS category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC
");
?>

<div class="p-6 md:p-8 space-y-6">

    <!-- Toast Error Message if any -->
    <?php if (!empty($modal_error)): ?>
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold flex items-center gap-2">
            <span>⚠️</span>
            <span><?= e($modal_error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Top Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-zinc-100 tracking-tight">Clothing Catalog</h1>
            <p class="text-xs text-zinc-400 mt-1">Manage apparel designs, categories, prices, stock, and product images</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="openCategoryModal()"
                class="px-3.5 py-2 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 font-semibold text-xs rounded-xl transition shadow-lg shadow-indigo-500/5 flex items-center gap-2 cursor-pointer">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                Manage Categories
            </button>

            <button onclick="openAddProductModal()"
                class="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-bold text-xs rounded-xl transition shadow-lg shadow-emerald-500/10 flex items-center gap-2 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add New Outfit
            </button>
        </div>
    </div>

    <!-- Products Table -->
    <div class="bg-zinc-900 border border-zinc-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-zinc-950/60 border-b border-zinc-800/80 text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                        <th class="py-3.5 px-4">Outfit & Image</th>
                        <th class="py-3.5 px-4">Category</th>
                        <th class="py-3.5 px-4">Price</th>
                        <th class="py-3.5 px-4">Stock</th>
                        <th class="py-3.5 px-4">Discount</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/50 text-xs">
                    <?php if ($productsResult &&$productsResult->num_rows > 0): ?>
                        <?php while ($prod =$productsResult->fetch_assoc()): ?>
                            <tr class="hover:bg-zinc-800/30 transition">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-xl bg-zinc-950 border border-zinc-800 flex items-center justify-center overflow-hidden flex-shrink-0">
                                            <?php
                                            $dbImagePath =$prod['image'] ?? '';
                                            $physicalFile = __DIR__ . '/../' .$dbImagePath;
                                            $webUrl = '../' .$dbImagePath;
                                            ?>
                                            <?php if (!empty($dbImagePath) && file_exists($physicalFile)): ?>
                                                <img src="<?= e($webUrl) ?>" alt="<?= e($prod['name']) ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <span class="text-[9px] text-zinc-600 font-bold uppercase text-center px-1">NO IMAGE</span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-zinc-100"><?= e($prod['name']) ?></p>
                                            <p class="text-[10px] text-zinc-400 truncate max-w-[220px]"><?= e($prod['description']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-zinc-300 font-medium">
                                    <?= e($prod['category_name'] ?? 'General') ?>
                                </td>
                                <td class="py-3 px-4 font-bold text-emerald-400">
                                    ৳<?= number_format((float) $prod['price'], 0) ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($prod['stock'] > 5): ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-semibold text-[10px]">
                                            <?= (int) $prod['stock'] ?> pcs
                                        </span>
                                    <?php elseif ($prod['stock'] > 0): ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 font-semibold text-[10px]">
                                            Low (<?= (int) $prod['stock'] ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-400 font-semibold text-[10px]">
                                            Out of Stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ((int) $prod['discount_percent'] > 0): ?>
                                        <span class="px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-bold text-[10px]">
                                            <?= (int) $prod['discount_percent'] ?>% OFF
                                        </span>
                                    <?php else: ?>
                                        <span class="text-zinc-600 text-[11px]">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right space-x-1">
                                    <button type="button"
                                        onclick='openEditModal(<?= json_encode($prod, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                        class="p-1.5 text-zinc-400 hover:text-amber-400 hover:bg-amber-500/10 rounded-lg transition cursor-pointer"
                                        title="Edit Outfit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>

                                    <button type="button"
                                        onclick="openDeleteModal(<?= $prod['id'] ?>, '<?= e(addslashes($prod['name'])) ?>')"
                                        class="p-1.5 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition cursor-pointer"
                                        title="Delete Outfit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-zinc-500 text-xs">No products in catalog yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 1: Add Product Modal -->
<div id="addProductModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md hidden">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-sm font-bold text-zinc-100">Add New Clothing Item</h3>
            <button onclick="closeAddProductModal()" class="text-zinc-500 hover:text-zinc-300 cursor-pointer">✕</button>
        </div>

        <form method="POST" action="products.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="add_product">

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Outfit Title / Name</label>
                <input type="text" name="name" required placeholder="e.g., Premium Denim Shirt"
                    class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-emerald-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Category</label>
                    <select name="category_id" required
                        class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-emerald-500">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as$cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Price (৳)</label>
                    <input type="number" step="1" name="price" required
                        class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-emerald-500 [color-scheme:dark]">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Stock Quantity (pcs)</label>
                    <input type="number" name="stock" value="10" required
                        class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-emerald-500 [color-scheme:dark]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Discount Offer (% OFF)</label>
                    <input type="number" name="discount_percent" value="0" min="0" max="90"
                        class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-emerald-500 [color-scheme:dark]">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Outfit Image Upload</label>
                <input type="file" name="image" accept="image/*"
                    class="w-full text-xs text-zinc-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:bg-zinc-800 file:text-zinc-200 cursor-pointer">
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Description / Fabric Details</label>
                <textarea name="description" rows="3"
                    class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-emerald-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                <button type="button" onclick="closeAddProductModal()"
                    class="px-4 py-2 text-xs font-medium bg-zinc-800 hover:bg-zinc-700 rounded-xl text-zinc-300 cursor-pointer">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 text-xs font-bold bg-emerald-500 hover:bg-emerald-400 text-zinc-950 rounded-xl cursor-pointer">Save Outfit</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Product Modal -->
<div id="editProductModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md hidden">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-sm font-bold text-zinc-100">Edit Outfit Details</h3>
            <button onclick="closeEditModal()" class="text-zinc-500 hover:text-zinc-300 cursor-pointer">✕</button>
        </div>

        <form method="POST" action="products.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" id="edit_product_id" name="product_id" value="">

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Outfit Title / Name</label>
                <input type="text" id="edit_name" name="name" required
                    class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-amber-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Category</label>
                    <select id="edit_category_id" name="category_id" required
                        class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-amber-500">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as$cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Price (৳)</label>
                    <input type="number" step="1" id="edit_price" name="price" required
                        class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-amber-500 [color-scheme:dark]">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Stock Quantity (pcs)</label>
                    <input type="number" id="edit_stock" name="stock" required
                        class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-amber-500 [color-scheme:dark]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-300 mb-1">Discount Offer (% OFF)</label>
                    <input type="number" id="edit_discount_percent" name="discount_percent" min="0" max="90"
                        class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-amber-500 [color-scheme:dark]">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Replace Image (Optional)</label>
                <input type="file" name="image" accept="image/*"
                    class="w-full text-xs text-zinc-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:bg-zinc-800 file:text-zinc-200 cursor-pointer">
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-300 mb-1">Description / Fabric Details</label>
                <textarea id="edit_description" name="description" rows="3"
                    class="w-full px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-amber-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                <button type="button" onclick="closeEditModal()"
                    class="px-4 py-2 text-xs font-medium bg-zinc-800 hover:bg-zinc-700 rounded-xl text-zinc-300 cursor-pointer">Cancel</button>
                <button type="submit"
                    class="px-4 py-2 text-xs font-bold bg-amber-500 hover:bg-amber-400 text-zinc-950 rounded-xl cursor-pointer">Update Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Category Management Modal -->
<div id="categoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md hidden">
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-sm font-bold text-zinc-100">Category Manager</h3>
            <button onclick="closeCategoryModal()" class="text-zinc-500 hover:text-zinc-300 cursor-pointer">✕</button>
        </div>

        <form method="POST" action="products.php" class="flex gap-2">
            <input type="hidden" name="action" value="add_category">
            <input type="text" name="cat_name" required placeholder="New category name (e.g., Men, Women, Kids)..."
                class="flex-1 px-3 py-2 bg-zinc-950 border border-zinc-800 rounded-xl text-xs text-zinc-100 focus:outline-none focus:border-emerald-500">
            <button type="submit"
                class="px-3 py-2 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 text-xs font-bold rounded-xl cursor-pointer">+ Add</button>
        </form>

        <div class="border-t border-zinc-800 pt-3">
            <p class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider mb-2">Existing Categories</p>
            <div class="max-h-48 overflow-y-auto space-y-1.5 pr-1">
                <?php foreach ($categories as$cat): ?>
                    <div class="flex items-center justify-between p-2 bg-zinc-950 rounded-xl border border-zinc-800/60 text-xs">
                        <span class="text-zinc-200 font-medium"><?= e($cat['name']) ?></span>
                        <form method="POST" action="products.php" class="inline">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                            <input type="hidden" name="cat_name" value="<?= e($cat['name']) ?>">
                            <button type="submit" onclick="return confirm('Delete this category?')"
                                class="text-zinc-500 hover:text-rose-400 text-xs px-1 cursor-pointer" title="Delete Category">✕</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript functions for Modals -->
<script>
    function openAddProductModal() {
        document.getElementById('addProductModal').classList.remove('hidden');
    }
    function closeAddProductModal() {
        document.getElementById('addProductModal').classList.add('hidden');
    }
    function openCategoryModal() {
        document.getElementById('categoryModal').classList.remove('hidden');
    }
    function closeCategoryModal() {
        document.getElementById('categoryModal').classList.add('hidden');
    }
    function openEditModal(prod) {
        document.getElementById('edit_product_id').value = prod.id;
        document.getElementById('edit_name').value = prod.name;
        document.getElementById('edit_category_id').value = prod.category_id;
        document.getElementById('edit_price').value = prod.price;
        document.getElementById('edit_stock').value = prod.stock;
        document.getElementById('edit_discount_percent').value = prod.discount_percent;
        document.getElementById('edit_description').value = prod.description || '';
        document.getElementById('editProductModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editProductModal').classList.add('hidden');
    }
    function openDeleteModal(id, name) {
        // Optional delete prompt or modal trigger
        if (confirm(`Are you sure you want to delete "${name}"?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'products.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_product';
            form.appendChild(actionInput);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'product_id';
            idInput.value = id;
            form.appendChild(idInput);

            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>